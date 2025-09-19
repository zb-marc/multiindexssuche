<?php
/**
 * Registriert die REST-API-Endpunkte für das Plugin.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert alle REST-Routen.
 */
function asmi_register_rest_routes() {
	$o = asmi_get_opts();
	if ( $o['expose_rest'] ) {
		register_rest_route( ASMI_REST_NS, '/' . ASMI_REST_ROUTE, [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => [
				'q'    => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				'lang' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
			],
			'callback'            => 'asmi_rest_search',
		]);
	}
	register_rest_route( ASMI_REST_NS, '/index/status', [
		'methods' => 'GET', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => 'asmi_rest_get_status',
	]);
	register_rest_route( ASMI_REST_NS, '/index/reindex', [
		'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => 'asmi_rest_reindex',
	]);
	register_rest_route( ASMI_REST_NS, '/index/reindex-wp', [
		'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => 'asmi_rest_reindex_wp',
	]);
	register_rest_route( ASMI_REST_NS, '/index/tick', [
		'methods' => 'POST', 'permission_callback' => '__return_true',
		'callback' => 'asmi_rest_tick',
	]);
	register_rest_route( ASMI_REST_NS, '/index/cancel', [
		'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => 'asmi_rest_cancel',
	]);
	register_rest_route( ASMI_REST_NS, '/index/clear', [
		'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => 'asmi_rest_clear',
	]);
	register_rest_route( ASMI_REST_NS, '/db/repair', [
		'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => 'asmi_rest_db_repair',
	]);
	register_rest_route( ASMI_REST_NS, '/images/delete/start', [
		'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => 'asmi_rest_image_delete_start',
	]);
	register_rest_route( ASMI_REST_NS, '/images/delete/status', [
		'methods' => 'GET', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => 'asmi_rest_image_delete_status',
	]);
	register_rest_route( ASMI_REST_NS, '/images/delete/tick', [
		'methods' => 'POST', 'permission_callback' => '__return_true',
		'callback' => 'asmi_rest_image_delete_tick',
	]);
}
add_action( 'rest_api_init', 'asmi_register_rest_routes' );

function asmi_rest_search( WP_REST_Request $r ) {
	$o = asmi_get_opts();
	$q = trim( (string) $r->get_param( 'q' ) );
	
	// KORREKTUR: Explizite Sprachbehandlung mit Debug-Output
	$lang_param = $r->get_param( 'lang' );
	$lang = !empty($lang_param) ? $lang_param : 'de';
	
	// Debug-Logging für die API-Anfrage
	asmi_debug_log("REST API search - Query: '{$q}', Language param: '{$lang_param}', Using: '{$lang}'");
	
	if ( mb_strlen( $q ) < 2 ) {
		return [ 'query' => $q, 'count' => 0, 'results' => [] ];
	}
	
	// WICHTIG: Übergebe die Sprache korrekt an die Suchfunktion
	$search_data = asmi_unified_search( $q, (int) $o['max_results'], $lang );
	
	$response = [
		'query' => $q,
		'lang' => $lang,  // KORREKTUR: Sprache in Response für Debugging
		'count' => count($search_data['products']) + count($search_data['wordpress']),
		'results' => $search_data,
	];
	
	// Debug-Logging der Response
	asmi_debug_log("REST API response - Products: " . count($search_data['products']) . ", WordPress: " . count($search_data['wordpress']));
	
	return $response;
}
function asmi_rest_get_status() {
	return new WP_REST_Response([ 'state' => asmi_get_index_state(), 'stats' => asmi_get_index_stats() ], 200 );
}
function asmi_rest_reindex() {
	asmi_index_reset_and_start();
	return new WP_REST_Response([ 'ok' => true, 'state' => asmi_get_index_state() ], 200 );
}
function asmi_rest_reindex_wp() {
	asmi_index_all_wp_content();
	return new WP_REST_Response([ 'ok' => true, 'message' => 'WordPress content indexing completed.' ], 200 );
}
function asmi_rest_tick( WP_REST_Request $r ) {
	$sent_token = $r->get_param( 'token' );
	$stored_token = get_option( 'asmi_tick_token' );
	
	asmi_debug_log( 'REST TICK: Token check - sent: ' . substr($sent_token, 0, 10) . '..., stored: ' . substr($stored_token, 0, 10) . '...' );
	
	if ( empty( $sent_token ) || empty( $stored_token ) || ! hash_equals( $stored_token, $sent_token ) ) {
		asmi_debug_log( 'REST TICK: Token validation FAILED' );
		return new WP_Error( 'invalid_token', 'Invalid token', [ 'status' => 403 ] );
	}
	
	asmi_debug_log( 'REST TICK: Token validated, executing tick action' );
	do_action( ASMI_INDEX_TICK_ACTION );
	asmi_debug_log( 'REST TICK: Tick action completed' );
	
	return new WP_REST_Response( [ 'ok' => true ], 200 );
}
function asmi_rest_cancel() {
	asmi_index_cancel();
	return new WP_REST_Response([ 'ok' => true, 'state' => asmi_get_index_state() ], 200 );
}
function asmi_rest_clear() {
	asmi_index_clear_table();
	return new WP_REST_Response( [ 'ok' => true ], 200 );
}
function asmi_rest_db_repair() {
	asmi_install_and_repair_database();
	return new WP_REST_Response( [ 'ok' => true, 'message' => 'Database repair executed.' ], 200 );
}
function asmi_rest_image_delete_start() {
	asmi_start_image_folder_deletion();
	return new WP_REST_Response( [ 'ok' => true ], 200 );
}
function asmi_rest_image_delete_status() {
	return new WP_REST_Response( [ 'state' => asmi_get_image_deletion_state() ], 200 );
}
function asmi_rest_image_delete_tick( WP_REST_Request $r ) {
	$sent_token = $r->get_param( 'token' );
	$stored_token = get_option( 'asmi_tick_token' );
	if ( empty( $sent_token ) || empty( $stored_token ) || ! hash_equals( $stored_token, $sent_token ) ) {
		return new WP_Error( 'invalid_token', 'Invalid token', [ 'status' => 403 ] );
	}
	do_action( ASMI_IMAGE_DELETE_TICK_ACTION );
	return new WP_REST_Response( [ 'ok' => true ], 200 );
}