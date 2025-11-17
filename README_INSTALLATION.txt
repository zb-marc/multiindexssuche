========================================================================
AS MULTIINDEX SEARCH v1.11.0 - INSTALLATIONS-ANLEITUNG
========================================================================

WICHTIG: Dies ist ein UPDATE-Paket für Version 1.10.5
--------------------------------------------------------

Dieses Paket enthält die KRITISCHEN ÄNDERUNGEN für Version 1.11.0:
- Neue Bildverwaltung mit URL-Caching
- Automatische Garbage Collection
- Erweiterte Datenbank-Struktur

========================================================================
INSTALLATIONS-OPTIONEN
========================================================================

OPTION 1: VOLLSTÄNDIGE NEU-INSTALLATION (Empfohlen)
----------------------------------------------------
1. Sichere deine aktuelle Installation (wp-content/plugins/as-multiindex-search/)
2. Sichere die Datenbank-Tabelle wp_asmi_index
3. Lösche das alte Plugin über WordPress Admin
4. Lade dieses ZIP hoch und aktiviere
5. Die Datenbank wird automatisch aktualisiert

OPTION 2: MANUELLE DATEI-ERSETZUNG
-----------------------------------
Ersetze nur diese Dateien in deiner bestehenden Installation:

AKTUALISIERTE DATEIEN:
- as-multiindex-search.php
- includes/db.php
- includes/indexing/images.php

NEUE DATEIEN:
- includes/indexing/image-cleanup.php

========================================================================
NACH DER INSTALLATION
========================================================================

1. Gehe zu: WordPress Admin → Multiindex → System
2. Klicke auf "Repair Database" 
3. Die neue Spalte "image_url_hash" wird automatisch hinzugefügt
4. Der Cron-Job für Image-Cleanup wird automatisch registriert

========================================================================
VERIFIKATION
========================================================================

Prüfe ob alles funktioniert:
1. Version sollte 1.11.0 anzeigen
2. Debug-Modus aktivieren
3. Einen Feed-Import starten
4. Im Log sollte erscheinen: "Image already cached: ..." bei Duplikaten

========================================================================
FEHLENDE DATEIEN?
========================================================================

Falls das Plugin Fehler wirft wegen fehlender Dateien:

1. Behalte deine aktuelle Installation von v1.10.5
2. Kopiere nur die GEÄNDERTEN Dateien aus diesem Paket
3. Alle anderen Dateien bleiben unverändert

Die meisten Dateien haben sich NICHT geändert!

========================================================================
ROLLBACK
========================================================================

Falls Probleme auftreten:
1. Deaktiviere das Plugin
2. Stelle dein Backup wieder her
3. Die Datenbank-Änderungen sind sicher (nur neue Spalte)

========================================================================
SUPPORT
========================================================================

Bei Fragen: marc@mirschel.biz
Repository: https://github.com/zb-marc/multiindexssuche

========================================================================
