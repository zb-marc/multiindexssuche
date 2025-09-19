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
 * Fügt das Modal-HTML einmalig in den Footer der Seite ein.
 *
 * @return void
 */
function asmi_add_modal_to_footer() {
	$o = asmi_get_opts();
	if ( empty( $o['enable_shortcode'] ) ) {
		return;
	}

	// KORREKTUR: Entfernt, da Sprache jetzt über data-Attribut übergeben wird
	?>
	<div>
		<div id="asmi-search-modal-backdrop" class="asmi-modal-backdrop"></div>
		<div id="asmi-search-modal" class="asmi-modal-window" data-lang="de">
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
 * Lädt die Frontend-Assets einmalig, wenn der Shortcode verwendet wird.
 * Fügt zusätzlich die notwendigen Inline-CSS-Regeln für Button/Hover/Mobile hinzu.
 *
 * @return void
 */
function asmi_enqueue_frontend_assets() {
	$o = asmi_get_opts();
	
	if ( ! wp_script_is( 'asmi-standalone', 'enqueued' ) ) {
		wp_enqueue_style( 'asmi-standalone', ASMI_ASSETS . 'standalone.css', array(), ASMI_VERSION );
		wp_enqueue_script( 'asmi-standalone', ASMI_ASSETS . 'standalone.js', array( 'jquery' ), ASMI_VERSION, true );

		// KORREKTUR: Basis-Konfiguration ohne spezifische Sprache
		wp_localize_script(
			'asmi-standalone',
			'ASMI',
			array(
				'endpoint'           => esc_url_raw( rest_url( ASMI_REST_NS . '/' . ASMI_REST_ROUTE ) ),
				'utmParameters'      => $o['utm_parameters'],
				'product_search_url' => esc_url( $o['product_search_url'] ),
				'wp_search_url'      => esc_url( home_url( '/?s=%s' ) ),
				'labels'             => array(
					'no_results'        => __( 'Keine Ergebnisse gefunden.', 'asmi-search' ),
					'searching'         => __( 'Suche...', 'asmi-search' ),
					'view_all_wp'       => __( 'Alle Informationen anzeigen', 'asmi-search' ),
					'view_all_products' => __( 'Alle Produkte anzeigen', 'asmi-search' ),
				),
			)
		);

		// Inline-CSS für Button, Hover und Mobile (wird an asmi-standalone angehängt)
		$inline_css = <<<'CSS'
/* ASMI modal trigger base (Fallbacks via !important, um Theme-Stile zu überschreiben) */
.asmi-modal-trigger {
  background: none !important;
  border: none !important;
  padding: 5px !important;
  cursor: pointer !important;
  line-height: 0 !important;
  color: #474747 !important;               /* Standardfarbe (Desktop) */
  transition: color 0.3s ease !important;
}

/* Hover-Effekt nur auf Geräten mit Hover-Unterstützung (Desktop) */
@media (hover: hover) {
  .asmi-modal-trigger:hover,
  .asmi-modal-trigger:focus {
    color: #7F0103 !important;            /* Hover-Farbe */
  }
}

/* Mobile: Icon/Farbe auf Weiß setzen (≤768px) */
@media (max-width: 768px) {
  .asmi-modal-trigger {
    color: #ffffff !important;            /* mobile Farbe */
  }

  /* Fallback: falls ein mobiles Gerät Hover unterstützt */
  .asmi-modal-trigger:hover,
  .asmi-modal-trigger:focus {
    color: #ffffff !important;
  }
}

/* Optional: sichtbarer Fokus-Indikator für Tastaturnutzer */
.asmi-modal-trigger:focus {
  outline: 2px solid rgba(127, 1, 3, 0.15);
  outline-offset: 2px;
}
CSS;

		// Hänge das Inline-CSS an das registrierte/enqueued Stylesheet
		wp_add_inline_style( 'asmi-standalone', $inline_css );
	}
}

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

		$atts = shortcode_atts(
			array(
				'lang' => 'de',
			),
			$atts,
			'multiindex_search'
		);
		
		// Validiere die Sprache
		$lang_attr = sanitize_key( $atts['lang'] );
		if ( ! in_array( $lang_attr, array( 'de', 'en' ), true ) ) {
			$lang_attr = 'de';
		}
		
		// Assets einmalig laden (inkl. Inline-CSS)
		asmi_enqueue_frontend_assets();

		// Rückgabe: Button mit minimalem Inline-Fallback (ohne !important, Styles kommen aus asmi-standalone + inline-css)
		return sprintf(
			'<button id="asmi-modal-trigger-icon-%1$s" type="button" class="asmi-modal-trigger" data-lang="%1$s" aria-label="%2$s"
    style="background: none; border: none; padding: 5px; cursor: pointer; line-height: 0; color: #474747; transition: color 0.3s ease;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true">
        <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path>
    </svg>
</button>',
			esc_attr( $lang_attr ),
			esc_attr__( 'Open search', 'asmi-search' )
		);
	}
);