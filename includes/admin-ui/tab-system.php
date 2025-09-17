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
 * @param array $o Die Plugin-Optionen.
 * @param array $nonces Die generierten Nonces.
 * @return void
 */
function asmi_render_tab_system( $o, $nonces ) {
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	$cache_table = $wpdb->prefix . 'asmi_chatgpt_cache';
	
	// Prüfe ob Cache-Tabelle existiert
	$cache_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$cache_table'" ) === $cache_table;
	
	// Statistiken für Info-Box
	$total_entries = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE source_type = 'wordpress'" );
	$manual_entries = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE source_type = 'wordpress' AND content_hash LIKE '%manual_import%'" );
	$avg_content_length = $wpdb->get_var( "SELECT AVG(CHAR_LENGTH(content)) FROM $table_name WHERE source_type = 'wordpress'" );
	
	// Cache-Statistiken wenn Tabelle existiert
	$cache_entries = 0;
	if ( $cache_table_exists ) {
		$cache_entries = $wpdb->get_var( "SELECT COUNT(*) FROM $cache_table" );
	}
	
	?>
	<div class="card" style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">
		<h4 style="margin-top: 0;"><?php esc_html_e( 'Index Statistics', 'asmi-search' ); ?></h4>
		<ul style="margin: 10px 0;">
			<li><?php echo sprintf( esc_html__( 'Total WordPress entries: %d', 'asmi-search' ), $total_entries ); ?></li>
			<li><?php echo sprintf( esc_html__( 'Protected manual imports: %d', 'asmi-search' ), $manual_entries ); ?></li>
			<li><?php echo sprintf( esc_html__( 'Average content length: %d characters', 'asmi-search' ), round( $avg_content_length ) ); ?></li>
			<?php if ( $cache_table_exists ) : ?>
				<li><?php echo sprintf( esc_html__( 'ChatGPT cache entries: %d', 'asmi-search' ), $cache_entries ); ?></li>
			<?php endif; ?>
		</ul>
		
		<?php if ( ! $cache_table_exists && ! empty( $o['use_chatgpt'] ) ) : ?>
		<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-top: 10px;">
			<strong><?php esc_html_e( 'ChatGPT cache table is missing!', 'asmi-search' ); ?></strong><br>
			<?php esc_html_e( 'The cache table needs to be created for ChatGPT to work properly.', 'asmi-search' ); ?>
			<br><br>
			<button type="button" class="button button-primary" id="asmi-create-cache-table">
				<?php esc_html_e( 'Create Cache Table Now', 'asmi-search' ); ?>
			</button>
		</div>
		<?php endif; ?>
	</div>
	
	<div class="card" style="margin-top: 20px;">
		<h3><?php esc_html_e( 'Index Optimization', 'asmi-search' ); ?></h3>
		
		<?php if ( ! empty( $o['use_chatgpt'] ) && ! empty( $o['chatgpt_api_key'] ) ) : ?>
			<p style="color: #46b450;">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'ChatGPT is active and will be used for intelligent content analysis.', 'asmi-search' ); ?>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Enable ChatGPT in the Integration tab for better content analysis.', 'asmi-search' ); ?></p>
		<?php endif; ?>
		
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Force Re-Index', 'asmi-search' ); ?></th>
				<td>
					<button type="button" class="button button-primary" id="asmi-force-compact-reindex" 
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'asmi_force_reindex' ) ); ?>">
						<?php 
						echo ! empty( $o['use_chatgpt'] ) && ! empty( $o['chatgpt_api_key'] ) 
							? esc_html__( 'Re-Index with ChatGPT', 'asmi-search' )
							: esc_html__( 'Re-Index with Compact Mode', 'asmi-search' );
						?>
					</button>
					<p class="description">
						<?php 
						if ( ! empty( $o['use_chatgpt'] ) && ! empty( $o['chatgpt_api_key'] ) ) {
							esc_html_e( 'This will re-index all WordPress content using ChatGPT for intelligent analysis. Manual imports will be preserved.', 'asmi-search' );
						} else {
							esc_html_e( 'This will re-index all WordPress content using compact keyword extraction. Manual imports will be preserved.', 'asmi-search' );
						}
						?>
					</p>
				</td>
			</tr>
			
			<?php if ( $cache_table_exists && $cache_entries > 0 ) : ?>
			<tr>
				<th><?php esc_html_e( 'Clear ChatGPT Cache', 'asmi-search' ); ?></th>
				<td>
					<button type="button" class="button" id="asmi-clear-cache">
						<?php esc_html_e( 'Clear Cache', 'asmi-search' ); ?>
					</button>
					<p class="description">
						<?php echo sprintf( esc_html__( 'Remove %d cached ChatGPT responses to force fresh analysis.', 'asmi-search' ), $cache_entries ); ?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
	</div>
	
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Cache-Tabelle erstellen
		$('#asmi-create-cache-table').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true).text('<?php esc_attr_e( 'Creating...', 'asmi-search' ); ?>');
			
			$.post(ajaxurl, {
				action: 'asmi_create_chatgpt_cache_table',
				_wpnonce: '<?php echo wp_create_nonce( "asmi_create_cache_table" ); ?>'
			}, function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
					$btn.prop('disabled', false).text('<?php esc_attr_e( 'Create Cache Table Now', 'asmi-search' ); ?>');
				}
			});
		});
		
		// Re-Index
		$('#asmi-force-compact-reindex').on('click', function(e) {
			e.preventDefault();
			if (!confirm('<?php esc_attr_e( 'This will re-index all WordPress content. Manual imports are protected. Continue?', 'asmi-search' ); ?>')) {
				return;
			}
			
			var $btn = $(this);
			var nonce = $btn.data('nonce');
			$btn.prop('disabled', true).text('<?php esc_attr_e( 'Re-indexing...', 'asmi-search' ); ?>');
			
			$.post(ajaxurl, {
				action: 'asmi_force_compact_reindex',
				_wpnonce: nonce
			}, function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
				}
				$btn.prop('disabled', false).text('<?php esc_attr_e( 'Re-Index', 'asmi-search' ); ?>');
			});
		});
		
		// Cache leeren
		$('#asmi-clear-cache').on('click', function(e) {
			e.preventDefault();
			if (!confirm('<?php esc_attr_e( 'Clear all cached ChatGPT responses?', 'asmi-search' ); ?>')) {
				return;
			}
			// Implementation für Cache-Clearing hier...
		});
	});
	</script>
	<?php
}