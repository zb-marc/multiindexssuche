<?php
/**
 * Zuständig für die Indexierung von WordPress-Inhalten mit ChatGPT.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexiert einen einzelnen WordPress-Beitrag mit ChatGPT.
 *
 * @param int   $post_id Die ID des zu indexierenden Beitrags.
 * @param array $languages Ein Array der zu indexierenden Sprach-Locales.
 * @return array Statistiken über verwendete APIs.
 */
function asmi_index_single_wp_post( $post_id, $languages = array() ) {
	global $wpdb;
	$original_post_obj = get_post( $post_id );
	if ( ! $original_post_obj || wp_is_post_revision( $post_id ) ) {
		return array( 'chatgpt_used' => 0, 'fallback_used' => 0, 'manually_imported' => 0 );
	}

	if ( empty( $languages ) ) {
		$languages = array( 'de_DE', 'en_GB' );
	}
	
	$o = asmi_get_opts();
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;

	// Originalinhalte holen
	$original_title = get_the_title( $post_id );
	$original_content_raw = $original_post_obj->post_content;
	$rendered_content = apply_filters( 'the_content', $original_content_raw );
	
	// Basis-Daten
	$content_hash_base = md5( $original_title . '|' . $rendered_content );
	$original_url = get_permalink( $post_id );
	$thumbnail_url = get_the_post_thumbnail_url( $post_id, 'medium' );
	$post_modified = $original_post_obj->post_modified;
	
	$stats = array( 'chatgpt_used' => 0, 'fallback_used' => 0, 'manually_imported' => 0 );
	
	// Indexierung für alle Zielsprachen
	foreach ( $languages as $lang_locale ) {
		$lang_slug_short = substr( $lang_locale, 0, 2 );
		
		// Prüfe ob bereits ein manuell importierter Eintrag existiert
		$existing_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT content_hash, CASE WHEN content_hash LIKE '%manual_import%' THEN 1 ELSE 0 END as is_manual
				FROM $table_name 
				WHERE source_id = %s AND lang = %s AND source_type = 'wordpress'",
				$post_id, $lang_locale
			),
			ARRAY_A
		);
		
		// Schütze manuell importierte Einträge
		if ( $existing_entry && $existing_entry['is_manual'] ) {
			$stats['manually_imported']++;
			asmi_debug_log( sprintf( 'Post %d (%s): Protected manual import', $post_id, $lang_locale ) );
			continue;
		}
		
		// Prüfe ob ChatGPT verfügbar ist
		$use_chatgpt = ! empty( $o['use_chatgpt'] ) && ! empty( $o['chatgpt_api_key'] );
		
		// Hauptverarbeitung mit ChatGPT
		if ( $use_chatgpt && function_exists( 'asmi_process_with_chatgpt_cached' ) ) {
			$chatgpt_lang = ( $lang_slug_short === 'en' ) ? 'en' : 'de';
			$chatgpt_result = asmi_process_with_chatgpt_cached( 
				$original_title, 
				$rendered_content, 
				$chatgpt_lang,
				$content_hash_base
			);
			
			if ( ! is_wp_error( $chatgpt_result ) ) {
				// ChatGPT erfolgreich
				$final_title = ! empty( $chatgpt_result['title'] ) ? 
					$chatgpt_result['title'] : $original_title;
				
				$final_content = ! empty( $chatgpt_result['content'] ) ? 
					$chatgpt_result['content'] : ( $chatgpt_result['summary'] ?? '' );
				
				// Erstelle Keyword-String für Excerpt
				$keyword_string = '';
				if ( ! empty( $chatgpt_result['brands'] ) ) {
					$keyword_string = implode( ' ', $chatgpt_result['brands'] ) . ' ';
				}
				if ( ! empty( $chatgpt_result['keywords'] ) ) {
					$keyword_string .= implode( ' ', array_slice( $chatgpt_result['keywords'], 0, 10 ) );
				}
				$final_excerpt = trim( $keyword_string );
				
				$final_url = ( 'en' === $lang_slug_short ) ? 
					home_url( '/en' . wp_make_link_relative( $original_url ) ) : 
					$original_url;
				
				$content_hash = hash( 'sha256', $content_hash_base . '|chatgpt_v2|' . $lang_locale );
				$stats['chatgpt_used']++;
				
				asmi_debug_log( sprintf( 
					'Post %d (%s): ChatGPT processed successfully', 
					$post_id, $lang_locale
				) );
			} else {
				// ChatGPT fehlgeschlagen - Verwende minimalen Fallback
				asmi_debug_log( sprintf( 
					'Post %d (%s): ChatGPT failed - %s. Using minimal fallback.', 
					$post_id, $lang_locale, $chatgpt_result->get_error_message()
				) );
				
				// Minimaler Fallback - nur bereinigter Text
				$final_title = $original_title;
				$final_content = wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = wp_trim_words( wp_strip_all_tags( $rendered_content ), 10 );
				$final_url = ( 'en' === $lang_slug_short ) ? 
					home_url( '/en' . wp_make_link_relative( $original_url ) ) : 
					$original_url;
				
				$content_hash = hash( 'sha256', $content_hash_base . '|minimal_v1|' . $lang_locale );
				$stats['fallback_used']++;
			}
		} else {
			// Kein ChatGPT konfiguriert - Lade erweiterten Fallback wenn nötig
			if ( ! function_exists( 'asmi_extract_search_keywords' ) ) {
				$fallback_file = plugin_dir_path( __FILE__ ) . 'keyword-fallback.php';
				if ( file_exists( $fallback_file ) ) {
					require_once $fallback_file;
				}
			}
			
			// Verwende erweiterten Fallback wenn verfügbar
			if ( function_exists( 'asmi_extract_search_keywords' ) ) {
				$extracted = asmi_extract_search_keywords( $original_title, $rendered_content, $lang_locale );
				$final_title = $extracted['title'] ?? $original_title;
				$final_content = $extracted['content'] ?? wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = $extracted['excerpt'] ?? '';
			} else {
				// Absoluter Minimal-Fallback
				$final_title = $original_title;
				$final_content = wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = wp_trim_words( wp_strip_all_tags( $rendered_content ), 10 );
			}
			
			$final_url = ( 'en' === $lang_slug_short ) ? 
				home_url( '/en' . wp_make_link_relative( $original_url ) ) : 
				$original_url;
			
			$content_hash = hash( 'sha256', $content_hash_base . '|fallback_v1|' . $lang_locale );
			$stats['fallback_used']++;
		}

		// Speichere in Datenbank
		$wpdb->replace(
			$table_name,
			array(
				'source_id'     => $post_id,
				'lang'          => $lang_locale,
				'source_type'   => 'wordpress',
				'title'         => $final_title,
				'content'       => $final_content,
				'excerpt'       => $final_excerpt,
				'url'           => $final_url,
				'image'         => $thumbnail_url,
				'content_hash'  => $content_hash,
				'last_modified' => $post_modified,
				'indexed_at'    => current_time( 'mysql' ),
			)
		);
	}
	
	return $stats;
}

/**
 * Indexiert alle relevanten WordPress-Inhalte.
 */
function asmi_index_all_wp_content() {
	global $wpdb;
	$o          = asmi_get_opts();
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	$post_types = ! empty( $o['wp_post_types'] ) ? 
		array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : 
		array( 'post', 'page' );

	if ( empty( $post_types ) ) {
		return;
	}
	
	$use_chatgpt = ! empty( $o['use_chatgpt'] ) && ! empty( $o['chatgpt_api_key'] );
	asmi_debug_log( 'WP Content Indexing: Starting with ' . ( $use_chatgpt ? 'ChatGPT' : 'Fallback' ) );

	// Hole alle Posts
	$args = array(
		'post_type'      => $post_types,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);
	
	$query    = new WP_Query( $args );
	$post_ids = $query->posts;

	if ( empty( $post_ids ) ) {
		// Lösche verwaiste Einträge
		$wpdb->query( 
			"DELETE FROM $table_name 
			WHERE source_type = 'wordpress' 
			AND content_hash NOT LIKE '%manual_import%'"
		);
		return;
	}

	// Filtere ausgeschlossene IDs
	if ( ! empty( $o['excluded_ids'] ) ) {
		$excluded_ids = array_map( 'intval', explode( ',', $o['excluded_ids'] ) );
		$post_ids     = array_diff( $post_ids, $excluded_ids );
	}

	// Lösche nicht mehr existierende Posts
	$existing_ids_placeholder = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $table_name 
			WHERE source_type = 'wordpress' 
			AND source_id NOT IN ($existing_ids_placeholder)
			AND content_hash NOT LIKE '%manual_import%'",
			$post_ids
		)
	);

	// Verarbeite Posts
	$languages = array( 'de_DE', 'en_GB' );
	$total_posts = count( $post_ids );
	$processed = 0;
	$chatgpt_used = 0;
	$fallback_used = 0;
	$manually_imported = 0;

	foreach ( $post_ids as $post_id ) {
		$result = asmi_index_single_wp_post( $post_id, $languages );
		$processed++;
		
		$chatgpt_used += $result['chatgpt_used'] ?? 0;
		$fallback_used += $result['fallback_used'] ?? 0;
		$manually_imported += $result['manually_imported'] ?? 0;
		
		// Status-Log alle 25 Posts
		if ( $processed % 25 === 0 || $processed === $total_posts ) {
			asmi_debug_log( sprintf( 
				'Progress: %d/%d posts | ChatGPT: %d | Fallback: %d | Protected: %d',
				$processed, $total_posts, $chatgpt_used, $fallback_used, $manually_imported
			));
		}
		
		// Rate limiting für ChatGPT
		if ( $use_chatgpt && $processed % 10 === 0 ) {
			sleep( 1 );
		}
	}
	
	asmi_debug_log( sprintf( 
		'Indexing Complete: %d posts | ChatGPT: %d | Fallback: %d | Protected: %d',
		$processed, $chatgpt_used, $fallback_used, $manually_imported
	));
}
add_action( 'asmi_cron_wp_content_index', 'asmi_index_all_wp_content' );

/**
 * Hook für Post-Speicherung.
 */
function asmi_hook_save_post( $post_id, $post ) {
	$o = asmi_get_opts();
	$post_types = ! empty( $o['wp_post_types'] ) ? 
		array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : 
		array();
		
	if ( in_array( $post->post_type, $post_types, true ) && 'publish' === $post->post_status ) {
		asmi_index_single_wp_post( $post_id );
	}
}
add_action( 'save_post', 'asmi_hook_save_post', 10, 2 );

/**
 * Reset der Cache-Einträge.
 */
function asmi_reset_translation_cache() {
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	
	$wpdb->query( 
		"UPDATE $table_name 
		SET content_hash = NULL 
		WHERE source_type = 'wordpress' 
		AND content_hash NOT LIKE '%manual_import%'"
	);
	
	asmi_debug_log( 'Cache reset - manual imports preserved.' );
}