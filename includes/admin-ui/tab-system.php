<?php
/**
 * Rendert den "System"-Tab im Admin-Bereich.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gibt das HTML für den System-Tab aus.
 *
 * @param array $o      Die Plugin-Optionen.
 * @param array $nonces Die Nonces für sichere Aktionen.
 * @return void
 */
function asmi_render_tab_system( $o, $nonces ) {
	?>
<div id="tab-system" class="asmi-tab card">
	<h2><?php esc_html_e( 'System', 'asmi-search' ); ?></h2>

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
					// translators: %1$s is the REST namespace, %2$s is the REST route.
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
	<?php
}