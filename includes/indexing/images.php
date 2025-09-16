<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action(ASMI_IMAGE_DELETE_TICK_ACTION, 'asmi_image_deletion_tick_handler');

/**
 * Holt und initialisiert das WordPress Filesystem API.
 */
function asmi_get_wp_filesystem() {
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once (ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
    }
    return $wp_filesystem;
}

/**
 * Erstellt den dedizierten Upload-Ordner und sichert ihn ab.
 * Gibt das Verzeichnis und die URL zurück.
 */
function asmi_get_image_cache_dir() {
    $upload_dir = wp_upload_dir();
    $cache_dir_path = $upload_dir['basedir'] . '/' . ASMI_UPLOAD_DIR;
    $cache_dir_url = $upload_dir['baseurl'] . '/' . ASMI_UPLOAD_DIR;
    
    if ( ! file_exists( $cache_dir_path ) ) {
        wp_mkdir_p( $cache_dir_path );
    }

    $fs = asmi_get_wp_filesystem();
    if ($fs) {
        if ( ! file_exists( $cache_dir_path . '/index.html' ) ) {
            $fs->put_contents( $cache_dir_path . '/index.html', '' );
        }
        if ( ! file_exists( $cache_dir_path . '/.htaccess' ) ) {
            $fs->put_contents( $cache_dir_path . '/.htaccess', 'Options -Indexes' );
        }
    }

    return ['path' => $cache_dir_path, 'url' => $cache_dir_url];
}

/**
 * Lädt ein Bild von einer URL und speichert es direkt im Dateisystem.
 */
function asmi_download_image_to_local_dir($url) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return new WP_Error('invalid_url', __('Ungültige Bild-URL.', 'asmi-search'));
    }

    $filename = basename(parse_url($url, PHP_URL_PATH));
    $sanitized_filename = sanitize_file_name($filename);

    $cache_dir = asmi_get_image_cache_dir();
    
    $unique_filename = wp_unique_filename($cache_dir['path'], $sanitized_filename);
    $target_file = trailingslashit($cache_dir['path']) . $unique_filename;

    $response = wp_remote_get($url, ['timeout' => 15, 'stream' => true, 'filename' => $target_file]);

    if (is_wp_error($response)) {
        asmi_debug_log('Image download failed for ' . $url . ': ' . $response->get_error_message());
        return $response;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        // Lösche die (wahrscheinlich leere) Datei, die bei einem Fehler erstellt wurde
        if (file_exists($target_file)) {
            unlink($target_file);
        }
        return new WP_Error('download_failed', sprintf(__('Bild-Download fehlgeschlagen (HTTP-Code: %s)', 'asmi-search'), $http_code));
    }

    return trailingslashit($cache_dir['url']) . $unique_filename;
}


/**
 * Löscht den gesamten Bilder-Cache-Ordner.
 */
function asmi_delete_image_cache_folder() {
    $fs = asmi_get_wp_filesystem();
    if (!$fs) return false;

    $cache_dir = asmi_get_image_cache_dir();
    
    return $fs->rmdir($cache_dir['path'], true);
}


/**
 * Startet den asynchronen Löschprozess für die Bilder im Cache-Ordner.
 */
function asmi_start_image_folder_deletion() {
    $cache_dir = asmi_get_image_cache_dir();
    $files = glob($cache_dir['path'] . '/*');
    if (empty($files)) {
        asmi_set_image_deletion_state(['status' => 'finished', 'total' => 0, 'deleted' => 0, 'finished_at' => time()]);
        return;
    }
    
    $image_files = array_filter($files, function($file) {
        return !in_array(basename($file), ['index.html', '.htaccess']);
    });

    if (empty($image_files)) {
        asmi_set_image_deletion_state(['status' => 'finished', 'total' => 0, 'deleted' => 0, 'finished_at' => time()]);
        return;
    }

    $state = [
        'status'  => 'deleting',
        'total'   => count($image_files),
        'deleted' => 0,
        'files'   => array_values($image_files), // Re-index array
        'offset'  => 0,
        'started_at' => time(),
    ];
    asmi_set_image_deletion_state($state);
    asmi_schedule_next_image_delete_tick();
}

/**
 * Plant den nächsten Tick für den Bild-Löschprozess.
 */
function asmi_schedule_next_image_delete_tick() {
    $token = get_option('asmi_tick_token');
    if (empty($token)) {
        $token = wp_generate_password(64, false, false);
        update_option('asmi_tick_token', $token);
    }
    $url = rest_url(ASMI_REST_NS . '/images/delete/tick');
    $args = ['method' => 'POST', 'timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters('https_local_ssl_verify', false), 'body' => ['token' => $token]];
    wp_remote_post($url, $args);
}

/**
 * Der Handler, der pro Tick eine Charge von Bildern löscht.
 */
function asmi_image_deletion_tick_handler() {
    $st = asmi_get_image_deletion_state();
    if ($st['status'] !== 'deleting') return;

    $batch_size = 200;
    $files_slice = array_slice($st['files'], $st['offset'], $batch_size);

    if (empty($files_slice)) {
        $st['status'] = 'finished';
        $st['finished_at'] = time();
        asmi_set_image_deletion_state($st);
        return;
    }

    $fs = asmi_get_wp_filesystem();
    if ($fs) {
        foreach ($files_slice as $file_path) {
            if ($fs->exists($file_path)) {
                $fs->delete($file_path);
            }
        }
    }
    
    $st['deleted'] += count($files_slice);
    $st['offset'] += $batch_size;
    asmi_set_image_deletion_state($st);

    if ($st['status'] === 'deleting') {
      asmi_schedule_next_image_delete_tick();
    }
}