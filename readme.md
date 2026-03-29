# Soulcraft PDF Generator

Ein WordPress Plugin zur automatischen PDF-Generierung aus verschiedenen Form Buildern mit anpassbarem Design und Layout.

## Unterstützte Form Builder

- **Ninja Forms**
- **Contact Form 7 (CF7)**
- **Elementor Pro** (Form Widget)

## Features

- Automatische PDF-Generierung bei Formular-Einreichung
- Individuelle Einstellungen pro Formular (über alle Form Builder hinweg)
- Modernes Dark Mode Admin Interface (Material Design M3)
- Flexibles Layout-System für PDFs
- Automatischer E-Mail-Versand an mehrere Empfänger
- Rich Text Unterstützung für Kopf- und Fußzeilen
- Google Fonts Installation direkt aus dem Admin
- Eigene TTF/OTF Schriftarten
- PDF-Vorschau per E-Mail
- Debug-Logging mit Live-Refresh
- Automatisches Aufräumen alter PDFs (Standard: 30 Tage)

## Installation

1. Plugin-Ordner in `/wp-content/plugins/` kopieren
2. Composer Dependencies installieren:
```bash
composer install
```
3. Plugin in WordPress aktivieren
4. PDF-Einstellungen unter **Einstellungen → PDF Einstellungen** konfigurieren

## Anforderungen

- WordPress 5.0+
- PHP 7.4+
- Mindestens eines der unterstützten Form Builder Plugins

## Abhängigkeiten (Composer)

| Paket | Zweck |
|---|---|
| `setasign/fpdf` | PDF-Generierung |
| `htmlburger/carbon-fields` | WordPress Settings Framework |
| `yahnis-elsts/plugin-update-checker` | Automatische Updates via GitHub |

## Konfiguration

### PDF-Einstellungen pro Formular

- PDF-Titel und Dateiname
- E-Mail-Empfänger (mehrere möglich)
- Kopf- und Fußzeile (Rich Text / HTML)
- Schriftart und -größe
- Hintergrundfarben
- Zeilenabstand
- Metadaten-Anzeige (Datum, URL, Absender-E-Mail)

### Unterstützte Schriftarten

- Helvetica, Arial, Times New Roman, Courier (Standard)
- Google Fonts (Installation direkt im Admin)
- Eigene TTF/OTF Dateien im Ordner `fonts/google/`

## Dateistruktur

```
soulcraft-form-pdf/
├── assets/
│   ├── css/
│   │   └── admin-style.css       # Dark Mode Material Design M3
│   └── js/
│       └── admin-js.js           # Admin-Interface Interaktionen
├── inc/
│   ├── class-pdf-debug.php       # Debug-Logging (Singleton)
│   ├── class-pdf-base.php        # Abstrakte Basis für alle Provider
│   ├── class-form-registry.php   # Provider-Registry & Form-Verwaltung
│   ├── class-pdf-generator.php   # PDF-Generierung via FPDF
│   ├── class-settings-page.php   # Admin-Einstellungen via Carbon Fields
│   └── class-font-manager.php    # TTF/Google Fonts Verwaltung
├── providers/
│   ├── class-ninja-provider.php      # Ninja Forms Integration
│   ├── class-cf7-provider.php        # Contact Form 7 Integration
│   └── class-elementor-provider.php  # Elementor Pro Integration
├── fonts/
│   └── google/                   # Installierte Schriftarten (TTF/OTF)
├── soulcraft-pdf.php             # Haupt-Plugin-Datei
├── composer.json
└── plugin.json                   # Plugin-Metadaten für Update-Checker
```

## Entwicklung

### Architektur

Das Plugin folgt einem **Provider-Pattern**: Jeder Form Builder wird durch einen eigenen Provider repräsentiert, der von der abstrakten Klasse `PDF_Base` erbt. Der `Form_Registry` verwaltet alle aktiven Provider und routet Einreichungen an den richtigen Provider.

**Ablauf:**
```
Form-Einreichung → Provider Hook → prepare_submission_data()
→ PDF_Generator::generate() → Speichern in /wp-content/uploads/soulcraft-pdf/
→ send_pdf_emails() → PDF-Pfad zurückgeben
```

### Admin Interface

- CSS nutzt CSS Custom Properties für Theming
- Material Design M3 Dark Theme
- jQuery-basierte AJAX-Interaktionen

### Carbon Fields

Das Plugin nutzt Carbon Fields für:
- Formular-spezifische Einstellungen als Repeater Fields
- Rich Text Editor Integration
- AJAX-Callbacks für Preview und Font-Installation

### Standards

- PSR-4 Autoloading
- WordPress Coding Standards
- Nonce-Verifikation in allen AJAX-Handlern

## Bekannte Einschränkungen

- FPDF unterstützt nur CP1252-Encoding — für vollständiges UTF-8/Unicode-Support (Arabisch, CJK etc.) wäre ein Wechsel auf mPDF oder TCPDF erforderlich.
- JavaScript-Abhängigkeit von jQuery (WordPress-Standard im Admin).

## Changelog

### 1.0.0
- Initiale Version
- PDF-Generierung mit FPDF
- E-Mail-Integration
- Dark Mode Admin Interface
- Unterstützung für Ninja Forms, CF7 und Elementor Pro
- Formular-spezifische Einstellungen
- Rich Text Support für Kopf-/Fußzeile
- Google Fonts Installation
- Metadaten-Integration
- Debug-Logging

## Credits

- [Carbon Fields](https://carbonfields.net/) — Settings Framework
- [FPDF](http://www.fpdf.org/) — PDF-Generierung
- [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) — Auto-Updates

## Lizenz

GPL v2 oder später
