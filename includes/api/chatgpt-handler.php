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
 * Verarbeitet Content mit ChatGPT für optimale Indexierung.
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
			'temperature' => 0.3, // Niedrig für konsistente Ergebnisse
			'max_tokens' => 800,
			'response_format' => array( 'type' => 'json_object' ) // Erzwinge JSON-Antwort
		) ),
	) );
	
	if ( is_wp_error( $response ) ) {
		asmi_debug_log( 'ChatGPT API Error: ' . $response->get_error_message() );
		return $response;
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
		asmi_debug_log( 'ChatGPT API unexpected response: ' . $body );
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
	return "You are an expert content analyzer for an e-commerce search index specializing in solar energy, batteries, and renewable energy products. Your task is to create optimal search index entries.

CRITICAL REQUIREMENTS:
1. Extract and identify ALL brand names mentioned (Victron, Pylontech, BYD, Fronius, SMA, etc.)
2. Identify key technical specifications and product features
3. Create concise, search-optimized summaries (max 150 chars for summary, max 400 chars for content)
4. Extract the most relevant search keywords
5. Maintain technical accuracy for the renewable energy sector
6. Return ONLY valid JSON format

IMPORTANT BRANDS TO RECOGNIZE:
Solar/Inverters: Victron, Fronius, SMA, Huawei, GoodWe, Growatt, SolarEdge, Sungrow, Kostal, Deye, Hoymiles
Batteries: Pylontech, BYD, LG Chem, Tesla, AlphaESS, Dyness, CATL, EVE, Lishen
Traditional: Exide, Banner, Bosch, Varta, Yuasa, Optima, Trojan, Hoppecke
Panels: Longi, Jinko, Trina, Canadian Solar, JA Solar, Q-Cells, REC, SunPower
Accessories: MPPT, PWM, MC4, Wago, Phoenix Contact

OUTPUT FORMAT (strict JSON):
{
  \"title\": \"optimized title\",
  \"summary\": \"ultra-concise summary max 150 chars\",
  \"content\": \"search-optimized content with preview and keywords, max 400 chars\",
  \"keywords\": [\"keyword1\", \"keyword2\", ...max 20],
  \"brands\": [\"brand1\", \"brand2\", ...all found brands],
  \"specs\": {\"key1\": \"value1\", \"key2\": \"value2\"}
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
	$lang_instruction = ( $target_lang === 'en' ) ? 
		"Provide the response in English." : 
		"Provide the response in German.";
	
	return "Analyze this content and create an optimized search index entry. $lang_instruction

TITLE: $title

CONTENT: $content

Requirements:
1. Identify ALL mentioned brand names (especially solar/battery brands)
2. Extract key technical specifications (voltage, capacity, power, etc.)
3. Create a 150-char summary highlighting the main topic and key products
4. Generate a 400-char search-optimized content with important keywords
5. List 15-20 most relevant search keywords
6. Extract any technical specs as key-value pairs

Focus on: product names, model numbers, technical terms, applications, and benefits.
Remove: contact info, generic phrases, navigation elements, marketing fluff.

Return ONLY valid JSON as specified.";
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
	
	// Verarbeite mit ChatGPT
	$result = asmi_process_with_chatgpt( $title, $content, $target_lang );
	
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
}

/**
 * Bereinigt alte ChatGPT Cache-Einträge.
 */
function asmi_cleanup_chatgpt_cache() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'asmi_chatgpt_cache';
	
	$wpdb->query( 
		"DELETE FROM $table_name 
		WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
	);
}
add_action( 'asmi_cleanup_chatgpt_cache', 'asmi_cleanup_chatgpt_cache' );