<?php
/**
 * Zuständig für die asynchrone Indexierung von WordPress-Inhalten mit ChatGPT.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Startet die asynchrone WordPress-Inhaltsindexierung.
 *
 * @return void
 */
function asmi_start_wp_content_indexing() {
	$current_state = asmi_get_wp_index_state();
	
	// Prüfe ob bereits ein Prozess läuft
	if ( $current_state['status'] === 'indexing' ) {
		$time_since_update = time() - $current_state['updated_at'];
		if ( $current_state['updated_at'] > 0 && $time_since_update < 300 ) {
			asmi_debug_log( 'WP INDEX BLOCKED: Another process is already running' );
			return;
		}
	}
	
	$o = asmi_get_opts();
	$post_types = ! empty( $o['wp_post_types'] ) ? 
		array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : 
		array( 'post', 'page' );

	if ( empty( $post_types ) ) {
		asmi_debug_log( 'WP INDEX: No post types configured' );
		return;
	}

	// Hole alle Posts für die Queue
	$args = array(
		'post_type'      => $post_types,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);
	
	$query = new WP_Query( $args );
	$post_ids = $query->posts;

	if ( empty( $post_ids ) ) {
		asmi_debug_log( 'WP INDEX: No posts found to index' );
		return;
	}

	// Filtere ausgeschlossene IDs
	if ( ! empty( $o['excluded_ids'] ) ) {
		$excluded_ids = array_map( 'intval', explode( ',', $o['excluded_ids'] ) );
		$post_ids = array_diff( $post_ids, $excluded_ids );
		asmi_debug_log( 'WP INDEX: Excluded ' . count( $excluded_ids ) . ' posts' );
	}

	$total_posts = count( $post_ids );
	asmi_debug_log( 'WP INDEX: Starting with ' . $total_posts . ' posts' );

	// Initialisiere den State
	$state = array(
		'status'               => 'indexing',
		'total_posts'          => $total_posts,
		'processed_posts'      => 0,
		'current_post'         => 0,
		'current_post_title'   => '',
		'current_lang'         => '',
		'chatgpt_used'         => 0,
		'fallback_used'        => 0,
		'manually_imported'    => 0,
		'timeout_errors'       => 0,
		'api_errors'           => 0,
		'post_queue'           => $post_ids,
		'languages'            => array( 'de_DE', 'en_GB' ),
		'current_action'       => __( 'Preparing indexing...', 'asmi-search' ),
		'started_at'           => time(),
		'batch_size'           => 5, // Kleinere Batches für bessere Stabilität
		'error'                => '',
	);

	asmi_set_wp_index_state( $state );
	asmi_schedule_wp_index_tick();
}

/**
 * Plant den nächsten WordPress-Indexierung-Tick.
 *
 * @return void
 */
function asmi_schedule_wp_index_tick() {
	$o = asmi_get_opts();
	
	// Prüfe ob High-Speed-Indexing aktiviert ist
	if ( ! empty( $o['high_speed_indexing'] ) ) {
		// Verwende Loopback-Request für schnellere Verarbeitung
		$token = get_option( 'asmi_tick_token' );
		if ( empty( $token ) ) {
			$token = wp_generate_password( 64, false, false );
			update_option( 'asmi_tick_token', $token );
		}

		$url = rest_url( ASMI_REST_NS . '/wp-index/tick' );
		
		$args = array(
			'method'    => 'POST',
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'body'      => array( 'token' => $token ),
		);
		
		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			asmi_debug_log( 'WP INDEX SCHEDULE ERROR (Loopback failed): ' . $response->get_error_message() );
			
			// Fallback auf WP Cron bei Loopback-Fehler
			if ( ! wp_next_scheduled( ASMI_WP_INDEX_TICK_ACTION ) ) {
				wp_schedule_single_event( time() + 2, ASMI_WP_INDEX_TICK_ACTION );
				asmi_debug_log( 'WP INDEX: Fallback to WP Cron scheduled' );
			}
		} else {
			asmi_debug_log( 'WP INDEX: Next tick scheduled via loopback' );
		}
	} else {
		// Verwende Standard WP Cron
		if ( ! wp_next_scheduled( ASMI_WP_INDEX_TICK_ACTION ) ) {
			wp_schedule_single_event( time() + 2, ASMI_WP_INDEX_TICK_ACTION );
			asmi_debug_log( 'WP INDEX: Next tick scheduled via WP Cron' );
		}
	}
}

/**
 * Handler für WordPress-Indexierung-Ticks.
 *
 * @return void
 */
function asmi_wp_index_tick_handler() {
	asmi_debug_log( 'WP INDEX TICK: Handler started' );
	
	$state = asmi_get_wp_index_state();
	
	if ( $state['status'] !== 'indexing' ) {
		asmi_debug_log( 'WP INDEX TICK: Status is not indexing, stopping' );
		return;
	}

	// Timeout-Check: Wenn der letzte Update zu lange her ist, setze den Status zurück
	$time_since_update = time() - $state['updated_at'];
	if ( $state['updated_at'] > 0 && $time_since_update > 3600 ) { // 1 Stunde Timeout
		asmi_debug_log( 'WP INDEX TICK: Process timeout detected (no update for ' . $time_since_update . ' seconds)' );
		$state['status'] = 'idle';
		$state['error'] = __( 'WordPress indexing timed out after 1 hour of inactivity.', 'asmi-search' );
		$state['finished_at'] = time();
		
		$state['last_run'] = array(
			'type'               => __( 'WordPress Content Indexing', 'asmi-search' ),
			'status'             => 'timeout',
			'finished_at'        => $state['finished_at'],
			'duration'           => $state['finished_at'] - $state['started_at'],
			'processed'          => $state['processed_posts'],
			'chatgpt_used'       => $state['chatgpt_used'],
			'fallback_used'      => $state['fallback_used'],
			'manually_imported'  => $state['manually_imported'],
			'timeout_errors'     => $state['timeout_errors'],
			'api_errors'         => $state['api_errors'],
		);
		
		asmi_set_wp_index_state( $state );
		return;
	}

	if ( empty( $state['post_queue'] ) ) {
		// Indexierung abgeschlossen
		asmi_debug_log( 'WP INDEX TICK: Queue is empty, finishing' );
		asmi_finish_wp_indexing( $state );
		return;
	}

	// Verarbeite ein Batch von Posts
	$batch_size = min( $state['batch_size'], count( $state['post_queue'] ) );
	$batch_posts = array_slice( $state['post_queue'], 0, $batch_size );
	
	asmi_debug_log( 'WP INDEX TICK: Processing batch of ' . $batch_size . ' posts' );

	foreach ( $batch_posts as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || wp_is_post_revision( $post_id ) ) {
			asmi_debug_log( 'WP INDEX TICK: Skipping invalid post ' . $post_id );
			continue;
		}

		$state['current_post'] = $post_id;
		$state['current_post_title'] = get_the_title( $post_id );
		$state['current_action'] = sprintf( 
			__( 'Processing: %s (ID: %d)', 'asmi-search' ), 
			$state['current_post_title'], 
			$post_id 
		);

		asmi_debug_log( 'WP INDEX TICK: Processing post ' . $post_id . ' - ' . $state['current_post_title'] );

		// Verarbeite alle Sprachen für diesen Post
		foreach ( $state['languages'] as $lang_locale ) {
			$state['current_lang'] = $lang_locale;
			asmi_set_wp_index_state( $state );

			$result = asmi_index_single_wp_post_robust( $post_id, array( $lang_locale ) );
			
			$state['chatgpt_used'] += $result['chatgpt_used'] ?? 0;
			$state['fallback_used'] += $result['fallback_used'] ?? 0;
			$state['manually_imported'] += $result['manually_imported'] ?? 0;
			$state['timeout_errors'] += $result['timeout_errors'] ?? 0;
			$state['api_errors'] += $result['api_errors'] ?? 0;
		}

		$state['processed_posts']++;
		
		// Entferne den verarbeiteten Post aus der Queue
		$state['post_queue'] = array_slice( $state['post_queue'], 1 );
		
		asmi_debug_log( 'WP INDEX TICK: Completed post ' . $post_id . ' - Progress: ' . 
			$state['processed_posts'] . '/' . $state['total_posts'] );
	}

	// Aktualisiere den State vor dem nächsten Tick
	asmi_set_wp_index_state( $state );
	
	// CRITICAL FIX: Verwende die robuste Scheduling-Funktion statt direktem wp_schedule_single_event
	asmi_debug_log( 'WP INDEX TICK: Scheduling next tick...' );
	asmi_schedule_wp_index_tick();
}
add_action( ASMI_WP_INDEX_TICK_ACTION, 'asmi_wp_index_tick_handler' );

/**
 * Robuste Version der WordPress-Post-Indexierung mit verbessertem Timeout-Management.
 *
 * @param int   $post_id Die ID des zu indexierenden Beitrags.
 * @param array $languages Ein Array der zu indexierenden Sprach-Locales.
 * @return array Statistiken über verwendete APIs und Fehler.
 */
function asmi_index_single_wp_post_robust( $post_id, $languages = array() ) {
	global $wpdb;
	$original_post_obj = get_post( $post_id );
	if ( ! $original_post_obj || wp_is_post_revision( $post_id ) ) {
		return array( 
			'chatgpt_used' => 0, 
			'fallback_used' => 0, 
			'manually_imported' => 0,
			'timeout_errors' => 0,
			'api_errors' => 0
		);
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
	
	$stats = array( 
		'chatgpt_used' => 0, 
		'fallback_used' => 0, 
		'manually_imported' => 0,
		'timeout_errors' => 0,
		'api_errors' => 0
	);
	
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
		
		// Hauptverarbeitung mit ChatGPT (mit Timeout-Management)
		if ( $use_chatgpt && function_exists( 'asmi_process_with_chatgpt_cached_robust' ) ) {
			$chatgpt_lang = ( $lang_slug_short === 'en' ) ? 'en' : 'de';
			$chatgpt_result = asmi_process_with_chatgpt_cached_robust( 
				$original_title, 
				$rendered_content, 
				$chatgpt_lang,
				$content_hash_base
			);
			
			if ( ! is_wp_error( $chatgpt_result ) ) {
				// ChatGPT erfolgreich
				$final_title = ! empty( $chatgpt_result['title'] ) ? 
					$chatgpt_result['title'] : $original_title;
				
				$final_content = '';
				if ( ! empty( $chatgpt_result['content'] ) ) {
					$final_content = $chatgpt_result['content'];
				} elseif ( ! empty( $chatgpt_result['summary'] ) ) {
					$final_content = $chatgpt_result['summary'];
				}
				
				if ( empty( $final_content ) ) {
					$final_content = wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				}
				
				// Excerpt: Marken und Keywords kombinieren
				$keyword_parts = array();
				
				if ( ! empty( $chatgpt_result['brands'] ) && is_array( $chatgpt_result['brands'] ) ) {
					$brands = array_unique( $chatgpt_result['brands'] );
					sort( $brands );
					$keyword_parts = array_merge( $keyword_parts, $brands );
				}
				
				if ( ! empty( $chatgpt_result['keywords'] ) && is_array( $chatgpt_result['keywords'] ) ) {
					$keywords_clean = array_diff( $chatgpt_result['keywords'], $keyword_parts );
					$keyword_parts = array_merge( $keyword_parts, $keywords_clean );
				}
				
				if ( ! empty( $chatgpt_result['specs'] ) && is_array( $chatgpt_result['specs'] ) ) {
					foreach ( $chatgpt_result['specs'] as $spec_key => $spec_value ) {
						if ( ! empty( $spec_value ) && is_scalar( $spec_value ) ) {
							$keyword_parts[] = $spec_key . ':' . $spec_value;
						}
					}
				}
				
				$keyword_parts = array_unique( $keyword_parts );
				$final_excerpt = implode( ', ', array_slice( $keyword_parts, 0, 50 ) );
				
				if ( empty( $final_excerpt ) ) {
					$final_excerpt = wp_trim_words( $final_content, 20 );
				}
				
				$final_url = ( 'en' === $lang_slug_short ) ? 
					home_url( '/en' . wp_make_link_relative( $original_url ) ) : 
					$original_url;
				
				$content_hash = hash( 'sha256', $content_hash_base . '|chatgpt_v3|' . $lang_locale );
				$stats['chatgpt_used']++;
				
			} else {
				// ChatGPT fehlgeschlagen - Analysiere den Fehler
				$error_message = $chatgpt_result->get_error_message();
				if ( strpos( $error_message, 'cURL error 28' ) !== false || strpos( $error_message, 'timeout' ) !== false ) {
					$stats['timeout_errors']++;
					asmi_debug_log( sprintf( 
						'Post %d (%s): ChatGPT timeout error - %s', 
						$post_id, $lang_locale, $error_message
					) );
				} else {
					$stats['api_errors']++;
					asmi_debug_log( sprintf( 
						'Post %d (%s): ChatGPT API error - %s', 
						$post_id, $lang_locale, $error_message
					) );
				}
				
				// Verwende Fallback
				$final_title = $original_title;
				$final_content = wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = wp_trim_words( wp_strip_all_tags( $rendered_content ), 10 );
				$final_url = ( 'en' === $lang_slug_short ) ? 
					home_url( '/en' . wp_make_link_relative( $original_url ) ) : 
					$original_url;
				
				$content_hash = hash( 'sha256', $content_hash_base . '|error_fallback_v1|' . $lang_locale );
				$stats['fallback_used']++;
			}
		} else {
			// Kein ChatGPT konfiguriert - Verwende Fallback
			if ( ! function_exists( 'asmi_extract_search_keywords' ) ) {
				$fallback_file = plugin_dir_path( __FILE__ ) . 'keyword-fallback.php';
				if ( file_exists( $fallback_file ) ) {
					require_once $fallback_file;
				}
			}
			
			if ( function_exists( 'asmi_extract_search_keywords' ) ) {
				$extracted = asmi_extract_search_keywords( $original_title, $rendered_content, $lang_locale );
				$final_title = $extracted['title'] ?? $original_title;
				$final_content = $extracted['content'] ?? wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = $extracted['excerpt'] ?? '';
			} else {
				$final_title = $original_title;
				$final_content = wp_trim_words( wp_strip_all_tags( $rendered_content ), 50 );
				$final_excerpt = wp_trim_words( wp_strip_all_tags( $rendered_content ), 10 );
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
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
			)
		);
		
		if ( $result === false ) {
			asmi_debug_log( sprintf( 
				'Post %d (%s): Database error - %s', 
				$post_id, $lang_locale, $wpdb->last_error
			) );
		}
	}
	
	return $stats;
}

/**
 * Schließt die WordPress-Indexierung ab und setzt Statistiken.
 *
 * @param array $state Der aktuelle Zustand.
 * @return void
 */
function asmi_finish_wp_indexing( $state ) {
	global $wpdb;
	$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;
	
	$state['status'] = 'finished';
	$state['finished_at'] = time();
	$state['current_action'] = '';
	
	// Bereinige nicht mehr existierende Posts
	$existing_post_ids = $wpdb->get_col( 
		"SELECT DISTINCT post_id FROM {$wpdb->posts} WHERE post_status = 'publish'"
	);
	
	if ( ! empty( $existing_post_ids ) ) {
		$existing_ids_placeholder = implode( ',', array_fill( 0, count( $existing_post_ids ), '%d' ) );
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name 
				WHERE source_type = 'wordpress' 
				AND source_id NOT IN ($existing_ids_placeholder)
				AND content_hash NOT LIKE '%manual_import%'",
				$existing_post_ids
			)
		);
		
		if ( $deleted > 0 ) {
			asmi_debug_log( 'WP INDEX: Cleaned up ' . $deleted . ' obsolete entries' );
		}
	}
	
	// Setze Last Run Statistiken
	$state['last_run'] = array(
		'type'               => __( 'WordPress Content Indexing', 'asmi-search' ),
		'status'             => 'completed',
		'finished_at'        => $state['finished_at'],
		'duration'           => $state['finished_at'] - $state['started_at'],
		'processed'          => $state['processed_posts'],
		'chatgpt_used'       => $state['chatgpt_used'],
		'fallback_used'      => $state['fallback_used'],
		'manually_imported'  => $state['manually_imported'],
		'timeout_errors'     => $state['timeout_errors'],
		'api_errors'         => $state['api_errors'],
	);
	
	asmi_set_wp_index_state( $state );
	
	asmi_debug_log( sprintf( 
		'WP INDEX COMPLETE: %d posts processed | ChatGPT: %d | Fallback: %d | Protected: %d | Timeouts: %d | API Errors: %d',
		$state['processed_posts'],
		$state['chatgpt_used'],
		$state['fallback_used'],
		$state['manually_imported'],
		$state['timeout_errors'],
		$state['api_errors']
	));
	
	// Optimiere Tabelle
	if ( $state['processed_posts'] > 50 ) {
		$wpdb->query( "OPTIMIZE TABLE $table_name" );
		asmi_debug_log( 'WP INDEX: Database table optimized' );
	}
}

/**
 * Bricht eine laufende WordPress-Indexierung ab.
 *
 * @return void
 */
function asmi_cancel_wp_indexing() {
	wp_clear_scheduled_hook( ASMI_WP_INDEX_TICK_ACTION );
	
	$state = asmi_get_wp_index_state();
	if ( $state['status'] === 'indexing' ) {
		$state['status'] = 'idle';
		$state['error'] = __( 'WordPress indexing was manually canceled.', 'asmi-search' );
		$state['finished_at'] = time();
		
		$state['last_run'] = array(
			'type'               => __( 'WordPress Content Indexing', 'asmi-search' ),
			'status'             => 'cancelled',
			'finished_at'        => $state['finished_at'],
			'duration'           => $state['finished_at'] - ( $state['started_at'] > 0 ? $state['started_at'] : time() ),
			'processed'          => $state['processed_posts'],
			'chatgpt_used'       => $state['chatgpt_used'],
			'fallback_used'      => $state['fallback_used'],
			'manually_imported'  => $state['manually_imported'],
			'timeout_errors'     => $state['timeout_errors'],
			'api_errors'         => $state['api_errors'],
		);
		
		asmi_set_wp_index_state( $state );
		asmi_debug_log( 'WP INDEX: Indexing canceled by user' );
	}
}

/**
 * Rückwärtskompatibilität: Synchrone Indexierung aller WordPress-Inhalte.
 * Startet jetzt die asynchrone Version.
 */
function asmi_index_all_wp_content() {
	asmi_debug_log( 'WP INDEX: Legacy function called, starting asynchronous indexing' );
	asmi_start_wp_content_indexing();
}
add_action( 'asmi_cron_wp_content_index', 'asmi_index_all_wp_content' );

/**
 * Hook für Post-Speicherung - Aktualisiert den Index bei Änderungen.
 */
function asmi_hook_save_post( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	
	$o = asmi_get_opts();
	$post_types = ! empty( $o['wp_post_types'] ) ? 
		array_filter( array_map( 'trim', explode( ',', $o['wp_post_types'] ) ) ) : 
		array();
		
	if ( in_array( $post->post_type, $post_types, true ) && 'publish' === $post->post_status ) {
		asmi_debug_log( sprintf( 'Updating index for post %d after save', $post_id ) );
		asmi_index_single_wp_post_robust( $post_id );
	}
}
add_action( 'save_post', 'asmi_hook_save_post', 10, 2 );

/**
 * Hook für Post-Löschung - Entfernt den Post aus dem Index.
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