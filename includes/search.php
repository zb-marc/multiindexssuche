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
	
	$fulltext_keyword = $keyword . '*';

	// --- Suche 1: Produkte ---
	// Explizite Sprachfilterung für Produkte
	$product_results = array();
	
	// Exact Match Suche mit korrekter Sprache
	$exact_match_query = $wpdb->prepare(
		"SELECT id, source_id, title, excerpt, url, image, price, sku, gtin
		 FROM {$table_name}
		 WHERE source_type = 'product' AND lang = %s AND (source_id = %s OR sku = %s OR gtin = %s)
		 LIMIT 1",
		$lang_short, $keyword, $keyword, $keyword
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
	// OPTIMIERUNG: Erhöhte Gewichtung für Marken im Excerpt
	$product_query = $wpdb->prepare(
		"SELECT id, title, excerpt, url, image, price, sku, gtin,
			(
				MATCH(title, content, excerpt, sku, gtin) AGAINST(%s IN BOOLEAN MODE)
				+
				CASE WHEN excerpt LIKE %s THEN 1.5 ELSE 0 END
				+
				CASE WHEN title LIKE %s THEN 0.8 ELSE 0 END
				+
				CASE WHEN sku LIKE %s THEN 0.7 ELSE 0 END
				+
				CASE WHEN gtin LIKE %s THEN 0.7 ELSE 0 END
				+
				CASE WHEN content LIKE %s THEN 0.6 ELSE 0 END
			) AS relevance
		FROM {$table_name}
		WHERE source_type = 'product' AND lang = %s
		AND MATCH(title, content, excerpt, sku, gtin) AGAINST(%s IN BOOLEAN MODE)
		ORDER BY relevance DESC
		LIMIT %d",
		$fulltext_keyword,
		'%' . $wpdb->esc_like( $keyword ) . '%',
		'%' . $wpdb->esc_like( $keyword ) . '%',
		$wpdb->esc_like( $keyword ) . '%',
		$wpdb->esc_like( $keyword ) . '%',
		'%' . $wpdb->esc_like( $keyword ) . '%',
		$lang_short,  // WICHTIG: Verwende den kurzen Sprachcode für Produkte
		$fulltext_keyword,
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

		// OPTIMIERUNG: Erhöhte Gewichtung für Keywords/Marken im Excerpt
		$query = "SELECT id, source_id, title, excerpt, url, image,
					(
						MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE)
						+
						CASE 
							WHEN LOWER(excerpt) LIKE %s THEN 2.0
							WHEN LOWER(title) LIKE %s THEN 1.5
							WHEN LOWER(content) LIKE %s THEN 1.0
							ELSE 0
						END
					) AS relevance
				  FROM {$table_name}
				  WHERE source_type = 'wordpress' AND lang = %s
				  AND MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE)
				  {$exclude_sql}
				  ORDER BY relevance DESC
				  LIMIT %d";
		
		$params = array_merge(
			array( 
				$fulltext_keyword, 
				'%' . strtolower( $wpdb->esc_like( $keyword ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $keyword ) ) . '%',
				'%' . strtolower( $wpdb->esc_like( $keyword ) ) . '%',
				$wp_lang_code, 
				$fulltext_keyword 
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