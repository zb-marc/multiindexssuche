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
		// Zeige Import/Export-Nachrichten an
		$import_message = get_transient( 'asmi_import_message' );
		if ( $import_message ) {
			delete_transient( 'asmi_import_message' );
			?>
			<div class="notice notice-<?php echo esc_attr( $import_message['type'] ); ?> is-dismissible">
				<p><?php echo wp_kses_post( $import_message['message'] ); ?></p>
			</div>
			<?php
		}
		
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

		<form method="post" action="options.php" id="asmi-settings-form">
			<?php settings_fields( $option_group ); ?>

			<?php
			asmi_render_tab_general( $o );
			asmi_render_tab_mapping( $o );
			asmi_render_tab_integration( $o );
			asmi_render_tab_index( $o, $nonces );
			?>
			
			<div id="tab-system" class="asmi-tab card">
				<h2><?php esc_html_e( 'System', 'asmi-search' ); ?></h2>
				
				<?php // System-Einstellungen innerhalb des Hauptformulars ?>
				<table class="form-table">
					<tr>
						<th><label for="max_results"><?php esc_html_e( 'Max. Results per Group', 'asmi-search' ); ?></label></th>
						<td><input type="number" name="<?php echo esc_attr( ASMI_OPT ); ?>[max_results]" id="max_results" class="small-text" value="<?php echo esc_attr( $o['max_results'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="weight_wp"><?php esc_html_e( 'Weighting WordPress', 'asmi-search' ); ?></label></th>
						<td><input type="number" step="0.1" name="<?php echo esc_attr( ASMI_OPT ); ?>[weight_wp]" id="weight_wp" class="small-text" value="<?php echo esc_attr( $o['weight_wp'] ); ?>">
							<p class="description"><?php esc_html_e( 'Base relevance score for WordPress hits. Higher = more important.', 'asmi-search' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="weight_sw"><?php esc_html_e( 'Weighting Product Feeds', 'asmi-search' ); ?></label></th>
						<td><input type="number" step="0.1" name="<?php echo esc_attr( ASMI_OPT ); ?>[weight_sw]" id="weight_sw" class="small-text" value="<?php echo esc_attr( $o['weight_sw'] ); ?>">
							<p class="description"><?php esc_html_e( 'Base relevance score for product hits. Higher = more important.', 'asmi-search' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cache_ttl"><?php esc_html_e( 'Feed Cache TTL (sec.)', 'asmi-search' ); ?></label></th>
						<td><input type="number" name="<?php echo esc_attr( ASMI_OPT ); ?>[cache_ttl]" id="cache_ttl" class="small-text" value="<?php echo esc_attr( $o['cache_ttl'] ); ?>">
							<p class="description"><?php esc_html_e( 'How long the downloaded feeds are cached before being reloaded.', 'asmi-search' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="index_batch"><?php esc_html_e( 'Index Batch Size', 'asmi-search' ); ?></label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( ASMI_OPT ); ?>[index_batch]" id="index_batch" class="small-text" min="20" max="2000" value="<?php echo esc_attr( $o['index_batch'] ); ?>">
							<p class="description"><?php esc_html_e( 'Number of records per background indexing step.', 'asmi-search' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Cache Management', 'asmi-search' ); ?></th>
						<td>
							<button id="asmi-delete-images-button" type="button" class="button" data-action="delete_images" data-nonce="<?php echo esc_attr( $nonces['delete_images'] ); ?>" data-confirm-msg="<?php esc_attr_e( 'Are you sure you want to delete all imported feed images from the cache folder?', 'asmi-search' ); ?>"><?php esc_html_e( 'Clear Image Cache', 'asmi-search' ); ?></button>
							<p class="description"><?php esc_html_e( 'Deletes all locally cached images from the product feeds.', 'asmi-search' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Database', 'asmi-search' ); ?></th>
						<td>
							<button id="asmi-db-repair-button" type="button" class="button" data-action="db_repair" data-nonce="<?php echo esc_attr( $nonces['db_repair'] ); ?>" data-confirm-msg="<?php esc_attr_e( 'Are you sure you want to check and repair the database table? This can fix issues after an update.', 'asmi-search' ); ?>"><?php esc_html_e( 'Repair Database', 'asmi-search' ); ?></button>
							<p class="description"><?php esc_html_e( 'Checks if the index table has the correct structure and adds missing columns or indexes.', 'asmi-search' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'REST Endpoint', 'asmi-search' ); ?></th>
						<td>
							<label class="as-glossar-toggle-switch">
								<input type="checkbox" name="<?php echo esc_attr( ASMI_OPT ); ?>[expose_rest]" value="1" <?php checked( $o['expose_rest'], 1 ); ?>>
								<span class="as-glossar-slider"></span>
							</label>
							<span class="as-glossar-toggle-label">
							<?php
							echo $o['expose_rest']
								? sprintf( esc_html__( 'active – API is available at /wp-json/%1$s/%2$s.', 'asmi-search' ), esc_html( ASMI_REST_NS ), esc_html( ASMI_REST_ROUTE ) )
								: esc_html__( 'inactive – API is turned off.', 'asmi-search' );
							?>
							</span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Debug Mode', 'asmi-search' ); ?></th>
						<td>
							<label class="as-glossar-toggle-switch">
								<input type="checkbox" name="<?php echo esc_attr( ASMI_OPT ); ?>[debug_mode]" id="asmi_debug_mode" value="1" <?php checked( $o['debug_mode'], 1 ); ?>>
								<span class="as-glossar-slider"></span>
							</label>
							<span class="as-glossar-toggle-label">
							<?php
							echo $o['debug_mode']
								? esc_html__( 'active – errors and messages will be written to the debug log.', 'asmi-search' )
								: esc_html__( 'inactive – no additional logs.', 'asmi-search' );
							?>
							</span>
						</td>
					</tr>
				</table>
			</div>
			
			<?php asmi_render_tab_data( $o ); ?>
			
			<input type="hidden" id="asmi_active_tab" name="<?php echo esc_attr( ASMI_OPT ); ?>[active_tab]" value="#tab-general" />

			<?php submit_button(); ?>
		</form>

		<?php
		// Export/Import-Formulare außerhalb des Hauptformulars
		?>
		<div id="asmi-system-extra-forms" style="display:none;">
			<div class="card">
				<h3><?php esc_html_e( 'WordPress Content Export/Import', 'asmi-search' ); ?></h3>
				<p><?php esc_html_e( 'Export WordPress content from the index as CSV for manual translation, then import the edited file back.', 'asmi-search' ); ?></p>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Export WordPress Index', 'asmi-search' ); ?></th>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'asmi_export_wp_index' ); ?>
								<input type="hidden" name="action" value="asmi_export_wp_index">
								<button type="submit" class="button"><?php esc_html_e( 'Export as CSV', 'asmi-search' ); ?></button>
							</form>
							<p class="description">
								<?php esc_html_e( 'Downloads all WordPress content from the index as CSV file. Contains both German and English versions.', 'asmi-search' ); ?><br>
								<?php esc_html_e( 'Columns: post_id, language, title, content, excerpt, url, image_url', 'asmi-search' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Import WordPress Index', 'asmi-search' ); ?></th>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
								<?php wp_nonce_field( 'asmi_import_wp_index' ); ?>
								<input type="hidden" name="action" value="asmi_import_wp_index">
								<input type="file" name="import_file" accept=".csv" required>
								<button type="submit" class="button"><?php esc_html_e( 'Import CSV', 'asmi-search' ); ?></button>
							</form>
							<p class="description">
								<?php esc_html_e( 'Upload a CSV file with translated content. The file must have the same structure as the export.', 'asmi-search' ); ?><br>
								<strong><?php esc_html_e( 'Important:', 'asmi-search' ); ?></strong> <?php esc_html_e( 'ALL matching entries will be overwritten with the imported data!', 'asmi-search' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Database Cleanup', 'asmi-search' ); ?></th>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-right: 10px;">
								<?php wp_nonce_field( 'asmi_clean_duplicates' ); ?>
								<input type="hidden" name="action" value="asmi_clean_duplicates">
								<button type="submit" class="button"><?php esc_html_e( 'Clean Duplicates', 'asmi-search' ); ?></button>
							</form>
							<p class="description" style="display: inline-block;">
								<?php esc_html_e( 'Removes duplicate index entries (same post_id and language).', 'asmi-search' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Translation Cache', 'asmi-search' ); ?></th>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'asmi_reset_translation_cache' ); ?>
								<input type="hidden" name="action" value="asmi_reset_translation_cache">
								<button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'This will force re-translation of all WordPress content on the next indexing. Continue?', 'asmi-search' ); ?>');">
									<?php esc_html_e( 'Reset Translation Cache', 'asmi-search' ); ?>
								</button>
							</form>
							<p class="description">
								<?php esc_html_e( 'Forces complete re-translation of all WordPress content during the next indexing process.', 'asmi-search' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		
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