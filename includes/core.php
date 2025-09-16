<?php
/**
 * Kernfunktionen des Plugins: Initialisierung, Menüs, Skripte, Einstellungen.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Definiert die Standardwerte für die Plugin-Optionen.
 *
 * @return array Ein assoziatives Array mit den Standardoptionen.
 */
function asmi_defaults() {
    return array(
        'feed_urls'              => '',
        'feed_urls_en'           => '',
        'preset'                 => 'gmc',
        'map_id'                 => 'g:id',
        'map_name'               => 'title',
        'map_desc'               => 'description',
        'map_sku'                => 'g:mpn',
        'map_gtin'               => 'g:gtin',
        'map_price'              => 'g:price',
        'map_image'              => 'g:image_link',
        'map_url'                => 'link',
        'map_updated'            => '',
        'cache_ttl'              => 900,
        'max_results'            => 20,
        'wp_post_types'          => 'post,page',
        'weight_wp'              => 1.0,
        'weight_sw'              => 1.2,
        'expose_rest'            => 1,
        'enable_shortcode'       => 1,
        'utm_parameters'         => 'utm_source=akkusys_de_search&utm_medium=Suche',
        'product_search_url'     => '',
        'enable_daily_reindex'   => 0,
        'index_batch'            => 200,
        'exclude_no_desc'        => 1,
        'high_speed_indexing'    => 1,
        'debug_mode'             => 0,
        'fallback_image_product' => '',
        'fallback_image_wp'      => '',
        'image_storage_mode'     => 'local',
        'active_tab'             => '#tab-general',
        'excluded_ids'           => '',
        'deepl_api_key'          => '',
    );
}

/**
 * Ruft die Plugin-Optionen ab und füllt sie mit Standardwerten auf.
 *
 * @return array Die vollständigen Plugin-Optionen.
 */
function asmi_get_opts() {
    return wp_parse_args( get_option( ASMI_OPT, array() ), asmi_defaults() );
}

/**
 * Schreibt eine Nachricht in das WordPress-Debug-Log, wenn der Debug-Modus aktiv ist.
 *
 * @param string $msg Die zu loggende Nachricht.
 * @return void
 */
function asmi_debug_log( $msg ) {
    $o = asmi_get_opts();
    if ( ! empty( $o['debug_mode'] ) ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( '[ASMI DEBUG] ' . $msg );
    }
}

/**
 * Registriert die Admin-Menüseite für das Plugin.
 *
 * @return void
 */
function asmi_register_admin_menu() {
    add_menu_page(
        __( 'Multiindex Search', 'asmi-search' ),
        __( 'Multiindex', 'asmi-search' ),
        'manage_options',
        ASMI_SLUG,
        'asmi_render_admin',
        'dashicons-search',
        66
    );
}
add_action( 'admin_menu', 'asmi_register_admin_menu' );

/**
 * Lädt die CSS- und JavaScript-Dateien für die Admin-Seite.
 *
 * @param string $hook Der Hook-Suffix der aktuellen Seite.
 * @return void
 */
function asmi_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, ASMI_SLUG ) === false ) {
        return;
    }

    wp_enqueue_media();

    if ( ! wp_style_is( 'asmi-admin', 'enqueued' ) ) {
        wp_enqueue_style( 'asmi-admin', ASMI_ASSETS . 'admin.css', array(), ASMI_VERSION );
    }
    if ( ! wp_script_is( 'asmi-admin', 'enqueued' ) ) {
        wp_enqueue_script( 'asmi-admin', ASMI_ASSETS . 'admin.js', array( 'jquery', 'media-upload', 'thickbox' ), ASMI_VERSION, true );
    }
}
add_action( 'admin_enqueue_scripts', 'asmi_enqueue_admin_assets' );

/**
 * Registriert die Plugin-Einstellungen und deren Sanitize-Callback.
 *
 * @return void
 */
function asmi_register_settings() {
    $option_group = defined( 'ASMI_SETTINGS_GROUP' ) ? ASMI_SETTINGS_GROUP : 'asmi_settings';

    register_setting(
        $option_group,
        ASMI_OPT,
        array(
            'type'              => 'array',
            'sanitize_callback' => 'asmi_sanitize_options',
            'default'           => asmi_defaults(),
        )
    );
}
add_action( 'admin_init', 'asmi_register_settings' );

/**
 * Sanitize-Callback für die Plugin-Optionen.
 *
 * @param array $in Das rohe Array der übergebenen Optionen.
 * @return array Das bereinigte Array der Optionen.
 */
function asmi_sanitize_options( $in ) {
    $defaults = asmi_defaults();
    $out      = array();

    $out['feed_urls']    = sanitize_text_field( $in['feed_urls'] ?? '' );
    $out['feed_urls_en'] = sanitize_text_field( $in['feed_urls_en'] ?? '' );
    $out['preset']       = in_array( ( $in['preset'] ?? 'auto' ), array( 'auto', 'sw6', 'sw5', 'gmc', 'csv', 'custom' ), true ) ? $in['preset'] : 'auto';

    foreach ( array( 'map_id', 'map_name', 'map_desc', 'map_sku', 'map_gtin', 'map_price', 'map_image', 'map_url', 'map_updated' ) as $k ) {
        $out[ $k ] = sanitize_text_field( $in[ $k ] ?? $defaults[ $k ] );
    }

    if ( isset( $in['excluded_ids'] ) ) {
        $sanitized_ids = preg_replace( '/[^0-9,]/', '', $in['excluded_ids'] );
        $out['excluded_ids'] = trim( preg_replace( '/,+/', ',', $sanitized_ids ), ',' );
    } else {
        $out['excluded_ids'] = '';
    }

    $out['wp_post_types']          = sanitize_text_field( $in['wp_post_types'] ?? $defaults['wp_post_types'] );
    $out['utm_parameters']         = sanitize_text_field( $in['utm_parameters'] ?? $defaults['utm_parameters'] );
    $out['product_search_url']     = esc_url_raw( $in['product_search_url'] ?? '' );
    $out['fallback_image_product'] = esc_url_raw( $in['fallback_image_product'] ?? '' );
    $out['fallback_image_wp']      = esc_url_raw( $in['fallback_image_wp'] ?? '' );
    $out['image_storage_mode']     = in_array( ( $in['image_storage_mode'] ?? 'local' ), array( 'local', 'stream' ), true ) ? $in['image_storage_mode'] : 'local';
    $out['deepl_api_key']          = sanitize_text_field( $in['deepl_api_key'] ?? '' );

    $out['enable_daily_reindex'] = ! empty( $in['enable_daily_reindex'] ) ? 1 : 0;
    $cron_hook                   = 'asmi_cron_reindex';
    if ( $out['enable_daily_reindex'] && ! wp_next_scheduled( $cron_hook ) ) {
        $time = strtotime( '1:00:00' );
        if ( $time < time() ) {
            $time = strtotime( 'tomorrow 1:00:00' );
        }
        wp_schedule_event( $time, 'daily', $cron_hook );
    } elseif ( ! $out['enable_daily_reindex'] && wp_next_scheduled( $cron_hook ) ) {
        wp_clear_scheduled_hook( $cron_hook );
    }

    $out['cache_ttl']             = max( 60, absint( $in['cache_ttl'] ?? $defaults['cache_ttl'] ) );
    $out['max_results']           = max( 1, absint( $in['max_results'] ?? $defaults['max_results'] ) );
    $out['weight_wp']             = floatval( $in['weight_wp'] ?? $defaults['weight_wp'] );
    $out['weight_sw']             = floatval( $in['weight_sw'] ?? $defaults['weight_sw'] );
    $out['expose_rest']           = ! empty( $in['expose_rest'] ) ? 1 : 0;
    $out['enable_shortcode']      = ! empty( $in['enable_shortcode'] ) ? 1 : 0;
    $out['index_batch']           = max( 20, min( 2000, absint( $in['index_batch'] ?? $defaults['index_batch'] ) ) );
    $out['exclude_no_desc']       = ! empty( $in['exclude_no_desc'] ) ? 1 : 0;
    $out['high_speed_indexing']   = ! empty( $in['high_speed_indexing'] ) ? 1 : 0;
    $out['debug_mode']            = ! empty( $in['debug_mode'] ) ? 1 : 0;
    $out['active_tab']            = sanitize_text_field( $in['active_tab'] ?? '#tab-general' );
    
    return $out;
}
add_action( 'asmi_cron_reindex', 'asmi_index_reset_and_start' );

/**
 * Übersetzt einen Text mit der DeepL API.
 *
 * @param string $text Der zu übersetzende Text.
 * @param string $target_lang Der Ziel-Sprachcode (z.B. 'EN-GB').
 * @param string $source_lang Der Quell-Sprachcode (z.B. 'DE').
 * @return string|WP_Error Der übersetzte Text oder ein WP_Error-Objekt bei einem Fehler.
 */
function asmi_translate_with_deepl( $text, $target_lang = 'EN-GB', $source_lang = 'DE' ) {
    $o       = asmi_get_opts();
    $api_key = $o['deepl_api_key'] ?? '';

    if ( empty( $api_key ) ) {
        return new WP_Error( 'no_api_key', __( 'DeepL API Key ist nicht konfiguriert.', 'asmi-search' ) );
    }

    // Prüfen, ob die kostenlose oder Pro-API verwendet wird
    $api_url = str_ends_with( $api_key, ':fx' )
        ? 'https://api-free.deepl.com/v2/translate'
        : 'https://api.deepl.com/v2/translate';

    $body = array(
        'auth_key'     => $api_key,
        'text'         => $text,
        'source_lang'  => $source_lang,
        'target_lang'  => $target_lang,
        'tag_handling' => 'xml',
    );

    $response = wp_remote_post(
        $api_url,
        array(
            'method'  => 'POST',
            'timeout' => 45,
            'body'    => $body,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 200 !== $response_code ) {
        $error_message = $response_body['message'] ?? __( 'Unbekannter DeepL API Fehler.', 'asmi-search' );
        return new WP_Error( 'deepl_api_error', 'DeepL Fehler: ' . $error_message );
    }

    return $response_body['translations'][0]['text'] ?? '';
}