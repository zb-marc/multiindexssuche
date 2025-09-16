<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function asmi_render_tab_general($o) {
?>
<div id="tab-general" class="asmi-tab card" style="display:block">
    <h2><?php esc_html_e( 'Allgemein', 'asmi-search' ); ?></h2>
    <table class="form-table">
      <tr>
        <th><label for="feed_urls"><?php esc_html_e( 'Feed-URLs (Deutsch)', 'asmi-search' ); ?></label></th>
        <td>
          <input name="<?php echo esc_attr(ASMI_OPT); ?>[feed_urls]" id="feed_urls" class="regular-text" value="<?php echo esc_attr($o['feed_urls']); ?>">
          <p class="description"><?php esc_html_e( 'Mehrere Feeds kommasepariert:', 'asmi-search' ); ?> <strong><?php esc_html_e( 'CSV & XML', 'asmi-search' ); ?></strong>. <?php esc_html_e( 'JSON wird zusätzlich unterstützt.', 'asmi-search' ); ?></p>
        </td>
      </tr>
      <tr>
        <th><label for="feed_urls_en"><?php esc_html_e( 'Feed-URLs (Englisch)', 'asmi-search' ); ?></label></th>
        <td>
          <input name="<?php echo esc_attr(ASMI_OPT); ?>[feed_urls_en]" id="feed_urls_en" class="regular-text" value="<?php echo esc_attr($o['feed_urls_en']); ?>">
          <p class="description"><?php esc_html_e( 'Optionale, separate Feed-Quellen für englische Inhalte.', 'asmi-search' ); ?></p>
        </td>
      </tr>
      <tr>
        <th><label for="preset"><?php esc_html_e( 'Mapping-Preset', 'asmi-search' ); ?></label></th>
        <td>
          <select name="<?php echo esc_attr(ASMI_OPT); ?>[preset]" id="preset">
            <option value="auto" <?php selected($o['preset'],'auto'); ?>><?php esc_html_e( 'Auto erkennen', 'asmi-search' ); ?></option>
            <option value="sw6" <?php selected($o['preset'],'sw6'); ?> data-map_id="id" data-map_name="name" data-map_desc="description" data-map_sku="productNumber" data-map_price="price" data-map_image="cover.media.url" data-map_url="url" data-map_updated="updatedAt"><?php esc_html_e( 'Shopware 6 (JSON)', 'asmi-search' ); ?></option>
            <option value="sw5" <?php selected($o['preset'],'sw5'); ?> data-map_id="id" data-map_name="name" data-map_desc="descriptionLong" data-map_sku="orderNumber" data-map_price="price" data-map_image="image" data-map_url="link" data-map_updated="changed"><?php esc_html_e( 'Shopware 5 (JSON/XML)', 'asmi-search' ); ?></option>
            <option value="gmc" <?php selected($o['preset'],'gmc'); ?> data-map_id="g:id" data-map_name="title" data-map_desc="description" data-map_sku="g:mpn" data-map_price="g:price" data-map_image="g:image_link" data-map_url="link" data-map_updated=""><?php esc_html_e( 'Google Merchant (XML mit g:)', 'asmi-search' ); ?></option>
            <option value="csv" <?php selected($o['preset'],'csv'); ?>><?php esc_html_e( 'Allgemeines CSV', 'asmi-search' ); ?></option>
            <option value="custom" <?php selected($o['preset'],'custom'); ?>><?php esc_html_e( 'Manuell (eigene Feldnamen)', 'asmi-search' ); ?></option>
          </select>
          <p class="description"><?php esc_html_e( 'Auto erkennt u. a. g:-Namespace (GMC), SW5/6-Felder oder CSV-Header automatisch.', 'asmi-search' ); ?></p>
        </td>
      </tr>
    </table>
</div>
<?php
}