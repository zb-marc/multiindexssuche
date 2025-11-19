<?php
/**
 * Datenbankoperationen für Produktindexierung mit intelligenter Bildverwaltung.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/images.php';

/**
 * Schreibt einen Stapel von Feed-Einträgen effizient in die Datenbanktabelle.
 * Verwendet intelligente Änderungserkennung und lädt Bilder nur bei Bedarf herunter.
 *
 * @param string $feed_url Feed-URL für Logging.
 * @param string $lang Sprache des Feeds (de/en).
 * @param array  $slice Array von Produktdaten.
 * @return array Statistiken über verarbeitete Items.
 */
function asmi_index_upsert_slice( $feed_url, $lang, $slice ) {
	global $wpdb;
	$stats = array(
		'processed'         => 0,
		'skipped_no_desc'   => 0,
		'image_errors'      => 0,
		'updated'           => 0,
		'new'               => 0,
		'images_reused'     => 0,
		'images_downloaded' => 0,
	);
	
	$o          = asmi_get_opts();
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;

	// Mapping-Felder aus den Optionen holen.
	$map_id_key    = sanitize_key( str_replace( ':', '_', $o['map_id'] ) );
	$map_name_key  = sanitize_key( str_replace( ':', '_', $o['map_name'] ) );
	$map_desc_key  = sanitize_key( str_replace( ':', '_', $o['map_desc'] ) );
	$map_sku_key   = sanitize_key( str_replace( ':', '_', $o['map_sku'] ) );
	$map_gtin_key  = sanitize_key( str_replace( ':', '_', $o['map_gtin'] ) );
	$map_price_key = sanitize_key( str_replace( ':', '_', $o['map_price'] ) );
	$map_image_key = sanitize_key( str_replace( ':', '_', $o['map_image'] ) );
	$map_url_key   = sanitize_key( str_replace( ':', '_', $o['map_url'] ) );

	foreach ( $slice as $p ) {
		$item_id     = $p[ $map_id_key ] ?? '';
		$title       = $p[ $map_name_key ] ?? '';
		$description = $p[ $map_desc_key ] ?? '';

		if ( empty( $title ) || empty( $item_id ) ) {
			continue;
		}

		if ( ! empty( $o['exclude_no_desc'] ) && empty( trim( strip_tags( $description ) ) ) ) {
			++$stats['skipped_no_desc'];
			continue;
		}

		// Erstelle content_hash für Änderungserkennung.
		$content_for_hash = wp_json_encode(
			array(
				'title'       => $title,
				'description' => $description,
				'price'       => $p[ $map_price_key ] ?? '',
				'url'         => $p[ $map_url_key ] ?? '',
			)
		);
		$content_hash     = hash( 'sha256', $content_for_hash );

		$remote_image_url = $p[ $map_image_key ] ?? '';
		$image_url_hash   = ! empty( $remote_image_url ) ? md5( $remote_image_url ) : '';

		// Prüfe ob Produkt bereits existiert.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, content_hash, image, image_url_hash FROM {$table_name} WHERE source_id = %s AND lang = %s AND source_type = 'product' LIMIT 1",
				$item_id,
				$lang
			)
		);

		$final_image_url  = '';
		$is_new           = empty( $existing );
		$content_changed  = $is_new || ( $existing->content_hash !== $content_hash );
		$image_url_changed = $is_new || ( $existing->image_url_hash !== $image_url_hash );

		// Bildlogik: Nur herunterladen wenn nötig.
		if ( $o['image_storage_mode'] === 'local' && ! empty( $remote_image_url ) ) {
			if ( $image_url_changed ) {
				// Bild-URL hat sich geändert oder ist neu - Download erforderlich.
				asmi_debug_log( 'Image URL changed for product ' . $item_id . ', downloading...' );
				$download_result = asmi_download_image_to_local_dir( $remote_image_url );
				if ( ! is_wp_error( $download_result ) ) {
					$final_image_url = $download_result;
					++$stats['images_downloaded'];
				} else {
					++$stats['image_errors'];
					$final_image_url = '';
					asmi_debug_log( 'Image download failed for product ' . $item_id . ': ' . $download_result->get_error_message() );
				}
			} else {
				// Bild-URL unverändert - bestehendes Bild wiederverwenden.
				$final_image_url = $existing->image ?? '';
				++$stats['images_reused'];
				asmi_debug_log( 'Image URL unchanged for product ' . $item_id . ', reusing existing image' );
			}
		} elseif ( $o['image_storage_mode'] === 'remote' && ! empty( $remote_image_url ) ) {
			$final_image_url = $remote_image_url;
		}

		// Daten vorbereiten.
		$data = array(
			'source_id'      => $item_id,
			'lang'           => $lang,
			'source_type'    => 'product',
			'title'          => $title,
			'content'        => $description,
			'excerpt'        => wp_trim_words( strip_tags( $description ), 30, '...' ),
			'url'            => $p[ $map_url_key ] ?? '',
			'image'          => $final_image_url,
			'image_url_hash' => $image_url_hash,
			'price'          => $p[ $map_price_key ] ?? '',
			'sku'            => $p[ $map_sku_key ] ?? '',
			'gtin'           => $p[ $map_gtin_key ] ?? '',
			'raw_data'       => wp_json_encode( $p ),
			'content_hash'   => $content_hash,
			'last_modified'  => current_time( 'mysql' ),
			'indexed_at'     => current_time( 'mysql' ),
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $is_new ) {
			// Neues Produkt - INSERT.
			$result = $wpdb->insert( $table_name, $data, $format );
			if ( false !== $result ) {
				++$stats['new'];
				++$stats['processed'];
			}
		} else {
			// Bestehendes Produkt - UPDATE nur wenn sich Inhalt geändert hat.
			if ( $content_changed || $image_url_changed ) {
				$result = $wpdb->update(
					$table_name,
					$data,
					array(
						'source_id'   => $item_id,
						'lang'        => $lang,
						'source_type' => 'product',
					),
					$format,
					array( '%s', '%s', '%s' )
				);
				if ( false !== $result ) {
					++$stats['updated'];
					++$stats['processed'];
				}
			} else {
				// Keine Änderung - nur last_modified aktualisieren.
				$wpdb->update(
					$table_name,
					array( 'last_modified' => current_time( 'mysql' ) ),
					array(
						'source_id'   => $item_id,
						'lang'        => $lang,
						'source_type' => 'product',
					),
					array( '%s' ),
					array( '%s', '%s', '%s' )
				);
				++$stats['processed'];
			}
		}
	}

	return $stats;
}
