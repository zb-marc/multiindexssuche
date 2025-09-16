<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function asmi_render_tab_integration($o) {
?>
<div id="tab-integration" class="asmi-tab card">
    <h2><?php esc_html_e( 'Integration', 'asmi-search' ); ?></h2>

    <h3><?php esc_html_e( 'DeepL API-Einstellungen', 'asmi-search' ); ?></h3>
    <p><?php esc_html_e( 'Geben Sie hier Ihren DeepL API-Schlüssel ein, um WordPress-Inhalte für den Index automatisch zu übersetzen. Ein kostenloser API-Schlüssel ist bei DeepL erhältlich.', 'asmi-search' ); ?></p>
    <table class="form-table">
        <tr>
            <th><label for="deepl_api_key"><?php esc_html_e( 'DeepL API Key', 'asmi-search' ); ?></label></th>
            <td>
                <input type="password" name="<?php echo esc_attr( ASMI_OPT ); ?>[deepl_api_key]" id="deepl_api_key" class="regular-text" value="<?php echo esc_attr( $o['deepl_api_key'] ?? '' ); ?>">
                <p class="description"><?php esc_html_e( 'Sie finden Ihren API-Schlüssel in Ihrem DeepL-Konto. Der Schlüssel wird aus Sicherheitsgründen als Passwortfeld dargestellt.', 'asmi-search' ); ?></p>
            </td>
        </tr>
    </table>
    <hr>
    
    <table class="form-table">
      <tr>
          <th><?php esc_html_e( 'Shortcode aktivieren', 'asmi-search' ); ?></th>
          <td>
            <label class="as-glossar-toggle-switch">
              <input type="checkbox" name="<?php echo esc_attr(ASMI_OPT); ?>[enable_shortcode]" value="1" <?php checked($o['enable_shortcode'], 1); ?>>
              <span class="as-glossar-slider"></span>
            </label>
            <span class="as-glossar-toggle-label">
              <?php esc_html_e( 'Aktiviert die Standalone-Suche über den Shortcode [multiindex_search].', 'asmi-search' ); ?>
            </span>
          </td>
      </tr>
      <tr>
        <th><label for="product_search_url"><?php esc_html_e( 'URL für Produkt-Suchergebnisseite', 'asmi-search' ); ?></label></th>
        <td><input name="<?php echo esc_attr( ASMI_OPT ); ?>[product_search_url]" id="product_search_url" class="regular-text" value="<?php echo esc_attr( $o['product_search_url'] ); ?>">
          <p class="description"><?php esc_html_e( 'Der Suchbegriff wird an diese URL angehängt. Beispiel:', 'asmi-search' ); ?> <code>https://akkusys.shop/search?sSearch=</code>.</p>
        </td>
      </tr>
      <tr>
        <th><label for="image_storage_mode"><?php esc_html_e( 'Bild-Speichermodus', 'asmi-search' ); ?></label></th>
        <td>
          <fieldset>
            <p>
              <label>
                <input type="radio" name="<?php echo esc_attr(ASMI_OPT); ?>[image_storage_mode]" value="local" <?php checked($o['image_storage_mode'], 'local'); ?>>
                <?php esc_html_e( 'Bilder lokal in die Mediathek importieren (Sideloading)', 'asmi-search' ); ?>
              </label>
              <br>
              <span class="description"><?php esc_html_e( 'Empfohlen. Bilder werden auf Ihren Server kopiert. Dies verbessert die Ladezeit und Verfügbarkeit.', 'asmi-search' ); ?></span>
            </p>
            <p>
              <label>
                <input type="radio" name="<?php echo esc_attr(ASMI_OPT); ?>[image_storage_mode]" value="stream" <?php checked($o['image_storage_mode'], 'stream'); ?>>
                <?php esc_html_e( 'Bilder direkt vom Feed laden (Hotlinking)', 'asmi-search' ); ?>
              </label>
              <br>
              <span class="description"><?php esc_html_e( 'Bilder werden direkt von der externen Quelle geladen. Spart Speicherplatz, kann aber langsamer sein und zu fehlenden Bildern führen, wenn die Quelle offline ist.', 'asmi-search' ); ?></span>
            </p>
          </fieldset>
        </td>
      </tr>
      <tr>
        <th><label for="fallback_image_product"><?php esc_html_e( 'Fallback-Bild (Produkte)', 'asmi-search' ); ?></label></th>
        <td>
            <div style="display:flex; align-items:center; gap:10px;">
                <input id="fallback_image_product" name="<?php echo esc_attr(ASMI_OPT); ?>[fallback_image_product]" type="text" class="regular-text" value="<?php echo esc_attr($o['fallback_image_product']); ?>">
                <button type="button" class="button asmi-upload-btn" data-target-input="#fallback_image_product"><?php esc_html_e('Bild auswählen', 'asmi-search'); ?></button>
            </div>
            <p class="description"><?php esc_html_e( 'Wird angezeigt, wenn ein Produkt-Ergebnis kein Bild hat.', 'asmi-search' ); ?></p>
        </td>
      </tr>
       <tr>
        <th><label for="fallback_image_wp"><?php esc_html_e( 'Fallback-Bild (WordPress)', 'asmi-search' ); ?></label></th>
        <td>
            <div style="display:flex; align-items:center; gap:10px;">
                <input id="fallback_image_wp" name="<?php echo esc_attr(ASMI_OPT); ?>[fallback_image_wp]" type="text" class="regular-text" value="<?php echo esc_attr($o['fallback_image_wp']); ?>">
                <button type="button" class="button asmi-upload-btn" data-target-input="#fallback_image_wp"><?php esc_html_e('Bild auswählen', 'asmi-search'); ?></button>
            </div>
            <p class="description"><?php esc_html_e( 'Wird angezeigt, wenn ein WordPress-Ergebnis kein Beitragsbild hat.', 'asmi-search' ); ?></p>
        </td>
      </tr>
      <tr>
        <th><label for="utm_parameters"><?php esc_html_e( 'UTM-Parameter für Produkt-URLs', 'asmi-search' ); ?></label></th>
        <td><input name="<?php echo esc_attr( ASMI_OPT ); ?>[utm_parameters]" id="utm_parameters" class="regular-text" value="<?php echo esc_attr( $o['utm_parameters'] ); ?>">
          <p class="description"><?php esc_html_e( 'Kompletter String, z.B.', 'asmi-search' ); ?> <code>utm_source=website&utm_medium=search</code>.</p>
        </td>
      </tr>
    </table>
</div>
<?php
}