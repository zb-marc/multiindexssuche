<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function asmi_render_tab_mapping($o) {
?>
<div id="tab-mapping" class="asmi-tab card">
    <h2><?php esc_html_e( 'Feld-Mapping', 'asmi-search' ); ?></h2>
    <p><?php esc_html_e( 'Diese Felder definieren, wie ein Suchergebnis im Frontend dargestellt wird. Die Suche selbst durchsucht alle verfügbaren Daten des Feeds.', 'asmi-search' ); ?></p>
    <table class="form-table">
      <?php
      $fields = [
        'map_id'      => __( 'Feld: ID (z.B. id / g:id)', 'asmi-search' ),
        'map_name'    => __( 'Feld: Name (z.B. name / title)', 'asmi-search' ),
        'map_desc'    => __( 'Feld: Beschreibung (z.B. description)', 'asmi-search' ),
        'map_sku'     => __( 'Feld: SKU/MPN (z.B. sku / mpn / g:mpn)', 'asmi-search' ),
        'map_gtin'    => __( 'Feld: EAN/GTIN (z.B. gtin / g:gtin)', 'asmi-search' ),
        'map_price'   => __( 'Feld: Preis (z.B. price / g:price)', 'asmi-search' ),
        'map_image'   => __( 'Feld: Bild-URL (z.B. image / image_link / g:image_link)', 'asmi-search' ),
        'map_url'     => __( 'Feld: Produkt-URL (z.B. url / link)', 'asmi-search' ),
        'map_updated' => __( 'Feld: Aktualisiert am (z.B. updatedAt / changed)', 'asmi-search' ),
      ];
      foreach ( $fields as $k => $label ) : ?>
        <tr>
          <th><label for="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label></th>
          <td><input name="<?php echo esc_attr( ASMI_OPT ); ?>[<?php echo esc_attr( $k ); ?>]" id="<?php echo esc_attr( $k ); ?>" class="regular-text" value="<?php echo esc_attr( $o[ $k ] ); ?>"></td>
        </tr>
      <?php endforeach; ?>
    </table>
</div>
<?php
}