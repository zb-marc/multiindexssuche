<?php
/**
 * Plugin Name: AS Multiindex Search
 * Description: Eine föderierte Suche, die native WordPress-Inhalte und mehrsprachige, externe Produktfeeds (XML, CSV, JSON) in jeder AJAX-Suche nahtlos zusammenführt.
 * Version:     1.11.1
 * Author:      Marc Mirschel
 * Author URI:  https://mirschel.biz
 * Plugin URI:  https://akkusys.de
 * License:     GPL-2.0+
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Text Domain: asmi-search
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-Konstanten.
define( 'ASMI_VERSION', '1.11.1' );
define( 'ASMI_OPT', 'asmi_options' );
define( 'ASMI_SLUG', 'asmi-settings' );
define( 'ASMI_REST_NS', 'asmi/v1' );
define( 'ASMI_REST_ROUTE', 'search' );
define( 'ASMI_INDEX_TICK_ACTION', 'asmi_do_index_tick' );
define( 'ASMI_DELETE_TICK_ACTION', 'asmi_do_delete_tick' );
define( 'ASMI_IMAGE_DELETE_TICK_ACTION', 'asmi_do_image_delete_tick' );
define( 'ASMI_IMAGE_CLEANUP_ACTION', 'asmi_do_image_cleanup' );
define( 'ASMI_WP_INDEX_TICK_ACTION', 'asmi_do_wp_index_tick' );
define( 'ASMI_ASSETS', plugin_dir_url( __FILE__ ) . 'assets/' );
define( 'ASMI_INDEX_TABLE', 'asmi_index' );
define( 'ASMI_INDEX_STATE_OPT', 'asmi_index_state' );
define( 'ASMI_DELETE_STATE_OPT', 'asmi_delete_state' );
define( 'ASMI_IMAGE_DELETE_STATE_OPT', 'asmi_image_delete_state' );
define( 'ASMI_IMAGE_CLEANUP_STATE_OPT', 'asmi_image_cleanup_state' );
define( 'ASMI_WP_INDEX_STATE_OPT', 'asmi_wp_index_state' );
define( 'ASMI_DB_VER_OPT', 'asmi_db_version' );
define( 'ASMI_SETTINGS_GROUP', 'asmi_settings' );
define( 'ASMI_UPLOAD_DIR', 'as-multiindex-search' );

/**
 * Lädt alle erforderlichen Plugin-Dateien.
 *
 * @return void
 */
function asmi_include_files() {
	$inc_path      = plugin_dir_path( __FILE__ ) . 'includes/';
	$indexing_path = $inc_path . 'indexing/';
	$admin_ui_path = $inc_path . 'admin-ui/';
	$api_path      = $inc_path . 'api/';
	$tools_path    = $inc_path . 'tools/';

	// Kern-Dateien.
	require_once $inc_path . 'core.php';
	require_once $inc_path . 'db.php';
	require_once $inc_path . 'parsers.php';
	require_once $inc_path . 'admin-ui.php';
	require_once $inc_path . 'rest.php';
	require_once $inc_path . 'frontend.php';
	require_once $inc_path . 'admin-actions.php';
	require_once $inc_path . 'warmup.php';
	require_once $inc_path . 'search.php';
	require_once $inc_path . 'import-export.php';

	// API-Handler.
	if ( file_exists( $api_path . 'chatgpt-handler.php' ) ) {
		require_once $api_path . 'chatgpt-handler.php';
	}

	// Indexierungs-Komponenten.
	require_once $indexing_path . 'state.php';
	require_once $indexing_path . 'images.php';
	require_once $indexing_path . 'image-cleanup.php';
	require_once $indexing_path . 'database.php';
	require_once $indexing_path . 'control.php';
	require_once $indexing_path . 'handler.php';
	require_once $indexing_path . 'deletion.php';
	require_once $indexing_path . 'wp-content-indexer.php';

	// Admin UI Tabs.
	foreach ( glob( $admin_ui_path . 'tab-*.php' ) as $file ) {
		require_once $file;
	}

	// Tools (Werkzeuge).
	if ( file_exists( $tools_path . 'image-manager.php' ) ) {
		require_once $tools_path . 'image-manager.php';
	}
}
asmi_include_files();

/**
 * Wird bei der Aktivierung des Plugins ausgeführt.
 *
 * @return void
 */
function asmi_activate_plugin() {
	asmi_install_and_repair_database();
	
	// Warmup Cron.
	if ( ! wp_next_scheduled( 'asmi_cron_warmup' ) ) {
		wp_schedule_event( time(), 'hourly', 'asmi_cron_warmup' );
	}
	
	// WordPress Content Indexing Cron.
	if ( ! wp_next_scheduled( 'asmi_cron_wp_content_index' ) ) {
		wp_schedule_event( time(), 'daily', 'asmi_cron_wp_content_index' );
	}
	
	// Image Cleanup Cron (täglich um 3 Uhr morgens).
	if ( ! wp_next_scheduled( ASMI_IMAGE_CLEANUP_ACTION ) ) {
		wp_schedule_event( strtotime( 'tomorrow 3:00' ), 'daily', ASMI_IMAGE_CLEANUP_ACTION );
	}
}
register_activation_hook( __FILE__, 'asmi_activate_plugin' );

/**
 * Wird bei der Deaktivierung des Plugins ausgeführt.
 *
 * @return void
 */
function asmi_deactivate_plugin() {
	wp_clear_scheduled_hook( 'asmi_cron_warmup' );
	wp_clear_scheduled_hook( ASMI_INDEX_TICK_ACTION );
	wp_clear_scheduled_hook( ASMI_DELETE_TICK_ACTION );
	wp_clear_scheduled_hook( ASMI_IMAGE_DELETE_TICK_ACTION );
	wp_clear_scheduled_hook( ASMI_IMAGE_CLEANUP_ACTION );
	wp_clear_scheduled_hook( ASMI_WP_INDEX_TICK_ACTION );
	wp_clear_scheduled_hook( 'asmi_cron_reindex' );
	wp_clear_scheduled_hook( 'asmi_cron_wp_content_index' );
}
register_deactivation_hook( __FILE__, 'asmi_deactivate_plugin' );

/**
 * Wird bei der Deinstallation des Plugins ausgeführt.
 *
 * @return void
 */
function asmi_uninstall_plugin() {
	delete_option( ASMI_OPT );
	delete_option( ASMI_INDEX_STATE_OPT );
	delete_option( ASMI_DELETE_STATE_OPT );
	delete_option( ASMI_IMAGE_DELETE_STATE_OPT );
	delete_option( ASMI_IMAGE_CLEANUP_STATE_OPT );
	delete_option( ASMI_WP_INDEX_STATE_OPT );
	delete_option( ASMI_DB_VER_OPT );
	delete_option( 'asmi_tick_token' );
	
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	
	if ( function_exists( 'asmi_delete_image_cache_folder' ) ) {
		asmi_delete_image_cache_folder();
	}
}
register_uninstall_hook( __FILE__, 'asmi_uninstall_plugin' );

/**
 * Stellt sicher, dass die DB-Tabelle bei jedem Plugin-Update repariert wird.
 * Prüft die gespeicherte Version und führt bei Unterschieden eine Reparatur durch.
 *
 * @return void
 */
function asmi_check_db_version() {
	$installed_ver = get_option( ASMI_DB_VER_OPT );
	
	if ( $installed_ver !== ASMI_VERSION ) {
		asmi_install_and_repair_database();
		asmi_debug_log( 'Database structure updated from version ' . $installed_ver . ' to ' . ASMI_VERSION );
	}
}
add_action( 'plugins_loaded', 'asmi_check_db_version', 10 );