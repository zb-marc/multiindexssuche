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
	asmi_run_db_install();
	$state = asmi_get_index_state();
	if ( 'idle' === $state['status'] || 'finished' === $state['status'] ) {
		return;
	}

	$o = asmi_get_opts();

	// PHASE 1: Alle Feeds scannen, um die Gesamtanzahl der Einträge zu ermitteln.
	if ( 'preparing' === $state['status'] ) {
		$prepare_i = intval( $state['feed_prepare_i'] );

		if ( isset( $state['feeds'][ $prepare_i ] ) ) {
			$feed       = $state['feeds'][ $prepare_i ];
			$items_data = asmi_fetch_items( $feed['url'], $o );

			if ( isset( $items_data['error'] ) ) {
				$state['status']      = 'idle';
				// translators: %1$s is the feed URL, %2$s is the error message.
				$state['error']       = sprintf( esc_html__( 'Error with feed %1$s: %2$s', 'asmi-search' ), esc_html( $feed['url'] ), esc_html( $items_data['error'] ) );
				$state['finished_at'] = time();
				asmi_set_index_state( $state );
				return;
			}

			$count                          = count( $items_data );
			$state['total_items']          += $count;
			$state['feed_details'][]        = array(
				'url'       => $feed['url'],
				'lang'      => $feed['lang'],
				'total'     => $count,
				'processed' => 0,
			);
			$state['feed_prepare_i']        = $prepare_i + 1;

			asmi_set_index_state( $state );
			asmi_schedule_next_tick();
			return;

		} else {
			// Nachdem alle Feeds vorbereitet wurden, zur Indexierung übergehen.
			$state['status']         = 'indexing_feeds';
			$state['current_action'] = __( 'Indexing feeds...', 'asmi-search' );
			asmi_set_index_state( $state );
			asmi_schedule_next_tick();
			return;
		}
	}

	// PHASE 2: Die Feeds nacheinander in Batches indexieren.
	if ( 'indexing_feeds' === $state['status'] ) {
		$i = intval( $state['feed_i'] );
		if ( ! isset( $state['feed_details'][ $i ] ) ) {
			// KORREKTUR: Die automatische Indexierung von WordPress-Inhalten wurde entfernt.
			// Die Prozesse sind jetzt entkoppelt, um Timeouts und blockierte Zustände zu verhindern.
			// Der Feed-Import wird jetzt sauber abgeschlossen.
			$state['status']         = 'finished';
			$state['finished_at']    = time();
			$state['current_action'] = '';

			$state['last_run'] = array(
				'type'        => __( 'Import', 'asmi-search' ),
				'status'      => 'completed',
				'finished_at' => $state['finished_at'],
				'duration'    => $state['finished_at'] - $state['started_at'],
				'processed'   => $state['processed_items'],
				'skipped'     => $state['skipped_no_desc'],
				'image_errors' => $state['image_errors'],
			);

			asmi_set_index_state( $state );
			return; // Prozess hier beenden.
		}

		$feed_detail             = $state['feed_details'][ $i ];
		// translators: %1$d is the current feed number, %2$d is the total number of feeds.
		$state['current_action'] = sprintf( esc_html__( 'Indexing feed %1$d/%2$d', 'asmi-search' ), $i + 1, count( $state['feed_details'] ) );

		$items  = asmi_fetch_items( $feed_detail['url'], $o );
		$offset = intval( $state['offset'] );
		$batch  = intval( $state['batch'] );

		if ( empty( $items ) || $offset >= $feed_detail['total'] ) {
			$state['feed_i'] = $i + 1;
			$state['offset'] = 0;
			asmi_set_index_state( $state );
			asmi_schedule_next_tick();
			return;
		}

		$slice       = array_slice( $items, $offset, $batch );
		$batch_stats = asmi_index_upsert_slice( $feed_detail['url'], $feed_detail['lang'], $slice );

		$state['processed_items'] += $batch_stats['processed'];
		$state['skipped_no_desc'] += $batch_stats['skipped_no_desc'];
		$state['image_errors']    += $batch_stats['image_errors'];

		$state['offset']                         = $offset + count( $slice );
		$state['feed_details'][ $i ]['processed'] = $state['offset'];
		$state['updated_at']                     = time();
		asmi_set_index_state( $state );
		asmi_schedule_next_tick();
		return;
	}
}
add_action( ASMI_INDEX_TICK_ACTION, 'asmi_index_tick_handler' );