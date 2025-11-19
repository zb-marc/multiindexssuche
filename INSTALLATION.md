# Installation & Upgrade Guide

Komplette Anleitung für Installation, Update und Upgrade von AS Multiindex Search.

---

## 📋 Inhaltsverzeichnis

1. [Voraussetzungen](#voraussetzungen)
2. [Neu-Installation](#neu-installation)
3. [Upgrade von älteren Versionen](#upgrade-von-älteren-versionen)
4. [Update-Paket anwenden](#update-paket-anwenden)
5. [Verifizierung](#verifizierung)
6. [Troubleshooting](#troubleshooting)
7. [Rollback](#rollback)

---

## 🔧 Voraussetzungen

### Minimale Anforderungen
- WordPress 5.8+
- PHP 7.4+
- MySQL 5.6+ (mit FULLTEXT-Support)
- WP-Cron aktiviert
- 256MB PHP Memory Limit

### Empfohlene Konfiguration
- WordPress 6.0+
- PHP 8.0+
- MySQL 8.0+
- 512MB PHP Memory Limit
- 60s PHP Max Execution Time

### Server-Prüfung

```bash
# PHP-Version prüfen
php -v

# WordPress-Version prüfen
wp core version

# Verfügbarer Speicher prüfen
wp eval "echo WP_MEMORY_LIMIT;"
```

---

## 🆕 Neu-Installation

### Option 1: Via WordPress Admin (Empfohlen)

1. **Plugin herunterladen**
   - Lade `as-multiindex-search-v1.11.2.zip` herunter

2. **Plugin hochladen**
   ```
   WordPress Admin → Plugins → Installieren → Plugin hochladen
   ```

3. **Plugin aktivieren**
   - Klicke auf "Aktivieren"
   - Die Datenbanktabelle wird automatisch erstellt

4. **Grundkonfiguration**
   ```
   Admin → Multiindex → Allgemein
   ├── Feed URLs (DE): https://example.com/feed-de.xml
   ├── Feed URLs (EN): https://example.com/feed-en.xml
   └── Enable Daily Reindexing: ✓
   ```

5. **Ersten Import starten**
   ```
   Admin → Multiindex → Index → "Start Import"
   ```

### Option 2: Via WP-CLI

```bash
# Plugin installieren
wp plugin install as-multiindex-search.zip --activate

# Datenbank erstellen/reparieren
wp eval "asmi_install_and_repair_database();"

# Feed-URLs konfigurieren
wp option update asmi_options '{"feed_urls":"https://example.com/feed.xml","enable_daily_reindex":1}' --format=json

# Ersten Import starten
wp eval "asmi_index_reset_and_start();"
```

### Option 3: Manuelle Installation

```bash
# In WordPress-Verzeichnis wechseln
cd /var/www/html

# Plugin entpacken
unzip as-multiindex-search-v1.11.2.zip -d wp-content/plugins/

# Berechtigungen setzen
chown -R www-data:www-data wp-content/plugins/as-multiindex-search
chmod -R 755 wp-content/plugins/as-multiindex-search

# Plugin aktivieren
wp plugin activate as-multiindex-search
```

---

## ⬆️ Upgrade von älteren Versionen

### Von Version 1.10.x → 1.11.2

**Wichtig**: Dieses Update enthält kritische Datenbankänderungen.

#### Schritt 1: Backup erstellen

```bash
# Datenbank-Backup
wp db export backup-before-1.11.2.sql

# Plugin-Verzeichnis sichern
cp -r wp-content/plugins/as-multiindex-search wp-content/plugins/as-multiindex-search-backup

# Uploads-Verzeichnis sichern (Optional, falls Rollback nötig)
tar -czf uploads-backup.tar.gz wp-content/uploads/as-multiindex-search/
```

#### Schritt 2: Plugin deaktivieren

```bash
# Via WP-CLI
wp plugin deactivate as-multiindex-search

# Oder im WordPress Admin:
# Plugins → AS Multiindex Search → Deaktivieren
```

#### Schritt 3: Alte Version entfernen

```bash
# Plugin-Verzeichnis löschen
rm -rf wp-content/plugins/as-multiindex-search

# Oder im WordPress Admin:
# Plugins → AS Multiindex Search → Löschen
```

**Hinweis**: Die Datenbanktabelle und Einstellungen bleiben erhalten!

#### Schritt 4: Neue Version installieren

```bash
# Via WP-CLI
wp plugin install as-multiindex-search-v1.11.2.zip --activate

# Oder im WordPress Admin:
# Plugins → Installieren → Plugin hochladen
```

#### Schritt 5: Datenbank aktualisieren

Die Datenbank wird automatisch bei Plugin-Aktivierung aktualisiert. Neue Spalten werden hinzugefügt:
- `image_url_hash` (VARCHAR(32)) - seit v1.11.0
- `content_hash` (VARCHAR(64)) - seit v1.11.2
- `last_modified` (DATETIME) - seit v1.11.2

**Manuelle Reparatur** (falls nötig):
```
Admin → Multiindex → System → "Repair Database"
```

#### Schritt 6: Einstellungen überprüfen

```
Admin → Multiindex → Allgemein
├── Feed URLs: ✓ (sollten erhalten geblieben sein)
├── Enable Daily Reindexing: ✓
└── Image Storage Mode: Local ✓
```

#### Schritt 7: Test-Import durchführen

```
Admin → Multiindex → Index → "Start Import"
```

Beobachte die neuen Statistiken:
- Neue Produkte
- Aktualisierte Produkte
- Bilder wiederverwendet ⭐ (sollte beim ersten Mal 0 sein)
- Bilder heruntergeladen

---

### Von Version 1.11.0/1.11.1 → 1.11.2

**Dies ist ein kleineres Update mit Bugfixes.**

#### Quick Update (5 Dateien)

1. **Backup erstellen** (empfohlen)
   ```bash
   cp wp-content/plugins/as-multiindex-search/as-multiindex-search.php \
      wp-content/plugins/as-multiindex-search/as-multiindex-search.php.backup
   ```

2. **Plugin deaktivieren**
   ```bash
   wp plugin deactivate as-multiindex-search
   ```

3. **Dateien ersetzen**
   
   Nur diese 5 Dateien müssen ersetzt werden:
   ```
   as-multiindex-search.php
   includes/indexing/control.php
   includes/indexing/database.php
   includes/indexing/handler.php
   includes/indexing/images.php
   ```

4. **Plugin aktivieren**
   ```bash
   wp plugin activate as-multiindex-search
   ```

5. **Fertig!**
   - Keine DB-Migration erforderlich
   - Neue Spalten werden automatisch hinzugefügt

---

## 📦 Update-Paket anwenden

Falls du nur ein Update-Paket (z.B. `asmi-update-1.11.2.zip`) hast:

### Schritt 1: Backup
```bash
wp db export backup-before-update.sql
cp -r wp-content/plugins/as-multiindex-search wp-content/plugins/as-multiindex-search-backup
```

### Schritt 2: Plugin deaktivieren
```bash
wp plugin deactivate as-multiindex-search
```

### Schritt 3: Update-Dateien entpacken
```bash
# Update-Paket entpacken
unzip asmi-update-1.11.2.zip

# Dateien in Plugin-Verzeichnis kopieren
cp -r asmi-update-1.11.2/* wp-content/plugins/as-multiindex-search/
```

### Schritt 4: Plugin aktivieren
```bash
wp plugin activate as-multiindex-search
```

---

## ✅ Verifizierung

### 1. Version prüfen

```bash
# Via WP-CLI
wp plugin list | grep multiindex

# Oder im WordPress Admin:
# Plugins → AS Multiindex Search → Version sollte 1.11.2 anzeigen
```

### 2. Datenbank-Struktur prüfen

```bash
# Prüfe ob neue Spalten existieren
wp db query "DESCRIBE wp_asmi_index;"

# Sollte enthalten:
# - image_url_hash (VARCHAR(32))
# - content_hash (VARCHAR(64))
# - last_modified (DATETIME)
```

### 3. Cron-Jobs prüfen

```bash
# Liste alle Cron-Jobs
wp cron event list | grep asmi

# Sollte enthalten:
# - asmi_cron_reindex (täglich um 1:00 Uhr)
# - asmi_do_image_cleanup (täglich um 3:00 Uhr)
# - asmi_cron_wp_content_index (täglich)
```

### 4. Funktionstest

```
Admin → Multiindex → Index → "Start Import"
```

**Erwartete Log-Ausgaben** (Debug-Modus):
```
[ASMI DEBUG] INDEX START: Beginning indexing process
[ASMI DEBUG] INDEX PREPARE: Marked all existing products with timestamp
[ASMI DEBUG] Image URL unchanged for product XXX, reusing existing image
[ASMI DEBUG] Image URL changed for product YYY, downloading...
[ASMI DEBUG] CLEANUP: Deleted 5 obsolete products and their images
```

### 5. Statistiken prüfen

Nach dem ersten Import mit v1.11.2:
```
Admin → Multiindex → Index → Status

Beim ERSTEN Import:
├── Neue Produkte: X (alle)
├── Aktualisierte Produkte: 0
├── Bilder wiederverwendet: 0
└── Bilder heruntergeladen: X (alle)

Beim ZWEITEN Import (keine Änderungen):
├── Neue Produkte: 0
├── Aktualisierte Produkte: 0
├── Bilder wiederverwendet: X (alle) ⭐
└── Bilder heruntergeladen: 0 ⭐
```

---

## 🔧 Troubleshooting

### Problem: Spalten fehlen nach Update

**Symptom:**
```
WordPress database error Unknown column 'content_hash'
```

**Lösung:**
```bash
# Manuelle Reparatur
wp eval "asmi_install_and_repair_database();"

# Oder im Admin:
Admin → Multiindex → System → "Repair Database"
```

### Problem: Plugin kann nicht aktiviert werden

**Symptom:**
```
The plugin does not have a valid header.
```

**Lösung:**
```bash
# Prüfe Dateirechte
ls -la wp-content/plugins/as-multiindex-search/

# Setze korrekte Rechte
chown -R www-data:www-data wp-content/plugins/as-multiindex-search
chmod 644 wp-content/plugins/as-multiindex-search/*.php
```

### Problem: Cron-Jobs laufen nicht

**Symptom:**
Tägliche Indexierung findet nicht statt.

**Lösung:**
```bash
# Prüfe WP-Cron
wp cron test

# Cron-Jobs manuell triggern
wp cron event run asmi_cron_reindex

# Alternative: System-Cron einrichten
# In /etc/crontab:
0 1 * * * www-data cd /var/www/html && wp cron event run asmi_cron_reindex
```

### Problem: Bilder werden immer noch neu geladen

**Symptom:**
`images_reused` ist immer 0, auch beim zweiten Durchlauf.

**Diagnose:**
```bash
# Prüfe ob image_url_hash gesetzt wird
wp db query "SELECT image_url_hash, image FROM wp_asmi_index WHERE image IS NOT NULL LIMIT 5;"

# Sollte Hash-Werte zeigen (z.B. 5d41402abc4b2a76b9719d911017c592)
```

**Lösung:**
```bash
# Falls keine Hashes vorhanden, einmalig neu indexieren
wp eval "asmi_index_reset_and_start();"
```

### Problem: High Memory Usage

**Symptom:**
```
Fatal error: Allowed memory size of X bytes exhausted
```

**Lösung:**
```php
// In wp-config.php:
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Oder Batch-Size reduzieren:
Admin → Multiindex → Index → Index Batch Size: 100 (statt 200)
```

---

## ⏮️ Rollback

Falls Probleme auftreten, kannst du zur vorherigen Version zurückkehren.

### Schritt 1: Plugin deaktivieren
```bash
wp plugin deactivate as-multiindex-search
```

### Schritt 2: Neue Version löschen
```bash
rm -rf wp-content/plugins/as-multiindex-search
```

### Schritt 3: Backup wiederherstellen
```bash
cp -r wp-content/plugins/as-multiindex-search-backup \
      wp-content/plugins/as-multiindex-search
```

### Schritt 4: Plugin aktivieren
```bash
wp plugin activate as-multiindex-search
```

### Schritt 5: Datenbank-Rollback (Optional)

**Achtung**: Nur falls schwerwiegende Probleme auftreten!

```bash
# Backup einspielen
wp db import backup-before-1.11.2.sql

# Neue Spalten entfernen (falls nötig)
wp db query "ALTER TABLE wp_asmi_index DROP COLUMN content_hash;"
wp db query "ALTER TABLE wp_asmi_index DROP COLUMN last_modified;"
```

**Hinweis**: Die Spalte `image_url_hash` aus v1.11.0 kann bleiben, sie schadet nicht.

---

## 📊 Post-Installation Checklist

Nach erfolgreicher Installation/Update:

- [ ] Version ist 1.11.2
- [ ] Feed-URLs sind konfiguriert
- [ ] Tägliche Indexierung ist aktiviert (1:00 Uhr)
- [ ] Image Storage Mode: Local
- [ ] Debug-Modus aktiviert (für erste Tests)
- [ ] Erster Test-Import erfolgreich
- [ ] Statistiken zeigen sinnvolle Werte
- [ ] Cron-Jobs sind registriert
- [ ] Bilder werden korrekt zwischengespeichert
- [ ] Suche funktioniert (Frontend-Test)
- [ ] REST API erreichbar (`/wp-json/asmi/v1/search`)

---

## 🆘 Support

Bei Problemen:

1. **Debug-Modus aktivieren**
   ```
   Admin → Multiindex → System → Debug Mode ✓
   ```

2. **Logs prüfen**
   ```bash
   tail -f wp-content/debug.log | grep "ASMI"
   ```

3. **System-Status exportieren**
   ```bash
   wp eval "print_r(asmi_get_opts());"
   wp db query "SELECT COUNT(*) as total FROM wp_asmi_index;"
   ```

4. **GitHub Issue erstellen**
   - Füge WordPress-Version hinzu
   - Füge PHP-Version hinzu
   - Füge relevante Log-Ausgaben hinzu
   - Beschreibe Schritte zur Reproduktion

---

**Entwickelt von Marc Mirschel für AKKUSYS GmbH**

*Letzte Aktualisierung: 19. November 2025*
