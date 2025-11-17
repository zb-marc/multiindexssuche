<?php
/**
 * Funktionen zur Erstellung und Wartung der Datenbanktabelle.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Erstellt, prüft und repariert die gesamte Datenbankstruktur des Plugins.
 * Diese Funktion ist die zentrale Anlaufstelle für die DB-Verwaltung.
 *
 * @return void
 */
function asmi_install_and_repair_database() {
	global $wpdb;
	$table_name      = $wpdb->prefix . ASMI_INDEX_TABLE;
	$charset_collate = $wpdb->get_charset_collate();

	// Definiere die vollständige, korrekte Tabellenstruktur.
	$required_columns = array(
		'id'             => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
		'source_id'      => 'VARCHAR(255) NOT NULL',
		'lang'           => 'VARCHAR(10) NOT NULL',
		'source_type'    => 'VARCHAR(20) NOT NULL',
		'title'          => 'TEXT NOT NULL',
		'content'        => 'LONGTEXT',
		'excerpt'        => 'TEXT',
		'url'            => 'VARCHAR(2048)',
		'image'          => 'VARCHAR(2048)',
		'image_url_hash' => 'VARCHAR(32)',
		'price'          => 'VARCHAR(50)',
		'sku'            => 'VARCHAR(100)',
		'gtin'           => 'VARCHAR(100)',
		'raw_data'       => 'LONGTEXT',
		'content_hash'   => 'VARCHAR(64)',
		'last_modified'  => 'DATETIME',
		'indexed_at'     => 'DATETIME NOT NULL',
	);

	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

	if ( ! $table_exists ) {
		// SCHRITT 1: Tabelle erstellen, falls sie nicht existiert.
		asmi_debug_log( 'Creating new index table...' );
		asmi_create_fresh_table( $table_name, $charset_collate );
		return;
	}

	// SCHRITT 2: Prüfe die Struktur der existierenden Tabelle.
	$existing_columns      = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name`", ARRAY_A );
	$existing_column_names = wp_list_pluck( $existing_columns, 'Field' );
	
	// Prüfe ob kritische Spalten fehlen.
	$critical_columns = array( 'id', 'source_id', 'lang', 'source_type', 'title', 'indexed_at' );
	$missing_critical = array_diff( $critical_columns, $existing_column_names );
	
	if ( ! empty( $missing_critical ) ) {
		asmi_debug_log( 'Critical columns missing: ' . implode( ', ', $missing_critical ) . '. Recreating table.' );
		
		// Sichere vorhandene Daten wenn möglich.
		$backup_data = asmi_backup_table_data( $table_name );
		
		// Lösche alte Tabelle und erstelle neue.
		$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		asmi_create_fresh_table( $table_name, $charset_collate );
		
		// Stelle Daten wieder her wenn Backup erfolgreich war.
		if ( ! empty( $backup_data ) ) {
			asmi_restore_table_data( $table_name, $backup_data );
		}
		
		return;
	}

	// SCHRITT 3: Füge fehlende optionale Spalten hinzu.
	foreach ( $required_columns as $col_name => $col_definition ) {
		if ( ! in_array( $col_name, $existing_column_names, true ) ) {
			$alter_sql = "ALTER TABLE `$table_name` ADD COLUMN `$col_name` $col_definition";
			$result    = $wpdb->query( $alter_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false !== $result ) {
				asmi_debug_log( "Database repair: Added missing column `$col_name`." );
			} else {
				asmi_debug_log( "Database repair: Failed to add column `$col_name`: " . $wpdb->last_error );
			}
		}
	}

	// SCHRITT 4: Prüfe und repariere Indizes.
	asmi_repair_table_indexes( $table_name );
	
	// Aktualisiere DB-Version.
	update_option( ASMI_DB_VER_OPT, ASMI_VERSION );
	asmi_debug_log( 'Database structure verified and repaired successfully.' );
}

/**
 * Erstellt eine neue, saubere Index-Tabelle mit korrekter Struktur.
 *
 * @param string $table_name      Der Tabellenname.
 * @param string $charset_collate Die Charset-Collation.
 * @return void
 */
function asmi_create_fresh_table( $table_name, $charset_collate ) {
	global $wpdb;
	
	$sql = "CREATE TABLE `$table_name` (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		source_id VARCHAR(255) NOT NULL,
		lang VARCHAR(10) NOT NULL,
		source_type VARCHAR(20) NOT NULL,
		title TEXT NOT NULL,
		content LONGTEXT,
		excerpt TEXT,
		url VARCHAR(2048),
		image VARCHAR(2048),
		image_url_hash VARCHAR(32),
		price VARCHAR(50),
		sku VARCHAR(100),
		gtin VARCHAR(100),
		raw_data LONGTEXT,
		content_hash VARCHAR(64),
		last_modified DATETIME,
		indexed_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY unq_source (source_id, lang, source_type),
		FULLTEXT KEY ft_wp_search (title, content, excerpt),
		FULLTEXT KEY ft_product_search (title, content, excerpt, sku, gtin),
		KEY idx_sku (sku),
		KEY idx_gtin (gtin),
		KEY idx_content_hash (content_hash),
		KEY idx_image_hash (image_url_hash)
	) $charset_collate;";
	
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
		asmi_debug_log( 'Fresh index table created successfully.' );
		update_option( ASMI_DB_VER_OPT, ASMI_VERSION );
	} else {
		asmi_debug_log( 'Failed to create fresh index table: ' . $wpdb->last_error );
	}
}

/**
 * Repariert die Indizes der Tabelle.
 *
 * @param string $table_name Der Tabellenname.
 * @return void
 */
function asmi_repair_table_indexes( $table_name ) {
	global $wpdb;
	
	$indexes      = $wpdb->get_results( "SHOW INDEX FROM `$table_name`", ARRAY_A );
	$existing_keys = wp_list_pluck( $indexes, 'Key_name' );
	
	// Prüfe und erstelle fehlende Indizes.
	$required_indexes = array(
		'ft_wp_search'      => 'FULLTEXT KEY ft_wp_search (title, content, excerpt)',
		'ft_product_search' => 'FULLTEXT KEY ft_product_search (title, content, excerpt, sku, gtin)',
		'idx_sku'           => 'KEY idx_sku (sku)',
		'idx_gtin'          => 'KEY idx_gtin (gtin)',
		'idx_content_hash'  => 'KEY idx_content_hash (content_hash)',
		'idx_image_hash'    => 'KEY idx_image_hash (image_url_hash)',
	);
	
	foreach ( $required_indexes as $index_name => $index_sql ) {
		if ( ! in_array( $index_name, $existing_keys, true ) ) {
			$alter_sql = "ALTER TABLE `$table_name` ADD $index_sql";
			$result    = $wpdb->query( $alter_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false !== $result ) {
				asmi_debug_log( "Database repair: Added missing index `$index_name`." );
			} else {
				asmi_debug_log( "Database repair: Failed to add index `$index_name`: " . $wpdb->last_error );
			}
		}
	}
	
	// Spezialbehandlung für ft_product_search - prüfe ob gtin enthalten ist.
	$product_index_cols = '';
	foreach ( $indexes as $index ) {
		if ( 'ft_product_search' === ( $index['Key_name'] ?? '' ) ) {
			$product_index_cols .= ( $index['Column_name'] ?? '' ) . ' ';
		}
	}
	
	if ( in_array( 'ft_product_search', $existing_keys, true ) && false === strpos( $product_index_cols, 'gtin' ) ) {
		$wpdb->query( "ALTER TABLE `$table_name` DROP INDEX ft_product_search" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `$table_name` ADD FULLTEXT INDEX ft_product_search (title, content, excerpt, sku, gtin)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		asmi_debug_log( 'Database repair: Re-created index ft_product_search to include gtin.' );
	}
}

/**
 * Erstellt ein Backup der Tabellendaten vor einer Struktur-Änderung.
 *
 * @param string $table_name Der Tabellenname.
 * @return array|false Die Backup-Daten oder false bei Fehler.
 */
function asmi_backup_table_data( $table_name ) {
	global $wpdb;
	
	try {
		// Versuche nur die wichtigsten Spalten zu sichern.
		$backup_columns   = array( 'source_id', 'lang', 'source_type', 'title', 'indexed_at' );
		$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table_name`" );
		$available_columns = array_intersect( $backup_columns, $existing_columns );
		
		if ( empty( $available_columns ) ) {
			asmi_debug_log( 'No compatible columns found for backup.' );
			return false;
		}
		
		$columns_sql = implode(
			', ',
			array_map(
				function ( $col ) {
					return "`$col`";
				},
				$available_columns
			)
		);
		$data        = $wpdb->get_results( "SELECT $columns_sql FROM `$table_name` LIMIT 1000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		
		if ( ! empty( $data ) ) {
			asmi_debug_log( sprintf( 'Backed up %d rows from existing table.', count( $data ) ) );
			return array(
				'columns' => $available_columns,
				'data'    => $data,
			);
		}
	} catch ( Exception $e ) {
		asmi_debug_log( 'Backup failed: ' . $e->getMessage() );
	}
	
	return false;
}

/**
 * Stellt Daten aus einem Backup wieder her.
 *
 * @param string $table_name  Der Tabellenname.
 * @param array  $backup_data Die Backup-Daten.
 * @return void
 */
function asmi_restore_table_data( $table_name, $backup_data ) {
	global $wpdb;
	
	if ( empty( $backup_data['data'] ) || empty( $backup_data['columns'] ) ) {
		return;
	}
	
	$restored = 0;
	foreach ( $backup_data['data'] as $row ) {
		// Fülle fehlende Spalten mit Standardwerten.
		$complete_row = array_merge(
			array(
				'source_id'   => '',
				'lang'        => 'de_DE',
				'source_type' => 'wordpress',
				'title'       => '',
				'indexed_at'  => current_time( 'mysql' ),
			),
			$row
		);
		
		$result = $wpdb->insert( $table_name, $complete_row );
		if ( false !== $result ) {
			++$restored;
		}
	}
	
	if ( $restored > 0 ) {
		asmi_debug_log( sprintf( 'Restored %d rows from backup.', $restored ) );
	}
}

/**
 * Alias-Funktion für Rückwärtskompatibilität.
 * WICHTIG: Diese Funktion ruft jetzt die korrekte Reparatur-Funktion auf!
 *
 * @return void
 */
function asmi_run_db_install() {
	asmi_install_and_repair_database();
}

/**
 * Alias-Funktion für Rückwärtskompatibilität.
 *
 * @return void
 */
function asmi_verify_and_repair_db_indexes() {
	// Diese Funktionalität ist jetzt in asmi_install_and_repair_database() integriert.
	asmi_install_and_repair_database();
}
