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
	asmi_debug_log( 'INDEX START: Beginning indexing process' );
	
	$current_state = asmi_get_index_state();
	if ( in_array( $current_state['status'], array( 'preparing', 'indexing_feeds' ), true ) ) {
		$time_since_update = time() - $current_state['updated_at'];
		if ( $current_state['updated_at'] > 0 && $time_since_update < 600 ) {
			asmi_debug_log( 'INDEX BLOCKED: Another process is running' );
			return;
		}
	}

	$o = asmi_get_opts();
	asmi_debug_log( 'INDEX CONFIG: Feed URLs DE: ' . $o['feed_urls'] );
	asmi_debug_log( 'INDEX CONFIG: Feed URLs EN: ' . $o['feed_urls_en'] );

	// Löscht nur die Produkte
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	$deleted = $wpdb->query( "DELETE FROM $table_name WHERE source_type = 'product'" );
	asmi_debug_log( 'INDEX CLEANUP: Deleted ' . $deleted . ' old product entries' );

	// Alte Feed-Cache-Transients löschen
	$cache_deleted = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_asmi\_feed\_%'" );
	asmi_debug_log( 'INDEX CLEANUP: Deleted ' . $cache_deleted . ' cache entries' );

	$feeds = array();
	$de_feeds = ! empty( $o['feed_urls'] ) ? array_filter( array_map( 'trim', explode( ',', $o['feed_urls'] ) ) ) : array();
	$en_feeds = ! empty( $o['feed_urls_en'] ) ? array_filter( array_map( 'trim', explode( ',', $o['feed_urls_en'] ) ) ) : array();

	asmi_debug_log( 'INDEX FEEDS: Found ' . count( $de_feeds ) . ' DE feeds and ' . count( $en_feeds ) . ' EN feeds' );

	foreach ( $de_feeds as $url ) {
		$feeds[] = array( 'url' => $url, 'lang' => 'de' );
		asmi_debug_log( 'INDEX FEED: Added DE feed: ' . $url );
	}
	foreach ( $en_feeds as $url ) {
		$feeds[] = array( 'url' => $url, 'lang' => 'en' );
		asmi_debug_log( 'INDEX FEED: Added EN feed: ' . $url );
	}

	if ( empty( $feeds ) ) {
		asmi_debug_log( 'INDEX ERROR: No feeds configured! Please add feed URLs in settings.' );
		$state = array(
			'status' => 'idle',
			'error' => 'Keine Feed-URLs konfiguriert. Bitte fügen Sie Feed-URLs in den Einstellungen hinzu.',
			'finished_at' => time(),
		);
		asmi_set_index_state( $state );
		return;
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
	
	asmi_debug_log( 'INDEX STATE: Set initial state with ' . count( $feeds ) . ' feeds' );
	asmi_set_index_state( $state );

	asmi_debug_log( 'INDEX SCHEDULE: Scheduling first tick' );
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
	
	asmi_debug_log( 'SCHEDULE: High speed indexing: ' . ( $o['high_speed_indexing'] ? 'YES' : 'NO' ) );
	
	if ( empty( $o['high_speed_indexing'] ) && ! $is_first_run ) {
		if ( ! wp_next_scheduled( ASMI_INDEX_TICK_ACTION ) ) {
			wp_schedule_single_event( time() + 5, ASMI_INDEX_TICK_ACTION );
			asmi_debug_log( 'SCHEDULE: Using WP Cron for next tick' );
		}
		return;
	}

	$token = get_option( 'asmi_tick_token' );
	if ( empty( $token ) ) {
		$token = wp_generate_password( 64, false, false );
		update_option( 'asmi_tick_token', $token );
	}

	$url = rest_url( ASMI_REST_NS . '/index/tick' );
	asmi_debug_log( 'SCHEDULE: Loopback URL: ' . $url );
	
	$args = array(
		'method'    => 'POST',
		'timeout'   => 0.01,
		'blocking'  => false,
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		'body'      => array( 'token' => $token ),
	);
	
	$response = wp_remote_post( $url, $args );

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		asmi_debug_log( 'SCHEDULE ERROR: Loopback request failed: ' . $error_message );

		$state = asmi_get_index_state();
		$state['status'] = 'idle';
		$state['error'] = sprintf( __( 'Could not start the indexing process. The server failed to connect to itself (loopback request). Please contact your host. Error: %s', 'asmi-search' ), $error_message );
		$state['finished_at'] = time();
		asmi_set_index_state( $state );
	} else {
		asmi_debug_log( 'SCHEDULE SUCCESS: Loopback request sent' );
	}
}