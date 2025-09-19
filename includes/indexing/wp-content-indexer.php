<?php
/**
 * Zuständig für die Indexierung von WordPress-Inhalten mit ChatGPT.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexiert einen einzelnen WordPress-Beitrag mit ChatGPT.
 *
 * @param int   $post_id Die ID des zu indexierenden Beitrags.
 * @param array $languages Ein Array der zu indexierenden Sprach-Locales.
 * @return array Statistiken über verwendete APIs.
 */
function asmi_index_single_wp_post( $post_id, $languages = array() ) {
	global $wpdb;
	$original_post_obj = get_post( $post_id );
	if ( ! $original_post_obj || wp_is_post_revision( $post_id ) ) {
		return array( 'chatgpt_used' => 0, 'fallback_used' => 0, 'manually_imported' => 0 );
	}

	if ( empty( $languages ) ) {
		$languages = array( 'de_DE', 'en_GB' );
	}
	
	$o = asmi_get_opts();
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;

	// Originalinhalte holen
	$original_title = get_the_title( $post_id );
	$original_content_raw = $original_post_obj->post_content;
	$rendered_content = apply_filters( 'the_content', $original_content_raw );
	
	// Basis-Daten
	$content_hash_base = md5( $original_title . '|' . $rendered_content );
	$original_url = get_permalink( $post_id );
	$thumbnail_url = get_the_post_thumbnail_url( $post_id, 'medium' );
	$post_modified = $original_post_obj->post_modified;
	
	$stats = array( 'chatgpt_used' => 0, 'fallback_used' => 0, 'manually_imported' => 0 );
	
	// Indexierung für alle Zielsprachen
	foreach ( $languages as $lang_locale ) {
		$lang_slug_short = substr( $lang_locale, 0, 2 );
		
		// Prüfe ob bereits ein manuell importierter Eintrag existiert
		$existing_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT content_hash, CASE WHEN content_hash LIKE '%manual_import%' THEN 1 ELSE 0 END as is_manual
				FROM $table_name 
				WHERE source_id = %s AND lang = %s AND source_type = 'wordpress'",
				$post_id, $lang_locale
			),
			ARRAY_A
		);
		
		// Schütze manuell importierte Einträge
		if ( $existing_entry && $existing_entry['is_manual'] ) {
			$stats['manually_imported']++;
			asmi_debug_log( sprintf( 'Post %d (%s): Protected manual import', $post_id, $lang_locale ) );
			continue;
		}
		
		// Prüfe ob ChatGPT verfügbar ist
		$use_chatgpt = ! empty( $o['use_chatgpt'] ) && ! empty( $o['chatgpt_api_key'] );
		
		// Hauptverarbeitung mit ChatGPT
		if ( $use_chatgpt && function_exists( 'asmi_process_with_chatgpt_cached' ) ) {
			$chatgpt_lang = ( $lang_slug_short === 'en' ) ? 'en' : 'de';
			$chatgpt_result = asmi_process_with_chatgpt_cached( 
				$original_title, 
				$rendered_content, 
				$chatgpt_lang,
				$content_hash_base
			);
			
			if ( ! is_wp_error( $chatgpt_result ) ) {
				// ChatGPT erfolgreich - Verwende die Ergebnisse
				
				// Titel: Bei Deutsch original, bei Englisch übersetzt
				$final_title = ! empty( $chatgpt_result['title'] ) ? 
					$chatgpt_result['title'] : $original_title;
				
				// Content: Verwende die Zusammenfassung
				$final_content = '';
				
				// Priorität: content > summary
				if ( ! empty( $chatgpt_result['content'] ) ) {
					$final_content = $chatgpt_result['content'];
				} elseif ( ! empty( $chatgpt_result['summary'] ) ) {
					$final_content = $chatgpt_result['summary'];
				}
				
				// Fallback falls beide leer
				if ( empty( $final_content ) ) {
					$final_content = wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				}
				
				// Excerpt: ALLE Marken und Keywords kombinieren
				$keyword_parts = array();
				
				// Marken haben höchste Priorität
				if ( ! empty( $chatgpt_result['brands'] ) && is_array( $chatgpt_result['brands'] ) ) {
					// Dedupliziere und sortiere Marken
					$brands = array_unique( $chatgpt_result['brands'] );
					sort( $brands );
					$keyword_parts = array_merge( $keyword_parts, $brands );
					
					asmi_debug_log( sprintf( 
						'Post %d (%s): Found brands: %s', 
						$post_id, 
						$lang_locale,
						implode( ', ', $brands )
					) );
				}
				
				// Dann Keywords hinzufügen
				if ( ! empty( $chatgpt_result['keywords'] ) && is_array( $chatgpt_result['keywords'] ) ) {
					// Dedupliziere Keywords und entferne bereits vorhandene Marken
					$keywords_clean = array_diff( 
						$chatgpt_result['keywords'], 
						$keyword_parts 
					);
					$keyword_parts = array_merge( $keyword_parts, $keywords_clean );
				}
				
				// Technische Specs hinzufügen wenn vorhanden
				if ( ! empty( $chatgpt_result['specs'] ) && is_array( $chatgpt_result['specs'] ) ) {
					foreach ( $chatgpt_result['specs'] as $spec_key => $spec_value ) {
						if ( ! empty( $spec_value ) && is_scalar( $spec_value ) ) {
							$keyword_parts[] = $spec_key . ':' . $spec_value;
						}
					}
				}
				
				// Deduplizieren und zu String konvertieren
				$keyword_parts = array_unique( $keyword_parts );
				
				// Maximal 50 Keywords/Marken im Excerpt speichern (mehr als vorher)
				$final_excerpt = implode( ', ', array_slice( $keyword_parts, 0, 50 ) );
				
				// Wenn kein Excerpt, verwende ersten Teil des Contents
				if ( empty( $final_excerpt ) ) {
					$final_excerpt = wp_trim_words( $final_content, 20 );
				}
				
				// URL-Generierung basierend auf Sprache
				$final_url = ( 'en' === $lang_slug_short ) ? 
					home_url( '/en' . wp_make_link_relative( $original_url ) ) : 
					$original_url;
				
				// Hash für Änderungserkennung
				$content_hash = hash( 'sha256', $content_hash_base . '|chatgpt_v3|' . $lang_locale );
				$stats['chatgpt_used']++;
				
				asmi_debug_log( sprintf( 
					'Post %d (%s): ChatGPT processed - Title: %s, Content length: %d, Keywords: %d', 
					$post_id, 
					$lang_locale,
					substr( $final_title, 0, 50 ),
					strlen( $final_content ),
					count( $keyword_parts )
				) );
				
			} else {
				// ChatGPT fehlgeschlagen - Verwende minimalen Fallback
				asmi_debug_log( sprintf( 
					'Post %d (%s): ChatGPT failed - %s. Using minimal fallback.', 
					$post_id, $lang_locale, $chatgpt_result->get_error_message()
				) );
				
				// Minimaler Fallback - nur bereinigter Text
				$final_title = $original_title;
				$final_content = wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = wp_trim_words( wp_strip_all_tags( $rendered_content ), 10 );
				$final_url = ( 'en' === $lang_slug_short ) ? 
					home_url( '/en' . wp_make_link_relative( $original_url ) ) : 
					$original_url;
				
				$content_hash = hash( 'sha256', $content_hash_base . '|minimal_v1|' . $lang_locale );
				$stats['fallback_used']++;
			}
		} else {
			// Kein ChatGPT konfiguriert - Lade erweiterten Fallback wenn nötig
			if ( ! function_exists( 'asmi_extract_search_keywords' ) ) {
				$fallback_file = plugin_dir_path( __FILE__ ) . 'keyword-fallback.php';
				if ( file_exists( $fallback_file ) ) {
					require_once $fallback_file;
				}
			}
			
			// Verwende erweiterten Fallback wenn verfügbar
			if ( function_exists( 'asmi_extract_search_keywords' ) ) {
				$extracted = asmi_extract_search_keywords( $original_title, $rendered_content, $lang_locale );
				$final_title = $extracted['title'] ?? $original_title;
				$final_content = $extracted['content'] ?? wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = $extracted['excerpt'] ?? '';
				
				asmi_debug_log( sprintf( 
					'Post %d (%s): Using keyword extraction fallback', 
					$post_id, 
					$lang_locale
				) );
			} else {
				// Absoluter Minimal-Fallback
				$final_title = $original_title;
				$final_content = wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = wp_trim_words( wp_strip_all_tags( $rendered_content ), 10 );
				
				asmi_debug_log( sprintf( 
					'Post %d (%s): Using minimal fallback', 
					$post_id, 
					$lang_locale
				) );
			}
			
			$final_url = ( 'en' === $lang_slug_short ) ? 
				home_url( '/en' . wp_make_link_relative( $original_url ) ) : 
				$original_url;
			
			$content_hash = hash( 'sha256', $content_hash_base . '|fallback_v1|' . $lang_locale );
			$stats['fallback_used']++;
		}

		// Speichere in Datenbank
		$result = $wpdb->replace(
			$table_name,
			array(
				'source_id'     => $post_id,
				'lang'          => $lang_locale,
				'source_type'   => 'wordpress',
				'title'         => $final_title,
				'content'       => $final_content,
				'excerpt'       => $final_excerpt,
				'url'           => $final_url,
				'image'         => $thumbnail_url,
				'content_hash'  => $content_hash,
				'last_modified' => $post_modified,
				'indexed_at'    => current_time( 'mysql' ),
			),
			array(
				'%s', // source_id
				'%s', // lang
				'%s', // source_type
				'%s', // title
				'%s', // content
				'%s', // excerpt
				'%s', // url
				'%s', // image
				'%s', // content_hash
				'%s', // last_modified
				'%s', // indexed_at
			)
		);
		
		if ( $result === false ) {
			asmi_debug_log( sprintf( 
				'Post %d (%s): Database error - %s', 
				$post_id, 
				$lang_locale,
				$wpdb->last_error
			) );
		}
	}
	
	return $stats;
}

/**
 * Indexiert alle relevanten WordPress-Inhalte.
 */
function asmi_index_all_wp_content() {
	global $wpdb;
	$o          = asmi_get_opts();
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	$post_types = ! empty( $o['wp_post_types'] ) ? 
		array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : 
		array( 'post', 'page' );

	if ( empty( $post_types ) ) {
		return;
	}
	
	$use_chatgpt = ! empty( $o['use_chatgpt'] ) && ! empty( $o['chatgpt_api_key'] );
	asmi_debug_log( 'WP Content Indexing: Starting with ' . ( $use_chatgpt ? 'ChatGPT' : 'Fallback' ) );

	// Hole alle Posts
	$args = array(
		'post_type'      => $post_types,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);
	
	$query    = new WP_Query( $args );
	$post_ids = $query->posts;

	if ( empty( $post_ids ) ) {
		// Lösche verwaiste Einträge
		$wpdb->query( 
			"DELETE FROM $table_name 
			WHERE source_type = 'wordpress' 
			AND content_hash NOT LIKE '%manual_import%'"
		);
		asmi_debug_log( 'No posts found to index' );
		return;
	}

	// Filtere ausgeschlossene IDs
	if ( ! empty( $o['excluded_ids'] ) ) {
		$excluded_ids = array_map( 'intval', explode( ',', $o['excluded_ids'] ) );
		$post_ids     = array_diff( $post_ids, $excluded_ids );
		asmi_debug_log( sprintf( 'Excluding %d posts from indexing', count( $excluded_ids ) ) );
	}

	// Lösche nicht mehr existierende Posts (außer manuell importierte)
	$existing_ids_placeholder = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
	$deleted = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $table_name 
			WHERE source_type = 'wordpress' 
			AND source_id NOT IN ($existing_ids_placeholder)
			AND content_hash NOT LIKE '%manual_import%'",
			$post_ids
		)
	);
	
	if ( $deleted > 0 ) {
		asmi_debug_log( sprintf( 'Deleted %d obsolete entries', $deleted ) );
	}

	// Verarbeite Posts
	$languages = array( 'de_DE', 'en_GB' );
	$total_posts = count( $post_ids );
	$processed = 0;
	$chatgpt_used = 0;
	$fallback_used = 0;
	$manually_imported = 0;
	$errors = 0;

	asmi_debug_log( sprintf( 'Starting to index %d posts', $total_posts ) );

	foreach ( $post_ids as $post_id ) {
		$result = asmi_index_single_wp_post( $post_id, $languages );
		$processed++;
		
		$chatgpt_used += $result['chatgpt_used'] ?? 0;
		$fallback_used += $result['fallback_used'] ?? 0;
		$manually_imported += $result['manually_imported'] ?? 0;
		
		// Status-Log alle 25 Posts oder am Ende
		if ( $processed % 25 === 0 || $processed === $total_posts ) {
			asmi_debug_log( sprintf( 
				'Progress: %d/%d posts | ChatGPT: %d | Fallback: %d | Protected: %d',
				$processed, $total_posts, $chatgpt_used, $fallback_used, $manually_imported
			));
		}
		
		// Rate limiting für ChatGPT (weniger aggressiv)
		if ( $use_chatgpt && $processed % 5 === 0 ) {
			sleep( 1 ); // 1 Sekunde Pause alle 5 Posts
		}
		
		// Speicher freigeben bei großen Batches
		if ( $processed % 100 === 0 ) {
			wp_cache_flush();
		}
	}
	
	asmi_debug_log( sprintf( 
		'Indexing Complete: %d posts processed | ChatGPT: %d | Fallback: %d | Protected: %d',
		$processed, $chatgpt_used, $fallback_used, $manually_imported
	));
	
	// Optimiere Tabelle nach großem Update
	if ( $processed > 100 ) {
		$wpdb->query( "OPTIMIZE TABLE $table_name" );
		asmi_debug_log( 'Database table optimized' );
	}
}
add_action( 'asmi_cron_wp_content_index', 'asmi_index_all_wp_content' );

/**
 * Hook für Post-Speicherung - Aktualisiert den Index bei Änderungen.
 *
 * @param int     $post_id Die Post-ID.
 * @param WP_Post $post    Das Post-Objekt.
 */
function asmi_hook_save_post( $post_id, $post ) {
	// Skip auto-saves und Revisionen
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	
	$o = asmi_get_opts();
	$post_types = ! empty( $o['wp_post_types'] ) ? 
		array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : 
		array();
		
	if ( in_array( $post->post_type, $post_types, true ) && 'publish' === $post->post_status ) {
		asmi_debug_log( sprintf( 'Updating index for post %d after save', $post_id ) );
		asmi_index_single_wp_post( $post_id );
	}
}
add_action( 'save_post', 'asmi_hook_save_post', 10, 2 );

/**
 * Hook für Post-Löschung - Entfernt den Post aus dem Index.
 *
 * @param int $post_id Die Post-ID.
 */
function asmi_hook_delete_post( $post_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	
	$deleted = $wpdb->delete(
		$table_name,
		array(
			'source_id'   => $post_id,
			'source_type' => 'wordpress'
		),
		array( '%s', '%s' )
	);
	
	if ( $deleted > 0 ) {
		asmi_debug_log( sprintf( 'Deleted %d index entries for post %d', $deleted, $post_id ) );
	}
}
add_action( 'delete_post', 'asmi_hook_delete_post' );

/**
 * Reset der Cache-Einträge - Erzwingt Neuindexierung.
 */
function asmi_reset_translation_cache() {
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	
	$updated = $wpdb->query( 
		"UPDATE $table_name 
		SET content_hash = NULL 
		WHERE source_type = 'wordpress' 
		AND content_hash NOT LIKE '%manual_import%'"
	);
	
	asmi_debug_log( sprintf( 'Cache reset - %d entries marked for re-indexing, manual imports preserved', $updated ) );
	
	// Lösche auch ChatGPT Cache
	if ( function_exists( 'asmi_cleanup_chatgpt_cache' ) ) {
		asmi_cleanup_chatgpt_cache();
		asmi_debug_log( 'ChatGPT cache cleared' );
	}
}