<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Führt eine direkte, schnelle Suche auf der benutzerdefinierten Index-Tabelle aus.
 *
 * @param string $q Der Suchbegriff.
 * @param int    $limit Die maximale Anzahl der Ergebnisse pro Gruppe.
 * @param string $lang Das Sprachkürzel (z.B. 'de' oder 'en').
 * @return array Ein Array mit zwei Schlüsseln: 'products' und 'wordpress'.
 */
function asmi_unified_search($q, $limit, $lang = 'de'){
    global $wpdb;
    $o = asmi_get_opts();
    $table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
    
    // Normalisiere den Sprachcode auf die ersten 2 Buchstaben (z.B. 'de' oder 'en').
    $lang_code = substr($lang, 0, 2);

    $keyword = trim($q);
    if (empty($keyword)) {
        return ['products' => [], 'wordpress' => []];
    }
    
    $fulltext_keyword = $keyword . '*';

    // --- Suche 2: Produkte ---
    $product_results = [];
    
    $exact_match_query = $wpdb->prepare(
        "SELECT id, source_id, title, excerpt, url, image, price, sku, gtin
         FROM {$table_name}
         WHERE source_type = 'product' AND lang = %s AND (source_id = %s OR sku = %s OR gtin = %s)
         LIMIT 1",
        $lang_code, $keyword, $keyword, $keyword
    );
    $exact_match = $wpdb->get_row($exact_match_query, ARRAY_A);

    if ($exact_match) {
        $product_results[] = [
            'source'  => 'product',
            'id'      => $exact_match['id'],
            'title'   => $exact_match['title'],
            'url'     => $exact_match['url'],
            'excerpt' => $exact_match['excerpt'],
            'image'   => !empty($exact_match['image']) ? $exact_match['image'] : $o['fallback_image_product'],
            'price'   => $exact_match['price'],
            'score'   => 1000,
            'date'    => time(),
            'sku'     => $exact_match['sku'],
            'gtin'    => $exact_match['gtin'],
        ];
        return [
            'wordpress' => [],
            'products' => $product_results
        ];
    }
    
    $product_query = $wpdb->prepare(
        "SELECT id, title, excerpt, url, image, price, sku, gtin,
            (
                MATCH(title, content, excerpt, sku, gtin) AGAINST(%s IN BOOLEAN MODE)
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
        '%' . $wpdb->esc_like($keyword) . '%',
        $wpdb->esc_like($keyword) . '%',
        $wpdb->esc_like($keyword) . '%',
        '%' . $wpdb->esc_like($keyword) . '%',
        $lang_code,
        $fulltext_keyword,
        $limit
    );
    $product_results_raw = $wpdb->get_results($product_query, ARRAY_A);

    foreach($product_results_raw as $p) {
        $product_results[] = [
            'source'  => 'product',
            'id'      => $p['id'],
            'title'   => $p['title'],
            'url'     => $p['url'],
            'excerpt' => $p['excerpt'],
            'image'   => !empty($p['image']) ? $p['image'] : $o['fallback_image_product'],
            'price'   => $p['price'],
            'score'   => (float)$p['relevance'] + (float)$o['weight_sw'],
            'date'    => time(),
            'sku'     => $p['sku'],
            'gtin'    => $p['gtin'],
        ];
    }

    // --- Suche 1: WordPress-Inhalte ---
    $wp_results = [];
    $wp_post_types = !empty($o['wp_post_types']) ? array_filter(array_map('trim', explode(',', $o['wp_post_types']))) : ['post', 'page'];
    
    if (!empty($wp_post_types)) {
        
        $exclude_sql = '';
        $exclude_ids = [];
        if ( ! empty( $o['excluded_ids'] ) ) {
            $exclude_ids = array_map( 'intval', explode( ',', $o['excluded_ids'] ) );
            if ( ! empty( $exclude_ids ) ) {
                $placeholders = implode( ', ', array_fill( 0, count( $exclude_ids ), '%d' ) );
                $exclude_sql  = " AND source_id NOT IN ( $placeholders )";
            }
        }

        // Bestimme den Zielsprachcode für die DB-Abfrage
        $db_lang_code = ('en' === $lang_code) ? 'en_GB' : 'de_DE';

        $query = "SELECT id, source_id, title, excerpt, url, image,
                    MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE) AS relevance
                  FROM {$table_name}
                  WHERE source_type = 'wordpress' AND lang = %s
                  AND MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE)
                  {$exclude_sql}
                  ORDER BY relevance DESC
                  LIMIT %d";
        
        $params = array_merge(
            [$fulltext_keyword, $db_lang_code, $fulltext_keyword],
            $exclude_ids,
            [$limit]
        );
        
        $wp_results_raw = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

        foreach ($wp_results_raw as $p) {
            $wp_results[] = [
                'source'  => 'wordpress',
                'id'      => $p['source_id'],
                'title'   => $p['title'],
                'url'     => $p['url'],
                'excerpt' => $p['excerpt'],
                'image'   => !empty($p['image']) ? $p['image'] : $o['fallback_image_wp'],
                'score'   => (float)$p['relevance'] + (float)$o['weight_wp'],
                'date'    => (int) get_post_time('U', true, $p['source_id']),
            ];
        }
    }
    
    return [
        'wordpress' => $wp_results,
        'products' => $product_results
    ];
}