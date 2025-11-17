<?php
/**
 * AS Image Manager - Eigenständiges Werkzeug zur Verwaltung des Bild-Cache-Ordners.
 * Erscheint unter: WordPress Admin → Werkzeuge → AS Image Manager
 *
 * @package ASMultiindexSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert den Menü-Eintrag unter "Werkzeuge".
 *
 * @return void
 */
function asmi_tools_register_image_manager_menu() {
	add_management_page(
		__( 'AS Image Manager', 'asmi-search' ),
		__( 'AS Image Manager', 'asmi-search' ),
		'manage_options',
		'asmi-image-manager',
		'asmi_tools_render_image_manager_page'
	);
}
add_action( 'admin_menu', 'asmi_tools_register_image_manager_menu' );

/**
 * Berechnet die Größe des Bild-Cache-Ordners rekursiv.
 *
 * @param string $dir Der Verzeichnispfad.
 * @return array Array mit 'size' (Bytes) und 'count' (Anzahl Dateien).
 */
function asmi_tools_get_directory_size( $dir ) {
	$size  = 0;
	$count = 0;
	
	if ( ! is_dir( $dir ) ) {
		return array(
			'size'  => 0,
			'count' => 0,
		);
	}
	
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS )
	);
	
	foreach ( $iterator as $file ) {
		if ( $file->isFile() ) {
			$size += $file->getSize();
			++$count;
		}
	}
	
	return array(
		'size'  => $size,
		'count' => $count,
	);
}

/**
 * Formatiert Bytes in lesbare Größe (KB, MB, GB).
 *
 * @param int $bytes Die Anzahl der Bytes.
 * @param int $precision Dezimalstellen.
 * @return string Die formatierte Größe.
 */
function asmi_tools_format_bytes( $bytes, $precision = 2 ) {
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	
	$bytes = max( $bytes, 0 );
	$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
	$pow   = min( $pow, count( $units ) - 1 );
	
	$bytes /= pow( 1024, $pow );
	
	return round( $bytes, $precision ) . ' ' . $units[ $pow ];
}

/**
 * Löscht alle Dateien im Bild-Cache-Ordner (außer geschützte Dateien).
 *
 * @return array Array mit 'success' (bool), 'deleted' (int), 'message' (string).
 */
function asmi_tools_clean_image_cache() {
	$cache_dir = asmi_get_image_cache_dir();
	$cache_path = $cache_dir['path'];
	
	if ( ! is_dir( $cache_path ) ) {
		return array(
			'success' => false,
			'deleted' => 0,
			'message' => __( 'Cache-Verzeichnis existiert nicht.', 'asmi-search' ),
		);
	}
	
	// Filesystem API initialisieren.
	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}
	
	$files = glob( $cache_path . '/*' );
	if ( false === $files ) {
		$files = array();
	}
	
	$deleted = 0;
	$protected_files = array( '.htaccess', 'index.html' );
	
	foreach ( $files as $file ) {
		$basename = basename( $file );
		
		// Überspringe geschützte Dateien.
		if ( in_array( $basename, $protected_files, true ) ) {
			continue;
		}
		
		// Überspringe Verzeichnisse.
		if ( is_dir( $file ) ) {
			continue;
		}
		
		// Lösche Datei.
		if ( $wp_filesystem->delete( $file ) ) {
			++$deleted;
		}
	}
	
	return array(
		'success' => true,
		'deleted' => $deleted,
		'message' => sprintf(
			/* translators: %d: Anzahl der gelöschten Dateien */
			__( '%d Dateien erfolgreich gelöscht.', 'asmi-search' ),
			$deleted
		),
	);
}

/**
 * Handler für die Cleanup-Aktion.
 *
 * @return void
 */
function asmi_tools_handle_cleanup_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Fehlende Berechtigung.', 'asmi-search' ) );
	}
	
	check_admin_referer( 'asmi_tools_cleanup_cache' );
	
	$result = asmi_tools_clean_image_cache();
	
	// Speichere Nachricht in Transient.
	set_transient(
		'asmi_tools_cleanup_message',
		array(
			'type'    => $result['success'] ? 'success' : 'error',
			'message' => $result['message'],
		),
		30
	);
	
	// Redirect zurück.
	wp_safe_redirect( admin_url( 'tools.php?page=asmi-image-manager' ) );
	exit;
}
add_action( 'admin_post_asmi_tools_cleanup_cache', 'asmi_tools_handle_cleanup_action' );

/**
 * Rendert die Image Manager Werkzeugseite.
 *
 * @return void
 */
function asmi_tools_render_image_manager_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Zeige gespeicherte Nachricht an.
	$message = get_transient( 'asmi_tools_cleanup_message' );
	if ( $message ) {
		delete_transient( 'asmi_tools_cleanup_message' );
		?>
		<div class="notice notice-<?php echo esc_attr( $message['type'] ); ?> is-dismissible">
			<p><?php echo wp_kses_post( $message['message'] ); ?></p>
		</div>
		<?php
	}
	
	// Hole Ordner-Informationen.
	$cache_dir  = asmi_get_image_cache_dir();
	$cache_path = $cache_dir['path'];
	$dir_info   = asmi_tools_get_directory_size( $cache_path );
	
	$dir_exists = is_dir( $cache_path );
	$total_size = $dir_info['size'];
	$file_count = $dir_info['count'];
	
	// Letztes Änderungsdatum.
	$last_modified = $dir_exists ? filemtime( $cache_path ) : 0;
	
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AS Image Manager', 'asmi-search' ); ?></h1>
		<p><?php esc_html_e( 'Verwalte den Bild-Cache-Ordner des AS Multiindex Search Plugins.', 'asmi-search' ); ?></p>
		
		<div class="card" style="max-width: 800px;">
			<h2><?php esc_html_e( 'Cache-Ordner Übersicht', 'asmi-search' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Ordner-Pfad:', 'asmi-search' ); ?></th>
					<td>
						<code><?php echo esc_html( $cache_path ); ?></code>
						<?php if ( ! $dir_exists ) : ?>
							<br><span style="color: #d63638;">
								<strong><?php esc_html_e( '⚠ Ordner existiert nicht!', 'asmi-search' ); ?></strong>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><?php esc_html_e( 'Status:', 'asmi-search' ); ?></th>
					<td>
						<?php if ( $dir_exists ) : ?>
							<span style="color: #46b450;">
								<strong><?php esc_html_e( '✓ Aktiv', 'asmi-search' ); ?></strong>
							</span>
						<?php else : ?>
							<span style="color: #d63638;">
								<strong><?php esc_html_e( '✗ Nicht verfügbar', 'asmi-search' ); ?></strong>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><?php esc_html_e( 'Gesamtgröße:', 'asmi-search' ); ?></th>
					<td>
						<strong style="font-size: 18px; color: #2271b1;">
							<?php echo esc_html( asmi_tools_format_bytes( $total_size ) ); ?>
						</strong>
						<?php if ( $total_size > 1073741824 ) : // > 1 GB ?>
							<br><span style="color: #996800;">
								<?php
								printf(
									/* translators: %s: Formatierte Größe */
									esc_html__( '⚠ Warnung: Der Ordner ist sehr groß (%s). Erwäge eine Bereinigung.', 'asmi-search' ),
									esc_html( asmi_tools_format_bytes( $total_size ) )
								);
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><?php esc_html_e( 'Anzahl Dateien:', 'asmi-search' ); ?></th>
					<td>
						<strong><?php echo number_format_i18n( $file_count ); ?></strong> <?php esc_html_e( 'Dateien', 'asmi-search' ); ?>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><?php esc_html_e( 'Letzte Änderung:', 'asmi-search' ); ?></th>
					<td>
						<?php
						if ( $last_modified > 0 ) {
							echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_modified ) );
						} else {
							esc_html_e( '–', 'asmi-search' );
						}
						?>
					</td>
				</tr>
			</table>
		</div>
		
		<?php if ( $dir_exists && $file_count > 0 ) : ?>
		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php esc_html_e( 'Cache-Bereinigung', 'asmi-search' ); ?></h2>
			<p><?php esc_html_e( 'Lösche alle gecachten Bilder aus dem Ordner. Geschützte Dateien (.htaccess, index.html) werden nicht entfernt.', 'asmi-search' ); ?></p>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" 
				  onsubmit="return confirm('<?php echo esc_js( __( 'Möchtest du wirklich ALLE gecachten Bilder löschen? Diese Aktion kann nicht rückgängig gemacht werden!', 'asmi-search' ) ); ?>');">
				<?php wp_nonce_field( 'asmi_tools_cleanup_cache' ); ?>
				<input type="hidden" name="action" value="asmi_tools_cleanup_cache">
				
				<p>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Cache jetzt bereinigen', 'asmi-search' ); ?>
					</button>
				</p>
				
				<p class="description">
					<strong><?php esc_html_e( 'Hinweis:', 'asmi-search' ); ?></strong>
					<?php esc_html_e( 'Nach der Bereinigung werden die Bilder beim nächsten Feed-Import automatisch neu heruntergeladen.', 'asmi-search' ); ?>
				</p>
			</form>
		</div>
		<?php elseif ( $dir_exists && $file_count === 0 ) : ?>
		<div class="notice notice-info" style="max-width: 760px; margin-top: 20px;">
			<p>
				<strong><?php esc_html_e( 'Der Cache-Ordner ist leer.', 'asmi-search' ); ?></strong><br>
				<?php esc_html_e( 'Es wurden noch keine Bilder heruntergeladen oder der Ordner wurde bereits bereinigt.', 'asmi-search' ); ?>
			</p>
		</div>
		<?php endif; ?>
		
		<div class="card" style="max-width: 800px; margin-top: 20px; background: #f0f8ff;">
			<h2><?php esc_html_e( 'Automatische Bereinigung', 'asmi-search' ); ?></h2>
			<p><?php esc_html_e( 'Das Plugin verfügt über ein automatisches Garbage Collection System:', 'asmi-search' ); ?></p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><?php esc_html_e( 'Läuft täglich um 3:00 Uhr morgens', 'asmi-search' ); ?></li>
				<li><?php esc_html_e( 'Entfernt verwaiste Bilder (nicht mehr im Index)', 'asmi-search' ); ?></li>
				<li><?php esc_html_e( 'Verhindert Duplikate durch URL-Caching', 'asmi-search' ); ?></li>
			</ul>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ASMI_SLUG . '#tab-system' ) ); ?>" class="button">
					<?php esc_html_e( '→ Zu den Plugin-Einstellungen', 'asmi-search' ); ?>
				</a>
			</p>
		</div>
	</div>
	<?php
}