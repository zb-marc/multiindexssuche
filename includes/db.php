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

	// SCHRITT 1: Tabelle erstellen, falls sie nicht existiert.
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
		$sql = "CREATE TABLE $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_id VARCHAR(255) NOT NULL,
			lang VARCHAR(10) NOT NULL,
			source_type VARCHAR(20) NOT NULL,
			title TEXT NOT NULL,
			indexed_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unq_source (source_id, lang, source_type)
		) $charset_collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( ASMI_DB_VER_OPT, ASMI_VERSION );
	}

	// SCHRITT 2: Alle Spalten prüfen und bei Bedarf hinzufügen.
	$all_columns = [
		'content'   => 'LONGTEXT', 'excerpt'   => 'TEXT', 'url' => 'VARCHAR(2048)',
		'image'     => 'VARCHAR(2048)', 'price' => 'VARCHAR(50)', 'sku' => 'VARCHAR(100)',
		'gtin'      => 'VARCHAR(100)', 'raw_data'  => 'LONGTEXT',
	];
	$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table_name`" );
	foreach ( $all_columns as $col_name => $col_type ) {
		if ( ! in_array( $col_name, $existing_columns, true ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `$col_name` $col_type" );
			asmi_debug_log( "Database repair: Added missing column `$col_name`." );
		}
	}

	// SCHRITT 3: Alle Indizes prüfen und bei Bedarf erstellen/reparieren.
	$indexes        = $wpdb->get_results( "SHOW INDEX FROM `$table_name`", ARRAY_A );
	$existing_keys  = wp_list_pluck( $indexes, 'Key_name' );
	$product_index_cols = '';
	foreach ( $indexes as $index ) {
		if ( 'ft_product_search' === ( $index['Key_name'] ?? '' ) ) {
			$product_index_cols .= ( $index['Column_name'] ?? '' ) . ' ';
		}
	}

	if ( ! in_array( 'ft_wp_search', $existing_keys, true ) ) {
		$wpdb->query( "ALTER TABLE `$table_name` ADD FULLTEXT INDEX ft_wp_search (title, content, excerpt)" );
		asmi_debug_log( 'Database repair: Added missing index ft_wp_search.' );
	}
	if ( ! in_array( 'ft_product_search', $existing_keys, true ) || strpos( $product_index_cols, 'gtin' ) === false ) {
		if ( in_array( 'ft_product_search', $existing_keys, true ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` DROP INDEX ft_product_search" );
		}
		$wpdb->query( "ALTER TABLE `$table_name` ADD FULLTEXT INDEX ft_product_search (title, content, excerpt, sku, gtin)" );
		asmi_debug_log( 'Database repair: Re-created index ft_product_search to include gtin.' );
	}
	if ( ! in_array( 'idx_sku', $existing_keys, true ) ) {
		$wpdb->query( "ALTER TABLE `$table_name` ADD KEY `idx_sku` (`sku`)" );
	}
	if ( ! in_array( 'idx_gtin', $existing_keys, true ) ) {
		$wpdb->query( "ALTER TABLE `$table_name` ADD KEY `idx_gtin` (`gtin`)" );
	}
}

// Leere alte Funktionen, um Konflikte zu vermeiden.
function asmi_run_db_install() {}
function asmi_verify_and_repair_db_indexes() {}