<?php
/**
 * Admin-bezogene Ajax-Aktionen.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax-Handler für ChatGPT Cache-Tabellen-Erstellung.
 */
function asmi_ajax_create_chatgpt_cache_table() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}
	
	check_ajax_referer( 'asmi_create_cache_table', '_wpnonce' );
	
	// Erstelle die Cache-Tabelle
	global $wpdb;
	$table_name = $wpdb->prefix . 'asmi_chatgpt_cache';
	$charset_collate = $wpdb->get_charset_collate();
	
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id int(11) NOT NULL AUTO_INCREMENT,
		cache_key varchar(64) NOT NULL,
		lang varchar(5) NOT NULL,
		response_data longtext NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY cache_lang (cache_key, lang),
		KEY created_at (created_at)
	) $charset_collate;";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	// Prüfe ob Tabelle existiert
	$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
	
	if ( $table_exists ) {
		wp_send_json_success( array( 
			'message' => __( 'ChatGPT cache table created successfully!', 'asmi-search' ) 
		) );
	} else {
		wp_send_json_error( array( 
			'message' => __( 'Failed to create cache table. Please check database permissions.', 'asmi-search' ) 
		) );
	}
}
add_action( 'wp_ajax_asmi_create_chatgpt_cache_table', 'asmi_ajax_create_chatgpt_cache_table' );

/**
/**
 * Ajax-Handler für ChatGPT API Test.
 */
function asmi_ajax_test_chatgpt() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}
	
	check_ajax_referer( 'asmi_test_chatgpt', '_wpnonce' );
	
	$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
	$model = sanitize_text_field( $_POST['model'] ?? 'gpt-4o-mini' );
	$assistant_id = sanitize_text_field( $_POST['assistant_id'] ?? '' );
	
	if ( empty( $api_key ) ) {
		wp_send_json_error( array( 'message' => __( 'API key is required', 'asmi-search' ) ) );
	}
	
	// Test Assistant wenn ID vorhanden
	if ( ! empty( $assistant_id ) ) {
		// Test Assistant-Abruf
		$response = wp_remote_get( "https://api.openai.com/v1/assistants/{$assistant_id}", array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'OpenAI-Beta'   => 'assistants=v2'
			)
		));
		
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 
				'message' => sprintf( __( 'Connection failed: %s', 'asmi-search' ), $response->get_error_message() ) 
			) );
		}
		
		$body = wp_remote_retrieve_body( $response );
		$body = function_exists('asmi_normalize_to_utf8') ? asmi_normalize_to_utf8($body) : $body;
		$data = json_decode( $body, true );
		
		if ( isset( $data['error'] ) ) {
			wp_send_json_error( array( 
				'message' => sprintf( __( 'Assistant Error: %s', 'asmi-search' ), $data['error']['message'] ?? 'Unknown error' ) 
			) );
		}
		
		if ( isset( $data['id'] ) && $data['id'] === $assistant_id ) {
			wp_send_json_success( array( 
				'message' => sprintf( 
					__( 'Assistant connected! Name: %s, Model: %s', 'asmi-search' ), 
					$data['name'] ?? 'Unnamed',
					$data['model'] ?? 'Unknown'
				) 
			) );
		}
	}
	
	// Fallback: Test normale API
	$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
		'timeout' => 10,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'body' => wp_json_encode( array(
			'model' => $model,
			'messages' => array(
				array( 'role' => 'user', 'content' => 'Say "API connection successful" in exactly 3 words.' )
			),
			'max_tokens' => 10,
			'temperature' => 0
		) ),
	) );
	
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 
			'message' => sprintf( __( 'Connection failed: %s', 'asmi-search' ), $response->get_error_message() ) 
		) );
	}
	
	$body = wp_remote_retrieve_body( $response );
		$body = function_exists('asmi_normalize_to_utf8') ? asmi_normalize_to_utf8($body) : $body;
	$data = json_decode( $body, true );
	
	if ( isset( $data['error'] ) ) {
		wp_send_json_error( array( 
			'message' => sprintf( __( 'API Error: %s', 'asmi-search' ), $data['error']['message'] ?? 'Unknown error' ) 
		) );
	}
	
	if ( isset( $data['choices'][0]['message']['content'] ) ) {
		wp_send_json_success( array( 
			'message' => sprintf( 
				__( 'Connection successful! Model: %s', 'asmi-search' ), 
				$data['model'] ?? $model 
			) 
		) );
	}
	
	wp_send_json_error( array( 'message' => __( 'Unexpected response format', 'asmi-search' ) ) );
}
add_action( 'wp_ajax_asmi_test_chatgpt', 'asmi_ajax_test_chatgpt' );

/**
 * Ajax-Handler für erzwungene Kompakt-Neuindexierung.
 *
 * @return void
 */
function asmi_ajax_force_compact_reindex() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}
	
	check_ajax_referer( 'asmi_force_reindex', '_wpnonce' );
	
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	
	// Lösche alle nicht-manuellen WordPress-Einträge
	$deleted = $wpdb->query( 
		"DELETE FROM $table_name 
		WHERE source_type = 'wordpress' 
		AND content_hash NOT LIKE '%manual_import%'"
	);
	
	asmi_debug_log( sprintf( 'Force reindex: Deleted %d old entries', $deleted ) );
	
	// Starte Neuindexierung
	if ( function_exists( 'asmi_index_all_wp_content' ) ) {
		asmi_index_all_wp_content();
	}
	
	$o = asmi_get_opts();
	$method = ! empty( $o['use_chatgpt'] ) && ! empty( $o['chatgpt_api_key'] ) ? 'ChatGPT' : 'keyword extraction';
	
	wp_send_json_success( array(
		'message' => sprintf( 
			__( 'Re-indexing complete using %s. %d old entries removed.', 'asmi-search' ), 
			$method,
			$deleted 
		)
	) );
}
add_action( 'wp_ajax_asmi_force_compact_reindex', 'asmi_ajax_force_compact_reindex' );