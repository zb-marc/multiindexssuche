<?php
/**
 * Fallback Keyword-Extraktion wenn ChatGPT nicht verfügbar.
 * Wird nur bei Bedarf geladen.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimale Keyword-Extraktion als Fallback.
 *
 * @param string $title Der Titel.
 * @param string $content Der Inhalt.
 * @param string $language Die Sprache.
 * @return array Extrahierte Daten.
 */
function asmi_extract_search_keywords( $title, $content, $language = 'de_DE' ) {
	// Bereinige Text
	$clean_title = wp_strip_all_tags( $title );
	$clean_content = wp_strip_all_tags( $content );
	
	// Entferne URLs und E-Mails
	$clean_content = preg_replace( '|https?://[^\s]+|', '', $clean_content );
	$clean_content = preg_replace( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $clean_content );
	
	// Kurze Vorschau
	$preview = wp_trim_words( $clean_content, 30 );
	
	// Einfache Keyword-Extraktion (häufigste Wörter > 4 Zeichen)
	$words = str_word_count( strtolower( $clean_content ), 1 );
	$word_freq = array_count_values( $words );
	
	// Filtere kurze und häufige Wörter
	$keywords = array();
	foreach ( $word_freq as $word => $count ) {
		if ( strlen( $word ) > 4 && $count > 1 ) {
			$keywords[] = $word;
		}
		if ( count( $keywords ) >= 15 ) {
			break;
		}
	}
	
	// Wichtige Solar/Batterie-Begriffe priorisieren
	$important_terms = array( 'solar', 'batterie', 'akku', 'wechselrichter', 'speicher', 
		'photovoltaik', 'energie', 'victron', 'pylontech', 'byd', 'fronius', 'sma' );
	
	foreach ( $important_terms as $term ) {
		if ( stripos( $clean_content, $term ) !== false && ! in_array( $term, $keywords ) ) {
			array_unshift( $keywords, $term );
		}
	}
	
	return array(
		'title'   => $clean_title,
		'content' => $preview . ' | ' . implode( ' ', array_slice( $keywords, 0, 10 ) ),
		'excerpt' => implode( ' ', array_slice( $keywords, 0, 10 ) )
	);
}