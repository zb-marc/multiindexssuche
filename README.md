# AS Multiindex Search

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.11.1-orange.svg)](https://github.com/zb-marc/multiindexssuche/releases)

Eine föderierte Suche für WordPress, die native WordPress-Inhalte und mehrsprachige, externe Produktfeeds (XML, CSV, JSON) in einer nahtlosen AJAX-Suche zusammenführt.

## 🌟 Features

### Kern-Funktionalitäten
- **Multi-Source-Suche**: Durchsucht gleichzeitig WordPress-Inhalte und externe Produktfeeds
- **Mehrsprachigkeit**: Vollständige Unterstützung für Deutsch und Englisch
- **Feed-Import**: Unterstützt XML, CSV und JSON Formate
- **Intelligente Indexierung**: Asynchrone Verarbeitung großer Datenmengen
- **ChatGPT-Integration**: Optionale KI-gestützte Inhaltsanalyse und Übersetzung

### Erweiterte Features
- **REST API**: Vollständige API für externe Integrationen
- **High-Speed Indexing**: Optimierte Verarbeitung für große Datenmengen
- **Smart Caching**: Intelligente Cache-Verwaltung für optimale Performance
- **URL-basiertes Bild-Caching**: Vermeidet Duplikate und reduziert Speicherverbrauch drastisch
- **Automatisches Image Cleanup**: Garbage Collection für verwaiste Bilder
- **Export/Import**: WordPress-Index als CSV exportieren und importieren
- **Marken-Erkennung**: Automatische Erkennung und Kategorisierung von Marken
- **Bindestrich-Suche**: Intelligente Behandlung von Begriffen mit Bindestrichen
- **Keyword-Fallback**: Automatische Keyword-Extraktion auch ohne ChatGPT

## 📦 Installation

### Voraussetzungen
- WordPress 5.8 oder höher
- PHP 7.4 oder höher
- MySQL 5.6 oder höher
- WP-Cron aktiviert (oder alternatives Cron-Setup)

### Installation via WordPress Admin
1. ZIP-Datei des Plugins herunterladen
2. Im WordPress Admin zu **Plugins → Installieren → Plugin hochladen** navigieren
3. ZIP-Datei auswählen und hochladen
4. Plugin aktivieren

### Manuelle Installation
```bash
# In das WordPress Plugin-Verzeichnis wechseln
cd wp-content/plugins/

# Plugin-Dateien kopieren
cp -r /path/to/as-multiindex-search ./

# Berechtigungen setzen
chmod 755 as-multiindex-search
chmod 644 as-multiindex-search/*.php
```

## ⚙️ Konfiguration

### Grundkonfiguration

1. **Feed-URLs einrichten** (Admin → Multiindex → Allgemein)
   - Deutsche Feeds: Kommaseparierte Liste von Feed-URLs
   - Englische Feeds: Separate Feed-Quellen für englische Inhalte

2. **Mapping konfigurieren** (Admin → Multiindex → Mapping)
   - Vordefinierte Presets: Shopware 5/6, Google Merchant, CSV
   - Custom Mapping für individuelle Feed-Strukturen

3. **WordPress-Inhalte** (Admin → Multiindex → Index)
   - Post-Types auswählen (Standard: post, page)
   - Ausgeschlossene IDs definieren

4. **Bildverwaltung** (Admin → Multiindex → System)
   - Lokale Bildspeicherung aktivieren
   - Automatische Bereinigung konfigurieren
   - Fallback-Bilder definieren

### API-Integration

#### ChatGPT (Empfohlen)
```php
// In den Plugin-Einstellungen:
- ChatGPT aktivieren: ✓
- API Key: sk-...
- Model: gpt-4o-mini (empfohlen für Kosten/Nutzen)
- Optional: Assistant ID für spezialisierte Verarbeitung
```

#### DeepL (Fallback)
```php
// Als Fallback für Übersetzungen:
- DeepL API Key: xxx-xxx-xxx
```

## 🔍 Verwendung

### Shortcode für die Suche
```php
// Deutsche Suche
[multiindex_search lang="de"]

// Englische Suche
[multiindex_search lang="en"]
```

### REST API Endpoints

#### Suche durchführen
```bash
GET /wp-json/asmi/v1/search?q=suchbegriff&lang=de
```

**Response:**
```json
{
  "query": "suchbegriff",
  "lang": "de",
  "count": 15,
  "results": {
    "products": [...],
    "wordpress": [...]
  }
}
```

#### Index-Status abrufen
```bash
GET /wp-json/asmi/v1/index/status
```

#### WordPress-Inhalte neu indexieren
```bash
POST /wp-json/asmi/v1/wp-index/start
```

## 🛠️ Entwickler-Dokumentation

### Hooks & Filter

#### Actions
```php
// Nach erfolgreicher Indexierung
do_action('asmi_after_index_complete', $stats);

// Vor Feed-Import
do_action('asmi_before_feed_import', $feed_url, $lang);

// Nach Post-Indexierung
do_action('asmi_after_post_indexed', $post_id, $languages);

// Nach Bildbereinigung
do_action('asmi_after_image_cleanup', $deleted_count);
```

#### Filter
```php
// Feed-Items vor Verarbeitung modifizieren
$items = apply_filters('asmi_feed_items', $items, $feed_url);

// Suchergebnisse modifizieren
$results = apply_filters('asmi_search_results', $results, $query, $lang);

// Cache-TTL anpassen
$ttl = apply_filters('asmi_cache_ttl', 900);

// Bildverarbeitung anpassen
$should_download = apply_filters('asmi_should_download_image', true, $url);
```

### Datenbank-Struktur

#### Haupttabelle: `wp_asmi_index`
```sql
CREATE TABLE wp_asmi_index (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT,
    source_id VARCHAR(255) NOT NULL,
    lang VARCHAR(10) NOT NULL,
    source_type VARCHAR(20) NOT NULL,
    title TEXT NOT NULL,
    content LONGTEXT,
    excerpt TEXT,
    url VARCHAR(2048),
    image VARCHAR(2048),
    image_url_hash VARCHAR(32),
    price VARCHAR(50),
    sku VARCHAR(100),
    gtin VARCHAR(100),
    raw_data LONGTEXT,
    content_hash VARCHAR(64),
    last_modified DATETIME,
    indexed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unq_source (source_id, lang, source_type),
    KEY idx_image_url_hash (image_url_hash),
    FULLTEXT KEY ft_search (title, content, excerpt)
);
```

### Programmatische Nutzung

#### Direkte Suche durchführen
```php
// Suche mit PHP
$results = asmi_unified_search(
    'suchbegriff',  // Query
    20,             // Limit
    'de'            // Sprache
);

// Ergebnisse verarbeiten
foreach ($results['products'] as $product) {
    echo $product['title'];
    echo $product['url'];
    echo $product['price'];
}
```

#### Feed manuell importieren
```php
// Feed-Import starten
asmi_index_reset_and_start();

// WordPress-Inhalte indexieren
asmi_start_wp_content_indexing();

// Bildbereinigung manuell starten
asmi_run_image_cleanup();
```

#### Bildverwaltung
```php
// Bild mit URL-Caching herunterladen
$local_url = asmi_download_image_to_local_dir($remote_url);

// Cache-Verzeichnis abrufen
$cache_info = asmi_get_image_cache_dir();
echo $cache_info['path']; // Lokaler Pfad
echo $cache_info['url'];  // Öffentliche URL

// Verwaiste Bilder bereinigen
asmi_run_image_cleanup();
```

## 📊 Performance-Optimierung

### Empfohlene Einstellungen

1. **High-Speed Indexing**: Aktivieren für große Datenmengen
2. **Batch-Size**: 200 (Standard) - bei Speicherproblemen reduzieren
3. **Cache-TTL**: 900 Sekunden (15 Minuten) für Feeds
4. **ChatGPT-Model**: gpt-4o-mini für optimales Kosten-Nutzen-Verhältnis
5. **Bildverwaltung**: URL-basiertes Caching aktivieren für Speicherersparnis

### Cron-Jobs

```bash
# Feed-Cache vorwärmen (stündlich)
wp cron event run asmi_cron_warmup

# WordPress-Inhalte indexieren (täglich)
wp cron event run asmi_cron_wp_content_index

# Feed-Import (täglich um 1:00 Uhr)
wp cron event run asmi_cron_reindex

# Bildbereinigung (täglich um 3:00 Uhr)
wp cron event run asmi_do_image_cleanup
```

### Speicher-Optimierung

Die URL-basierte Bildverwaltung reduziert den Speicherverbrauch erheblich:
- **Vor Optimierung**: ~26GB Bild-Cache
- **Nach Optimierung**: ~6-7GB Bild-Cache
- **Einsparung**: ~75% weniger Speicherverbrauch

## 🐛 Debugging

### Debug-Modus aktivieren
```php
// In den Plugin-Einstellungen:
Debug Mode: ✓ aktiviert

// Logs einsehen:
tail -f wp-content/debug.log | grep "ASMI"
```

### Häufige Probleme

#### Loopback-Request fehlgeschlagen
```bash
# .htaccess prüfen
# Firewall-Regeln checken
# Alternative: High-Speed Indexing deaktivieren
```

#### Speicherlimit-Fehler
```php
// wp-config.php anpassen:
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

#### UTF-8 Encoding Probleme
```php
// Bereits in Version 1.11.1 behoben
// Deutsche Umlaute werden korrekt verarbeitet
```

#### Hoher Speicherverbrauch durch Bilder
```php
// Lösung: URL-basiertes Caching aktivieren
// Automatische Bereinigung aktivieren
// Bringt bis zu 75% Speichereinsparung
```

## 📈 Changelog

### Version 1.11.1 (17. November 2025)
- **Storage-Optimierung**: URL-basiertes Bild-Caching reduziert Speicherverbrauch von ~26GB auf ~6-7GB (~75% Einsparung)
- **Automatisches Cleanup**: Garbage Collection System für verwaiste Bilder (täglich um 3:00 Uhr)
- **Image URL Hash**: Neue Spalte `image_url_hash` verhindert Duplikate durch MD5-basierte Speicherung
- **Robuste Fehlerbehandlung**: Verbesserte Behandlung von "Cannot redeclare" Funktionskonflikten
- **UTF-8 Encoding**: Korrekte Verarbeitung deutscher Umlaute (ä, ö, ü, ß)
- **Bindestriche-Handling**: Optimierte Suche für technische Begriffe wie "Q-Batteries"
- **Keyword-Fallback**: Automatische Keyword-Extraktion auch ohne ChatGPT-Integration
- **Cloudflare Bypass**: Verbesserte Feed-Verarbeitung mit Cloudflare-Schutz
- **Database Repair**: Umfassende Reparaturfunktionen für Datenbankstruktur
- **Performance**: Intelligente Change Detection reduziert unnötige Re-Indexierung

### Version 1.11.0 (November 2025)
- **Batch Processing**: Intelligente Batchverarbeitung mit Token-Bucket-System
- **API-Optimierung**: Reduzierte ChatGPT API-Requests durch Change Detection
- **Asynchrone Verarbeitung**: Verbesserte Background-Processing-Engine
- **Cancellation Support**: Sichere Abbruchsmechanismen für laufende Prozesse

### Version 1.10.5 (29. September 2025)
- **Kritischer Bugfix**: Behebt "Cannot redeclare" Fatal Errors bei asynchroner Verarbeitung
- **Include-Guards**: Robuster Schutz gegen Mehrfacheinbindungen mit `function_exists()` 
- **SQL-Fix**: Korrektur der Spaltenbezeichnung in Cleanup-Funktion (`ID` statt `post_id`)
- **REST API Stabilität**: Routes werden nun zuverlässig registriert
- **Performance**: Stabile Verarbeitung auch bei hoher Last und parallelen Requests

### Version 1.10.4 (28. September 2025)
- **Asynchrone Stabilität**: Verbesserte Fehlerbehandlung bei WordPress-Indexierung
- **Memory Management**: Optimierte Speichernutzung bei großen Batches
- **Cache-Optimierung**: Intelligentere Hash-Vergleiche verhindern unnötige Neuindexierung
- **Statistik-Erweiterung**: Neue Metriken für übersprungene und aktualisierte Posts
- **Adaptive Batch-Size**: Automatische Anpassung basierend auf Verarbeitungsgeschwindigkeit

### Version 1.10.3 (September 2025)
- Verbesserte ChatGPT-Integration mit Assistant-Support
- Erweiterte Marken-Erkennung
- Optimierte Bindestrich-Suche
- Bugfixes bei der Spracherkennung

### Version 1.10.0 (September 2025)
- ChatGPT-Integration hinzugefügt
- Asynchrone WordPress-Indexierung
- Export/Import-Funktionalität

### Version 1.9.0 (September 2025)
- Multi-Language Support
- REST API erweitert
- Performance-Optimierungen

## 🤝 Support & Beitrag

### Support
- **Website**: [https://akkusys.de](https://akkusys.de)
- **Entwickler**: Marc Mirschel
- **Entwickler-Website**: [https://mirschel.biz](https://mirschel.biz)

### Fehler melden
Bitte erstellen Sie detaillierte Fehlerberichte mit:
- WordPress-Version
- PHP-Version
- Debug-Log-Auszüge
- Schritte zur Reproduktion

### Beitragen
Pull Requests sind willkommen! Bitte beachten Sie:
- WordPress Coding Standards einhalten
- PHPDoc für alle Funktionen
- Internationalisierung für alle User-Strings
- Unit-Tests für neue Features

## 📄 Lizenz

Dieses Plugin ist unter der GPL-2.0+ Lizenz veröffentlicht. 

## 🙏 Credits

- **Entwicklung**: Marc Mirschel
- **Sponsor**: AKKUSYS GmbH
- **APIs**: OpenAI (ChatGPT), DeepL
- **Framework**: WordPress

## 🎯 Roadmap

### Geplante Features

**Phase 1: Quick Wins (Kurzfristig)**
- Levenshtein-Distanz für Tippfehlerkorrektur
- Boost-Faktoren für Produktergebnisse
- Rate Limiting mit Token-Bucket-Algorithmus

**Phase 2: Analytics & Insights (Mittelfristig)**
- Such-Analytics Dashboard
- Performance-Monitoring
- Click-Analytics für Suchergebnisse

**Phase 3: Advanced Features (Langfristig)**
- Content Security Policy für externe Bilder
- Personalisierte Empfehlungen
- Autocomplete-Vorschläge
- Facettierte Filterung

## 🔧 Technische Details

### Architektur
- **Asynchrones Processing**: Tick-basiertes System für stabile Verarbeitung
- **State Management**: Persistente Zustandsspeicherung
- **Modular Design**: Klare Trennung von Indexing, API und Frontend
- **Security First**: Nonce-Verifizierung, Prepared Statements, Capability Checks

### Performance-Metriken
- **Indexierung**: ~200 Einträge pro Batch (konfigurierbar)
- **Such-Performance**: <100ms für durchschnittliche Suchen
- **API-Effizienz**: Intelligente Caching-Strategie reduziert API-Calls um ~80%
- **Speicheroptimierung**: URL-basiertes Caching spart bis zu 75% Speicher

---

*AS Multiindex Search - Intelligente föderierte Suche für WordPress und externe Datenquellen*