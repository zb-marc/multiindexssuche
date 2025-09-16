<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Preis-Parsing: akzeptiert "0.96 EUR", "3,90 €", "53.17 EUR" etc. */
function asmi_parse_price($raw) {
  if ($raw === null || $raw === '') return null;
  if (is_numeric($raw)) return (float)$raw;
  $s = trim( wp_strip_all_tags( (string)$raw ) );
  $s_clean = preg_replace('/[^0-9\.,]/', '', $s);
  if ($s_clean === '') return null;
  $last_comma = strrpos($s_clean, ',');
  $last_dot   = strrpos($s_clean, '.');
  if ($last_comma !== false && ($last_dot === false || $last_comma > $last_dot)) {
    $s_clean = str_replace('.', '', $s_clean);   // tausenderpunkt
    $s_clean = str_replace(',', '.', $s_clean);  // dezimalkomma -> punkt
  } else {
    $s_clean = str_replace(',', '', $s_clean);   // tausenderkomma
  }
  return is_numeric($s_clean) ? (float)$s_clean : null;
}

/** CSV → Array (assoziativ, Header aus erster Zeile) */
function asmi_csv_to_array($csv_string){
  $csv_string = str_replace(["\r\n","\r"], "\n", $csv_string);
  $lines = explode("\n", trim($csv_string));
  if (count($lines) < 1) return [];
  $header = null; $rows = [];
  foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $fields = str_getcsv($line, ',', '"', "\\");
    if ($header === null) {
      $header = array_map('trim', $fields);
      continue;
    }
    $row = [];
    foreach ($fields as $i => $val) {
      $key = isset($header[$i]) ? $header[$i] : 'col'.$i;
      $row[$key] = $val;
    }
    $rows[] = $row;
  }
  return $rows;
}

/** XML → Array mit Namespace-Unterstützung (z. B. g:...) */
function asmi_xml_to_array_ns($xml_string){
  libxml_use_internal_errors(true);
  $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
  if ($xml === false) return [];

  $items = [];
  $nsAll = $xml->getDocNamespaces(true);
  if (isset($xml->channel)) {
    foreach ($xml->channel->item as $item) {
      $arr = [];
      foreach ($item->children() as $k => $v) {
        $arr[$k] = (string)$v;
      }
      foreach ($nsAll as $prefix => $uri) {
        foreach ($item->children($uri) as $k => $v) {
          $arr[$prefix . ':' . $k] = (string)$v;
        }
      }
      $items[] = $arr;
    }
    return $items;
  }

  $json = json_encode($xml);
  $arr = json_decode($json, true);
  if (isset($arr['channel']['item'])) return $arr['channel']['item'];
  if (isset($arr['item'])) return $arr['item'];
  if (isset($arr['entry'])) return $arr['entry'];
  return $arr;
}

/** Lädt URL, erkennt Typ (CSV/XML/JSON) und normalisiert Items */
function asmi_fetch_items($url, $o){
  $key = 'asmi_'.md5($url);
  $cached = get_transient($key);
  if (!$cached){
    $res = wp_remote_get($url, ['timeout'=>60]);
    if (is_wp_error($res)) {
      return ['error' => 'Feed konnte nicht geladen werden: ' . $res->get_error_message()];
    }
    $body = wp_remote_retrieve_body($res);
    set_transient($key, $body, (int)$o['cache_ttl']);
    $cached = $body;
  }
  $body = trim((string)$cached); if ($body==='') return [];

  $is_json = ($body !== '' && ($body[0]==='{' || $body[0]==='['));
  $is_xml  = (!$is_json && strlen($body) && $body[0]==='<');
  $raw = [];
  if ($is_json) {
    $raw = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return ['error' => 'JSON-Parsing-Fehler: ' . json_last_error_msg()];
    }
  } elseif ($is_xml) {
    $raw = asmi_xml_to_array_ns($body);
  } else {
    $raw = asmi_csv_to_array($body);
  }

  if (!is_array($raw)) return [];
  if (isset($raw['error'])) return $raw;

  $items = isset($raw[0]) ? $raw : ( $raw['data'] ?? ($raw['items'] ?? $raw) );

  $norm = [];
  foreach ($items as $it){
    if (!is_array($it)) continue;

    $final_item = [];
    foreach ($it as $key => $value) {
        if(is_scalar($value)) {
            // KORREKTUR: Schlüssel sanieren für die Verwendung als Meta-Key
            $clean_key = sanitize_key(str_replace(':', '_', $key));
            $final_item[$clean_key] = (string)$value;
        }
    }
    $norm[] = $final_item;
  }
  return $norm;
}