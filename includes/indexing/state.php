<?php
/**
 * Funktionen zur Verwaltung des Zustands (State) der Indexierungsprozesse.
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include-Guard mit function_exists statt define
if ( ! function_exists( 'asmi_get_index_state' ) ) {

	/**
	 * Ruft den aktuellen Zustand des Indexierungsprozesses ab.
	 *
	 * @return array Der aktuelle Zustand.
	 */
	function asmi_get_index_state() {
		$state     = get_option( ASMI_INDEX_STATE_OPT, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$defaults = array(
			'status'           => 'idle',
			'feeds'            => array(),
			'feed_details'     => array(),
			'feed_i'           => 0,
			'feed_prepare_i'   => 0,
			'offset'           => 0,
			'batch'            => asmi_get_opts()['index_batch'],
			'total_items'      => 0,
			'processed_items'  => 0,
			'skipped_no_desc'  => 0,
			'image_errors'     => 0,
			'current_action'   => '',
			'started_at'       => 0,
			'updated_at'       => 0,
			'finished_at'      => 0,
			'error'            => '',
			'last_run'         => array(),
		);
		return wp_parse_args( $state, $defaults );
	}

	/**
	 * Speichert den Zustand des Indexierungsprozesses.
	 *
	 * @param array $state Der zu speichernde Zustand.
	 * @return void
	 */
	function asmi_set_index_state( $state ) {
		update_option( ASMI_INDEX_STATE_OPT, $state, false );
	}

	/**
	 * Ruft den aktuellen Zustand des WordPress-Indexierungsprozesses ab.
	 *
	 * @return array Der aktuelle Zustand.
	 */
	function asmi_get_wp_index_state() {
		$state = get_option( ASMI_WP_INDEX_STATE_OPT, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$defaults = array(
			'status'               => 'idle',
			'total_posts'          => 0,
			'processed_posts'      => 0,
			'skipped_unchanged'    => 0,  // NEU: Anzahl übersprungener unveränderter Posts
			'updated_posts'        => 0,   // NEU: Anzahl tatsächlich aktualisierter Posts
			'current_post'         => 0,
			'current_post_title'   => '',
			'current_lang'         => '',
			'chatgpt_used'         => 0,
			'fallback_used'        => 0,
			'manually_imported'    => 0,
			'timeout_errors'       => 0,
			'api_errors'           => 0,
			'post_queue'           => array(),
			'languages'            => array( 'de_DE', 'en_GB' ),
			'current_action'       => '',
			'started_at'           => 0,
			'updated_at'           => 0,
			'finished_at'          => 0,
			'error'                => '',
			'last_run'             => array(),
			'batch_size'           => 5,
		);
		return wp_parse_args( $state, $defaults );
	}

	/**
	 * Speichert den Zustand des WordPress-Indexierungsprozesses.
	 *
	 * @param array $state Der zu speichernde Zustand.
	 * @return void
	 */
	function asmi_set_wp_index_state( $state ) {
		$state['updated_at'] = time();
		update_option( ASMI_WP_INDEX_STATE_OPT, $state, false );
	}

	/**
	 * Ruft den aktuellen Zustand des Bild-Löschprozesses ab.
	 *
	 * @return array Der aktuelle Zustand.
	 */
	function asmi_get_image_deletion_state() {
		return get_option(
			ASMI_IMAGE_DELETE_STATE_OPT,
			array(
				'status'      => 'idle',
				'total'       => 0,
				'deleted'     => 0,
				'files'       => array(),
				'offset'      => 0,
				'started_at'  => 0,
				'finished_at' => 0,
			)
		);
	}

	/**
	 * Speichert den Zustand des Bild-Löschprozesses.
	 *
	 * @param array $state Der zu speichernde Zustand.
	 * @return void
	 */
	function asmi_set_image_deletion_state( $state ) {
		update_option( ASMI_IMAGE_DELETE_STATE_OPT, $state, false );
	}


	/**
	 * Ruft grundlegende Statistiken über den Index ab.
	 *
	 * @return array Ein Array mit der Anzahl der Produkte und WP-Inhalte.
	 */
	function asmi_get_index_stats() {
		global $wpdb;
		$table_name = $wpdb->prefix . ASMI_INDEX_TABLE;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return array(
				'total'    => 0,
				'total_wp' => 0,
			);
		}

		$total_products = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name WHERE source_type = 'product'" );

		// KORREKTUR: Zählt jetzt die ANZAHL EINZIGARTIGER WordPress-Beiträge (via DISTINCT source_id),
		// anstatt jede Sprachversion einzeln zu zählen. Dies korrigiert die Statistik-Anzeige.
		$total_wp = $wpdb->get_var( "SELECT COUNT(DISTINCT source_id) FROM $table_name WHERE source_type = 'wordpress'" );

		return array(
			'total'    => (int) $total_products,
			'total_wp' => (int) $total_wp,
		);
	}

} // Ende Include-Guard