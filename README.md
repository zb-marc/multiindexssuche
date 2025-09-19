# Multiindex Search

**Version:** 1.9.3  
**Author:** Marc Mirschel  
**Website:** [zoobro.de](https://zoobro.de)  
**License:** GPL-2.0+  
**Requires:** WordPress 5.8+, PHP 7.4+

## 📋 Überblick

Multiindex Search ist ein hochleistungsfähiges WordPress-Plugin für föderierte Suche, das externe Produktfeeds und native WordPress-Inhalte in einer einheitlichen, mehrsprachigen AJAX-Suchlösung zusammenführt. Speziell entwickelt für E-Commerce-Websites im Solar- und Batteriebereich, bietet es intelligente Inhaltsanalyse durch ChatGPT/OpenAI Assistant API Integration.

## ✨ Hauptfunktionen

### 🔍 Föderierte Suche
- **Unified Search Interface**: Durchsucht gleichzeitig externe Produktdatenbanken und WordPress-Inhalte
- **Modal AJAX Search**: Elegante, nicht-blockierende Sucherfahrung mit Echtzeit-Ergebnissen
- **Tabbed Results**: Getrennte Anzeige für Produkte und Informationsseiten
- **Smart Ranking**: Konfigurierbares Relevanz-Scoring mit Gewichtung

### 🌍 Mehrsprachigkeit
- **Dual Language Support**: Vollständige Unterstützung für Deutsch (DE) und Englisch (EN)
- **Separate Feed Sources**: Unterschiedliche Produktfeeds für jede Sprache
- **Auto-Translation**: Intelligente Übersetzung von WordPress-Inhalten via ChatGPT oder DeepL

### 📊 Feed-Import & Indexierung
- **Multi-Format Support**: XML (inkl. Google Merchant), CSV, JSON
- **Auto-Detection**: Automatische Erkennung von Feed-Strukturen (Shopware 5/6, Google Merchant)
- **Background Processing**: Asynchrone Indexierung ohne Performance-Einbußen
- **High-Speed Mode**: Optimierter Import für große Datenmengen (>10.000 Produkte)
- **Image Caching**: Lokale Speicherung von Produktbildern mit automatischer Verwaltung

### 🤖 KI-Integration
- **ChatGPT/GPT-4o Integration**: Intelligente Inhaltsanalyse und Zusammenfassung
- **OpenAI Assistant Support**: Nutzung vorkonfigurierter Assistenten mit Markenwissen
- **Smart Brand Detection**: Automatische Erkennung von Marken und technischen Spezifikationen
- **Fallback Mechanism**: DeepL-Integration als Backup für Übersetzungen

### 📈 WordPress Content Processing
- **Post Type Support**: Flexibel konfigurierbare Post-Types (Posts, Pages, Custom)
- **Incremental Updates**: Automatische Aktualisierung bei Content-Änderungen
- **Manual Import Protection**: Schutz manuell importierter Übersetzungen
- **Excluded IDs**: Möglichkeit zum Ausschluss spezifischer Posts

## 🚀 Installation

### Systemanforderungen
- WordPress 5.8 oder höher
- PHP 7.4 oder höher
- MySQL 5.7+ mit FULLTEXT Index Support
- WP Cron oder externe Cron-Jobs
- Min. 256MB PHP Memory Limit (512MB empfohlen)

### Installation via WordPress Admin
1. Plugin-ZIP hochladen unter `Plugins > Installieren > Plugin hochladen`
2. Plugin aktivieren
3. Menüpunkt "Multiindex" im WordPress-Admin aufrufen
4. Grundkonfiguration vornehmen

### Manuelle Installation
```bash
# In WordPress Plugin-Verzeichnis wechseln
cd wp-content/plugins/

# Plugin-Verzeichnis erstellen
mkdir as-multiindex-search

# Plugin-Dateien kopieren
# ... Dateien hierhin kopieren ...

# Rechte setzen
chmod -R 755 as-multiindex-search
chown -R www-data:www-data as-multiindex-search
```

## ⚙️ Konfiguration

### 1. Feed-Einrichtung (Tab: Allgemein)
```
Feed-URLs (Deutsch): https://shop.example.com/feed-de.xml
Feed-URLs (Englisch): https://shop.example.com/feed-en.xml
Mapping-Preset: Google Merchant (XML mit g:)
```

### 2. Feld-Mapping (Tab: Mapping)
Konfigurieren Sie die Zuordnung der Feed-Felder:
- **ID**: `g:id` oder `id`
- **Name**: `title` oder `name`
- **Beschreibung**: `description`
- **SKU/MPN**: `g:mpn` oder `sku`
- **EAN/GTIN**: `g:gtin` oder `gtin`
- **Preis**: `g:price` oder `price`
- **Bild-URL**: `g:image_link` oder `image`
- **Produkt-URL**: `link` oder `url`

### 3. API-Integration (Tab: Integration)

#### ChatGPT/OpenAI Konfiguration
1. API-Key von [platform.openai.com](https://platform.openai.com/api-keys) abrufen
2. Model auswählen (empfohlen: GPT-4o Mini)
3. Optional: Assistant ID für vorkonfigurierte Marken-Erkennung
4. Verbindung testen

#### DeepL Konfiguration (Fallback)
1. API-Key von [deepl.com/pro-api](https://www.deepl.com/pro-api) abrufen
2. Als Fallback-Option eintragen

### 4. Index-Verwaltung (Tab: Index)

#### Feed-Import starten
```
1. "Feed-Produkte neu importieren" klicken
2. Fortschritt im Dashboard beobachten
3. Bei Bedarf über "Feed-Import abbrechen" stoppen
```

#### WordPress-Inhalte indexieren
```
1. "WordPress-Inhalte neu indexieren" klicken
2. ChatGPT-Verarbeitung läuft automatisch (falls konfiguriert)
3. Fortschritt inkl. API-Nutzung wird angezeigt
```

## 💻 Verwendung

### Shortcode Integration
```php
// Deutsche Suche
[multiindex_search lang="de"]

// Englische Suche  
[multiindex_search lang="en"]
```

### REST API Endpoint
```javascript
// Suche via REST API
GET /wp-json/asmi/v1/search?q=batterie&lang=de

// Response Format
{
  "query": "batterie",
  "lang": "de",
  "count": 25,
  "results": {
    "products": [...],
    "wordpress": [...]
  }
}
```

### JavaScript Integration
```javascript
// Modal programmatisch öffnen
jQuery('.asmi-modal-trigger[data-lang="de"]').click();

// Auf Suchergebnisse reagieren
jQuery(document).on('asmi:search:complete', function(e, results) {
    console.log('Suchergebnisse:', results);
});
```

## 🔧 Erweiterte Funktionen

### High-Speed Indexierung
Für große Datenmengen (>10.000 Produkte):
- Aktivieren unter "Index > High-Speed Indexierung"
- Nutzt Loopback-Requests statt WP-Cron
- Batch-Größe auf 500-1000 erhöhen

### Tägliche Automatisierung
```php
// Cron-Jobs werden automatisch registriert:
- asmi_cron_warmup (stündlich): Feed-Cache vorwärmen
- asmi_cron_wp_content_index (täglich): WordPress-Inhalte aktualisieren
- asmi_cron_reindex (täglich, optional): Feeds neu importieren
```

### Export/Import Funktionen
- **Export**: WordPress-Index als CSV exportieren für manuelle Übersetzungen
- **Import**: Überarbeitete CSV-Dateien reimportieren
- **Schutz**: Manuell importierte Einträge werden bei Re-Indexierung geschützt

### Performance-Optimierung
```php
// Empfohlene Einstellungen für große Installationen:
define('WP_MEMORY_LIMIT', '512M');
define('DISABLE_WP_CRON', true); // Externe Cron verwenden
```

## 📊 Datenbank-Schema

### Haupttabelle: `wp_asmi_index`
```sql
- id (BIGINT): Primärschlüssel
- source_id (VARCHAR): Produkt-ID oder Post-ID
- lang (VARCHAR): Sprachcode (de/en/de_DE/en_GB)
- source_type (VARCHAR): 'product' oder 'wordpress'
- title (TEXT): Titel/Name
- content (LONGTEXT): Beschreibung/Inhalt
- excerpt (TEXT): Kurzfassung/Keywords
- url (VARCHAR): Produkt/Seiten-URL
- image (VARCHAR): Bild-URL
- price (VARCHAR): Preis (nur Produkte)
- sku (VARCHAR): Artikelnummer
- gtin (VARCHAR): EAN/GTIN
- content_hash (VARCHAR): Änderungs-Tracking
- indexed_at (DATETIME): Indexierungszeitpunkt
```

### Cache-Tabelle: `wp_asmi_chatgpt_cache`
```sql
- id (INT): Primärschlüssel
- cache_key (VARCHAR): Content-Hash
- lang (VARCHAR): Zielsprache
- response_data (LONGTEXT): ChatGPT-Response
- created_at (DATETIME): Erstellungsdatum
```

## 🐛 Troubleshooting

### Häufige Probleme

#### "Loopback Request Failed"
```bash
# .htaccess prüfen
# Firewall-Regeln checken
# Alternative: High-Speed Indexierung deaktivieren
```

#### ChatGPT Timeouts
```php
// Batch-Größe reduzieren
'wp_index_batch_size' => 3, // statt 5

// Model wechseln
'chatgpt_model' => 'gpt-3.5-turbo', // schneller
```

#### Fehlende Suchergebnisse
1. Index-Status prüfen (Tab: System)
2. Datenbank reparieren: "Datenbank reparieren" klicken
3. Debug-Modus aktivieren für Log-Ausgaben

### Debug-Modus
```php
// In wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Plugin Debug aktivieren:
// Tab System > Debug-Modus aktivieren

// Logs einsehen:
tail -f wp-content/debug.log | grep "ASMI"
```

## 📈 Performance-Metriken

### Typische Verarbeitungszeiten
- **Feed-Import**: ~100-200 Produkte/Sekunde
- **WordPress-Indexierung mit ChatGPT**: ~10-20 Posts/Minute
- **Suchanfragen**: <100ms für 10.000 Einträge
- **Image-Cache**: ~5-10 Bilder/Sekunde

### Empfohlene Limits
- Max. 50.000 Produkte pro Feed
- Max. 5.000 WordPress-Posts
- Batch-Size: 200-500 für Feeds, 3-5 für ChatGPT

## 🔄 Update-Prozess

### Automatische Updates
- Datenbank-Struktur wird automatisch aktualisiert
- Bestehende Daten bleiben erhalten
- Manuelle Imports werden geschützt

### Manuelle Migration
```sql
-- Backup erstellen
mysqldump -u user -p database wp_asmi_index > backup.sql

-- Nach Update: Struktur reparieren
-- Tab System > "Datenbank reparieren"
```

## 📝 Changelog

### Version 1.9.3
- OpenAI Assistant API Support
- Verbesserte Timeout-Behandlung
- WordPress 6.x Kompatibilität
- Optimierte Marken-Erkennung

### Version 1.9.0
- ChatGPT Integration
- Asynchrone WordPress-Indexierung
- Manual Import Protection
- Performance-Optimierungen

## 🤝 Support & Entwicklung

### Kontakt
- **Website**: [akkusys.de](https://akkusys.de)
- **Entwickler**: Marc Mirschel ([mirschel.biz](https://mirschel.biz))

### Mitwirkende
Beiträge sind willkommen! Bitte erstellen Sie einen Pull Request oder öffnen Sie ein Issue.

### Lizenz
Dieses Plugin ist unter der GPL v2 oder später lizenziert.

## 🔒 Sicherheit

### Best Practices
- API-Keys niemals im Code speichern
- Regelmäßige Backups der Index-Tabelle
- Zugriffsrechte auf Admin-Bereich beschränken
- SSL/HTTPS für API-Kommunikation verwenden

### Datenschutz
- Keine personenbezogenen Daten im Index
- ChatGPT-Cache nach 30 Tagen automatisch gelöscht
- Lokale Bildspeicherung optional deaktivierbar

---

*AS Multiindex Search - Professionelle föderierte Suche für WordPress*