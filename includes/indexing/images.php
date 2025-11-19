<?php
/**
 * Bildverwaltungs-Funktionen mit URL-basiertem Caching.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( ASMI_IMAGE_DELETE_TICK_ACTION, 'asmi_image_deletion_tick_handler' );

/**
 * Holt und initialisiert das WordPress Filesystem API.
 *
 * @return WP_Filesystem_Base|false Das Filesystem-Objekt oder false bei Fehler.
 */
function asmi_get_wp_filesystem() {
	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}
	return $wp_filesystem;
}

/**
 * Erstellt den dedizierten Upload-Ordner und sichert ihn ab.
 * Gibt das Verzeichnis und die URL zurück.
 *
 * @return array Array mit 'path' und 'url' Schlüsseln.
 */
function asmi_get_image_cache_dir() {
	$upload_dir     = wp_upload_dir();
	$cache_dir_path = $upload_dir['basedir'] . '/' . ASMI_UPLOAD_DIR;
	$cache_dir_url  = $upload_dir['baseurl'] . '/' . ASMI_UPLOAD_DIR;
	
	if ( ! file_exists( $cache_dir_path ) ) {
		wp_mkdir_p( $cache_dir_path );
	}

	$fs = asmi_get_wp_filesystem();
	if ( $fs ) {
		if ( ! file_exists( $cache_dir_path . '/index.html' ) ) {
			$fs->put_contents( $cache_dir_path . '/index.html', '' );
		}
		if ( ! file_exists( $cache_dir_path . '/.htaccess' ) ) {
			$fs->put_contents( $cache_dir_path . '/.htaccess', 'Options -Indexes' );
		}
	}

	return array(
		'path' => $cache_dir_path,
		'url'  => $cache_dir_url,
	);
}

/**
 * Lädt ein Bild mit URL-basiertem Caching herunter.
 * Prüft zuerst, ob die URL bereits heruntergeladen wurde.
 *
 * @param string $url Die Bild-URL.
 * @return string|WP_Error Die lokale URL oder WP_Error bei Fehler.
 */
function asmi_download_image_to_local_dir( $url ) {
	global $wpdb;
	
	if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return new WP_Error( 'invalid_url', __( 'Ungültige Bild-URL.', 'asmi-search' ) );
	}

	// URL-Hash für Duplikatsprüfung.
	$url_hash    = md5( $url );
	$table_name  = $wpdb->prefix . ASMI_INDEX_TABLE;
	
	// Prüfe ob diese URL bereits heruntergeladen wurde.
	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT image FROM {$table_name} WHERE image_url_hash = %s AND image IS NOT NULL AND image != '' LIMIT 1",
			$url_hash
		)
	);
	
	if ( $existing ) {
		// Prüfe ob Datei noch existiert.
		$cache_dir  = asmi_get_image_cache_dir();
		$local_path = str_replace( $cache_dir['url'], $cache_dir['path'], $existing );
		
		if ( file_exists( $local_path ) ) {
			asmi_debug_log( 'Image already cached: ' . $url );
			return $existing;
		}
		
		asmi_debug_log( 'Cached image file missing, re-downloading: ' . $url );
	}

	// Download erforderlich.
	$filename           = basename( parse_url( $url, PHP_URL_PATH ) );
	$sanitized_filename = sanitize_file_name( $filename );

	$cache_dir       = asmi_get_image_cache_dir();
	$unique_filename = wp_unique_filename( $cache_dir['path'], $sanitized_filename );
	$target_file     = trailingslashit( $cache_dir['path'] ) . $unique_filename;

	// WordPress HTTP API mit Streaming verwenden.
	$response = wp_remote_get(
		$url,
		array(
			'timeout'  => 15,
			'stream'   => true,
			'filename' => $target_file,
		)
	);

	if ( is_wp_error( $response ) ) {
		asmi_debug_log( 'Image download failed for ' . $url . ': ' . $response->get_error_message() );
		return $response;
	}

	$http_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $http_code ) {
		// Lösche die (wahrscheinlich leere) Datei, die bei einem Fehler erstellt wurde.
		if ( file_exists( $target_file ) ) {
			wp_delete_file( $target_file );
		}
		return new WP_Error(
			'download_failed',
			/* translators: %s: HTTP status code */
			sprintf( __( 'Bild-Download fehlgeschlagen (HTTP-Code: %s)', 'asmi-search' ), $http_code )
		);
	}

	$local_url = trailingslashit( $cache_dir['url'] ) . $unique_filename;
	
	asmi_debug_log( 'Image downloaded successfully: ' . $url . ' -> ' . $local_url );
	
	return $local_url;
}

/**
 * Löscht ein einzelnes Bild aus dem Cache wenn es nicht mehr referenziert wird.
 *
 * @param string $image_url Die lokale Bild-URL.
 * @return bool True bei Erfolg, false bei Fehler.
 */
function asmi_delete_orphaned_image( $image_url ) {
	if ( empty( $image_url ) ) {
		return false;
	}

	$cache_dir  = asmi_get_image_cache_dir();
	
	// Prüfe ob URL zu unserem Cache gehört.
	if ( strpos( $image_url, $cache_dir['url'] ) === false ) {
		return false;
	}

	$local_path = str_replace( $cache_dir['url'], $cache_dir['path'], $image_url );
	
	if ( file_exists( $local_path ) ) {
		return wp_delete_file( $local_path );
	}

	return false;
}

/**
 * Löscht den gesamten Bilder-Cache-Ordner.
 *
 * @return bool True bei Erfolg, false bei Fehler.
 */
function asmi_delete_image_cache_folder() {
	$fs = asmi_get_wp_filesystem();
	if ( ! $fs ) {
		return false;
	}

	$cache_dir = asmi_get_image_cache_dir();
	
	return $fs->rmdir( $cache_dir['path'], true );
}

/**
 * Startet den asynchronen Löschprozess für die Bilder im Cache-Ordner.
 *
 * @return void
 */
function asmi_start_image_folder_deletion() {
	$cache_dir = asmi_get_image_cache_dir();
	$files     = glob( $cache_dir['path'] . '/*' );
	
	if ( empty( $files ) ) {
		asmi_set_image_deletion_state(
			array(
				'status'      => 'finished',
				'total'       => 0,
				'deleted'     => 0,
				'finished_at' => time(),
			)
		);
		return;
	}
	
	$image_files = array_filter(
		$files,
		function ( $file ) {
			return ! in_array( basename( $file ), array( 'index.html', '.htaccess' ), true );
		}
	);

	if ( empty( $image_files ) ) {
		asmi_set_image_deletion_state(
			array(
				'status'      => 'finished',
				'total'       => 0,
				'deleted'     => 0,
				'finished_at' => time(),
			)
		);
		return;
	}

	$state = array(
		'status'     => 'deleting',
		'total'      => count( $image_files ),
		'deleted'    => 0,
		'files'      => array_values( $image_files ),
		'offset'     => 0,
		'started_at' => time(),
	);
	asmi_set_image_deletion_state( $state );
	asmi_schedule_next_image_delete_tick();
}

/**
 * Plant den nächsten Tick für den Bild-Löschprozess.
 *
 * @return void
 */
function asmi_schedule_next_image_delete_tick() {
	$token = get_option( 'asmi_tick_token' );
	if ( empty( $token ) ) {
		$token = wp_generate_password( 64, false, false );
		update_option( 'asmi_tick_token', $token );
	}
	
	$url  = rest_url( ASMI_REST_NS . '/images/delete/tick' );
	$args = array(
		'method'    => 'POST',
		'timeout'   => 0.01,
		'blocking'  => false,
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		'body'      => array( 'token' => $token ),
	);
	wp_remote_post( $url, $args );
}

/**
 * Der Handler, der pro Tick eine Charge von Bildern löscht.
 *
 * @return void
 */
function asmi_image_deletion_tick_handler() {
	$st = asmi_get_image_deletion_state();
	if ( 'deleting' !== $st['status'] ) {
		return;
	}

	$batch_size  = 200;
	$files_slice = array_slice( $st['files'], $st['offset'], $batch_size );

	if ( empty( $files_slice ) ) {
		$st['status']      = 'finished';
		$st['finished_at'] = time();
		asmi_set_image_deletion_state( $st );
		return;
	}

	$fs = asmi_get_wp_filesystem();
	if ( $fs ) {
		foreach ( $files_slice as $file_path ) {
			if ( $fs->exists( $file_path ) ) {
				$fs->delete( $file_path );
			}
		}
	}
	
	$st['deleted'] += count( $files_slice );
	$st['offset']  += $batch_size;
	asmi_set_image_deletion_state( $st );

	if ( 'deleting' === $st['status'] ) {
		asmi_schedule_next_image_delete_tick();
	}
}
