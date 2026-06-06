<?php
$language_array = array(

    /* =====================================================
     * WIZARD / STEPS
     * ===================================================== */
    'update_step_prepare'          => 'Vorbereitung',
    'update_step_migration'        => 'Migration',
    'update_step_finish'           => 'Abschluss',

    'update_step_tmp_check'        => 'Prüfe tmp-Verzeichnis',
    'update_step_download'         => 'Lade Updates herunter',
    'update_step_migration_action' => 'Führe Datenbank-Migrationen aus',
    'update_step_extract_files'    => 'Entpacke Update-Dateien und prüfe Dateiänderungen',
    'update_step_system_sync'      => '5. System-Synchronisation',
    'update_show_finish'           => 'Abschluss anzeigen',

    /* =====================================================
     * VERSION & STATUS
     * ===================================================== */
    'update_current_version'          => 'Aktuell installierte Version',
    'update_core_version_label'       => 'nexpell Core Version',
    'update_installed_at'             => 'Installiert am',

    'update_status_uptodate'          => 'Dein System ist auf dem neuesten Stand und einsatzbereit.',
    'update_status_updates_available' => 'Dein System läuft stabil, es sind jedoch Updates verfügbar.',
    'update_status_beta_hint'         => 'Du nutzt den Beta-Kanal und erhältst Vorab-Versionen.',
    'update_status_dev_hint'          => 'Du nutzt den Dev-Kanal.',
    'update_status_dev_warning'       => 'Achtung: Dev-Kanal – Versionen können instabil sein.',

    'update_channel_label' => 'Update-Kanal',

    'update_channel_stable' => 'Stable (empfohlen)',
    'update_channel_beta'   => 'Beta (Vorschau)',
    'update_channel_dev'    => 'Dev (intern)',

    'update_channel_stable_active_title' => 'Stable-Kanal aktiv:',
    'update_channel_stable_active_text'  => 'Es werden ausschließlich geprüfte und freigegebene Updates installiert.',

    'update_channel_beta_active_title' => 'Beta-Kanal aktiv:',
    'update_channel_beta_active_text'  => 'Du erhältst Vorab-Updates, die noch nicht final getestet sind.',

    'update_channel_dev_active_title' => 'Dev-Kanal aktiv:',
    'update_channel_dev_active_text'  => 'Interne Entwickler-Builds – nicht für Produktivsysteme geeignet!',


    /* =====================================================
     * BADGES
     * ===================================================== */
    'update_badge_uptodate'   => 'Aktuell',
    'update_badge_available' => 'Update verfügbar',
    'update_badge_beta'      => 'BETA',
    'update_badge_dev'       => 'DEV',

    /* =====================================================
     * UPDATE-ÜBERSICHT
     * ===================================================== */
    'update_no_updates_title'  => 'Dein System ist auf dem neuesten Stand.',
    'update_no_updates_text'   => "Es sind aktuell keine Updates verfügbar.\nAlle bekannten Stabilitäts- und Sicherheitsupdates\nwurden erfolgreich installiert.",
    'update_no_description'    => 'Keine Beschreibung.',

    'update_single_available'   => 'Ein neues Update steht bereit!',
    'update_multiple_available' => '{count} Updates stehen zur Installation bereit!',

    /* =====================================================
     * CHANGELOG
     * ===================================================== */
    'update_changelog_title'       => 'Änderungsprotokoll',
    'update_changelog_description' => 'Die folgenden Änderungen und Verbesserungen sind in den verfügbaren Updates enthalten:',
    'update_available_versions'    => 'Verfügbare Versionen',
    'update_info_description'      => 'Es wurden neue Versionen des Nexpell-Cores gefunden, die wichtige Verbesserungen, Sicherheits-Patches und neue Funktionen enthalten.',

    /* =====================================================
     * ACTIONS
     * ===================================================== */
    'update_start_now'      => 'Update jetzt starten',
    'update_reload_now'     => 'Updater jetzt neu laden',
    'update_back_overview'  => 'Zurück zur Übersicht',

    /* =====================================================
     * LOCK / UPDATER
     * ===================================================== */
    'update_lock_active'           => 'Der neue Updater ist aktiv.',
    'update_lock_confirm_continue' => 'Bitte entscheide explizit, ob du fortfahren möchtest.',

    'update_new_updater_installed'   => 'Neuer Updater (%s) wurde installiert.',
    'update_process_paused'          => 'Der Update-Prozess wurde bewusst pausiert, damit der neue Updater aktiv geladen werden kann.',
    'update_all_previous_completed'  => 'Alle bisherigen Updates wurden vollständig installiert und protokolliert.',

    /* =====================================================
     * TMP / DOWNLOAD
     * ===================================================== */
    'update_tmp_ok'            => 'tmp-Verzeichnis vorhanden / erstellt',
    'update_tmp_create_failed' => 'tmp-Verzeichnis konnte nicht erstellt werden',
    'update_tmp_log_title'     => 'tmp-Verzeichnis-Prüf-Log',

    'update_skip_build'        => 'Überspringe Version',
    'update_download_start'   => 'Lade Update',
    'update_download_failed'  => 'Update konnte nicht geladen werden',
    'update_zip_saved'        => 'ZIP erfolgreich gespeichert',
    'update_download_log'     => 'Download-Log',
    'update_error'            => 'Fehler:',

    /* =====================================================
     * MIGRATION
     * ===================================================== */
    'update_migration_log'                => 'Datenbank-Migrationen-Log',
    'update_no_migrations'                => 'Keine auszuführenden Migrationen gefunden.',
    'update_migration_extracted'          => 'Migration extrahiert',
    'update_no_migration'                 => 'Keine Datenbank-Migration enthalten',
    'update_migration_not_callable'       => 'Migration %s ist nicht ausführbar.',
    'update_migration_unexpected_output'  => 'Migration %s erzeugte unerwartete Ausgabe.',
    'update_migration_details'            => 'Migrations-Details',
    'update_migration_success'            => 'Migration %s erfolgreich abgeschlossen.',
    'update_migration_default_note'       => 'Datenbank-Migration',
    'update_migration_error'              => 'Fehler in Migration %s:',

    /* =====================================================
     * FILES
     * ===================================================== */
    'update_file_install_log' => 'Datei-Installations-Log',

    'update_files_extracted_success' =>
        'Dateien für Version <b>%s</b> erfolgreich entpackt.',

    'update_files_created'     => 'Neu erstellt (%d)',
    'update_files_overwritten' => 'Überschrieben (%d)',
    'update_files_deleted'     => 'Gelöscht (%d)',
    'update_file_changes'      => 'Dateiänderungen',

    'update_dir_not_exists'    => 'ℹ️ Ordner nicht vorhanden: %s',
    'update_dir_protected'     => '⛔ Schutz: %s darf nicht gelöscht werden',
    'update_dir_delete_start'  => '▶️ Starte Ordner-Löschung: %s',

    /* =====================================================
     * SYSTEM SYNC
     * ===================================================== */
    'update_cms_log_title'        => 'CMS-Updater Log',
    'update_system_sync_ok'       => 'System-Synchronisation ohne Meldungen abgeschlossen.',
    'update_cms_warning_title'    => 'CMS-Updater-Warnung:',
    'update_system_sync_skipped'  => 'CMS-Synchronisation übersprungen – Update wurde abgebrochen.',

    /* =====================================================
     * FINISH
     * ===================================================== */
    'update_finished_success' => 'System wurde erfolgreich aktualisiert auf Version',
    'update_finished_at'      => 'Aktualisiert am',

    /* =====================================================
     * DELETE / FS SAFETY
     * ===================================================== */
    'update_delete_abort_depth'    => '⛔ Abbruch: maximale Rekursionstiefe erreicht',
    'update_delete_symlink_skip'   => '⛔ Symlink übersprungen',
    'update_delete_scandir_failed' => '❌ scandir fehlgeschlagen',
    'update_delete_file_failed'    => '⚠️ Datei konnte nicht gelöscht werden',
    'update_delete_dir_failed'     => '⚠️ Ordner konnte nicht entfernt werden',
    'update_delete_dir_success'    => '🗑️ Ordner gelöscht',

    /* =====================================================
     * SERVER / HTTP / DIAGNOSE
     * ===================================================== */
    'update_server_title'       => 'Update-Server',
    'update_server_status'      => 'Status',
    'update_server_description' => 'Dieser Server stellt Core-, Plugin- und Sicherheitsupdates für Nexpell bereit.',

    'update_http_no_response'    => 'Keine Antwort',
    'update_error_no_connection' => 'Keine Verbindung zum Server – möglicherweise offline oder blockiert.',
    'update_http_200_warning'    => 'Datei ist erreichbar, aber enthält möglicherweise fehlerhafte Daten.',
    'update_http_403'            => 'Zugriff verweigert – der Server blockiert die Anfrage.',
    'update_http_404'            => 'Update-Datei nicht gefunden – möglicherweise verschoben oder gelöscht.',
    'update_http_5xx'            => 'Der Update-Server meldet einen internen Fehler oder ist überlastet.',
    'update_http_unknown'        => 'Unerwartete Server-Antwort: HTTP',

    'update_error_load_failed'   => 'Update-Informationen konnten nicht geladen werden',
    'update_error_json_invalid'  => 'Update-Informationen konnten nicht korrekt verarbeitet werden',

    'update_label_server'      => 'Server',
    'update_label_resource'    => 'Ressource',
    'update_label_http_status' => 'HTTP-Status',
    'update_label_reason'      => 'Ursache',
    'update_label_file'        => 'Datei',
    'update_label_json_error'  => 'JSON-Fehler',
    'update_label_hint'        => 'Hinweis',

    'update_help_title'          => 'Hilfe & Diagnose',
    'update_help_https'          => 'Prüfe, ob dein Server ausgehende HTTPS-Verbindungen erlaubt.',
    'update_help_shared_hosting' => 'Wenn du Shared Hosting nutzt (z. B. Lima-City, All-Inkl), aktiviere allow_url_fopen oder cURL.',
    'update_help_test_direct'    => 'Teste die Erreichbarkeit direkt',
    'update_help_checking'       => 'Prüfe Verbindung zu update.nexpell.de …',

    'update_server_reachable'   => 'Server ist erreichbar.',
    'update_server_unreachable' => 'Server ist weiterhin nicht erreichbar.',

    'update_hint_json_corrupt'  => 'Möglicherweise ist die Datei beschädigt oder leer.',


    'update_title'           => 'Nexpell Core Updater',
    'update_subtitle'        => 'Core-Updates prüfen, herunterladen und installieren',

    'update_channel_title'   => 'Update-Kanal',
    'update_channel_hint'    => 'Wähle aus, welche Art von Updates angezeigt und installiert werden sollen.',

    'update_progress_title'  => 'Update-Fortschritt',
    'update_steps_title'     => 'Schritte',

    'update_log_title'       => 'Updates',

    'update_footer_hint'     => 'Sicherer Core-Updater · Nexpell CMS',


    'update_history'        => 'Update-Historie',
    'update_type_initial'   => 'Initial',
    'update_channel_stable' => 'Stabil',
    'update_notes_empty'   => '–',

    'update_col_type'    => 'Typ',
    'update_col_version' => 'Version',
    'update_col_channel' => 'Kanal',
    'update_col_notes'   => 'Notizen',
    'update_col_date'    => 'Datum',

);
