<?php
/**
 * Rendert den "Index-Daten"-Tab im Admin-Bereich.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gibt das HTML für den Index-Daten-Tab aus.
 *
 * @param array $o Die Plugin-Optionen.
 * @return void
 */
function asmi_render_tab_data( $o ) {
	// Sprachen sind jetzt fest definiert, da keine automatische Erkennung mehr stattfindet.
	$languages = array( 'de_DE', 'en_GB' );
	?>
<div id="tab-data" class="asmi-tab card">
	<h2><?php esc_html_e( 'Index Data', 'asmi-search' ); ?></h2>
	<p><?php esc_html_e( 'Shows the 50 most recently indexed entries per language and data type.', 'asmi-search' ); ?></p>
	
	<div class="asmi-data-nav">
		<strong><?php esc_html_e( 'Data Type:', 'asmi-search' ); ?></strong>
		<a href="#" class="asmi-data-filter asmi-data-type-btn active" data-type="product"><?php esc_html_e( 'Products', 'asmi-search' ); ?></a>
		<a href="#" class="asmi-data-filter asmi-data-type-btn" data-type="wordpress"><?php esc_html_e( 'WordPress', 'asmi-search' ); ?></a>
	</div>
	<div class="asmi-data-nav">
		<strong><?php esc_html_e( 'Language:', 'asmi-search' ); ?></strong>
		<?php foreach ( $languages as $index => $lang_code ) : ?>
			<a href="#" class="asmi-data-filter asmi-data-lang-btn <?php echo 0 === $index ? 'active' : ''; ?>" data-lang="<?php echo esc_attr( $lang_code ); ?>"><?php echo esc_html( strtoupper( substr( $lang_code, 0, 2 ) ) ); ?></a>
		<?php endforeach; ?>
	</div>
	
	<div id="asmi-data-tables-container">
		<?php
		foreach ( $languages as $index => $lang_code ) {
			$is_active_lang = 0 === $index;
			?>
			<div id="asmi-data-container-<?php echo esc_attr( $lang_code ); ?>-product" class="asmi-data-container" data-lang="<?php echo esc_attr( $lang_code ); ?>" data-type="product" style="<?php echo $is_active_lang ? 'display: block;' : 'display: none;'; ?>">
				<?php echo asmi_render_data_table( $lang_code, 'product', 50 ); ?>
			</div>
			<div id="asmi-data-container-<?php echo esc_attr( $lang_code ); ?>-wordpress" class="asmi-data-container" data-lang="<?php echo esc_attr( $lang_code ); ?>" data-type="wordpress" style="display: none;">
				<?php echo asmi_render_data_table( $lang_code, 'wordpress', 50 ); ?>
			</div>
			<?php
		}
		?>
	</div>
</div>
	<?php
}

/**
 * Rendert eine einzelne HTML-Tabelle mit Index-Daten.
 *
 * @param string $lang        Der Sprachcode.
 * @param string $source_type Der Quelltyp ('product' oder 'wordpress').
 * @param int    $limit       Die maximale Anzahl der Zeilen.
 * @return string Das gerenderte HTML der Tabelle.
 */
function asmi_render_data_table( $lang, $source_type, $limit = 50 ) {
	global $wpdb;
	$table = $wpdb->prefix . ASMI_INDEX_TABLE;

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return '<em>' . esc_html__( 'Index table does not exist yet. Please start an indexing process.', 'asmi-search' ) . '</em>';
	}
	
	// Für Produkte verwenden wir den zweistelligen Code, da Feeds diesen oft nur liefern.
	$lang_query = ( 'product' === $source_type ) ? substr( $lang, 0, 2) : $lang;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT title, url, lang, excerpt, image, sku, price, indexed_at FROM $table WHERE lang = %s AND source_type = %s ORDER BY indexed_at DESC LIMIT %d",
			$lang_query,
			$source_type,
			absint( $limit )
		),
		ARRAY_A
	);

	if ( empty( $rows ) ) {
		// translators: %1$s is the source type (e.g., "product"), %2$s is the language code (e.g., "en").
		return '<em>' . sprintf( esc_html__( 'No "%1$s" entries found for language "%2$s" in the index.', 'asmi-search' ), esc_html( $source_type ), esc_html( $lang ) ) . '</em>';
	}

	$headers = array_keys( $rows[0] );
	$html    = '<div class="asmi-data-table-wrap"><table class="widefat striped"><thead><tr>';
	foreach ( $headers as $header ) {
		$html .= '<th>' . esc_html( $header ) . '</th>';
	}
	$html .= '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$html .= '<tr>';
		foreach ( $headers as $header ) {
			$value = $row[ $header ] ?? '';
			if ( 'image' === $header && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$value = '<img src="' . esc_url( $value ) . '" style="max-width:60px; max-height:60px; object-fit:contain;" loading="lazy" />';
			} else {
				$value = esc_html( is_scalar( $value ) ? $value : wp_json_encode( $value ) );
			}
			$html .= '<td>' . $value . '</td>';
		}
		$html .= '</tr>';
	}
	$html .= '</tbody></table></div>';
	return $html;
}