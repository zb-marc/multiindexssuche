# AS Multiindex Search

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.11.2-orange.svg)](https://github.com/zb-marc/multiindexssuche/releases)

Eine föderierte Suche für WordPress, die native WordPress-Inhalte und mehrsprachige, externe Produktfeeds (XML, CSV, JSON) in einer nahtlosen AJAX-Suche zusammenführt.

## 🌟 Features

### Kern-Funktionalitäten
- **Multi-Source-Suche**: Durchsucht gleichzeitig WordPress-Inhalte und externe Produktfeeds
- **Mehrsprachigkeit**: Vollständige Unterstützung für Deutsch und Englisch
- **Feed-Import**: Unterstützt XML, CSV und JSON Formate
- **Intelligente Indexierung**: Asynchrone Verarbeitung großer Datenmengen mit Änderungserkennung
- **ChatGPT-Integration**: Optionale KI-gestützte Inhaltsanalyse und Übersetzung

### Erweiterte Features
- **REST API**: Vollständige API für externe Integrationen
- **High-Speed Indexing**: Optimierte Verarbeitung für große Datenmengen
- **Smart Caching**: Intelligente Cache-Verwaltung für optimale Performance
- **URL-basiertes Bild-Caching**: Vermeidet Duplikate und reduziert Speicherverbrauch drastisch
- **Intelligente Änderungserkennung**: Lädt Bilder nur bei tatsächlichen Änderungen herunter (>95% Einsparung)
- **Automatisches Image Cleanup**: Garbage Collection für verwaiste Bilder
- **Export/Import**: WordPress-Index als CSV exportieren und importieren
- **Marken-Erkennung**: Automatische Erkennung und Kategorisierung von Marken
- **Bindestrich-Suche**: Intelligente Behandlung von Begriffen mit Bindestrichen
- **Keyword-Fallback**: Automatische Keyword-Extraktion auch ohne ChatGPT

## 🚀 Schnellstart

```bash
# 1. Plugin installieren
wp plugin install as-multiindex-search.zip --activate

# 2. Feed-URLs konfigurieren
# Admin → Multiindex → Allgemein

# 3. Ersten Import starten
# Admin → Multiindex → Index → "Start Import"
```

Detaillierte Installationsanleitung: Siehe [INSTALLATION.md](INSTALLATION.md)

## 📦 Voraussetzungen

- WordPress 5.8 oder höher
- PHP 7.4 oder höher
- MySQL 5.6 oder höher (mit FULLTEXT-Support)
- WP-Cron aktiviert (oder alternatives Cron-Setup)
- Mindestens 256MB PHP Memory Limit

## ⚙️ Grundkonfiguration

### 1. Feed-URLs einrichten
```
Admin → Multiindex → Allgemein
├── Deutsche Feeds: https://example.com/feed-de.xml
└── Englische Feeds: https://example.com/feed-en.xml
```

### 2. Mapping konfigurieren
```
Admin → Multiindex → Mapping
├── Preset wählen: Shopware 6
└── Oder Custom Mapping definieren
```

### 3. Bildverwaltung aktivieren
```
Admin → Multiindex → System
├── Image Storage Mode: Local ✓
├── Enable Daily Reindexing: ✓ (um 1:00 Uhr)
└── Image Cleanup: Automatisch (täglich um 3:00 Uhr)
```

### 4. ChatGPT Integration (Optional)
```
Admin → Multiindex → API
├── ChatGPT aktivieren: ✓
├── API Key: sk-...
└── Model: gpt-4o-mini (empfohlen)
```

## 📝 Verwendung

### Shortcode
```php
// Deutsche Suche
[multiindex_search lang="de"]

// Englische Suche
[multiindex_search lang="en"]
```

### REST API
```bash
# Suche durchführen
GET /wp-json/asmi/v1/search?q=batterie&lang=de

# Index-Status abrufen
GET /wp-json/asmi/v1/index/status

# WordPress-Inhalte neu indexieren
POST /wp-json/asmi/v1/wp-index/start
```

### PHP-Integration
```php
// Direkte Suche
$results = asmi_unified_search('batterie', 20, 'de');

// Feed-Import starten
asmi_index_reset_and_start();

// Bild mit Caching herunterladen
$local_url = asmi_download_image_to_local_dir($remote_url);
```

## 🎯 Neue Features in v1.11.2

### Intelligente Änderungserkennung
- **Problem behoben**: Bilder wurden bei jedem täglichen Durchlauf erneut heruntergeladen
- **Lösung**: Content-Hash und Image-URL-Hash prüfen Änderungen
- **Ergebnis**: >95% weniger Bild-Downloads

### Optimierte Produktverwaltung
- Keine komplette Löschung mehr bei jedem Durchlauf
- Nur Updates/Inserts für geänderte/neue Produkte
- Automatische Entfernung obsoleter Produkte inkl. Bilder

### Erweiterte Statistiken
```
Indexierungs-Report:
├── Neue Produkte: 45
├── Aktualisierte Produkte: 12
├── Bilder wiederverwendet: 1.234
└── Bilder heruntergeladen: 57
```

## 📊 Performance

### Speicher-Optimierung (seit v1.11.0)
- **Vorher**: ~26GB Bild-Cache
- **Nachher**: ~6-7GB Bild-Cache
- **Einsparung**: ~75% durch URL-basiertes Caching

### Download-Optimierung (v1.11.2)
- **Vorher**: 100% Downloads bei jedem Durchlauf
- **Nachher**: <5% Downloads (nur bei Änderungen)
- **Einsparung**: >95% weniger Bandbreite

### Indexierungs-Performance
- **Batch-Size**: 200 Einträge (konfigurierbar)
- **Such-Performance**: <100ms für durchschnittliche Suchen
- **API-Effizienz**: ~80% weniger ChatGPT-Calls durch Change Detection

## 🔧 Entwickler-Dokumentation

### Wichtige Hooks

```php
// Nach erfolgreicher Indexierung
add_action('asmi_after_index_complete', function($stats) {
    error_log('Indexing completed: ' . $stats['processed_items'] . ' items');
});

// Suchergebnisse modifizieren
add_filter('asmi_search_results', function($results, $query, $lang) {
    // Ergebnisse anpassen
    return $results;
}, 10, 3);

// Bildverarbeitung anpassen
add_filter('asmi_should_download_image', function($should_download, $url) {
    // Entscheidung ob Bild geladen werden soll
    return $should_download;
}, 10, 2);
```

### Datenbank-Struktur

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
    image_url_hash VARCHAR(32),      -- NEU in v1.11.0
    content_hash VARCHAR(64),        -- NEU in v1.11.2
    last_modified DATETIME,          -- NEU in v1.11.2
    price VARCHAR(50),
    sku VARCHAR(100),
    gtin VARCHAR(100),
    raw_data LONGTEXT,
    indexed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unq_source (source_id, lang, source_type),
    KEY idx_image_hash (image_url_hash),
    KEY idx_content_hash (content_hash),
    FULLTEXT KEY ft_search (title, content, excerpt, sku, gtin)
);
```

## 🛠️ Debugging

### Debug-Modus aktivieren
```php
// In Plugin-Einstellungen:
Admin → Multiindex → System → Debug Mode ✓

// Logs ansehen:
tail -f wp-content/debug.log | grep "ASMI"
```

### Typische Log-Ausgaben
```
[ASMI DEBUG] Image URL unchanged for product ABC123, reusing existing image
[ASMI DEBUG] Image URL changed for product XYZ789, downloading...
[ASMI DEBUG] CLEANUP: Deleted 15 obsolete products and their images
```

## 📈 Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für detaillierte Versionshistorie.

**Aktuelle Version: 1.11.2** (19. November 2025)
- Intelligente Änderungserkennung für Bilder
- Optimierte Produktverwaltung ohne komplette Löschung
- Detaillierte Statistiken (new/updated/reused/downloaded)
- Automatische Bereinigung obsoleter Produkte

## 🤝 Support & Entwicklung

### Support
- **Website**: [https://akkusys.de](https://akkusys.de)
- **Entwickler**: Marc Mirschel
- **Website**: [https://mirschel.biz](https://mirschel.biz)
- **Repository**: [GitHub](https://github.com/zb-marc/multiindexssuche)

### Fehler melden
Erstellen Sie detaillierte Bug-Reports mit:
- WordPress-Version
- PHP-Version
- Debug-Log (aktiviert im Plugin)
- Schritte zur Reproduktion

### Beitragen
Pull Requests sind willkommen! Beachten Sie:
- WordPress Coding Standards
- PHPDoc für alle Funktionen
- Internationalisierung (i18n)
- Security Best Practices

## 📄 Lizenz

GPL-2.0+ - Siehe [LICENSE](LICENSE)

## 🙏 Credits

- **Entwicklung**: Marc Mirschel
- **Sponsor**: AKKUSYS GmbH
- **APIs**: OpenAI (ChatGPT), DeepL
- **Framework**: WordPress

## 🎯 Roadmap

### In Entwicklung
- [ ] Levenshtein-Distanz für Tippfehlerkorrektur
- [ ] Such-Analytics Dashboard
- [ ] Performance-Monitoring
- [ ] Autocomplete-Vorschläge

### Geplant
- [ ] Facettierte Filterung
- [ ] Personalisierte Empfehlungen
- [ ] GraphQL API
- [ ] Elasticsearch-Integration

---

**AS Multiindex Search** - Intelligente föderierte Suche für WordPress und externe Datenquellen

*Entwickelt von Marc Mirschel für AKKUSYS GmbH*
