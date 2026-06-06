# 📜 CHANGELOG

## [1.0.0] – 2025-05-05  
### nexpell

> ⚠️ **Major Release**  
> Vollständige Neuimplementierung der Plattform. Nicht abwärtskompatibel mit 2.x.

---

### 🚀 Highlights

- Komplette Neuimplementierung auf Basis moderner objektorientierter PHP-Strukturen
- Neue Template-Engine mit `{ placeholder }`- und `[[lang:de]]Mehrsprachigkeit[[lang:en]]Multilanguage`-Support
- Intelligenter 6-Schritte-Installer mit Sicherheitscheck
- Neue rollenbasierte Rechteverwaltung (RBAC) inkl. Admin- und Modulsteuerung
- Dynamisches Theme- und Plugin-System
- Verbesserte Sicherheit: password_hash, CSRF, IP-Ban, Login-Versuchszähler, reCAPTCHA
- Volle Trennung von Backend, Frontend, Plugins und Systemlogik
- Neues Admin-Dashboard mit Statistiken, Widgets und Benutzerübersicht

---

### ✨ Neu

- `Template`-, `AccessControl`-, `PluginManager`- und `ThemeManager`-Klassen
- Templates laden aus `/admin/templates/`, `/themes/`, `/plugins/`
- Adminrechte über `navigation_dashboard_links` + `admin_access_rights`
- Neue Datenbankstruktur mit `user_roles`, `user_role_assignments`, `banned_ips`, `failed_login_attempts`
- Installer schreibt `installed.lock` nach erfolgreichem Setup
- Frontend-/Backend-Templates vollständig entkoppelt

---

### 🔒 Sicherheit

- Passwörter: `password_hash()` + Pepper
- IP-Sperren: `banned_ips`
- Login-Schutz: `failed_login_attempts`
- reCAPTCHA v2-Integration
- CSRF-Token-Schutz auf allen Formularen

---

### 💡 Entwickler

- Klassenbasierte Architektur in `/system/classes/`
- Zentrale Konfiguration über `config.inc.php` + Theme-Settings
- Erweiterbar über Plugins mit Templates, Sprachdateien und eigener Navigation

---

### ❌ Entfernt

- Veraltete Includes (`tmpl_*`, `index_functions.php`, `admin_rights.php`)
- Alte Benutzergruppen-Logik
- Statische Template-PHP-Vermischungen
- Legacy-Plugins (werden durch neue Struktur ersetzt)

---

### 🏁 Migration

> Aufgrund der strukturellen Änderungen ist ein **Upgrade von 2.x nicht direkt möglich**.  
> Eine **Neuinstallation wird empfohlen**. Datenmigration muss manuell oder per Konverter erfolgen.

---



📄 Lizenzhinweis

    nexpell ist ein freies, quelloffenes Content-Management-System
    zur Erstellung und Verwaltung moderner Web-Communitys.

    Es wird unter der GNU General Public License v3.0 (GPL) veröffentlicht.

    Das System wurde von Grund auf neu entwickelt, mit dem Ziel,
    eine sichere, erweiterbare und modulare Plattform auf Basis aktueller
    Webtechnologien bereitzustellen.

    Jeder darf den Quellcode nutzen, verändern und weiterverbreiten –
    unter Einhaltung der Bedingungen der GPL v3.

    Eine kommerzielle Nutzung ist erlaubt, solange der Quellcode offenbleibt
    und Lizenzhinweise erhalten bleiben.

---

### 🔗 Links

- [Website](https://www.nexpell.de)
- [Forum](https://www.nexpell.de/forum.html)
- [Dokumentation](https://www.nexpell.de/wiki.html)
- [GitHub](https://github.com/nexpell/)

---

© 2025 nexpell Team – GNU General Public License (GPL)
