# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [1.11.2] - 2025-11-19

### 🎯 Hauptfokus: Intelligente Änderungserkennung & Performance-Optimierung

#### Behoben
- **KRITISCHER BUGFIX**: Bilder werden nicht mehr bei jedem täglichen Durchlauf neu heruntergeladen
  - Problem: Alle Bilder wurden täglich um 1:00 Uhr erneut geladen, obwohl sich Produkte nicht geändert hatten
  - Ursache: Komplette Löschung aller Produkte vor jedem Import
  - Lösung: Intelligente Änderungserkennung mit Content-Hash und Image-URL-Hash

#### Hinzugefügt
- **Intelligente Änderungserkennung**
  - Neue DB-Spalte: `content_hash` (SHA256 von Titel, Beschreibung, Preis, URL)
  - Neue DB-Spalte: `last_modified` (Zeitstempel für Tracking)
  - Bilder werden nur noch heruntergeladen bei:
    * Neuen Produkten
    * Geänderten Bild-URLs
  - Unveränderte Bilder werden wiederverwendet (>95% Einsparung)

- **Optimierte Produktverwaltung**
  - Keine komplette Löschung mehr bei jedem Durchlauf
  - Nur noch INSERT/UPDATE für neue/geänderte Produkte
  - Automatische Entfernung obsoleter Produkte am Ende der Indexierung
  - Zugehörige Bilder werden automatisch mitgelöscht

- **Erweiterte Statistiken**
  - `new_items`: Anzahl neuer Produkte
  - `updated_items`: Anzahl aktualisierter Produkte
  - `images_reused`: Anzahl wiederverwendeter Bilder
  - `images_downloaded`: Anzahl neu heruntergeladener Bilder

- **Cleanup-Funktion für obsolete Produkte**
  - Neue Funktion: `asmi_cleanup_obsolete_products()`
  - Identifiziert Produkte anhand des `last_modified` Zeitstempels
  - Löscht nicht mehr vorhandene Produkte und ihre Bilder

#### Geändert
- `includes/indexing/control.php`: Markiert Produkte statt sie zu löschen
- `includes/indexing/database.php`: Implementiert intelligente Änderungserkennung
- `includes/indexing/handler.php`: Ruft Cleanup-Funktion nach Indexierung auf
- `includes/indexing/images.php`: Verbesserte Fehlerbehandlung mit `wp_delete_file()`

#### Performance-Verbesserungen
- **Download-Reduktion**: >95% weniger Bild-Downloads (nur bei tatsächlichen Änderungen)
- **Indexierungs-Geschwindigkeit**: Schnellere Durchläufe durch weniger I/O-Operationen
- **Serverlast**: Deutlich reduziert durch intelligentes Caching
- **Bandbreite**: Massive Einsparung durch vermiedene Downloads

### Migration
- DB-Struktur wird automatisch bei Plugin-Aktivierung aktualisiert
- Neue Spalten: `content_hash`, `last_modified` werden automatisch hinzugefügt
- Keine manuelle Migration erforderlich

---

## [1.11.1] - 2025-11-17

### 🎯 Hauptfokus: Storage-Optimierung & Robustheit

#### Hinzugefügt
- **URL-basiertes Bild-Caching**
  - Neue DB-Spalte: `image_url_hash` (MD5-Hash der Bild-URL)
  - Neuer Index: `idx_image_hash` für schnelle Duplikatsprüfung
  - Verhindert mehrfaches Herunterladen identischer Bild-URLs
  - Speicherreduktion: Von ~26GB auf ~6-7GB (~75% Einsparung)

- **Automatisches Image Cleanup**
  - Neue Datei: `includes/indexing/image-cleanup.php`
  - Garbage Collection für verwaiste Bilder
  - Läuft täglich um 3:00 Uhr via WP-Cron
  - Löscht nur Bilder ohne DB-Referenzen
  - Schützt `.htaccess` und `index.html`

- **Erweiterte Database Repair**
  - Funktion: `asmi_install_and_repair_database()`
  - Prüft und repariert DB-Struktur automatisch
  - Fügt fehlende Spalten hinzu
  - Erstellt fehlende Indizes
  - Backup-System vor kritischen Änderungen

#### Behoben
- **"Cannot redeclare" Fatal Errors**
  - Include-Guards mit `function_exists()` für alle globalen Funktionen
  - Verhindert Mehrfacheinbindung bei asynchroner Verarbeitung
  - Betrifft: `database.php`, `handler.php`, `control.php`

- **UTF-8 Encoding-Probleme**
  - Korrekte Verarbeitung deutscher Umlaute (ä, ö, ü, ß)
  - Feed-Parsing respektiert XML-Encoding-Deklarationen
  - Datenbankeinträge werden korrekt gespeichert

- **SQL-Fehler in Cleanup-Funktion**
  - Korrektur: `ID` statt `post_id` in WHERE-Klausel
  - Betrifft: `wp-content-indexer.php`

- **Cloudflare Bypass**
  - Verbesserte Header für Feed-Requests
  - User-Agent wird für geschützte Feeds simuliert
  - Timeout-Handling optimiert

#### Geändert
- `as-multiindex-search.php`: Version 1.11.1, neue Konstante `ASMI_IMAGE_CLEANUP_ACTION`
- `includes/db.php`: Erweiterte Reparaturfunktionen
- `includes/indexing/images.php`: URL-Hash-basiertes Caching implementiert
- Alle indexing-Dateien: Include-Guards hinzugefügt

#### Performance
- **Speicher**: ~75% Reduktion durch URL-basiertes Caching
- **Suche**: Verbesserte FULLTEXT-Indizes mit `gtin`-Unterstützung
- **Async Processing**: Stabilere Verarbeitung durch Error-Guards

### Migration
- Automatische DB-Migration bei Plugin-Aktivierung
- Cron-Job für Image-Cleanup wird automatisch registriert
- Kein manueller Eingriff erforderlich

---

## [1.11.0] - 2025-11-15

### 🎯 Hauptfokus: Bild-Cache-Optimierung

#### Hinzugefügt
- **Batch Processing mit Token-Bucket-System**
  - Intelligente Rate-Limiting für API-Calls
  - Verhindert API-Quotenüberschreitung
  - Adaptive Batch-Größe basierend auf Verarbeitungsgeschwindigkeit

- **Change Detection für API-Calls**
  - Reduziert unnötige ChatGPT-Anfragen um ~80%
  - Hash-Vergleich der Inhalte vor API-Call
  - Nur geänderte Inhalte werden verarbeitet

- **Asynchrone Background-Processing-Engine**
  - Tick-basiertes System für stabile Verarbeitung
  - State Management mit persistenter Speicherung
  - Cancellation-Support für laufende Prozesse

#### Geändert
- Optimierte Fehlerbehandlung bei Timeout-Situationen
- Verbesserte Logging-Funktionen für Debugging
- Erweiterte Admin-UI für Prozess-Überwachung

---

## [1.10.5] - 2025-09-29

### Behoben
- **KRITISCHER BUGFIX**: "Cannot redeclare" Fatal Errors
  - Include-Guards für alle globalen Funktionen
  - Robuster Schutz gegen Mehrfacheinbindungen
  - `function_exists()` Prüfungen hinzugefügt

- **SQL-Fix**: Korrektur der Spaltenbezeichnung in Cleanup
  - `ID` statt `post_id` in WHERE-Klausel
  - Betrifft: `wp-content-indexer.php`

- **REST API Stabilität**
  - Routes werden nun zuverlässig registriert
  - Verbesserte Error-Handling bei Registrierung

#### Geändert
- Performance: Stabile Verarbeitung auch bei hoher Last
- Verbesserte Fehlerbehandlung bei parallelen Requests

---

## [1.10.4] - 2025-09-28

### Hinzugefügt
- **Statistik-Erweiterung**
  - Neue Metriken für übersprungene Posts
  - Metriken für aktualisierte Posts
  - Detaillierte Performance-Logs

- **Adaptive Batch-Size**
  - Automatische Anpassung basierend auf Geschwindigkeit
  - Verhindert Memory-Probleme bei großen Batches

#### Behoben
- Asynchrone Stabilität bei WordPress-Indexierung
- Memory Management bei großen Batches
- Cache-Optimierung für Hash-Vergleiche

---

## [1.10.3] - 2025-09-20

### Hinzugefügt
- Erweiterte Marken-Erkennung
- Optimierte Bindestrich-Suche
- Verbesserte ChatGPT-Integration mit Assistant-Support

#### Behoben
- Bugfixes bei der Spracherkennung
- Verbesserte Fehlerbehandlung bei Feed-Parsing

---

## [1.10.0] - 2025-09-15

### Hinzugefügt
- **ChatGPT-Integration**
  - Inhaltsanalyse mit OpenAI GPT-4o-mini
  - Automatische Übersetzungen
  - Keyword-Extraktion
  - Assistant API Support

- **Asynchrone WordPress-Indexierung**
  - Background-Processing für große Post-Mengen
  - Tick-basierte Verarbeitung
  - State Management

- **Export/Import-Funktionalität**
  - WordPress-Index als CSV exportieren
  - CSV-Import für Bulk-Updates
  - Datensicherung und -migration

---

## [1.9.0] - 2025-09-01

### Hinzugefügt
- Multi-Language Support (DE/EN)
- REST API erweitert
- Performance-Optimierungen für große Datenmengen

---

## [1.8.0] - 2025-08-15

### Hinzugefügt
- Initiales öffentliches Release
- Feed-Import (XML, CSV, JSON)
- WordPress-Content-Integration
- AJAX-Suche
- Admin-Interface

---

## Legende

- **Hinzugefügt**: Neue Features
- **Geändert**: Änderungen an bestehenden Features
- **Veraltet**: Bald zu entfernende Features
- **Entfernt**: Entfernte Features
- **Behoben**: Bugfixes
- **Sicherheit**: Security-Fixes

---

## Kommende Versionen

### [1.12.0] - Geplant

#### Geplant
- Detaillierte Statistik-Anzeige im Admin
- Levenshtein-Distanz für Tippfehlerkorrektur
- Such-Analytics Dashboard
- Performance-Monitoring

### [2.0.0] - Vision

#### Breaking Changes
- Elasticsearch-Integration
- GraphQL API
- React-basiertes Admin-Interface
- Multi-Tenant-Support

---

**Semantic Versioning Schema:**
- **MAJOR**: Inkompatible API-Änderungen
- **MINOR**: Neue Features (rückwärtskompatibel)
- **PATCH**: Bugfixes (rückwärtskompatibel)
