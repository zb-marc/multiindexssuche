<?php
/**
 * Frontend-Logik, Shortcode und Skript-Einbindung.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eine Helper-Klasse, um Daten vom Shortcode an die wp_footer-Aktion zu übergeben.
 */
final class ASMI_Frontend_Helper {
	/**
	 * Hält die Sprach-Überschreibung vom Shortcode.
	 *
	 * @var string|null
	 */
	public static $lang_override = null;
}

/**
 * Fügt das Modal-HTML einmalig in den Footer der Seite ein.
 *
 * @return void
 */
function asmi_add_modal_to_footer() {
	$o = asmi_get_opts();
	if ( empty( $o['enable_shortcode'] ) ) {
		return;
	}

	if ( ! wp_script_is( 'asmi-standalone', 'enqueued' ) ) {
		wp_enqueue_style( 'asmi-standalone', ASMI_ASSETS . 'standalone.css', array(), ASMI_VERSION );
		wp_enqueue_script( 'asmi-standalone', ASMI_ASSETS . 'standalone.js', array( 'jquery' ), ASMI_VERSION, true );

		// Die Sprache wird ausschließlich durch den Shortcode bestimmt. Fallback auf 'de'.
		$current_lang = 'de';
		if ( ! empty( ASMI_Frontend_Helper::$lang_override ) ) {
			$current_lang = ASMI_Frontend_Helper::$lang_override;
		}

		wp_localize_script(
			'asmi-standalone',
			'ASMI',
			array(
				'endpoint'           => esc_url_raw( rest_url( ASMI_REST_NS . '/' . ASMI_REST_ROUTE ) ),
				'utmParameters'      => $o['utm_parameters'],
				'product_search_url' => esc_url( $o['product_search_url'] ),
				'wp_search_url'      => esc_url( home_url( '/?s=%s' ) ),
				'lang'               => $current_lang,
				'labels'             => array(
					'no_results'        => __( 'No results found.', 'asmi-search' ),
					'searching'         => __( 'Searching...', 'asmi-search' ),
					'view_all_wp'       => __( 'View all information', 'asmi-search' ),
					'view_all_products' => __( 'View all products', 'asmi-search' ),
				),
			)
		);
	}
	?>
	<div data-no-translation>
		<div id="asmi-search-modal-backdrop" class="asmi-modal-backdrop"></div>
		<div id="asmi-search-modal" class="asmi-modal-window">
			<div class="asmi-modal-content">
				<div class="asmi-search-form-wrapper">
					<input type="search" id="asmi-modal-q" class="asmi-search-field" placeholder="<?php esc_attr_e( 'Search…', 'asmi-search' ); ?>" autocomplete="off" />
					<button id="asmi-modal-close" type="button" class="asmi-modal-close">&times;</button>
				</div>
				<div id="asmi-modal-results" class="asmi-results-area" style="display: none;"></div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'asmi_add_modal_to_footer' );


/**
 * Registriert den Shortcode [multiindex_search].
 */
add_shortcode(
	'multiindex_search',
	function( $atts ) {
		$o = asmi_get_opts();
		if ( empty( $o['enable_shortcode'] ) ) {
			return '';
		}

		$atts      = shortcode_atts(
			array(
				'lang' => 'de',
			),
			$atts,
			'multiindex_search'
		);
		$lang_attr = sanitize_key( $atts['lang'] );

		if ( in_array( $lang_attr, array( 'de', 'en' ), true ) ) {
			ASMI_Frontend_Helper::$lang_override = $lang_attr;
		}

		return '<button id="asmi-modal-trigger-icon" type="button" class="asmi-modal-trigger" aria-label="' . esc_attr__( 'Open search', 'asmi-search' ) . '">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path></svg>
			  </button>';
	}
);