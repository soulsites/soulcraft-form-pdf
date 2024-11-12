# Ninja Forms PDF Generator

Ein WordPress Plugin zur automatischen PDF-Generierung aus Ninja Forms Submissions mit anpassbarem Design und Layout.

## Features

- Automatische PDF-Generierung bei Formular-Einreichung
- Individuelle Einstellungen pro Formular
- Modernes Dark Mode Interface für die Admin-Oberfläche
- Flexibles Layout-System für PDFs
- Automatischer E-Mail-Versand

## Installation

1. Plugin-Ordner in `/wp-content/plugins/` kopieren
2. Composer Dependencies installieren:
```bash
composer install
```
3. Plugin in WordPress aktivieren
4. PDF-Einstellungen unter "Einstellungen" → "PDF Generator" konfigurieren

## Abhängigkeiten

- WordPress 5.0+
- PHP 7.4+
- Ninja Forms Plugin
- Composer für:
    - Carbon Fields
    - FPDF

## Konfiguration

### PDF-Einstellungen pro Formular:

- Titel und Dateiname
- Schriftart und -größe
- Kopf- und Fußzeilen (Rich Text)
- Hintergrundfarben
- Zeilenabstände
- Metadaten-Anzeige

### Unterstützte Schriftarten:
- Helvetica
- Arial
- Times New Roman
- Courier

## Dateien

```
ninja-forms-pdf/
├── assets/
│   ├── css/
│   │   └── admin-style.css
│   └── js/
│       └── admin-js.js
├── inc/
│   ├── class-pdf-generator.php
│   └── class-settings-page.php
├── vendor/
├── composer.json
└── ninja-forms-pdf.php
```

## Entwicklung

### Build-Tools

- Assets werden dynamisch mit Versions-Hash geladen
- CSS verwendet CSS-Custom-Properties für Theming
- JS ist modular aufgebaut und verwendet jQuery

### Carbon Fields

Das Plugin nutzt Carbon Fields für:
- Formular-spezifische Einstellungen
- Repeater Fields für multiple Formulare
- Rich Text Editor Integration

### Standards

- PSR-4 Autoloading
- WordPress Coding Standards
- Modernes Asset Management

## Changelog

### 1.0.0
- Initiale Version
- PDF-Generierung
- E-Mail-Integration
- Dark Mode Admin Interface
- Formular-spezifische Einstellungen
- Rich Text Support
- Metadaten Integration

## Credits

- Carbon Fields für das Settings Framework
- FPDF für die PDF-Generierung
- Ninja Forms als Form Builder

## Lizenz

GPL v2 oder später