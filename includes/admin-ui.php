<?php
/**
 * Haupt-Renderer für die Admin-Seite des Plugins.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gibt das komplette HTML für die Einstellungsseite des Plugins aus.
 *
 * @return void
 */
function asmi_render_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	asmi_run_db_install();

	$o = asmi_get_opts();

	// KORREKTUR: Der fehlende "db_repair" Nonce wurde hier hinzugefügt.
	// Dadurch wird die PHP-Warnung behoben, der das JavaScript lahmgelegt hat
	// und alle Aktions-Buttons wieder funktionsfähig macht.
	$nonces = array(
		'reindex'       => wp_create_nonce( 'wp_rest' ),
		'clear'         => wp_create_nonce( 'wp_rest' ),
		'cancel'        => wp_create_nonce( 'wp_rest' ),
		'status'        => wp_create_nonce( 'wp_rest' ),
		'delete_images' => wp_create_nonce( 'wp_rest' ),
		'delete_status' => wp_create_nonce( 'wp_rest' ),
		'db_repair'     => wp_create_nonce( 'wp_rest' ),
	);

	$option_group = defined( 'ASMI_SETTINGS_GROUP' ) ? ASMI_SETTINGS_GROUP : 'asmi_settings';
	?>
	<div class="wrap asmi-wrap" data-nonces="<?php echo esc_attr( wp_json_encode( $nonces ) ); ?>">
		<h1><?php esc_html_e( 'Multiindex Search', 'asmi-search' ); ?></h1>

		<div id="asmi-admin-notice" class="notice notice-success is-dismissible" style="display:none;"><p></p></div>

		<?php
		if ( function_exists( 'settings_errors' ) ) {
			settings_errors();
		}
		?>

		<h2 class="nav-tab-wrapper">
			<a href="#tab-general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 'asmi-search' ); ?></a>
			<a href="#tab-mapping" class="nav-tab"><?php esc_html_e( 'Mapping', 'asmi-search' ); ?></a>
			<a href="#tab-integration" class="nav-tab"><?php esc_html_e( 'Integration', 'asmi-search' ); ?></a>
			<a href="#tab-index" class="nav-tab"><?php esc_html_e( 'Index', 'asmi-search' ); ?></a>
			<a href="#tab-system" class="nav-tab"><?php esc_html_e( 'System', 'asmi-search' ); ?></a>
			<a href="#tab-data" class="nav-tab"><?php esc_html_e( 'Index Data', 'asmi-search' ); ?></a>
		</h2>

		<form method="post" action="options.php">
			<?php settings_fields( $option_group ); ?>

			<?php
			asmi_render_tab_general( $o );
			asmi_render_tab_mapping( $o );
			asmi_render_tab_integration( $o );
			asmi_render_tab_index( $o, $nonces );
			asmi_render_tab_system( $o, $nonces );
			asmi_render_tab_data( $o );
			?>
			
			<input type="hidden" id="asmi_active_tab" name="<?php echo esc_attr( ASMI_OPT ); ?>[active_tab]" value="#tab-general" />

			<?php submit_button(); ?>
		</form>
		
	</div>
	
	<div id="asmi-modal-backdrop" style="display:none;"></div>
	<div id="asmi-modal-wrap" class="asmi-modal" style="display:none;">
		<div class="asmi-modal-title">
			<h3><?php esc_html_e( 'Confirmation Required', 'asmi-search' ); ?></h3>
			<button type="button" class="asmi-modal-close">&times;</button>
		</div>
		<div class="asmi-modal-content">
			<p id="asmi-modal-text"></p>
		</div>
		<div class="asmi-modal-footer">
			<button type="button" class="button" id="asmi-modal-cancel"><?php esc_html_e( 'Cancel', 'asmi-search' ); ?></button>
			<button type="button" class="button button-primary" id="asmi-modal-confirm"><?php esc_html_e( 'Confirm', 'asmi-search' ); ?></button>
		</div>
	</div>
	<?php
}