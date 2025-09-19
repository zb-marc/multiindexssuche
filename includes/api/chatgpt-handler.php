<?php
/**
 * ChatGPT API Handler für intelligente Inhaltsverarbeitung.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verarbeitet Content mit einem OpenAI Assistant.
 *
 * @param string $title Der Titel des Inhalts.
 * @param string $content Der vollständige Inhalt.
 * @param string $target_lang Zielsprache (de oder en).
 * @return array|WP_Error Verarbeitete Daten oder Fehler.
 */
function asmi_process_with_assistant( $title, $content, $target_lang = 'de' ) {
	$o = asmi_get_opts();
	$api_key = $o['chatgpt_api_key'] ?? '';
	$assistant_id = $o['chatgpt_assistant_id'] ?? '';
	
	if ( empty( $api_key ) || empty( $assistant_id ) ) {
		asmi_debug_log( 'Assistant not configured - falling back to direct API' );
		return asmi_process_with_chatgpt( $title, $content, $target_lang );
	}
	
	// Bereite den Content vor
	$clean_content = wp_strip_all_tags( $content );
	if ( strlen( $clean_content ) > 4000 ) {
		$clean_content = substr( $clean_content, 0, 4000 );
	}
	
	// 1. Thread erstellen
	$thread_response = wp_remote_post( 'https://api.openai.com/v1/threads', array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'OpenAI-Beta'   => 'assistants=v2'
		),
		'body' => wp_json_encode( array() )
	));
	
	if ( is_wp_error( $thread_response ) ) {
		asmi_debug_log( 'Thread creation failed: ' . $thread_response->get_error_message() );
		return $thread_response;
	}
	
	$thread = json_decode( wp_remote_retrieve_body( $thread_response ), true );
	if ( ! isset( $thread['id'] ) ) {
		return new WP_Error( 'thread_error', 'Failed to create thread' );
	}
	$thread_id = $thread['id'];
	
	// 2. Message hinzufügen - KORREKTUR: $is_english VOR der ersten Verwendung definieren
	$is_english = ( $target_lang === 'en' || $target_lang === 'en_GB' );
	
	$message_body = sprintf(
		"Process this content for search indexing.\n\n" .
		"Target Language: %s\n" .
		"Original German Title: %s\n\n" .
		"Content:\n%s\n\n" .
		"CRITICAL INSTRUCTION:\n" .
		"%s\n\n" .
		"Additional Rules:\n" .
		"- Extract ALL brand names from your knowledge file\n" .
		"- Focus on factual content, no marketing language\n" .
		"- Return valid JSON format with: title, summary, content, keywords, brands, specs",
		$is_english ? 'English' : 'German',
		$title,
		$clean_content,
		$is_english ? 
    "CRITICAL: YOU MUST TRANSLATE THE GERMAN TITLE '{$title}' TO ENGLISH! 
     NEVER return German titles when English is requested!
     Example: 'Batterien für Boote' MUST become 'Batteries for Boats'" : 
    "Keep German title exactly as-is"
	);
	
	$message_response = wp_remote_post( "https://api.openai.com/v1/threads/{$thread_id}/messages", array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'OpenAI-Beta'   => 'assistants=v2'
		),
		'body' => wp_json_encode( array(
			'role' => 'user',
			'content' => $message_body
		))
	));
	
	if ( is_wp_error( $message_response ) ) {
		asmi_debug_log( 'Message creation failed: ' . $message_response->get_error_message() );
		return $message_response;
	}
	
	// 3. Run erstellen und ausführen
	$run_response = wp_remote_post( "https://api.openai.com/v1/threads/{$thread_id}/runs", array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'OpenAI-Beta'   => 'assistants=v2'
		),
		'body' => wp_json_encode( array(
			'assistant_id' => $assistant_id,
			'temperature' => 0.1
		))
	));
	
	if ( is_wp_error( $run_response ) ) {
		asmi_debug_log( 'Run creation failed: ' . $run_response->get_error_message() );
		return $run_response;
	}
	
	$run = json_decode( wp_remote_retrieve_body( $run_response ), true );
	if ( ! isset( $run['id'] ) ) {
		return new WP_Error( 'run_error', 'Failed to create run' );
	}
	$run_id = $run['id'];
	
	// 4. Auf Completion warten (Polling)
	$max_attempts = 30;
	$attempt = 0;
	
	while ( $attempt < $max_attempts ) {
		sleep( 2 ); // 2 Sekunden warten zwischen Checks
		
		$status_response = wp_remote_get( 
			"https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}",
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'OpenAI-Beta'   => 'assistants=v2'
				)
			)
		);
		
		if ( is_wp_error( $status_response ) ) {
			asmi_debug_log( 'Run status check failed: ' . $status_response->get_error_message() );
			return $status_response;
		}
		
		$status = json_decode( wp_remote_retrieve_body( $status_response ), true );
		
		if ( $status['status'] === 'completed' ) {
			asmi_debug_log( 'Assistant run completed successfully' );
			break;
		} elseif ( in_array( $status['status'], array( 'failed', 'cancelled', 'expired' ) ) ) {
			asmi_debug_log( 'Assistant run failed with status: ' . $status['status'] );
			if ( isset( $status['last_error'] ) ) {
				asmi_debug_log( 'Error details: ' . wp_json_encode( $status['last_error'] ) );
			}
			return new WP_Error( 'run_failed', 'Assistant run ' . $status['status'] );
		}
		
		$attempt++;
	}
	
	if ( $attempt >= $max_attempts ) {
		return new WP_Error( 'timeout', 'Assistant run timeout after 60 seconds' );
	}
	
	// 5. Messages abrufen
	$messages_response = wp_remote_get( 
		"https://api.openai.com/v1/threads/{$thread_id}/messages",
		array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'OpenAI-Beta'   => 'assistants=v2'
			)
		)
	);
	
	if ( is_wp_error( $messages_response ) ) {
		asmi_debug_log( 'Messages retrieval failed: ' . $messages_response->get_error_message() );
		return $messages_response;
	}
	
	$messages = json_decode( wp_remote_retrieve_body( $messages_response ), true );
	
	if ( ! isset( $messages['data'][0]['content'][0]['text']['value'] ) ) {
		return new WP_Error( 'no_response', 'No response from assistant' );
	}
	
	$assistant_message = $messages['data'][0]['content'][0]['text']['value'];
	
	// JSON extrahieren (falls in Markdown-Code-Block)
	if ( preg_match( '/```json\s*(.*?)\s*```/s', $assistant_message, $matches ) ) {
		$assistant_message = $matches[1];
	} elseif ( preg_match( '/```\s*(.*?)\s*```/s', $assistant_message, $matches ) ) {
		$assistant_message = $matches[1];
	}
	
	// JSON parsen
	$result = json_decode( $assistant_message, true );
	
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		asmi_debug_log( 'Assistant JSON decode error: ' . json_last_error_msg() . ' - Raw: ' . substr( $assistant_message, 0, 500 ) );
		return new WP_Error( 'json_error', 'Failed to parse assistant response' );
	}
	
	asmi_debug_log( 'Assistant processing successful for: ' . $title );
	
	return $result;
}

/**
 * Verarbeitet Content mit ChatGPT für optimale Indexierung (Fallback).
 *
 * @param string $title Der Titel des Inhalts.
 * @param string $content Der vollständige Inhalt.
 * @param string $target_lang Zielsprache (de oder en).
 * @return array|WP_Error Verarbeitete Daten oder Fehler.
 */
function asmi_process_with_chatgpt( $title, $content, $target_lang = 'de' ) {
	$o = asmi_get_opts();
	$api_key = $o['chatgpt_api_key'] ?? '';
	
	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_api_key', 'ChatGPT API key not configured' );
	}
	
	// Bereite den Content vor (entferne HTML, kürze auf max. 4000 Zeichen)
	$clean_content = wp_strip_all_tags( $content );
	if ( strlen( $clean_content ) > 4000 ) {
		$clean_content = substr( $clean_content, 0, 4000 );
	}
	
	// Erstelle den optimierten Prompt
	$system_prompt = asmi_get_chatgpt_system_prompt();
	$user_prompt = asmi_get_chatgpt_user_prompt( $title, $clean_content, $target_lang );
	
	// API-Aufruf
	$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'body' => wp_json_encode( array(
			'model' => $o['chatgpt_model'] ?? 'gpt-4o-mini',
			'messages' => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt )
			),
			'temperature' => 0.1,
			'max_tokens' => 1000,
			'response_format' => array( 'type' => 'json_object' )
		) ),
	) );
	
	if ( is_wp_error( $response ) ) {
		asmi_debug_log( 'ChatGPT API Error: ' . $response->get_error_message() );
		return $response;
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
		asmi_debug_log( 'ChatGPT API unexpected response: ' . substr( $body, 0, 500 ) );
		return new WP_Error( 'invalid_response', 'Invalid ChatGPT API response' );
	}
	
	// Parse die JSON-Antwort
	$result = json_decode( $data['choices'][0]['message']['content'], true );
	
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		asmi_debug_log( 'ChatGPT JSON decode error: ' . json_last_error_msg() );
		return new WP_Error( 'json_error', 'Failed to parse ChatGPT response' );
	}
	
	// Log Token-Verbrauch
	if ( isset( $data['usage'] ) ) {
		asmi_debug_log( sprintf( 
			'ChatGPT tokens used - Input: %d, Output: %d, Total: %d',
			$data['usage']['prompt_tokens'] ?? 0,
			$data['usage']['completion_tokens'] ?? 0,
			$data['usage']['total_tokens'] ?? 0
		) );
	}
	
	return $result;
}

/**
 * Generiert den System-Prompt für ChatGPT.
 *
 * @return string Der System-Prompt.
 */
function asmi_get_chatgpt_system_prompt() {
	return "You process German solar/battery website content for search indexing.

ABSOLUTE RULES:
1. TITLE: For German keep exactly as-is. For English ALWAYS translate completely to English.
2. CONTENT: Create factual summary of actual page content - no marketing language.
3. EXCERPT: List ALL found brand names and technical keywords, comma-separated.
4. Never add information not in source.

CRITICAL FOR ENGLISH:
- ALWAYS translate German titles to English
- Examples:
  * 'Batterien für Boote' → 'Batteries for Boats'
  * 'Wärmepumpen' → 'Heat Pumps'
  * 'Balkonkraftwerke' → 'Balcony Power Plants'
  * 'Starterbatterien' → 'Starter Batteries'

BRANDS TO RECOGNIZE:
Akkusys brands from knowledge file plus:
- Solar: Victron, Fronius, SMA, Huawei, GoodWe, Growatt, SolarEdge, Kostal, Deye, Hoymiles
- Batteries: Pylontech, BYD, LG Chem, AlphaESS, Dyness, Hoppecke, Exide, Banner, Varta, Trojan
- Panels: Longi, Jinko, Trina, Canadian Solar, JA Solar, Q-Cells, REC, SunPower

OUTPUT FORMAT:
{
  \"title\": \"exact title or full English translation\",
  \"summary\": \"150-char factual summary\",
  \"content\": \"400-char comprehensive description\",
  \"keywords\": [\"technical terms\"],
  \"brands\": [\"found brand names\"],
  \"specs\": {}
}";
}

/**
 * Generiert den User-Prompt für ChatGPT.
 *
 * @param string $title Der Titel.
 * @param string $content Der Inhalt.
 * @param string $target_lang Die Zielsprache.
 * @return string Der User-Prompt.
 */
function asmi_get_chatgpt_user_prompt( $title, $content, $target_lang ) {
	$is_english = ( $target_lang === 'en' || $target_lang === 'en_GB' );
	
	return "Process this content for search indexing.

ORIGINAL GERMAN TITLE: {$title}

CONTENT:
{$content}

INSTRUCTIONS:
" . ( $is_english ? "
1. TITLE: YOU MUST TRANSLATE '{$title}' TO ENGLISH! Full translation required, no German words allowed.
2. SUMMARY: Write 150-char English summary of what this page covers.
3. CONTENT: Create 400-char English description of actual content.
4. KEYWORDS: Extract and translate all technical terms to English.
5. BRANDS: List all brand names found (do not translate brand names).

CRITICAL: The title MUST be in English. German titles are NOT acceptable.
" : "
1. TITLE: Keep '{$title}' exactly as-is in German. Only adjust plural→singular if grammatically needed.
2. SUMMARY: Write 150-char German summary of what this page covers.
3. CONTENT: Create 400-char German description of actual content.
4. KEYWORDS: Extract all technical terms in German.
5. BRANDS: List all brand names found.
" ) . "

IMPORTANT:
- Scan for ALL brand names from your knowledge
- Include model numbers (US2000, MultiPlus, etc.)
- Include technical specs (kWh, V, A, W)
- NO generic marketing phrases
- Focus on WHAT is described

Return ONLY valid JSON matching the format above.";
}

/**
 * Cached ChatGPT-Verarbeitung mit Datenbank-Speicherung.
 *
 * @param string $title Der Titel.
 * @param string $content Der Inhalt.
 * @param string $target_lang Die Zielsprache.
 * @param string $cache_key Eindeutiger Cache-Schlüssel.
 * @return array|WP_Error Verarbeitete Daten oder Fehler.
 */
function asmi_process_with_chatgpt_cached( $title, $content, $target_lang, $cache_key ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'asmi_chatgpt_cache';
	
	// Prüfe ob Tabelle existiert
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
		asmi_create_chatgpt_cache_table();
	}
	
	// Prüfe Cache
	$cached = $wpdb->get_var( $wpdb->prepare(
		"SELECT response_data FROM $table_name 
		WHERE cache_key = %s AND lang = %s 
		AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
		$cache_key, $target_lang
	) );
	
	if ( $cached ) {
		asmi_debug_log( "Using cached ChatGPT response for key: $cache_key ($target_lang)" );
		return json_decode( $cached, true );
	}
	
	// Verarbeite mit Assistant wenn konfiguriert, sonst mit ChatGPT
	$o = asmi_get_opts();
	if ( ! empty( $o['chatgpt_assistant_id'] ) ) {
		asmi_debug_log( 'Using Assistant: ' . $o['chatgpt_assistant_id'] );
		$result = asmi_process_with_assistant( $title, $content, $target_lang );
	} else {
		asmi_debug_log( 'Using direct ChatGPT API' );
		$result = asmi_process_with_chatgpt( $title, $content, $target_lang );
	}
	
	if ( ! is_wp_error( $result ) ) {
		// Speichere in Cache
		$wpdb->replace( $table_name, array(
			'cache_key'     => $cache_key,
			'lang'          => $target_lang,
			'response_data' => wp_json_encode( $result ),
			'created_at'    => current_time( 'mysql' )
		) );
	}
	
	return $result;
}

/**
 * Erstellt die ChatGPT Cache-Tabelle.
 */
function asmi_create_chatgpt_cache_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'asmi_chatgpt_cache';
	$charset_collate = $wpdb->get_charset_collate();
	
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id int(11) NOT NULL AUTO_INCREMENT,
		cache_key varchar(64) NOT NULL,
		lang varchar(5) NOT NULL,
		response_data longtext NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY cache_lang (cache_key, lang),
		KEY created_at (created_at)
	) $charset_collate;";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	asmi_debug_log( 'ChatGPT cache table created/verified' );
}

/**
 * Bereinigt alte ChatGPT Cache-Einträge.
 */
function asmi_cleanup_chatgpt_cache() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'asmi_chatgpt_cache';
	
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
		$deleted = $wpdb->query( 
			"DELETE FROM $table_name 
			WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);
		
		if ( $deleted > 0 ) {
			asmi_debug_log( sprintf( 'ChatGPT cache cleaned - %d old entries removed', $deleted ) );
		}
	}
}
add_action( 'asmi_cleanup_chatgpt_cache', 'asmi_cleanup_chatgpt_cache' );