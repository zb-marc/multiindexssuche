<?php
/**
 * Suchfunktionalität für den Index.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Führt eine direkte, schnelle Suche auf der benutzerdefinierten Index-Tabelle aus.
 *
 * @param string $q Der Suchbegriff.
 * @param int    $limit Die maximale Anzahl der Ergebnisse pro Gruppe.
 * @param string $lang Das Sprachkürzel (z.B. 'de' oder 'en').
 * @return array Ein Array mit zwei Schlüsseln: 'products' und 'wordpress'.
 */
function asmi_unified_search( $q, $limit, $lang = 'de' ) {
	global $wpdb;
	$o = asmi_get_opts();
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	
	// Erweiterte Sprachcode-Verarbeitung für korrekte Filterung
	// Normalisiere den Sprachcode auf die ersten 2 Buchstaben für Produkte
	$lang_short = substr( $lang, 0, 2 );
	
	// Debug-Logging für Sprachprobleme
	asmi_debug_log( "Search requested - Query: '{$q}', Language param: '{$lang}', Short: '{$lang_short}'" );

	$keyword = trim( $q );
	if ( empty( $keyword ) ) {
		return array( 'products' => array(), 'wordpress' => array() );
	}
	
	// OPTIMIERUNG: Intelligente Behandlung von Bindestrichen
	// Erstelle verschiedene Suchvarianten für bessere Treffer
	$search_variants = asmi_prepare_search_variants( $keyword );
	$original_keyword = $keyword;
	$fulltext_keyword = $search_variants['fulltext'] . '*';
	$like_keyword = $search_variants['like'];
	
	asmi_debug_log( "Search variants - Original: '{$original_keyword}', Fulltext: '{$search_variants['fulltext']}', Like: '{$like_keyword}'" );

	// --- Suche 1: Produkte ---
	// Explizite Sprachfilterung für Produkte
	$product_results = array();
	
	// Exact Match Suche mit korrekter Sprache (erweitert um Bindestrich-Varianten)
	$exact_match_query = $wpdb->prepare(
		"SELECT id, source_id, title, excerpt, url, image, price, sku, gtin
		 FROM {$table_name}
		 WHERE source_type = 'product' AND lang = %s 
		 AND (source_id = %s OR sku = %s OR gtin = %s 
		      OR sku LIKE %s OR gtin LIKE %s
		      OR REPLACE(sku, '-', ' ') = %s OR REPLACE(gtin, '-', ' ') = %s)
		 LIMIT 1",
		$lang_short, 
		$original_keyword, $original_keyword, $original_keyword,
		'%' . $wpdb->esc_like( $like_keyword ) . '%', '%' . $wpdb->esc_like( $like_keyword ) . '%',
		$search_variants['normalized'], $search_variants['normalized']
	);
	$exact_match = $wpdb->get_row( $exact_match_query, ARRAY_A );

	if ( $exact_match ) {
		asmi_debug_log( "Exact product match found for '{$keyword}' in language '{$lang_short}'" );
		$product_results[] = array(
			'source'  => 'product',
			'id'      => $exact_match['id'],
			'title'   => $exact_match['title'],
			'url'     => $exact_match['url'],
			'excerpt' => $exact_match['excerpt'],
			'image'   => ! empty( $exact_match['image'] ) ? $exact_match['image'] : $o['fallback_image_product'],
			'price'   => $exact_match['price'],
			'score'   => 1000,
			'date'    => time(),
			'sku'     => $exact_match['sku'],
			'gtin'    => $exact_match['gtin'],
		);
		
		// Bei Exact Match nur Produkte zurückgeben, keine WordPress-Inhalte
		return array(
			'wordpress' => array(),
			'products'  => $product_results
		);
	}
	
	// Volltext-Suche für Produkte mit korrekter Sprache
	// ERWEITERT: Kombinierte Suche mit FULLTEXT und LIKE für Bindestrich-Begriffe
	$product_query = $wpdb->prepare(
		"SELECT id, title, excerpt, url, image, price, sku, gtin,
			(
				CASE 
					WHEN MATCH(title, content, excerpt, sku, gtin) AGAINST(%s IN BOOLEAN MODE) THEN
						MATCH(title, content, excerpt, sku, gtin) AGAINST(%s IN BOOLEAN MODE)
					ELSE 0
				END
				+
				CASE WHEN excerpt LIKE %s THEN 2.0 ELSE 0 END
				+
				CASE WHEN title LIKE %s THEN 1.5 ELSE 0 END
				+
				CASE WHEN sku LIKE %s THEN 1.3 ELSE 0 END
				+
				CASE WHEN gtin LIKE %s THEN 1.3 ELSE 0 END
				+
				CASE WHEN content LIKE %s THEN 0.8 ELSE 0 END
				+
				-- Zusätzliche Gewichtung für Bindestrich-Varianten
				CASE WHEN REPLACE(title, '-', ' ') LIKE %s THEN 1.2 ELSE 0 END
				+
				CASE WHEN REPLACE(excerpt, '-', ' ') LIKE %s THEN 1.0 ELSE 0 END
				+
				CASE WHEN REPLACE(sku, '-', ' ') LIKE %s THEN 1.0 ELSE 0 END
			) AS relevance
		FROM {$table_name}
		WHERE source_type = 'product' AND lang = %s
		AND (
			MATCH(title, content, excerpt, sku, gtin) AGAINST(%s IN BOOLEAN MODE)
			OR title LIKE %s
			OR excerpt LIKE %s
			OR sku LIKE %s
			OR gtin LIKE %s
			OR content LIKE %s
			OR REPLACE(title, '-', ' ') LIKE %s
			OR REPLACE(excerpt, '-', ' ') LIKE %s
			OR REPLACE(sku, '-', ' ') LIKE %s
			OR REPLACE(gtin, '-', ' ') LIKE %s
		)
		ORDER BY relevance DESC
		LIMIT %d",
		$fulltext_keyword,
		$fulltext_keyword,
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $search_variants['normalized'] ) . '%',
		'%' . $wpdb->esc_like( $search_variants['normalized'] ) . '%',
		'%' . $wpdb->esc_like( $search_variants['normalized'] ) . '%',
		$lang_short,  // WICHTIG: Verwende den kurzen Sprachcode für Produkte
		$fulltext_keyword,
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $like_keyword ) . '%',
		'%' . $wpdb->esc_like( $search_variants['normalized'] ) . '%',
		'%' . $wpdb->esc_like( $search_variants['normalized'] ) . '%',
		'%' . $wpdb->esc_like( $search_variants['normalized'] ) . '%',
		'%' . $wpdb->esc_like( $search_variants['normalized'] ) . '%',
		$limit
	);
	
	$product_results_raw = $wpdb->get_results( $product_query, ARRAY_A );
	
	// Debug-Ausgabe
	asmi_debug_log( "Product search found " . count( $product_results_raw ) . " results for language '{$lang_short}'" );

	foreach ( $product_results_raw as $p ) {
		$product_results[] = array(
			'source'  => 'product',
			'id'      => $p['id'],
			'title'   => $p['title'],
			'url'     => $p['url'],
			'excerpt' => $p['excerpt'],
			'image'   => ! empty( $p['image'] ) ? $p['image'] : $o['fallback_image_product'],
			'price'   => $p['price'],
			'score'   => (float) $p['relevance'] + (float) $o['weight_sw'],
			'date'    => time(),
			'sku'     => $p['sku'],
			'gtin'    => $p['gtin'],
		);
	}

	// --- Suche 2: WordPress-Inhalte ---
	$wp_results = array();
	$wp_post_types = ! empty( $o['wp_post_types'] ) ? array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : array( 'post', 'page' );
	
	if ( ! empty( $wp_post_types ) ) {
		
		$exclude_sql = '';
		$exclude_ids = array();
		if ( ! empty( $o['excluded_ids'] ) ) {
			$exclude_ids = array_map( 'intval', explode( ',', $o['excluded_ids'] ) );
			if ( ! empty( $exclude_ids ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $exclude_ids ), '%d' ) );
				$exclude_sql  = " AND source_id NOT IN ( $placeholders )";
			}
		}

		// Bestimme den korrekten Sprachcode für WordPress-Inhalte
		// WordPress-Inhalte sind mit de_DE oder en_GB gespeichert
		$wp_lang_code = 'de_DE'; // Default
		if ( $lang_short === 'en' ) {
			$wp_lang_code = 'en_GB';
		} elseif ( in_array( $lang, array( 'en_GB', 'en_US' ), true ) ) {
			$wp_lang_code = 'en_GB';
		} elseif ( $lang === 'de_DE' || $lang_short === 'de' ) {
			$wp_lang_code = 'de_DE';
		}
		
		asmi_debug_log( "WordPress content search using language '{$wp_lang_code}'" );

		// ERWEITERT: WordPress-Suche mit Bindestrich-Unterstützung
		$query = "SELECT id, source_id, title, content, excerpt, url, image,
					(
						CASE 
							WHEN MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE) THEN
								MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE)
							ELSE 0
						END
						+
						CASE 
							WHEN LOWER(excerpt) LIKE %s THEN 2.5
							WHEN LOWER(title) LIKE %s THEN 2.0
							WHEN LOWER(content) LIKE %s THEN 1.5
							ELSE 0
						END
						+
						-- Zusätzliche Gewichtung für Bindestrich-Varianten
						CASE 
							WHEN LOWER(REPLACE(excerpt, '-', ' ')) LIKE %s THEN 1.5
							WHEN LOWER(REPLACE(title, '-', ' ')) LIKE %s THEN 1.2
							WHEN LOWER(REPLACE(content, '-', ' ')) LIKE %s THEN 1.0
							ELSE 0
						END
					) AS relevance
				  FROM {$table_name}
				  WHERE source_type = 'wordpress' AND lang = %s
				  AND (
					  MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE)
					  OR LOWER(title) LIKE %s
					  OR LOWER(excerpt) LIKE %s
					  OR LOWER(content) LIKE %s
					  OR LOWER(REPLACE(title, '-', ' ')) LIKE %s
					  OR LOWER(REPLACE(excerpt, '-', ' ')) LIKE %s
					  OR LOWER(REPLACE(content, '-', ' ')) LIKE %s
				  )
				  {$exclude_sql}
				  ORDER BY relevance DESC
				  LIMIT %d";
		
		$params = array_merge(
			array( 
				$fulltext_keyword,
				$fulltext_keyword, 
				'%' . strtolower( $wpdb->esc_like( $like_keyword ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $like_keyword ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $like_keyword ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $search_variants['normalized'] ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $search_variants['normalized'] ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $search_variants['normalized'] ) ) . '%',
				$wp_lang_code, 
				$fulltext_keyword,
				'%' . strtolower( $wpdb->esc_like( $like_keyword ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $like_keyword ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $like_keyword ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $search_variants['normalized'] ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $search_variants['normalized'] ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $search_variants['normalized'] ) ) . '%'
			),
			$exclude_ids,
			array( $limit )
		);
		
		$wp_results_raw = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );
		
		// Debug-Ausgabe
		asmi_debug_log( "WordPress search found " . count( $wp_results_raw ) . " results for language '{$wp_lang_code}'" );

		foreach ( $wp_results_raw as $p ) {
			$wp_results[] = array(
				'source'  => 'wordpress',
				'id'      => $p['source_id'],
				'title'   => $p['title'],
				'content' => $p['content'],  // KORREKTUR: content-Feld hinzugefügt!
				'url'     => $p['url'],
				'excerpt' => $p['excerpt'],
				'image'   => ! empty( $p['image'] ) ? $p['image'] : $o['fallback_image_wp'],
				'score'   => (float) $p['relevance'] + (float) $o['weight_wp'],
				'date'    => (int) get_post_time( 'U', true, $p['source_id'] ),
			);
		}
	}
	
	// Debug-Ausgabe der Gesamtergebnisse
	asmi_debug_log( "Total search results - Products: " . count( $product_results ) . ", WordPress: " . count( $wp_results ) );
	
	return array(
		'wordpress' => $wp_results,
		'products'  => $product_results
	);
}

/**
 * Bereitet verschiedene Suchvarianten für bessere Treffer vor.
 * Behandelt speziell Begriffe mit Bindestrichen intelligent.
 *
 * @param string $keyword Der Original-Suchbegriff.
 * @return array Array mit verschiedenen Suchvarianten.
 */
function asmi_prepare_search_variants( $keyword ) {
	$variants = array(
		'original' => $keyword,
		'fulltext' => $keyword,
		'like' => $keyword,
		'normalized' => $keyword
	);
	
	// Behandle Bindestriche intelligent
	if ( strpos( $keyword, '-' ) !== false ) {
		// Für FULLTEXT: Ersetze Bindestrich durch Leerzeichen
		// Dies hilft bei Begriffen wie "q-batteries" -> "q batteries"
		$fulltext_variant = str_replace( '-', ' ', $keyword );
		
		// Entferne einzelne Buchstaben am Anfang, wenn sie durch Bindestrich getrennt sind
		// z.B. "q-batteries" -> "batteries" für zusätzliche Suche
		$parts = explode( '-', $keyword );
		if ( count( $parts ) > 1 && strlen( $parts[0] ) <= 2 ) {
			// Wenn der erste Teil sehr kurz ist (1-2 Zeichen), nutze auch den Rest allein
			$additional_search = implode( ' ', array_slice( $parts, 1 ) );
			$fulltext_variant = $fulltext_variant . ' ' . $additional_search;
		}
		
		// SICHERHEIT: Prüfe ob der FULLTEXT-Begriff zu kurz ist
		// MySQL benötigt mindestens 3 Zeichen für FULLTEXT (ft_min_word_len)
		$fulltext_parts = explode( ' ', $fulltext_variant );
		$valid_parts = array();
		foreach ( $fulltext_parts as $part ) {
			$clean_part = trim( $part );
			// Füge nur Teile hinzu, die mindestens 3 Zeichen haben
			if ( strlen( $clean_part ) >= 3 ) {
				$valid_parts[] = $clean_part;
			}
		}
		
		// Wenn keine gültigen Teile übrig sind, verwende einen leeren String
		// (FULLTEXT wird dann einfach keine Treffer liefern, aber keinen Fehler werfen)
		$variants['fulltext'] = ! empty( $valid_parts ) ? implode( ' ', $valid_parts ) : '';
		$variants['normalized'] = str_replace( '-', ' ', $keyword );
	}
	
	// ZUSÄTZLICHE SICHERHEIT: Prüfe auch den normalen Begriff
	if ( strlen( trim( $variants['fulltext'] ) ) < 3 ) {
		// Bei zu kurzen Begriffen verwende einen leeren String für FULLTEXT
		// Die LIKE-Suche wird trotzdem funktionieren
		$variants['fulltext'] = '';
	}
	
	// Behandle auch andere Sonderzeichen
	// Entferne oder ersetze Sonderzeichen für LIKE-Suche
	$like_variant = preg_replace( '/[^\p{L}\p{N}\s\-]/u', '', $keyword );
	$variants['like'] = $like_variant;
	
	return $variants;
}