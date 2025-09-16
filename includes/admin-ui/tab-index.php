<?php
/**
 * Rendert den "Index"-Tab im Admin-Bereich.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gibt das HTML für den Index-Steuerungs-Tab aus.
 *
 * @param array $o      Die Plugin-Optionen.
 * @param array $nonces Die Nonces für sichere Aktionen.
 * @return void
 */
function asmi_render_tab_index( $o, $nonces ) {
	?>
<div id="tab-index" class="asmi-tab card">
	<h2><?php esc_html_e( 'Index Control', 'asmi-search' ); ?></h2>
	<p><?php esc_html_e( 'Manage the import of external product feeds and the indexing of your WordPress content here.', 'asmi-search' ); ?></p>

	<div id="asmi-status-dashboard" class="asmi-status-dashboard">
		<div class="asmi-status-overview">
			<h3><span class="asmi-status-text">...</span></h3>
			<div class="asmi-status-total-count">
				<span class="asmi-stats-total">...</span>
				<br>
				<small><?php esc_html_e( 'Products in Index', 'asmi-search' ); ?></small>
			</div>
			<div class="asmi-status-total-count">
				<span class="asmi-stats-wp-total">...</span>
				<br>
				<small><?php esc_html_e( 'WP Content in Index', 'asmi-search' ); ?></small>
			</div>
		</div>

		<div class="asmi-process-details" style="display:none;">
			<p style="margin-top:0;"><strong><span class="asmi-process-title"></span></strong></p>
			<div class="asmi-progress-bar-total"><div class="asmi-progress-bar-inner"></div></div>
			<p>
				<strong><span class="asmi-progress-label"></span></strong>
				<span class="asmi-state-done">0</span> / <span class="asmi-state-total">0</span> (<span class="asmi-state-pct">0</span>%)
			</p>
			<div id="asmi-feed-progress-container" style="display:none;"></div>
			<p><small>
				<strong><?php esc_html_e( 'Started:', 'asmi-search' ); ?></strong> <span class="asmi-state-started">...</span> |
				<strong><?php esc_html_e( 'Duration:', 'asmi-search' ); ?></strong> <span class="asmi-state-duration">...</span>
			</small></p>
			<p style="color:#d63638; display:none;" class="asmi-state-error-p"><strong><?php esc_html_e( 'Error:', 'asmi-search' ); ?></strong> <span class="asmi-state-error"></span></p>
		</div>

		<div class="asmi-last-run-summary" style="display:none;">
			<h4><?php esc_html_e( 'Summary of the last run', 'asmi-search' ); ?></h4>
			<ul class="asmi-summary-list"></ul>
		</div>
	</div>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Indexing Options', 'asmi-search' ); ?></th>
			<td>
				<p><label><input type="checkbox" name="<?php echo esc_attr( ASMI_OPT ); ?>[exclude_no_desc]" value="1" <?php checked( $o['exclude_no_desc'], 1 ); ?>> <?php esc_html_e( 'Exclude products without a description from indexing.', 'asmi-search' ); ?></label></p>
				<p><label><input type="checkbox" name="<?php echo esc_attr( ASMI_OPT ); ?>[high_speed_indexing]" value="1" <?php checked( $o['high_speed_indexing'], 1 ); ?>> <strong><?php esc_html_e( 'Enable High-Speed Indexing', 'asmi-search' ); ?></strong></label><br>
				<span class="description"><?php esc_html_e( 'Uses a faster, continuous method. Recommended for large datasets.', 'asmi-search' ); ?></span></p>
			</td>
		</tr>
        <tr>
            <th><label for="excluded_ids"><?php esc_html_e( 'Excluded Post IDs', 'asmi-search' ); ?></label></th>
            <td>
                <input type="text" name="<?php echo esc_attr( ASMI_OPT ); ?>[excluded_ids]" id="excluded_ids" class="regular-text" value="<?php echo esc_attr( $o['excluded_ids'] ); ?>">
                <p class="description"><?php esc_html_e( 'Enter the IDs of pages or posts you want to exclude from indexing, separated by commas (e.g., 12, 345, 67).', 'asmi-search' ); ?></p>
            </td>
        </tr>
        <tr>
			<th><?php esc_html_e( 'Daily Re-indexing', 'asmi-search' ); ?></th>
			<td>
				<label class="as-glossar-toggle-switch">
					<input type="checkbox" name="<?php echo esc_attr( ASMI_OPT ); ?>[enable_daily_reindex]" value="1" <?php checked( $o['enable_daily_reindex'], 1 ); ?>>
					<span class="as-glossar-slider"></span>
				</label>
				<span class="as-glossar-toggle-label">
					<?php esc_html_e( 'Automatically start feed indexing daily at 1:00 AM.', 'asmi-search' ); ?>
				</span>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Actions', 'asmi-search' ); ?></th>
			<td>
				<button id="asmi-reindex-button" type="button" class="button button-primary" data-action="reindex" data-nonce="<?php echo esc_attr( $nonces['reindex'] ); ?>" data-confirm-msg="<?php esc_attr_e( 'Are you sure you want to completely re-import all products from the feeds?', 'asmi-search' ); ?>"><?php esc_html_e( 'Re-import Feed Products', 'asmi-search' ); ?></button>
				<button id="asmi-reindex-wp-button" type="button" class="button" data-action="reindex_wp" data-nonce="<?php echo esc_attr( $nonces['reindex'] ); ?>" data-confirm-msg="<?php esc_attr_e( 'Are you sure you want to re-index all WordPress content?', 'asmi-search' ); ?>"><?php esc_html_e( 'Re-index WordPress Content', 'asmi-search' ); ?></button>
				
				<button id="asmi-clear-button" type="button" class="button" data-action="clear" data-nonce="<?php echo esc_attr( $nonces['clear'] ); ?>" data-confirm-msg="<?php esc_attr_e( 'Are you sure you want to permanently delete the entire search index (all products and WordPress content)? This action cannot be undone.', 'asmi-search' ); ?>"><?php esc_html_e( 'Delete Index', 'asmi-search' ); ?></button>
				
				<button id="asmi-cancel-button" type="button" class="button button-secondary" style="display:none;" data-action="cancel" data-nonce="<?php echo esc_attr( $nonces['cancel'] ); ?>"><?php esc_html_e( 'Cancel', 'asmi-search' ); ?></button>
			</td>
		</tr>
	</table>
</div>
	<?php
}