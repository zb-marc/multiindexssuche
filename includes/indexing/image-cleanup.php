<?php
/**
 * Garbage Collection System für verwaiste Bilder.
 * Löscht automatisch Bilder, die nicht mehr im Index referenziert werden.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Registriere Cron-Handler.
add_action( ASMI_IMAGE_CLEANUP_ACTION, 'asmi_run_image_cleanup' );

/**
 * Führt den automatischen Image-Cleanup durch.
 * Wird täglich via Cron ausgeführt.
 *
 * @return void
 */
function asmi_run_image_cleanup() {
	global $wpdb, $wp_filesystem;
	
	$start_time = time();
	asmi_debug_log( '=== Image Cleanup Started ===' );
	
	// Filesystem API initialisieren.
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	$cache_dir  = asmi_get_image_cache_dir();
	
	// Prüfe ob Tabelle existiert.
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
		asmi_debug_log( 'Image Cleanup: Index table does not exist. Skipping cleanup.' );
		return;
	}
	
	// SCHRITT 1: Hole alle referenzierten Bilder aus dem Index.
	$referenced = $wpdb->get_col(
		"SELECT DISTINCT image FROM {$table_name} WHERE image IS NOT NULL AND image != ''"
	);
	
	if ( empty( $referenced ) ) {
		asmi_debug_log( 'Image Cleanup: No images referenced in index.' );
		return;
	}
	
	// Extrahiere Dateinamen aus URLs.
	$referenced_files = array();
	foreach ( $referenced as $image_url ) {
		$filename = basename( $image_url );
		if ( ! empty( $filename ) ) {
			$referenced_files[] = $filename;
		}
	}
	
	asmi_debug_log( sprintf( 'Image Cleanup: Found %d referenced images in index.', count( $referenced_files ) ) );
	
	// SCHRITT 2: Hole alle Dateien im Cache-Verzeichnis.
	$all_files = glob( $cache_dir['path'] . '/*' );
	if ( false === $all_files ) {
		$all_files = array();
	}
	
	// SCHRITT 3: Finde verwaiste Dateien.
	$deleted       = 0;
	$protected_files = array( '.htaccess', 'index.html' );
	
	foreach ( $all_files as $file ) {
		$basename = basename( $file );
		
		// Überspringe geschützte Dateien.
		if ( in_array( $basename, $protected_files, true ) ) {
			continue;
		}
		
		// Überspringe Verzeichnisse.
		if ( is_dir( $file ) ) {
			continue;
		}
		
		// Prüfe ob Datei im Index referenziert wird.
		if ( ! in_array( $basename, $referenced_files, true ) ) {
			// Datei ist verwaist - lösche sie.
			if ( $wp_filesystem->delete( $file ) ) {
				++$deleted;
				asmi_debug_log( 'Image Cleanup: Deleted orphaned file: ' . $basename );
			} else {
				asmi_debug_log( 'Image Cleanup: Failed to delete file: ' . $basename );
			}
		}
	}
	
	$duration = time() - $start_time;
	
	// SCHRITT 4: Statistiken loggen.
	asmi_debug_log(
		sprintf(
			'=== Image Cleanup Completed === Duration: %ds | Deleted: %d orphaned files | Referenced: %d files',
			$duration,
			$deleted,
			count( $referenced_files )
		)
	);
	
	// SCHRITT 5: Cleanup-State aktualisieren für Admin-Anzeige.
	$state = array(
		'last_run'           => current_time( 'mysql' ),
		'deleted_count'      => $deleted,
		'referenced_count'   => count( $referenced_files ),
		'duration'           => $duration,
		'next_scheduled'     => wp_next_scheduled( ASMI_IMAGE_CLEANUP_ACTION ),
	);
	update_option( ASMI_IMAGE_CLEANUP_STATE_OPT, $state, false );
}

/**
 * Ruft den Status des letzten Image-Cleanups ab.
 *
 * @return array Der Cleanup-Status.
 */
function asmi_get_image_cleanup_state() {
	$default = array(
		'last_run'         => null,
		'deleted_count'    => 0,
		'referenced_count' => 0,
		'duration'         => 0,
		'next_scheduled'   => wp_next_scheduled( ASMI_IMAGE_CLEANUP_ACTION ),
	);
	
	$state = get_option( ASMI_IMAGE_CLEANUP_STATE_OPT, $default );
	
	// Aktualisiere next_scheduled falls es sich geändert hat.
	$state['next_scheduled'] = wp_next_scheduled( ASMI_IMAGE_CLEANUP_ACTION );
	
	return $state;
}

/**
 * Startet einen manuellen Image-Cleanup.
 *
 * @return void
 */
function asmi_trigger_manual_cleanup() {
	asmi_debug_log( 'Manual image cleanup triggered.' );
	asmi_run_image_cleanup();
}
