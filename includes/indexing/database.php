<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/images.php';

/**
 * Schreibt einen Stapel von Feed-Einträgen effizient in die neue, benutzerdefinierte Datenbanktabelle.
 * Verwendet einen einzigen, vorbereiteten SQL-Befehl für maximale Geschwindigkeit.
 */
function asmi_index_upsert_slice($feed_url, $lang, $slice){
    global $wpdb;
    $stats = ['processed' => 0, 'skipped_no_desc' => 0, 'image_errors' => 0];
    $o = asmi_get_opts();
    $table_name = $wpdb->prefix . ASMI_INDEX_TABLE;

    // Mapping-Felder aus den Optionen holen
    $map_id_key    = sanitize_key(str_replace(':', '_', $o['map_id']));
    $map_name_key  = sanitize_key(str_replace(':', '_', $o['map_name']));
    $map_desc_key  = sanitize_key(str_replace(':', '_', $o['map_desc']));
    $map_sku_key   = sanitize_key(str_replace(':', '_', $o['map_sku']));
    $map_gtin_key  = sanitize_key(str_replace(':', '_', $o['map_gtin']));
    $map_price_key = sanitize_key(str_replace(':', '_', $o['map_price']));
    $map_image_key = sanitize_key(str_replace(':', '_', $o['map_image']));
    $map_url_key   = sanitize_key(str_replace(':', '_', $o['map_url']));

    $query = "INSERT INTO $table_name (source_id, lang, source_type, title, content, excerpt, url, image, price, sku, gtin, raw_data, indexed_at) VALUES ";
    $placeholders = [];
    $values = [];

    foreach ($slice as $p) {
        $item_id = $p[$map_id_key] ?? '';
        $title = $p[$map_name_key] ?? '';
        $description = $p[$map_desc_key] ?? '';

        if (empty($title) || empty($item_id)) continue;

        if (!empty($o['exclude_no_desc']) && empty(trim(strip_tags($description)))) {
            $stats['skipped_no_desc']++;
            continue;
        }
        
        $remote_image_url = $p[$map_image_key] ?? '';
        $final_image_url = $remote_image_url;
        if ($o['image_storage_mode'] === 'local' && !empty($remote_image_url)) {
            $download_result = asmi_download_image_to_local_dir($remote_image_url);
            if (!is_wp_error($download_result)) {
                $final_image_url = $download_result;
            } else {
                $stats['image_errors']++;
                $final_image_url = '';
            }
        }

        array_push(
            $values,
            $item_id,
            $lang,
            'product',
            $title,
            $description,
            wp_trim_words(strip_tags($description), 30, '...'),
            $p[$map_url_key] ?? '',
            $final_image_url,
            $p[$map_price_key] ?? '',
            $p[$map_sku_key] ?? '',
            $p[$map_gtin_key] ?? '',
            wp_json_encode($p),
            current_time('mysql')
        );
        $placeholders[] = "(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)";
    }

    if (empty($placeholders)) {
        return $stats;
    }
    
    $update_clause = "
        ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        content = VALUES(content),
        excerpt = VALUES(excerpt),
        url = VALUES(url),
        image = VALUES(image),
        price = VALUES(price),
        sku = VALUES(sku),
        gtin = VALUES(gtin),
        raw_data = VALUES(raw_data),
        indexed_at = VALUES(indexed_at)
    ";

    $full_query = $query . implode(', ', $placeholders) . $update_clause;
    $result = $wpdb->query($wpdb->prepare($full_query, $values));

    if ($result !== false) {
        $stats['processed'] = count($placeholders);
    }
    
    return $stats;
}