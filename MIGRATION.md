# Dokumentations-Migration

## Übersicht der Konsolidierung

Die Plugin-Dokumentation wurde von **4 Dateien auf 3 Dateien** konsolidiert.

---

## Alte Struktur (4 Dateien)

```
📄 README.md (v1.11.1)
   ├── Vollständige Dokumentation
   ├── Features, Installation, API
   └── 300+ Zeilen

📄 README_INSTALLATION.txt (v1.11.0)
   ├── Update-Anleitung für v1.11.0
   ├── Installations-Optionen
   └── 80 Zeilen

📄 README_UPDATE.txt (v1.11.2)
   ├── Bugfix-Release-Hinweise
   ├── Installationsschritte
   └── 70 Zeilen

📄 UPGRADE_NOTES.md (v1.11.0)
   ├── Technische Details zu v1.11.0
   ├── DB-Änderungen
   └── 120 Zeilen
```

**Probleme:**
- ❌ Versionskonflikte (3 verschiedene Versionen)
- ❌ ~70% Überschneidungen
- ❌ Unklare Struktur für Nutzer
- ❌ Veraltete Informationen

---

## Neue Struktur (3 Dateien)

```
📄 README.md
   ├── Haupt-Dokumentation (GitHub/Plugin-Verzeichnis)
   ├── Aktuelle Version: 1.11.2
   ├── Features, Quickstart, API
   ├── Entwickler-Dokumentation
   └── ~250 Zeilen (kompakter durch Verlinkungen)

📄 CHANGELOG.md
   ├── Alle Versionsänderungen chronologisch
   ├── v1.11.2 → v1.8.0
   ├── Semantic Versioning
   ├── Kategorisiert: Added/Changed/Fixed/Security
   └── ~200 Zeilen

📄 INSTALLATION.md
   ├── Komplette Installations- und Upgrade-Anleitung
   ├── Neu-Installation
   ├── Upgrade von jeder Version
   ├── Troubleshooting
   ├── Rollback-Anleitung
   └── ~400 Zeilen
```

**Vorteile:**
- ✅ Eine Quelle der Wahrheit (README.md)
- ✅ Klare Trennung: Was (README) vs. Wann (CHANGELOG) vs. Wie (INSTALLATION)
- ✅ Keine Redundanz
- ✅ Immer aktuelle Version
- ✅ Besser wartbar

---

## Mapping: Alt → Neu

### README_INSTALLATION.txt → INSTALLATION.md

```
VORHER: README_INSTALLATION.txt
├── Option 1: Vollständige Neu-Installation
├── Option 2: Manuelle Datei-Ersetzung
└── Verifikation

NACHHER: INSTALLATION.md
├── Neu-Installation (Option 1-3)
├── Upgrade von älteren Versionen
│   ├── Von 1.10.x → 1.11.2
│   └── Von 1.11.0/1.11.1 → 1.11.2
├── Update-Paket anwenden
├── Verifizierung
├── Troubleshooting
└── Rollback
```

### README_UPDATE.txt → CHANGELOG.md + INSTALLATION.md

```
VORHER: README_UPDATE.txt
├── Änderungen in v1.11.2
├── Installation
├── Erwartete Ergebnisse
└── Vorteile

NACHHER:
├── CHANGELOG.md
│   └── [1.11.2] - 2025-11-19 (Vollständige Änderungshistorie)
└── INSTALLATION.md
    └── Upgrade von 1.11.x → 1.11.2 (Schritt-für-Schritt)
```

### UPGRADE_NOTES.md → CHANGELOG.md + INSTALLATION.md

```
VORHER: UPGRADE_NOTES.md
├── Neue Features v1.11.0
├── Technische Änderungen
├── Installation
└── Debugging

NACHHER:
├── CHANGELOG.md
│   └── [1.11.0] - 2025-11-15 (Detaillierte Änderungen)
└── INSTALLATION.md
    └── Upgrade von 1.10.x → 1.11.2 (Inkl. v1.11.0 Änderungen)
```

---

## Migration Durchführen

### Schritt 1: Alte Dateien sichern

```bash
cd /path/to/plugin
mkdir docs-backup
mv README_INSTALLATION.txt docs-backup/
mv README_UPDATE.txt docs-backup/
mv UPGRADE_NOTES.md docs-backup/
```

### Schritt 2: Neue Dateien hinzufügen

```bash
# Von konsolidiertem Paket kopieren
cp asmi-docs-consolidated/README.md ./
cp asmi-docs-consolidated/CHANGELOG.md ./
cp asmi-docs-consolidated/INSTALLATION.md ./
```

### Schritt 3: Git Commit (falls verwendet)

```bash
git add README.md CHANGELOG.md INSTALLATION.md
git rm README_INSTALLATION.txt README_UPDATE.txt UPGRADE_NOTES.md
git commit -m "docs: Konsolidiere Dokumentation (4→3 Dateien)

- README.md: Aktualisiert auf v1.11.2, gestrafft
- CHANGELOG.md: Alle Versionen chronologisch
- INSTALLATION.md: Komplette Installations-/Upgrade-Anleitung
- Entfernt: README_INSTALLATION.txt, README_UPDATE.txt, UPGRADE_NOTES.md"
```

---

## Nutzer-Perspektive

### Vorher (Verwirrend)

**Szenario 1**: Nutzer will Plugin installieren
- Liest README.md → Erwähnt v1.11.1
- Findet README_INSTALLATION.txt → Zeigt v1.11.0
- Findet README_UPDATE.txt → Zeigt v1.11.2
- **Frage**: Welche Datei ist aktuell? 🤔

**Szenario 2**: Nutzer will von v1.10.5 upgraden
- Liest README_INSTALLATION.txt → Nur v1.11.0
- Liest README_UPDATE.txt → Nur v1.11.2
- Liest UPGRADE_NOTES.md → Nur v1.11.0
- **Frage**: Kann ich direkt zu 1.11.2? Muss ich zuerst 1.11.0? 🤔

### Nachher (Klar)

**Szenario 1**: Nutzer will Plugin installieren
- Liest README.md → Quickstart in 3 Zeilen
- Klickt auf "Siehe INSTALLATION.md" für Details
- **Klar**: Eine Datei, alle Optionen ✓

**Szenario 2**: Nutzer will von v1.10.5 upgraden
- Liest INSTALLATION.md → Abschnitt "Upgrade von 1.10.x → 1.11.2"
- Findet Schritt-für-Schritt-Anleitung
- **Klar**: Direkter Upgrade-Pfad ✓

**Szenario 3**: Nutzer will Änderungen sehen
- Liest CHANGELOG.md → Alle Versionen chronologisch
- **Klar**: Vollständige Historie ✓

---

## Wartung zukünftiger Versionen

### Bei neuer Version (z.B. v1.12.0)

**Nur 2 Dateien aktualisieren:**

1. **README.md**
   ```markdown
   # AS Multiindex Search
   [![Version](https://img.shields.io/badge/Version-1.12.0-orange.svg)]
   
   ## Neue Features in v1.12.0
   - Feature 1
   - Feature 2
   ```

2. **CHANGELOG.md**
   ```markdown
   ## [1.12.0] - 2025-12-15
   
   ### Hinzugefügt
   - Feature 1
   - Feature 2
   ```

3. **INSTALLATION.md** (nur bei Breaking Changes)
   ```markdown
   ### Von Version 1.11.x → 1.12.0
   
   [Upgrade-Anleitung]
   ```

**Fertig!** ✓

---

## Vorteile der neuen Struktur

### Für Endnutzer
- ✅ Eine klare Einstiegsdatei (README.md)
- ✅ Schnelle Antworten durch Verlinkungen
- ✅ Keine veralteten Informationen

### Für Entwickler
- ✅ Klare Historie (CHANGELOG.md)
- ✅ Technische Details in README.md
- ✅ Wartung nur an einer Stelle

### Für Support
- ✅ Eine Referenz-Quelle
- ✅ Klare Upgrade-Pfade
- ✅ Vollständiges Troubleshooting

### Für Wartung
- ✅ Keine Redundanz
- ✅ Weniger Dateien zu aktualisieren
- ✅ Keine Versionskonflikte

---

## Checkliste für Plugin-Release

```
Release-Vorbereitung:
├── [ ] Version in as-multiindex-search.php aktualisieren
├── [ ] ASMI_VERSION Konstante aktualisieren
├── [ ] README.md: Version Badge aktualisieren
├── [ ] README.md: "Neue Features in vX.X.X" aktualisieren
├── [ ] CHANGELOG.md: Neuen Eintrag [X.X.X] hinzufügen
├── [ ] INSTALLATION.md: Upgrade-Pfad hinzufügen (falls nötig)
└── [ ] Git Tag erstellen: v1.12.0
```

---

## FAQ

**Q: Soll ich die alten Dateien löschen?**
A: Ja, nach erfolgreicher Migration. Sichere sie vorher in `docs-backup/`.

**Q: Was ist mit bestehenden Links zu README_INSTALLATION.txt?**
A: Erstelle einen Redirect oder ein Hinweis-Dokument:
```markdown
# README_INSTALLATION.txt

⚠️ Diese Datei wurde verschoben!

Neue Dokumentation:
- [INSTALLATION.md](INSTALLATION.md) - Installation & Upgrade
- [README.md](README.md) - Haupt-Dokumentation
```

**Q: Wie handle ich alte GitHub Issues mit Links?**
A: GitHub redirected automatisch zu README.md. Für andere: Bot-Kommentar mit neuen Links.

**Q: Muss ich CHANGELOG.md manuell pflegen?**
A: Ja, aber es ist eine Datei statt 3-4. Tools wie `standard-version` können helfen.

---

**Best Practices beachtet:**
- ✅ [Keep a Changelog](https://keepachangelog.com/)
- ✅ [Semantic Versioning](https://semver.org/)
- ✅ [Conventional Commits](https://www.conventionalcommits.org/)
- ✅ [GitHub Markdown Best Practices](https://guides.github.com/features/mastering-markdown/)

---

*Dokumentations-Konsolidierung durchgeführt: 19. November 2025*
*Von: Marc Mirschel*
