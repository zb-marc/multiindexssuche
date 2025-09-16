<?php
/**
 * Zuständig für die Indexierung von WordPress-Inhalten.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexiert alle relevanten WordPress-Inhalte.
 */
function asmi_index_all_wp_content() {
	global $wpdb;
	$o          = asmi_get_opts();
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	$post_types = ! empty( $o['wp_post_types'] ) ? array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : array( 'post', 'page' );

	if ( empty( $post_types ) ) {
		return;
	}

	$wpdb->delete( $table_name, array( 'source_type' => 'wordpress' ) );

	$args = array(
		'post_type'      => $post_types,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);
	$query    = new WP_Query( $args );
	$post_ids = $query->posts;

	if ( empty( $post_ids ) ) {
		return;
	}

    if ( ! empty( $o['excluded_ids'] ) ) {
        $excluded_ids = array_map( 'intval', explode( ',', $o['excluded_ids'] ) );
        $post_ids     = array_diff( $post_ids, $excluded_ids );
    }

	// Die zu indexierenden Sprachen sind jetzt fest definiert.
	$languages = array( 'de_DE', 'en_GB' );

	foreach ( $post_ids as $post_id ) {
		asmi_index_single_wp_post( $post_id, $languages );
	}
}
add_action( 'asmi_cron_wp_content_index', 'asmi_index_all_wp_content' );


/**
 * Hook, um einen einzelnen Beitrag beim Speichern zu indexieren.
 */
function asmi_hook_save_post( $post_id, $post ) {
	$o          = asmi_get_opts();
	$post_types = ! empty( $o['wp_post_types'] ) ? array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : array();
	if ( in_array( $post->post_type, $post_types, true ) && 'publish' === $post->post_status ) {
		asmi_index_single_wp_post( $post_id );
	}
}
add_action( 'save_post', 'asmi_hook_save_post', 10, 2 );


/**
 * Indexiert einen einzelnen WordPress-Beitrag, indem es die Inhalte
 * für die Zielsprachen mit der DeepL API übersetzt.
 *
 * @param int   $post_id Die ID des zu indexierenden Beitrags.
 * @param array $languages Ein Array der zu indexierenden Sprach-Locales.
 */
function asmi_index_single_wp_post( $post_id, $languages = array() ) {
	global $wpdb;
	$original_post_obj = get_post( $post_id );
	if ( ! $original_post_obj || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( empty( $languages ) ) {
		$languages = array( 'de_DE', 'en_GB' );
	}
    
    $o = asmi_get_opts();
    $api_key = $o['deepl_api_key'] ?? '';

	$table_name        = $wpdb->prefix . ASMI_INDEX_TABLE;
	$default_language  = 'de_DE'; 

	// Originalinhalte (Deutsch) holen
	$original_title        = get_the_title( $post_id );
	$original_content_raw  = $original_post_obj->post_content;
	$rendered_content      = apply_filters('the_content', $original_content_raw);
	$original_content_text = wp_strip_all_tags( $rendered_content );
	$original_excerpt      = wp_trim_words( $original_content_text, 55, '...' );
	$original_url          = get_permalink( $post_id );
	$thumbnail_url         = get_the_post_thumbnail_url( $post_id, 'medium' );
	
	// Indexierung für alle Zielsprachen
	foreach ( $languages as $lang_locale ) {
		
		$final_title   = $original_title;
		$final_content = $original_content_text;
		$final_excerpt = $original_excerpt;
		$final_url     = $original_url;
		$lang_slug_short = substr($lang_locale, 0, 2); // z.B. 'en' aus 'en_GB'
		
		if ( $lang_locale !== $default_language && !empty($api_key) ) {
			
			// Titel übersetzen
			$translated_title = asmi_translate_with_deepl($original_title, strtoupper( $lang_slug_short ) );
			if (!is_wp_error($translated_title) && !empty($translated_title)) {
				$final_title = $translated_title;
			} else {
				asmi_debug_log('DeepL Übersetzungsfehler für Titel (Post ID ' . $post_id . '): ' . (is_wp_error($translated_title) ? $translated_title->get_error_message() : 'Leere Antwort'));
			}
			
			// Inhalt übersetzen
			$translated_content = asmi_translate_with_deepl($original_content_text, strtoupper( $lang_slug_short ) );
			if (!is_wp_error($translated_content) && !empty($translated_content)) {
				$final_content = $translated_content;
				$final_excerpt = wp_trim_words($final_content, 55, '...');
			} else {
				asmi_debug_log('DeepL Übersetzungsfehler für Inhalt (Post ID ' . $post_id . '): ' . (is_wp_error($translated_content) ? $translated_content->get_error_message() : 'Leere Antwort'));
			}
            
            // URL anpassen, falls ein /en/ Slug existiert
            if ('en' === $lang_slug_short) {
                $final_url = home_url('/en' . wp_make_link_relative($original_url));
            }
		}

		// Daten in die Datenbank schreiben
		$wpdb->replace(
			$table_name,
			array(
				'source_id'   => $post_id,
				'lang'        => $lang_locale,
				'source_type' => 'wordpress',
				'title'       => $final_title,
				'content'     => $final_content,
				'excerpt'     => $final_excerpt,
				'url'         => $final_url,
				'image'       => $thumbnail_url,
				'indexed_at'  => current_time( 'mysql' ),
			)
		);
	}
}