<?php
/**
 * Rendert den "Integration"-Tab im Admin-Bereich.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gibt das HTML für den Integration-Tab aus.
 *
 * @param array $o Die Plugin-Optionen.
 * @return void
 */
function asmi_render_tab_integration( $o ) {
	?>
<div id="tab-integration" class="asmi-tab card">
	<h2><?php esc_html_e( 'Integration', 'asmi-search' ); ?></h2>

	<div class="notice notice-info">
		<p><?php esc_html_e( 'ChatGPT provides superior content analysis with intelligent summarization and brand detection. DeepL is used as fallback for translations.', 'asmi-search' ); ?></p>
	</div>

	<h3><?php esc_html_e( 'Frontend Output', 'asmi-search' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Search Shortcodes', 'asmi-search' ); ?></th>
			<td>
				<div style="background: #f1f1f1; padding: 10px; border-radius: 5px; font-family: monospace;">
					<strong><?php esc_html_e( 'German:', 'asmi-search' ); ?></strong> [multiindex_search lang="de"]<br>
					<strong><?php esc_html_e( 'English:', 'asmi-search' ); ?></strong> [multiindex_search lang="en"]
				</div>
				<p class="description">
					<?php esc_html_e( 'Use these shortcodes to add search buttons to your pages. The search is always active.', 'asmi-search' ); ?>
				</p>
				<!-- Hidden field to keep shortcode always enabled -->
				<input type="hidden" name="<?php echo esc_attr( ASMI_OPT ); ?>[enable_shortcode]" value="1">
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'ChatGPT API (Recommended)', 'asmi-search' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Use ChatGPT for Indexing', 'asmi-search' ); ?></th>
			<td>
				<label class="as-glossar-toggle-switch">
					<input type="checkbox" name="<?php echo esc_attr( ASMI_OPT ); ?>[use_chatgpt]" 
					       value="1" <?php checked( $o['use_chatgpt'] ?? 0, 1 ); ?>>
					<span class="as-glossar-slider"></span>
				</label>
				<span class="as-glossar-toggle-label">
				<?php
				echo ( $o['use_chatgpt'] ?? 0 )
					? esc_html__( 'active - ChatGPT will analyze content intelligently', 'asmi-search' )
					: esc_html__( 'inactive - Using keyword extraction + DeepL', 'asmi-search' );
				?>
				</span>
			</td>
		</tr>
		<tr>
			<th><label for="chatgpt_api_key"><?php esc_html_e( 'ChatGPT API Key', 'asmi-search' ); ?></label></th>
			<td>
				<input type="text" name="<?php echo esc_attr( ASMI_OPT ); ?>[chatgpt_api_key]" 
				       id="chatgpt_api_key" class="regular-text" 
				       value="<?php echo esc_attr( $o['chatgpt_api_key'] ?? '' ); ?>"
				       placeholder="sk-...">
				<p class="description">
					<?php esc_html_e( 'Your OpenAI API key for ChatGPT. Get it from', 'asmi-search' ); ?> 
					<a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="chatgpt_assistant_id"><?php esc_html_e( 'Assistant ID (Optional)', 'asmi-search' ); ?></label></th>
			<td>
				<input type="text" name="<?php echo esc_attr( ASMI_OPT ); ?>[chatgpt_assistant_id]" 
				       id="chatgpt_assistant_id" class="regular-text" 
				       value="<?php echo esc_attr( $o['chatgpt_assistant_id'] ?? '' ); ?>"
				       placeholder="asst_...">
				<p class="description">
					<?php esc_html_e( 'Optional: Use a pre-configured OpenAI Assistant for better brand recognition.', 'asmi-search' ); ?><br>
					<?php 
					if ( ! empty( $o['chatgpt_assistant_id'] ) ) {
						echo '<strong style="color: #46b450;">' . esc_html__( 'Assistant active:', 'asmi-search' ) . '</strong> ' . esc_html( $o['chatgpt_assistant_id'] );
					} else {
						echo esc_html__( 'Create an Assistant at', 'asmi-search' ) . ' <a href="https://platform.openai.com/assistants" target="_blank">platform.openai.com/assistants</a>';
					}
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="chatgpt_model"><?php esc_html_e( 'ChatGPT Model', 'asmi-search' ); ?></label></th>
			<td>
				<select name="<?php echo esc_attr( ASMI_OPT ); ?>[chatgpt_model]" id="chatgpt_model">
					<option value="gpt-4o-mini" <?php selected( $o['chatgpt_model'] ?? 'gpt-4o-mini', 'gpt-4o-mini' ); ?>>
						GPT-4o Mini (Fastest &amp; Cheapest - Recommended)
					</option>
					<option value="gpt-4o" <?php selected( $o['chatgpt_model'] ?? '', 'gpt-4o' ); ?>>
						GPT-4o (Better quality, 10x more expensive)
					</option>
					<option value="gpt-3.5-turbo" <?php selected( $o['chatgpt_model'] ?? '', 'gpt-3.5-turbo' ); ?>>
						GPT-3.5 Turbo (Legacy, cheap)
					</option>
				</select>
				<p class="description">
					<?php 
					if ( ! empty( $o['chatgpt_assistant_id'] ) ) {
						esc_html_e( 'Note: When using an Assistant, the model is configured in the Assistant settings.', 'asmi-search' );
					} else {
						esc_html_e( 'GPT-4o Mini: $0.15/1M input, $0.60/1M output tokens. Perfect for this use case.', 'asmi-search' );
					}
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Test ChatGPT Connection', 'asmi-search' ); ?></th>
			<td>
				<button type="button" class="button" id="asmi-test-chatgpt">
					<?php esc_html_e( 'Test API Connection', 'asmi-search' ); ?>
				</button>
				<span id="asmi-chatgpt-test-result"></span>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'DeepL API (Fallback)', 'asmi-search' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="deepl_api_key"><?php esc_html_e( 'DeepL API Key', 'asmi-search' ); ?></label></th>
			<td>
				<input type="text" name="<?php echo esc_attr( ASMI_OPT ); ?>[deepl_api_key]" 
				       id="deepl_api_key" class="regular-text" 
				       value="<?php echo esc_attr( $o['deepl_api_key'] ); ?>">
				<p class="description">
					<?php esc_html_e( 'Used as fallback when ChatGPT is not available. Get from', 'asmi-search' ); ?>
					<a href="https://www.deepl.com/pro-api" target="_blank">deepl.com</a>
				</p>
			</td>
		</tr>
	</table>
	
	<h3><?php esc_html_e( 'Brand Recognition', 'asmi-search' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="custom_brand_names"><?php esc_html_e( 'Custom Brand Names', 'asmi-search' ); ?></label></th>
			<td>
				<textarea name="<?php echo esc_attr( ASMI_OPT ); ?>[custom_brand_names]" id="custom_brand_names" 
				          class="large-text" rows="3" placeholder="victron, pylontech, byd, fronius"><?php 
				          echo esc_textarea( $o['custom_brand_names'] ?? '' ); 
				?></textarea>
				<p class="description">
					<?php 
					if ( ! empty( $o['chatgpt_assistant_id'] ) ) {
						esc_html_e( 'Note: When using an Assistant, brands should be configured in the Assistant\'s knowledge files.', 'asmi-search' );
					} else {
						esc_html_e( 'Additional brands for ChatGPT to recognize (comma-separated).', 'asmi-search' );
						echo '<br>';
						esc_html_e( 'ChatGPT already knows major brands but you can add specific or niche brands here.', 'asmi-search' );
					}
					?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Other Settings', 'asmi-search' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="utm_parameters"><?php esc_html_e( 'UTM Parameters', 'asmi-search' ); ?></label></th>
			<td>
				<input type="text" name="<?php echo esc_attr( ASMI_OPT ); ?>[utm_parameters]" id="utm_parameters" 
				       class="large-text" value="<?php echo esc_attr( $o['utm_parameters'] ); ?>" 
				       placeholder="utm_source=search&utm_medium=modal">
				<p class="description"><?php esc_html_e( 'UTM parameters appended to product URLs.', 'asmi-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="product_search_url"><?php esc_html_e( 'Product Search URL', 'asmi-search' ); ?></label></th>
			<td>
				<input type="url" name="<?php echo esc_attr( ASMI_OPT ); ?>[product_search_url]" 
				       id="product_search_url" class="large-text" 
				       value="<?php echo esc_attr( $o['product_search_url'] ); ?>" 
				       placeholder="https://shop.example.com/search/?q=">
				<p class="description"><?php esc_html_e( 'Link for "View all products". Search term will be appended.', 'asmi-search' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#asmi-test-chatgpt').on('click', function() {
		var $btn = $(this);
		var $result = $('#asmi-chatgpt-test-result');
		var apiKey = $('#chatgpt_api_key').val();
		var model = $('#chatgpt_model').val();
		var assistantId = $('#chatgpt_assistant_id').val();
		
		if (!apiKey) {
			$result.html('<span style="color:red;">Please enter an API key first.</span>');
			return;
		}
		
		$btn.prop('disabled', true);
		$result.html('<span style="color:blue;">Testing...</span>');
		
		$.post(ajaxurl, {
			action: 'asmi_test_chatgpt',
			api_key: apiKey,
			model: model,
			assistant_id: assistantId,
			_wpnonce: '<?php echo wp_create_nonce( "asmi_test_chatgpt" ); ?>'
		}, function(response) {
			$btn.prop('disabled', false);
			if (response.success) {
				$result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
			} else {
				$result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
			}
		});
	});
});
</script>
	<?php
}