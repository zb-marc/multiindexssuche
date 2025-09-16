<?php
/**
 * Steuerungslogik für den Indexierungsprozess.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setzt den Index zurück und startet den asynchronen Indexierungsprozess für Feeds.
 *
 * @return void
 */
function asmi_index_reset_and_start() {
	$current_state = asmi_get_index_state();
	if ( in_array( $current_state['status'], array( 'preparing', 'indexing_feeds' ), true ) ) {
		$time_since_update = time() - $current_state['updated_at'];
		if ( $current_state['updated_at'] > 0 && $time_since_update < 600 ) {
			return;
		}
	}

	$o = asmi_get_opts();

	// Löscht nur die Produkte, da der WP-Content bei der Neuindexierung ohnehin gelöscht wird.
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DELETE FROM $table_name WHERE source_type = 'product'" );

	// Alte Feed-Cache-Transients löschen, um frische Daten zu erzwingen.
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_asmi\_feed\_%'" );

	$feeds    = array();
	$de_feeds = ! empty( $o['feed_urls'] ) ? array_filter( array_map( 'trim', explode( ',', $o['feed_urls'] ) ) ) : array();
	$en_feeds = ! empty( $o['feed_urls_en'] ) ? array_filter( array_map( 'trim', explode( ',', $o['feed_urls_en'] ) ) ) : array();

	foreach ( $de_feeds as $url ) {
		$feeds[] = array( 'url' => $url, 'lang' => 'de' );
	}
	foreach ( $en_feeds as $url ) {
		$feeds[] = array( 'url' => $url, 'lang' => 'en' );
	}

	$state = array(
		'status'           => 'preparing',
		'feeds'            => $feeds,
		'feed_details'     => array(),
		'feed_i'           => 0,
		'feed_prepare_i'   => 0,
		'offset'           => 0,
		'batch'            => $o['index_batch'],
		'total_items'      => 0,
		'processed_items'  => 0,
		'skipped_no_desc'  => 0,
		'image_errors'     => 0,
		'current_action'   => __( 'Scanning feeds...', 'asmi-search' ),
		'started_at'       => time(),
		'updated_at'       => time(),
		'finished_at'      => 0,
		'error'            => '',
	);
	asmi_set_index_state( $state );

	asmi_schedule_next_tick( true );
}

/**
 * Bricht einen laufenden Indexierungsprozess ab.
 *
 * @return void
 */
function asmi_index_cancel() {
	wp_clear_scheduled_hook( ASMI_INDEX_TICK_ACTION );

	$state = asmi_get_index_state();
	if ( 'idle' !== $state['status'] && 'finished' !== $state['status'] ) {
		$state['status']      = 'idle';
		$state['error']       = __( 'The process was manually canceled by the user.', 'asmi-search' );
		$state['finished_at'] = time();

		$state['last_run'] = array(
			'type'        => __( 'Import', 'asmi-search' ),
			'status'      => 'cancelled',
			'finished_at' => $state['finished_at'],
			'duration'    => $state['finished_at'] - ( $state['started_at'] > 0 ? $state['started_at'] : time() ),
			'processed'   => $state['processed_items'],
			'skipped'     => $state['skipped_no_desc'],
			'image_errors' => $state['image_errors'],
		);

		asmi_set_index_state( $state );
	}
}

/**
 * Leert die komplette Index-Tabelle (Produkte und WordPress-Inhalte).
 *
 * @return void
 */
function asmi_index_clear_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	// KORREKTUR: Verwendet TRUNCATE TABLE, um die gesamte Tabelle (Produkte UND WordPress) effizient zu leeren.
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "TRUNCATE TABLE {$table_name}" );
}

/**
 * Plant den nächsten Tick des asynchronen Indexierungsprozesses.
 *
 * @param bool $is_first_run Gibt an, ob dies der erste Tick des Prozesses ist.
 * @return void
 */
function asmi_schedule_next_tick( $is_first_run = false ) {
	$o = asmi_get_opts();
	if ( empty( $o['high_speed_indexing'] ) && ! $is_first_run ) {
		if ( ! wp_next_scheduled( ASMI_INDEX_TICK_ACTION ) ) {
			wp_schedule_single_event( time() + 5, ASMI_INDEX_TICK_ACTION );
		}
		return;
	}

	$token = get_option( 'asmi_tick_token' );
	if ( empty( $token ) ) {
		$token = wp_generate_password( 64, false, false );
		update_option( 'asmi_tick_token', $token );
	}

	$url      = rest_url( ASMI_REST_NS . '/index/tick' );
	$args     = array(
		'method'    => 'POST',
		'timeout'   => 0.01,
		'blocking'  => false,
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		'body'      => array( 'token' => $token ),
	);
	$response = wp_remote_post( $url, $args );

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		asmi_debug_log( 'Loopback request failed: ' . $error_message );

		$state                = asmi_get_index_state();
		$state['status']      = 'idle';
		// translators: %s contains the technical error message.
		$state['error']       = sprintf( __( 'Could not start the indexing process. The server failed to connect to itself (loopback request). Please contact your host. Error: %s', 'asmi-search' ), $error_message );
		$state['finished_at'] = time();
		asmi_set_index_state( $state );
	}
}