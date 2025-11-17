# AS Multiindex Search v1.11.0 - Upgrade Notes

## ⚠️ WICHTIGE HINWEISE

Diese Version implementiert **zwei neue Features zur Optimierung der Bildverwaltung**:

### 🆕 Neue Features

#### 1. URL-basiertes Bild-Caching (Duplikatsprävention)
- **Problem gelöst:** Bilder mit gleicher URL werden nicht mehr mehrfach heruntergeladen
- **Speicher-Reduktion:** 60-70% weniger Duplikate
- **Technologie:** MD5-Hash der Bild-URL wird in DB gespeichert
- **Automatisch:** Funktioniert ab Installation ohne Konfiguration

#### 2. Garbage Collection (Automatische Bereinigung)
- **Problem gelöst:** Verwaiste Bilder (von gelöschten Produkten) werden automatisch entfernt
- **Zeitplan:** Täglich um 3 Uhr morgens via WP-Cron
- **Speicher-Reduktion:** 30-40% durch Entfernung verwaister Dateien
- **Sicherheit:** Geschützte Dateien (.htaccess, index.html) werden nie gelöscht

### 📊 Erwartete Gesamt-Reduktion
**Von 26 GB auf ca. 6-7 GB** (~70-75% Reduktion)

---

## 🔧 Technische Änderungen

### Datenbank
- **Neue Spalte:** `image_url_hash` (VARCHAR(32))
- **Neuer Index:** `idx_image_hash` auf `image_url_hash`
- **Migration:** Erfolgt automatisch bei Plugin-Aktivierung

### Neue WordPress-APIs verwendet
- ✅ `wp_remote_get()` mit `stream => true` Parameter
- ✅ `WP_Filesystem` API für sichere Dateiverwaltung
- ✅ `WP-Cron` für automatische Bereinigung
- ✅ `wpdb->prepare()` für alle DB-Queries

### Neue Dateien
- `includes/indexing/image-cleanup.php` - Garbage Collection System

### Geänderte Dateien
- `as-multiindex-search.php` - Version 1.11.0, neue Cron-Jobs
- `includes/db.php` - DB-Schema erweitert
- `includes/indexing/images.php` - URL-Caching implementiert

---

## 📥 Installation

### Neu-Installation
1. Lade die ZIP-Datei hoch
2. Aktiviere das Plugin
3. Fertig! Die Features funktionieren automatisch

### Upgrade von 1.10.x
1. Deaktiviere das alte Plugin (Daten bleiben erhalten)
2. Lösche das alte Plugin-Verzeichnis
3. Lade die neue Version hoch
4. Aktiviere das Plugin
5. Die Datenbank wird automatisch aktualisiert

---

## 🎛️ Admin-Interface

### Neue Informationen im Admin
- **System-Tab:** Zeigt Status des letzten Cleanup
- **Statistiken:** Anzahl gelöschter/referenzierter Bilder
- **Nächster Cleanup:** Zeitpunkt des nächsten automatischen Laufs

---

## 🐛 Bekannte Einschränkungen

1. **Erstmalige Bereinigung:** Dauert bei 26 GB ca. 5-10 Minuten
2. **Cron-Abhängigkeit:** Erfordert funktionierendes WP-Cron
3. **Keine Rückgängig:** Gelöschte verwaiste Bilder können nicht wiederhergestellt werden

---

## 🔍 Debugging

### Debug-Modus aktivieren
Im Admin unter **Multiindex → System → Debug Mode**

### Log-Ausgaben prüfen
```
[ASMI DEBUG] Image already cached: https://...
[ASMI DEBUG] Image Cleanup: Deleted orphaned file: image-123.jpg
[ASMI DEBUG] === Image Cleanup Completed === Duration: 45s | Deleted: 1234 files
```

---

## 📞 Support

Bei Problemen:
1. Debug-Modus aktivieren
2. Log-Datei prüfen (`wp-content/debug.log`)
3. Admin → Multiindex → System → "Repair Database"

---

## 🎯 Performance-Hinweise

### Empfohlene Server-Konfiguration
- PHP Memory Limit: mind. 256MB
- PHP Max Execution Time: mind. 60s
- WP-Cron: Aktiviert

### Optimale Einstellungen
- Index Batch Size: 200 (Standard)
- Cache TTL: 900s (Standard)

---

**Entwickelt von:** Marc Mirschel (marc@mirschel.biz)
**Für:** AKKU SYS GmbH
**Version:** 1.11.0
**Datum:** November 2025
