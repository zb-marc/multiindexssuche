<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Diese Aktionen dienen jetzt als Fallback, falls JavaScript im Browser des Nutzers
// deaktiviert ist oder ein Fehler auftritt. Die primäre Interaktion läuft
// über die REST API und die assets/admin.js Datei.

// Neuindexierung
add_action('admin_post_asmi_reindex', function(){
  if (!current_user_can('manage_options')) wp_die('forbidden');
  check_admin_referer('asmi_reindex');
  asmi_index_reset_and_start();
  wp_safe_redirect( admin_url('admin.php?page='.ASMI_SLUG.'#tab-index') );
  exit;
});

// Index leeren
add_action('admin_post_asmi_clear', function(){
  if (!current_user_can('manage_options')) wp_die('forbidden');
  check_admin_referer('asmi_clear');
  asmi_index_clear_table();
  wp_safe_redirect( admin_url('admin.php?page='.ASMI_SLUG.'#tab-index') );
  exit;
});

// Repair / fehlende DB-Spalten ergänzen
add_action('admin_post_asmi_repair', function(){
  if (!current_user_can('manage_options')) wp_die('forbidden');
  check_admin_referer('asmi_repair');
  try {
    // KORREKTUR: Ruft die neue, korrekte und zentrale Reparaturfunktion auf.
    asmi_install_and_repair_database();
    asmi_debug_log("Repair ausgeführt: Tabelle geprüft/ergänzt.");
  } catch (Exception $e){
    asmi_debug_log("Repair Fehler: ".$e->getMessage());
  }
  wp_safe_redirect( admin_url('admin.php?page='.ASMI_SLUG.'#tab-system') );
  exit;
});

// Importierte Bilder löschen (Fallback)
add_action('admin_post_asmi_delete_images', function(){
  if (!current_user_can('manage_options')) wp_die('forbidden');
  check_admin_referer('asmi_delete_images');
  if (function_exists('asmi_start_image_folder_deletion')) {
      asmi_start_image_folder_deletion();
  }
  wp_safe_redirect( admin_url('admin.php?page='.ASMI_SLUG.'#tab-system') );
  exit;
});