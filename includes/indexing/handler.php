<?php
/**
 * Haupt-Handler für den asynchronen Indexierungsprozess.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Führt einen einzelnen Schritt ("Tick") des Indexierungsprozesses aus.
 *
 * @return void
 */
function asmi_index_tick_handler() {
	asmi_debug_log( 'TICK HANDLER: Started execution' );
	
	asmi_install_and_repair_database();
	
	$state = asmi_get_index_state();
	asmi_debug_log( 'TICK HANDLER: Current state status: ' . $state['status'] );
	
	if ( 'idle' === $state['status'] || 'finished' === $state['status'] ) {
		asmi_debug_log( 'TICK HANDLER: State is idle or finished, stopping' );
		return;
	}

	$o = asmi_get_opts();
	asmi_debug_log( 'TICK HANDLER: Processing with batch size: ' . $o['index_batch'] );

	// PHASE 1: Alle Feeds scannen, um die Gesamtanzahl der Einträge zu ermitteln.
	if ( 'preparing' === $state['status'] ) {
		asmi_debug_log( 'TICK HANDLER: In PREPARING phase' );
		$prepare_i = intval( $state['feed_prepare_i'] );
		asmi_debug_log( 'TICK HANDLER: Preparing feed index: ' . $prepare_i );

		if ( isset( $state['feeds'][ $prepare_i ] ) ) {
			$feed = $state['feeds'][ $prepare_i ];
			asmi_debug_log( 'TICK HANDLER: Fetching items from feed: ' . $feed['url'] );
			
			$items_data = asmi_fetch_items( $feed['url'], $o );
			asmi_debug_log( 'TICK HANDLER: Fetched items count: ' . ( is_array( $items_data ) ? count( $items_data ) : 'ERROR' ) );

			if ( isset( $items_data['error'] ) ) {
				asmi_debug_log( 'TICK HANDLER: Feed error: ' . $items_data['error'] );
				$state['status']      = 'idle';
				// translators: %1$s is the feed URL, %2$s is the error message.
				$state['error']       = sprintf( esc_html__( 'Error with feed %1$s: %2$s', 'asmi-search' ), esc_html( $feed['url'] ), esc_html( $items_data['error'] ) );
				$state['finished_at'] = time();
				asmi_set_index_state( $state );
				return;
			}

			$count = count( $items_data );
			$state['total_items'] += $count;
			$state['feed_details'][] = array(
				'url'       => $feed['url'],
				'lang'      => $feed['lang'],
				'total'     => $count,
				'processed' => 0,
			);
			$state['feed_prepare_i'] = $prepare_i + 1;
			
			asmi_debug_log( 'TICK HANDLER: Feed prepared, total items so far: ' . $state['total_items'] );
			asmi_set_index_state( $state );
			asmi_schedule_next_tick();
			return;

		} else {
			// Nachdem alle Feeds vorbereitet wurden, zur Indexierung übergehen.
			asmi_debug_log( 'TICK HANDLER: All feeds prepared, switching to indexing phase' );
			$state['status'] = 'indexing_feeds';
			$state['current_action'] = __( 'Indexing feeds...', 'asmi-search' );
			asmi_set_index_state( $state );
			asmi_schedule_next_tick();
			return;
		}
	}

	// PHASE 2: Die Feeds nacheinander in Batches indexieren.
	if ( 'indexing_feeds' === $state['status'] ) {
		asmi_debug_log( 'TICK HANDLER: In INDEXING phase' );
		$i = intval( $state['feed_i'] );
		
		if ( ! isset( $state['feed_details'][ $i ] ) ) {
			// Alle Feeds verarbeitet - jetzt nicht mehr vorhandene Produkte löschen.
			asmi_debug_log( 'TICK HANDLER: All feeds processed, cleaning up obsolete products' );
			asmi_cleanup_obsolete_products( $state['indexing_start_time'] );
			
			asmi_debug_log( 'TICK HANDLER: Indexing complete, finishing' );
			$state['status']         = 'finished';
			$state['finished_at']    = time();
			$state['current_action'] = '';

			$state['last_run'] = array(
				'type'              => __( 'Import', 'asmi-search' ),
				'status'            => 'completed',
				'finished_at'       => $state['finished_at'],
				'duration'          => $state['finished_at'] - $state['started_at'],
				'processed'         => $state['processed_items'],
				'new'               => $state['new_items'],
				'updated'           => $state['updated_items'],
				'skipped'           => $state['skipped_no_desc'],
				'image_errors'      => $state['image_errors'],
				'images_reused'     => $state['images_reused'],
				'images_downloaded' => $state['images_downloaded'],
			);

			asmi_set_index_state( $state );
			return;
		}

		$feed_detail = $state['feed_details'][ $i ];
		// translators: %1$d is the current feed number, %2$d is the total number of feeds.
		$state['current_action'] = sprintf( esc_html__( 'Indexing feed %1$d/%2$d', 'asmi-search' ), $i + 1, count( $state['feed_details'] ) );
		
		asmi_debug_log( 'TICK HANDLER: Processing feed ' . ( $i + 1 ) . ' of ' . count( $state['feed_details'] ) );

		$items  = asmi_fetch_items( $feed_detail['url'], $o );
		$offset = intval( $state['offset'] );
		$batch  = intval( $state['batch'] );
		
		asmi_debug_log( 'TICK HANDLER: Feed items count: ' . count( $items ) . ', offset: ' . $offset . ', batch: ' . $batch );

		if ( empty( $items ) || $offset >= $feed_detail['total'] ) {
			asmi_debug_log( 'TICK HANDLER: Feed complete, moving to next' );
			$state['feed_i'] = $i + 1;
			$state['offset'] = 0;
			asmi_set_index_state( $state );
			asmi_schedule_next_tick();
			return;
		}

		$slice = array_slice( $items, $offset, $batch );
		asmi_debug_log( 'TICK HANDLER: Processing slice of ' . count( $slice ) . ' items' );
		
		$batch_stats = asmi_index_upsert_slice( $feed_detail['url'], $feed_detail['lang'], $slice );
		
		asmi_debug_log( 'TICK HANDLER: Batch stats - processed: ' . $batch_stats['processed'] . ', new: ' . $batch_stats['new'] . ', updated: ' . $batch_stats['updated'] . ', images reused: ' . $batch_stats['images_reused'] . ', images downloaded: ' . $batch_stats['images_downloaded'] );

		$state['processed_items']    += $batch_stats['processed'];
		$state['new_items']          += $batch_stats['new'];
		$state['updated_items']      += $batch_stats['updated'];
		$state['skipped_no_desc']    += $batch_stats['skipped_no_desc'];
		$state['image_errors']       += $batch_stats['image_errors'];
		$state['images_reused']      += $batch_stats['images_reused'];
		$state['images_downloaded']  += $batch_stats['images_downloaded'];

		$state['offset'] = $offset + count( $slice );
		$state['feed_details'][ $i ]['processed'] = $state['offset'];
		$state['updated_at'] = time();
		
		asmi_debug_log( 'TICK HANDLER: Total processed so far: ' . $state['processed_items'] );
		asmi_set_index_state( $state );
		asmi_schedule_next_tick();
		return;
	}
	
	asmi_debug_log( 'TICK HANDLER: Unknown state, ending' );
}
add_action( ASMI_INDEX_TICK_ACTION, 'asmi_index_tick_handler' );

/**
 * Löscht Produkte, die nicht mehr im Feed vorhanden sind.
 * Identifiziert Produkte anhand des last_modified Zeitstempels.
 *
 * @param string $indexing_start_time Zeitpunkt des Indexierungsstarts.
 * @return void
 */
function asmi_cleanup_obsolete_products( $indexing_start_time ) {
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;

	// Finde alle Produkte, die NICHT während dieser Indexierung aktualisiert wurden.
	$obsolete_products = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, source_id, image FROM {$table_name} 
			WHERE source_type = 'product' 
			AND (last_modified < %s OR last_modified IS NULL)",
			$indexing_start_time
		),
		ARRAY_A
	);

	if ( empty( $obsolete_products ) ) {
		asmi_debug_log( 'CLEANUP: No obsolete products found' );
		return;
	}

	$deleted_count = 0;
	$cache_dir     = asmi_get_image_cache_dir();

	foreach ( $obsolete_products as $product ) {
		// Lösche zugehöriges Bild wenn vorhanden.
		if ( ! empty( $product['image'] ) && strpos( $product['image'], $cache_dir['url'] ) !== false ) {
			$local_path = str_replace( $cache_dir['url'], $cache_dir['path'], $product['image'] );
			if ( file_exists( $local_path ) ) {
				wp_delete_file( $local_path );
				asmi_debug_log( 'CLEANUP: Deleted image for obsolete product ' . $product['source_id'] );
			}
		}

		// Lösche Produkt aus Datenbank.
		$wpdb->delete(
			$table_name,
			array( 'id' => $product['id'] ),
			array( '%d' )
		);
		++$deleted_count;
	}

	asmi_debug_log( 'CLEANUP: Deleted ' . $deleted_count . ' obsolete products and their images' );
}
