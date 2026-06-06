## nexpell – Verzeichnisstruktur mit Beschreibung

```
/nexpell/
├── /admin/                          # Adminbereich des CMS
│   ├── /css/                        # Stylesheets für das Admin-Interface
│   ├── /images/                     # Admin-Bilder (z. B. Flaggen, Überschriften)
│   │   ├── /flags/                  # Länderflaggen für Sprachumschaltung o. ä.
│   │   └── /headlines/              # Headerbilder für Admin-Module
│   ├── /img/                        # Weitere Admin-Bilder
│   │   └── /bootstrap-colorpicker/  # Farbauswahl-Tool im Adminbereich
│   ├── /js/                         # JavaScript-Funktionen für Adminmodule
│   ├── /languages/                  # Sprachdateien für Adminmodule
│   │    ├── /de/                    # Deutsch
│   │    ├── /en/                    # Englisch
│   │    └── /it/                    # Italienisch
│   ├── /Logos                       # Logdateiteien
│   ├── /templates/                  # HTML/PHP-Templates für Adminmodule
│   ├── /tmp/                        # Temporäre Dateien 
│   └── /update_core                 # Update Dateien
│
├── /components/                     # Drittanbieter-Bibliotheken & wiederverwendbare UI-Komponenten
│   ├── /bootstrap/                  # Bootstrap-Framework (lokal eingebunden)
│   │   ├── /css/                    # Bootstrap-Stylesheets
│   │   └── /js/                     # Bootstrap-JavaScript
│   ├── /ckeditor/                   # CKEditor für Rich-Text-Editor-Funktionalität
│   ├── /cookies/                    # Cookie-Hinweis/Verwaltung (Cookie-Consent)
│   │   ├──                          # Stylesheets für Cookie-Banner
│   │   └──                          # JS für Cookie-Funktionalität
│   ├── /css/                        # Zusätzliche globale Styles (z. B. Icons, Themes)
│   ├── /PHPMailer/                  # PHP-Mail-Versandbibliothek (SMTP, TLS etc.)
│   └── /scrolltotop/                # Button-Funktion zum Hochscrollen
│       ├── /css/                    # Styles für den Scroll-Button
│       └── /js/                     # JS für Scroll-Logik
│
├── /images/                         # Globale Bilder für das Frontend
│   └── /avatars/                    # Benutzer-Avatare
│
├── /includes/                       # Zentrale Funktionsbibliothek des Systems
│   ├── /modules/                    # Backend-/Frontend-Module mit eigener Logik
│   ├── /plugins/                    # Plugin-Initialisierungen & Helper
│   └── /themes/                     # Template-bezogene Hilfsfunktionen
│       └── /default/                # Standard-Theme
│           ├── /css/                # Theme-Stylesheets
│           │   └── /dist            # Installierte Theme-Stylesheets
│           ├── /images/             # Theme-spezifische Bilder
│           └── /templates/          # Theme-Templates (HTML/PHP)
│
├── /install/                        # Installationsroutine und Setup-Dateien
│   ├── /css/                        # Styles für das Setup-UI
│   ├── /data/                       # SQL-Dateien für Struktur & Demodaten
│   ├── /images/                     # Installationsgrafiken, Logos etc.
│   │   └──flags                     # Länderflaggen für Sprachumschaltung
│   ├──  /js/                        # JavaScript für Setup-Ablauf
│   ├── /languages/                  # Globale Sprachdateien für die Installation
│   │   ├── /de/                     # Deutsch
│   │   ├── /en/                     # Englisch
│   │   └── /it/                     # Italienisch
│   └── /system/                     # Systemlogik und Infrastruktur
│
├── /languages/                      # Globale Sprachdateien für das Frontend
│   ├── /de/                         # Deutsch
│   ├── /en/                         # Englisch
│   └── /it/                         # Italienisch
│
├── /system/                         # Systemlogik und Infrastruktur
│   ├── /classes/                    # Kernklassen (z. B. Authentifizierung, DB, Templating)
│   ├── /core/                       # Klassenabfragen für /includes/themes/default/index.php
│   └── /func/                       # Zentrale Funktionen (z. B. Security, Mail, Routing)
│
├── /tmp/                            # Temporäre Dateien (z. B. während Uploads oder Exports)
│
├── .gitignore                       # Git-Konfiguration: ignorierte Dateien/Ordner
├── .htaccess                        # Apache-Umschreiberegeln und Zugriffsschutz
├── doku.html                        # Dokumenration
├── README.md                        # Projektübersicht, Installation, Hinweise
├── index.php                        # Einstiegspunkt für das Frontend
├── robots.txt                       # Robots
├── sitemap.php                      # Sitemap (für google)
└── sitemap.xml                      # Sitemap (für google)
```

In Arbeit:
...