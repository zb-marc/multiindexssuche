<?php
/**
 * Import/Export-Funktionalität für WordPress-Index-Daten.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exportiert alle WordPress-Inhalte aus dem Index als CSV.
 *
 * @return void
 */
function asmi_handle_export_wp_index() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}

	check_admin_referer( 'asmi_export_wp_index' );

	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;

	// Hole alle WordPress-Einträge
	$results = $wpdb->get_results(
		"SELECT source_id, lang, title, content, excerpt, url, image, content_hash, last_modified 
		FROM $table_name 
		WHERE source_type = 'wordpress' 
		ORDER BY source_id, lang",
		ARRAY_A
	);

	if ( empty( $results ) ) {
		wp_die( __( 'No WordPress content found in index.', 'asmi-search' ) );
	}

	// Verwende fputcsv für korrektes CSV-Format
	// Öffne temporären Stream
	$temp = fopen( 'php://temp', 'r+' );
	
	// UTF-8 BOM für Excel-Kompatibilität
	fwrite( $temp, "\xEF\xBB\xBF" );
	
	// CSV-Header
	$headers = array(
		'post_id',
		'language', 
		'post_title_original',
		'title',
		'content',
		'excerpt',
		'url',
		'image_url',
		'content_hash',
		'last_modified'
	);
	fputcsv( $temp, $headers );

	// Füge Daten hinzu
	foreach ( $results as $row ) {
		// Hole den Original-Titel des Posts für Referenz
		$original_title = get_the_title( $row['source_id'] );
		
		$fields = array(
			$row['source_id'],
			$row['lang'],
			$original_title,
			$row['title'] ?? '',
			$row['content'] ?? '',
			$row['excerpt'] ?? '',
			$row['url'] ?? '',
			$row['image'] ?? '',
			$row['content_hash'] ?? '',
			$row['last_modified'] ?? ''
		);
		
		// fputcsv kümmert sich automatisch um korrektes Escaping
		fputcsv( $temp, $fields );
	}

	// Setze Pointer zurück zum Anfang
	rewind( $temp );
	
	// Lese gesamten Inhalt
	$csv_data = stream_get_contents( $temp );
	fclose( $temp );

	// Setze Headers für CSV-Download
	$filename = 'asmi-wp-index-export-' . date( 'Y-m-d-His' ) . '.csv';
	
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	
	echo $csv_data;
	exit;
}
add_action( 'admin_post_asmi_export_wp_index', 'asmi_handle_export_wp_index' );

/**
 * Importiert WordPress-Index-Daten aus einer CSV-Datei.
 *
 * @return void
 */
function asmi_handle_import_wp_index() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}

	check_admin_referer( 'asmi_import_wp_index' );

	// Prüfe ob Datei hochgeladen wurde
	if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
		wp_die( __( 'File upload failed.', 'asmi-search' ) );
	}

	$uploaded_file = $_FILES['import_file'];
	
	// Validiere Dateityp
	$file_ext = strtolower( pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION ) );
	if ( $file_ext !== 'csv' ) {
		wp_die( __( 'Please upload a CSV file.', 'asmi-search' ) );
	}

	// Verwende fgetcsv für robustes CSV-Parsing
	$file_handle = fopen( $uploaded_file['tmp_name'], 'r' );
	if ( ! $file_handle ) {
		wp_die( __( 'Could not open uploaded file.', 'asmi-search' ) );
	}

	// Überspringe BOM falls vorhanden
	$bom = fread( $file_handle, 3 );
	if ( $bom !== "\xEF\xBB\xBF" ) {
		// Kein BOM, zurückspulen
		rewind( $file_handle );
	}

	$header = null;
	$imported = 0;
	$updated = 0;
	$errors = array();
	$line_num = 0;

	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;

	// Lese CSV Zeile für Zeile
	while ( ( $data = fgetcsv( $file_handle, 0, ',', '"', '\\' ) ) !== false ) {
		$line_num++;
		
		// Erste Zeile als Header
		if ( $header === null ) {
			$header = $data;
			asmi_debug_log( 'CSV Import - Header detected: ' . implode( ', ', $header ) );
			continue;
		}

		// Validiere Anzahl der Felder
		if ( count( $data ) !== count( $header ) ) {
			$errors[] = sprintf( 
				__( 'Line %d: Invalid number of fields (expected %d, got %d)', 'asmi-search' ), 
				$line_num, 
				count( $header ), 
				count( $data ) 
			);
			continue;
		}

		// Erstelle assoziatives Array
		$row = array_combine( $header, $data );
		
		// Behandle source_id konsistent als String (wie in DB definiert)
		$post_id = trim( $row['post_id'] );
		$language = trim( $row['language'] );
		
		// Debug-Log für erste Zeilen
		if ( $line_num <= 5 ) {
			asmi_debug_log( sprintf( 
				'CSV Import - Line %d: post_id=%s, lang=%s, title=%s', 
				$line_num,
				$post_id, 
				$language,
				substr( $row['title'] ?? '', 0, 50 ) . '...'
			) );
		}
		
		// Validiere erforderliche Felder
		if ( empty( $post_id ) || empty( $language ) ) {
			$errors[] = sprintf( __( 'Line %d: Missing post_id or language', 'asmi-search' ), $line_num );
			continue;
		}

		// Prüfe ob Post existiert
		$post = get_post( intval( $post_id ) );
		if ( ! $post ) {
			$errors[] = sprintf( 
				__( 'Line %d: Post ID %s does not exist', 'asmi-search' ), 
				$line_num, 
				$post_id 
			);
			continue;
		}

		// Prüfe ob Eintrag existiert
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $table_name 
				WHERE source_id = %s AND lang = %s AND source_type = %s",
				$post_id,
				$language,
				'wordpress'
			),
			ARRAY_A
		);

		// Bereite Daten für Datenbank vor
		$db_data = array(
			'source_id'     => $post_id,
			'lang'          => $language,
			'source_type'   => 'wordpress',
			'title'         => $row['title'] ?? '',
			'content'       => $row['content'] ?? '',
			'excerpt'       => $row['excerpt'] ?? '',
			'url'           => $row['url'] ?? '',
			'image'         => $row['image_url'] ?? '',
			'indexed_at'    => current_time( 'mysql' ),
		);

		// Content-Hash und last_modified
		if ( ! empty( $row['content_hash'] ) ) {
			$db_data['content_hash'] = $row['content_hash'];
		} else {
			// Generiere neuen Hash basierend auf importierten Daten
			$content_for_hash = $db_data['title'] . '|' . $db_data['content'] . '|' . $db_data['image'];
			$db_data['content_hash'] = hash( 'sha256', $content_for_hash );
		}

		if ( ! empty( $row['last_modified'] ) ) {
			$db_data['last_modified'] = $row['last_modified'];
		} else {
			$db_data['last_modified'] = $post->post_modified;
		}

		// KORREKTUR: IMMER aktualisieren/einfügen, keine Prüfung auf Änderungen
		if ( $existing ) {
			// UPDATE vorhandenen Eintrag - verwende wpdb->replace für sicheren Update
			$result = $wpdb->replace( 
				$table_name, 
				$db_data,
				null // Automatische Format-Erkennung
			);
			
			if ( $result !== false ) {
				$updated++;
				asmi_debug_log( sprintf( 
					'Force-Updated Post ID %s for language %s (replaced existing entry)', 
					$post_id, 
					$language 
				) );
			} else {
				$errors[] = sprintf( 
					__( 'Line %d: Failed to update Post ID %s - %s', 'asmi-search' ), 
					$line_num,
					$post_id,
					$wpdb->last_error
				);
				asmi_debug_log( sprintf( 
					'Failed to update Post ID %s: %s', 
					$post_id, 
					$wpdb->last_error 
				) );
			}
		} else {
			// INSERT neuer Eintrag
			$result = $wpdb->insert( $table_name, $db_data );
			
			if ( $result !== false ) {
				$imported++;
				asmi_debug_log( sprintf( 
					'Imported new Post ID %s for language %s', 
					$post_id, 
					$language 
				) );
			} else {
				$errors[] = sprintf( 
					__( 'Line %d: Failed to import Post ID %s - %s', 'asmi-search' ), 
					$line_num,
					$post_id,
					$wpdb->last_error
				);
				asmi_debug_log( sprintf( 
					'Failed to import Post ID %s: %s', 
					$post_id, 
					$wpdb->last_error 
				) );
			}
		}
		
		// Log-Ausgabe alle 50 Zeilen
		if ( ( $imported + $updated ) % 50 === 0 && ( $imported + $updated ) > 0 ) {
			asmi_debug_log( sprintf( 
				'Import progress: %d imported, %d updated', 
				$imported, 
				$updated 
			) );
		}
	}

	fclose( $file_handle );

	// Erstelle detaillierte Statusmeldung
	$message_parts = array();
	
	if ( $imported > 0 ) {
		$message_parts[] = sprintf( __( '%d new entries imported', 'asmi-search' ), $imported );
	}
	if ( $updated > 0 ) {
		$message_parts[] = sprintf( __( '%d entries updated', 'asmi-search' ), $updated );
	}
	
	$message = __( 'Import complete.', 'asmi-search' );
	if ( ! empty( $message_parts ) ) {
		$message .= ' ' . implode( ', ', $message_parts ) . '.';
	} else if ( empty( $errors ) ) {
		$message .= ' ' . __( 'No changes made.', 'asmi-search' );
	}

	if ( ! empty( $errors ) ) {
		$message .= ' ' . sprintf( __( '%d errors occurred.', 'asmi-search' ), count( $errors ) );
		
		// Zeige nur die ersten 10 Fehler
		if ( count( $errors ) <= 10 ) {
			$message .= '<br><br><strong>' . __( 'Errors:', 'asmi-search' ) . '</strong><br>' . implode( '<br>', $errors );
		} else {
			$first_errors = array_slice( $errors, 0, 10 );
			$message .= '<br><br><strong>' . __( 'First 10 errors:', 'asmi-search' ) . '</strong><br>' . implode( '<br>', $first_errors );
			$message .= '<br>' . sprintf( __( '... and %d more errors', 'asmi-search' ), count( $errors ) - 10 );
		}
		
		// Debug-Log für erste 50 Fehler
		asmi_debug_log( 'CSV Import Errors: ' . implode( '; ', array_slice( $errors, 0, 50 ) ) );
	}

	// Erfolgs-Log
	asmi_debug_log( sprintf( 
		'CSV Import Complete: %d imported, %d updated, %d errors from %d total lines', 
		$imported, 
		$updated, 
		count( $errors ), 
		$line_num - 1 
	) );

	// Speichere Nachricht in Transient für Anzeige
	set_transient( 'asmi_import_message', array(
		'type' => empty( $errors ) ? 'success' : ( ( $imported + $updated ) > 0 ? 'warning' : 'error' ),
		'message' => $message
	), 60 );

	// Redirect zurück zur Admin-Seite
	wp_redirect( admin_url( 'admin.php?page=' . ASMI_SLUG . '#tab-system' ) );
	exit;
}
add_action( 'admin_post_asmi_import_wp_index', 'asmi_handle_import_wp_index' );

/**
 * Setzt den Übersetzungs-Cache zurück.
 *
 * @return void
 */
function asmi_handle_reset_translation_cache() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}

	check_admin_referer( 'asmi_reset_translation_cache' );

	// Rufe die Reset-Funktion auf
	asmi_reset_translation_cache();

	// Speichere Erfolgsmeldung
	set_transient( 'asmi_import_message', array(
		'type' => 'success',
		'message' => __( 'Translation cache has been reset. All content will be re-translated on next indexing.', 'asmi-search' )
	), 30 );

	// Redirect zurück
	wp_redirect( admin_url( 'admin.php?page=' . ASMI_SLUG . '#tab-system' ) );
	exit;
}
add_action( 'admin_post_asmi_reset_translation_cache', 'asmi_handle_reset_translation_cache' );

/**
 * Bereinigt doppelte Einträge in der Datenbank.
 *
 * @return int Anzahl der gelöschten Duplikate.
 */
function asmi_clean_duplicate_entries() {
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	
	// Finde Duplikate (gleiche source_id, lang, source_type)
	$duplicates = $wpdb->get_results(
		"SELECT source_id, lang, source_type, COUNT(*) as cnt, MIN(id) as keep_id
		FROM $table_name
		WHERE source_type = 'wordpress'
		GROUP BY source_id, lang, source_type
		HAVING cnt > 1",
		ARRAY_A
	);
	
	$deleted = 0;
	foreach ( $duplicates as $dup ) {
		// Lösche alle außer dem ältesten Eintrag
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name 
				WHERE source_id = %s 
				AND lang = %s 
				AND source_type = %s 
				AND id != %d",
				$dup['source_id'],
				$dup['lang'],
				$dup['source_type'],
				$dup['keep_id']
			)
		);
		
		if ( $result ) {
			$deleted += $result;
			asmi_debug_log( sprintf( 
				'Cleaned %d duplicate(s) for post_id=%s, lang=%s', 
				$result, 
				$dup['source_id'], 
				$dup['lang'] 
			) );
		}
	}
	
	if ( $deleted > 0 ) {
		asmi_debug_log( sprintf( 'Total duplicates cleaned: %d', $deleted ) );
	}
	
	return $deleted;
}

/**
 * Admin-Action für das Bereinigen von Duplikaten
 */
function asmi_handle_clean_duplicates() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}

	check_admin_referer( 'asmi_clean_duplicates' );

	$deleted = asmi_clean_duplicate_entries();

	// Speichere Erfolgsmeldung
	if ( $deleted > 0 ) {
		$message = sprintf( 
			__( 'Database cleanup complete. %d duplicate entries removed.', 'asmi-search' ), 
			$deleted 
		);
		$type = 'success';
	} else {
		$message = __( 'No duplicate entries found.', 'asmi-search' );
		$type = 'info';
	}

	set_transient( 'asmi_import_message', array(
		'type' => $type,
		'message' => $message
	), 30 );

	// Redirect zurück
	wp_redirect( admin_url( 'admin.php?page=' . ASMI_SLUG . '#tab-system' ) );
	exit;
}
add_action( 'admin_post_asmi_clean_duplicates', 'asmi_handle_clean_duplicates' );