<?php
$language_array = array(

    /* =====================================================
     * WIZARD / STEPS
     * ===================================================== */
    'update_step_prepare'          => 'Preparazione',
    'update_step_migration'        => 'Migrazione',
    'update_step_finish'           => 'Conclusione',

    'update_step_tmp_check'        => 'Controlla la directory tmp',
    'update_step_download'         => 'Scarica aggiornamenti',
    'update_step_migration_action' => 'Esegui migrazioni del database',
    'update_step_extract_files'    => 'Estrai i file di aggiornamento e verifica le modifiche',
    'update_step_system_sync'      => '5. Sincronizzazione del sistema',
    'update_show_finish'           => 'Mostra riepilogo',

    /* =====================================================
     * VERSION & STATUS
     * ===================================================== */
    'update_current_version'          => 'Versione attualmente installata',
    'update_core_version_label'       => 'Versione core nexpell',
    'update_installed_at'             => 'Installato il',

    'update_status_uptodate'          => 'Il tuo sistema è aggiornato e pronto all’uso.',
    'update_status_updates_available' => 'Il tuo sistema è stabile, ma sono disponibili aggiornamenti.',
    'update_status_beta_hint'         => 'Stai utilizzando il canale beta e ricevi versioni di anteprima.',
    'update_status_dev_hint'          => 'Stai utilizzando il canale dev.',
    'update_status_dev_warning'       => 'Attenzione: canale Dev – le versioni possono essere instabili.',

    'update_channel_label' => 'Canale di aggiornamento',

    'update_channel_stable' => 'Stable (consigliato)',
    'update_channel_beta'   => 'Beta (anteprima)',
    'update_channel_dev'    => 'Dev (interno)',

    'update_channel_stable_active_title' => 'Canale Stable attivo:',
    'update_channel_stable_active_text'  => 'Vengono installati solo aggiornamenti verificati e approvati.',

    'update_channel_beta_active_title' => 'Canale Beta attivo:',
    'update_channel_beta_active_text'  => 'Ricevi aggiornamenti di anteprima non ancora completamente testati.',

    'update_channel_dev_active_title' => 'Canale Dev attivo:',
    'update_channel_dev_active_text'  => 'Build interne per sviluppatori – non adatte a sistemi di produzione!',

    /* =====================================================
     * BADGES
     * ===================================================== */
    'update_badge_uptodate'   => 'Aggiornato',
    'update_badge_available' => 'Aggiornamento disponibile',
    'update_badge_beta'      => 'BETA',
    'update_badge_dev'       => 'DEV',

    /* =====================================================
     * UPDATE OVERVIEW
     * ===================================================== */
    'update_no_updates_title'  => 'Il tuo sistema è aggiornato.',
    'update_no_updates_text'   => "Al momento non sono disponibili aggiornamenti.\nTutti gli aggiornamenti di stabilità e sicurezza\nsono stati installati correttamente.",
    'update_no_description'    => 'Nessuna descrizione.',

    'update_single_available'   => 'È disponibile un nuovo aggiornamento!',
    'update_multiple_available' => 'Sono disponibili {count} aggiornamenti!',

    /* =====================================================
     * CHANGELOG
     * ===================================================== */
    'update_changelog_title'       => 'Registro delle modifiche',
    'update_changelog_description' => 'Le seguenti modifiche e migliorie sono incluse negli aggiornamenti disponibili:',
    'update_available_versions'    => 'Versioni disponibili',
    'update_info_description'      => 'Sono state trovate nuove versioni del core Nexpell con miglioramenti, patch di sicurezza e nuove funzionalità.',

    /* =====================================================
     * ACTIONS
     * ===================================================== */
    'update_start_now'      => 'Avvia aggiornamento',
    'update_reload_now'     => 'Ricarica updater',
    'update_back_overview'  => 'Torna alla panoramica',

    /* =====================================================
     * LOCK / UPDATER
     * ===================================================== */
    'update_lock_active'           => 'Il nuovo updater è attivo.',
    'update_lock_confirm_continue' => 'Decidi esplicitamente se vuoi continuare.',

    'update_new_updater_installed'  => 'È stato installato un nuovo updater (%s).',
    'update_process_paused'         => 'Il processo di aggiornamento è stato sospeso per caricare il nuovo updater.',
    'update_all_previous_completed' => 'Tutti gli aggiornamenti precedenti sono stati installati e registrati.',

    /* =====================================================
     * TMP / DOWNLOAD
     * ===================================================== */
    'update_tmp_ok'            => 'Directory tmp presente / creata',
    'update_tmp_create_failed' => 'Impossibile creare la directory tmp',
    'update_tmp_log_title'     => 'Log di controllo directory tmp',

    'update_skip_build'        => 'Salta versione',
    'update_download_start'   => 'Scaricamento aggiornamento',
    'update_download_failed'  => 'Impossibile scaricare l’aggiornamento',
    'update_zip_saved'        => 'ZIP salvato correttamente',
    'update_download_log'     => 'Log di download',
    'update_error'            => 'Errore:',

    /* =====================================================
     * MIGRATION
     * ===================================================== */
    'update_migration_log'               => 'Log migrazioni database',
    'update_no_migrations'               => 'Nessuna migrazione da eseguire.',
    'update_migration_extracted'         => 'Migrazione estratta',
    'update_no_migration'                => 'Nessuna migrazione database inclusa',
    'update_migration_not_callable'      => 'La migrazione %s non è eseguibile.',
    'update_migration_unexpected_output' => 'La migrazione %s ha prodotto output imprevisto.',
    'update_migration_details'           => 'Dettagli migrazione',
    'update_migration_success'           => 'Migrazione %s completata con successo.',
    'update_migration_default_note'      => 'Migrazione database',
    'update_migration_error'             => 'Errore nella migrazione %s:',

    /* =====================================================
     * FILES
     * ===================================================== */
    'update_file_install_log' => 'Log installazione file',

    'update_files_extracted_success' =>
        'I file per la versione <b>%s</b> sono stati estratti correttamente.',

    'update_files_created'     => 'Creati (%d)',
    'update_files_overwritten' => 'Sovrascritti (%d)',
    'update_files_deleted'     => 'Eliminati (%d)',
    'update_file_changes'      => 'Modifiche ai file',

    'update_dir_not_exists'    => 'ℹ️ Directory non esistente: %s',
    'update_dir_protected'     => '⛔ Protezione: %s non può essere eliminata',
    'update_dir_delete_start'  => '▶️ Avvio eliminazione directory: %s',

    /* =====================================================
     * SYSTEM SYNC
     * ===================================================== */
    'update_cms_log_title'       => 'Log updater CMS',
    'update_system_sync_ok'      => 'Sincronizzazione del sistema completata senza messaggi.',
    'update_cms_warning_title'   => 'Avviso updater CMS:',
    'update_system_sync_skipped' => 'Sincronizzazione del sistema saltata – aggiornamento interrotto.',

    /* =====================================================
     * FINISH
     * ===================================================== */
    'update_finished_success' => 'Il sistema è stato aggiornato con successo alla versione',
    'update_finished_at'      => 'Aggiornato il',

    /* =====================================================
     * DELETE / FS SAFETY
     * ===================================================== */
    'update_delete_abort_depth'    => '⛔ Interruzione: profondità massima di ricorsione raggiunta',
    'update_delete_symlink_skip'   => '⛔ Symlink ignorato',
    'update_delete_scandir_failed' => '❌ scandir non riuscito',
    'update_delete_file_failed'    => '⚠️ Impossibile eliminare il file',
    'update_delete_dir_failed'     => '⚠️ Impossibile rimuovere la directory',
    'update_delete_dir_success'    => '🗑️ Directory eliminata',

    /* =====================================================
     * SERVER / HTTP / DIAGNOSIS
     * ===================================================== */
    'update_server_title'       => 'Server di aggiornamento',
    'update_server_status'      => 'Stato',
    'update_server_description' => 'Questo server fornisce aggiornamenti core, plugin e di sicurezza per Nexpell.',

    'update_http_no_response'    => 'Nessuna risposta',
    'update_error_no_connection' => 'Nessuna connessione al server – potrebbe essere offline o bloccato.',
    'update_http_200_warning'    => 'Il file è raggiungibile ma potrebbe contenere dati non validi.',
    'update_http_403'            => 'Accesso negato – il server blocca la richiesta.',
    'update_http_404'            => 'File di aggiornamento non trovato.',
    'update_http_5xx'            => 'Il server di aggiornamento segnala un errore interno o è sovraccarico.',
    'update_http_unknown'        => 'Risposta server imprevista: HTTP',

    'update_error_load_failed'  => 'Impossibile caricare le informazioni di aggiornamento',
    'update_error_json_invalid' => 'Impossibile elaborare correttamente le informazioni di aggiornamento',

    'update_label_server'      => 'Server',
    'update_label_resource'    => 'Risorsa',
    'update_label_http_status' => 'Stato HTTP',
    'update_label_reason'      => 'Motivo',
    'update_label_file'        => 'File',
    'update_label_json_error'  => 'Errore JSON',
    'update_label_hint'        => 'Nota',

    'update_help_title'          => 'Aiuto e diagnostica',
    'update_help_https'          => 'Verifica che il server consenta connessioni HTTPS in uscita.',
    'update_help_shared_hosting' => 'Se utilizzi hosting condiviso, abilita allow_url_fopen o cURL.',
    'update_help_test_direct'    => 'Testa l’accessibilità direttamente',
    'update_help_checking'       => 'Verifica connessione a update.nexpell.de …',

    'update_server_reachable'   => 'Server raggiungibile.',
    'update_server_unreachable' => 'Server ancora non raggiungibile.',

    'update_hint_json_corrupt'  => 'Il file potrebbe essere danneggiato o vuoto.',

    'update_title'           => 'Updater core Nexpell',
    'update_subtitle'        => 'Controlla, scarica e installa aggiornamenti del core',

    'update_channel_title'   => 'Canale di aggiornamento',
    'update_channel_hint'    => 'Seleziona quali tipi di aggiornamenti devono essere mostrati e installati.',

    'update_progress_title'  => 'Avanzamento aggiornamento',
    'update_steps_title'     => 'Passaggi',

    'update_log_title'       => 'Aggiornamenti',

    'update_footer_hint'     => 'Updater core sicuro · Nexpell CMS',
);
