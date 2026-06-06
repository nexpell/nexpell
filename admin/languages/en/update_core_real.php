<?php
$language_array = array(

    /* =====================================================
     * WIZARD / STEPS
     * ===================================================== */
    'update_step_prepare'          => 'Preparation',
    'update_step_migration'        => 'Migration',
    'update_step_finish'           => 'Finish',

    'update_step_tmp_check'        => 'Check tmp directory',
    'update_step_download'         => 'Download updates',
    'update_step_migration_action' => 'Run database migrations',
    'update_step_extract_files'    => 'Extract update files and check file changes',
    'update_step_system_sync'      => '5. System synchronization',
    'update_show_finish'           => 'Show summary',

    /* =====================================================
     * VERSION & STATUS
     * ===================================================== */
    'update_current_version'          => 'Currently installed version',
    'update_core_version_label'       => 'nexpell Core version',
    'update_installed_at'             => 'Installed on',

    'update_status_uptodate'          => 'Your system is up to date and ready for use.',
    'update_status_updates_available' => 'Your system is stable, but updates are available.',
    'update_status_beta_hint'         => 'You are using the beta channel and receive preview versions.',
    'update_status_dev_hint'          => 'You are using the dev channel.',
    'update_status_dev_warning'       => 'Warning: Dev channel – versions may be unstable.',

    'update_channel_label' => 'Update channel',

    'update_channel_stable' => 'Stable (recommended)',
    'update_channel_beta'   => 'Beta (preview)',
    'update_channel_dev'    => 'Dev (internal)',

    'update_channel_stable_active_title' => 'Stable channel active:',
    'update_channel_stable_active_text'  => 'Only tested and approved updates will be installed.',

    'update_channel_beta_active_title' => 'Beta channel active:',
    'update_channel_beta_active_text'  => 'You receive preview updates that are not yet fully tested.',

    'update_channel_dev_active_title' => 'Dev channel active:',
    'update_channel_dev_active_text'  => 'Internal developer builds – not suitable for production systems!',

    /* =====================================================
     * BADGES
     * ===================================================== */
    'update_badge_uptodate'   => 'Up to date',
    'update_badge_available' => 'Update available',
    'update_badge_beta'      => 'BETA',
    'update_badge_dev'       => 'DEV',

    /* =====================================================
     * UPDATE OVERVIEW
     * ===================================================== */
    'update_no_updates_title'  => 'Your system is up to date.',
    'update_no_updates_text'   => "There are currently no updates available.\nAll known stability and security updates\nhave been successfully installed.",
    'update_no_description'    => 'No description.',

    'update_single_available'   => 'One new update is available!',
    'update_multiple_available' => '{count} updates are available for installation!',

    /* =====================================================
     * CHANGELOG
     * ===================================================== */
    'update_changelog_title'       => 'Changelog',
    'update_changelog_description' => 'The following changes and improvements are included in the available updates:',
    'update_available_versions'    => 'Available versions',
    'update_info_description'      => 'New versions of the Nexpell core were found containing important improvements, security patches and new features.',

    /* =====================================================
     * ACTIONS
     * ===================================================== */
    'update_start_now'      => 'Start update now',
    'update_reload_now'     => 'Reload updater now',
    'update_back_overview'  => 'Back to overview',

    /* =====================================================
     * LOCK / UPDATER
     * ===================================================== */
    'update_lock_active'           => 'The new updater is active.',
    'update_lock_confirm_continue' => 'Please explicitly decide whether you want to continue.',

    'update_new_updater_installed'  => 'New updater (%s) has been installed.',
    'update_process_paused'         => 'The update process was intentionally paused so the new updater can be loaded.',
    'update_all_previous_completed' => 'All previous updates were fully installed and logged.',

    /* =====================================================
     * TMP / DOWNLOAD
     * ===================================================== */
    'update_tmp_ok'            => 'tmp directory exists / created',
    'update_tmp_create_failed' => 'tmp directory could not be created',
    'update_tmp_log_title'     => 'tmp directory check log',

    'update_skip_build'        => 'Skip version',
    'update_download_start'   => 'Downloading update',
    'update_download_failed'  => 'Update could not be downloaded',
    'update_zip_saved'        => 'ZIP successfully saved',
    'update_download_log'     => 'Download log',
    'update_error'            => 'Error:',

    /* =====================================================
     * MIGRATION
     * ===================================================== */
    'update_migration_log'               => 'Database migration log',
    'update_no_migrations'               => 'No migrations to execute.',
    'update_migration_extracted'         => 'Migration extracted',
    'update_no_migration'                => 'No database migration included',
    'update_migration_not_callable'      => 'Migration %s is not executable.',
    'update_migration_unexpected_output' => 'Migration %s produced unexpected output.',
    'update_migration_details'           => 'Migration details',
    'update_migration_success'           => 'Migration %s completed successfully.',
    'update_migration_default_note'      => 'Database migration',
    'update_migration_error'             => 'Error in migration %s:',

    /* =====================================================
     * FILES
     * ===================================================== */
    'update_file_install_log' => 'File installation log',

    'update_files_extracted_success' =>
        'Files for version <b>%s</b> were successfully extracted.',

    'update_files_created'     => 'Created (%d)',
    'update_files_overwritten' => 'Overwritten (%d)',
    'update_files_deleted'     => 'Deleted (%d)',
    'update_file_changes'      => 'File changes',

    'update_dir_not_exists'    => 'ℹ️ Directory does not exist: %s',
    'update_dir_protected'     => '⛔ Protection: %s must not be deleted',
    'update_dir_delete_start'  => '▶️ Starting directory deletion: %s',

    /* =====================================================
     * SYSTEM SYNC
     * ===================================================== */
    'update_cms_log_title'       => 'CMS updater log',
    'update_system_sync_ok'      => 'System synchronization completed without messages.',
    'update_cms_warning_title'   => 'CMS updater warning:',
    'update_system_sync_skipped' => 'System synchronization skipped – update was aborted.',

    /* =====================================================
     * FINISH
     * ===================================================== */
    'update_finished_success' => 'System was successfully updated to version',
    'update_finished_at'      => 'Updated on',

    /* =====================================================
     * DELETE / FS SAFETY
     * ===================================================== */
    'update_delete_abort_depth'    => '⛔ Abort: maximum recursion depth reached',
    'update_delete_symlink_skip'   => '⛔ Symlink skipped',
    'update_delete_scandir_failed' => '❌ scandir failed',
    'update_delete_file_failed'    => '⚠️ File could not be deleted',
    'update_delete_dir_failed'     => '⚠️ Directory could not be removed',
    'update_delete_dir_success'    => '🗑️ Directory deleted',

    /* =====================================================
     * SERVER / HTTP / DIAGNOSIS
     * ===================================================== */
    'update_server_title'       => 'Update server',
    'update_server_status'      => 'Status',
    'update_server_description' => 'This server provides core, plugin and security updates for Nexpell.',

    'update_http_no_response'    => 'No response',
    'update_error_no_connection' => 'No connection to server – possibly offline or blocked.',
    'update_http_200_warning'    => 'File is reachable but may contain invalid data.',
    'update_http_403'            => 'Access denied – the server is blocking the request.',
    'update_http_404'            => 'Update file not found – possibly moved or deleted.',
    'update_http_5xx'            => 'The update server reports an internal error or is overloaded.',
    'update_http_unknown'        => 'Unexpected server response: HTTP',

    'update_error_load_failed'  => 'Update information could not be loaded',
    'update_error_json_invalid' => 'Update information could not be processed correctly',

    'update_label_server'      => 'Server',
    'update_label_resource'    => 'Resource',
    'update_label_http_status' => 'HTTP status',
    'update_label_reason'      => 'Reason',
    'update_label_file'        => 'File',
    'update_label_json_error'  => 'JSON error',
    'update_label_hint'        => 'Hint',

    'update_help_title'          => 'Help & diagnostics',
    'update_help_https'          => 'Check whether your server allows outgoing HTTPS connections.',
    'update_help_shared_hosting' => 'If you use shared hosting, enable allow_url_fopen or cURL.',
    'update_help_test_direct'    => 'Test accessibility directly',
    'update_help_checking'       => 'Checking connection to update.nexpell.de …',

    'update_server_reachable'   => 'Server is reachable.',
    'update_server_unreachable' => 'Server is still not reachable.',

    'update_hint_json_corrupt'  => 'The file may be corrupted or empty.',

    'update_title'           => 'Nexpell Core Updater',
    'update_subtitle'        => 'Check, download and install core updates',

    'update_channel_title'   => 'Update channel',
    'update_channel_hint'    => 'Select which type of updates should be displayed and installed.',

    'update_progress_title'  => 'Update progress',
    'update_steps_title'     => 'Steps',

    'update_log_title'       => 'Updates',

    'update_footer_hint'     => 'Secure core updater · Nexpell CMS',
);
