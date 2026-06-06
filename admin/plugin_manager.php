<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Den Admin-Zugriff fÃ¼r das Modul Ã¼berprÃ¼fen
AccessControl::checkAdminAccess('ac_plugin_manager');

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    $action = '';
}

if (!function_exists('normalize_plugin_modulname')) {
    function normalize_plugin_modulname(string $value): string
    {
        $value = trim($value);
        $value = trim($value, ',');
        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
        return strtolower((string)$value);
    }
}



    $do = $_GET['do'] ?? '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0 && ($do === 'dea' || $do === 'act')) {

        $res = safe_query("SELECT `modulname` FROM `settings_plugins` WHERE `pluginID` = '" . (int)$id . "'");
        if (!$res || mysqli_num_rows($res) === 0) nx_redirect('admincenter.php?site=plugin_manager','danger','alert_plugin_not_found',false);

        $row = mysqli_fetch_assoc($res);
        $modulname = normalize_plugin_modulname((string)($row['modulname'] ?? ''));

        try {

            if ($do === 'dea') {
                safe_query("UPDATE `settings_plugins` SET `activate` = '0' WHERE `pluginID` = '" . (int)$id . "'");
                safe_query("UPDATE `navigation_website_sub` SET `indropdown` = '0' WHERE `modulname` = '" . escape($modulname) . "'");

                nx_audit_update('settings_plugins',(string)$id,true,$modulname,'admincenter.php?site=plugin_manager',['state' => 'deactivated']);
                nx_redirect('admincenter.php?site=plugin_manager','success',sprintf($languageService->get('alert_plugin_deactivated'),htmlspecialchars($modulname,ENT_QUOTES,'UTF-8')),false,true);
            } else {
                safe_query("UPDATE `settings_plugins` SET `activate` = '1' WHERE `pluginID` = '" . (int)$id . "'");
                safe_query("UPDATE `navigation_website_sub` SET `indropdown` = '1' WHERE `modulname` = '" . escape($modulname) . "'");

                nx_audit_update('settings_plugins',(string)$id,true,$modulname,'admincenter.php?site=plugin_manager',['state' => 'activated']);
                nx_redirect('admincenter.php?site=plugin_manager','success',sprintf($languageService->get('alert_plugin_activated'),htmlspecialchars($modulname,ENT_QUOTES,'UTF-8')),false,true);
            }

        } catch (Exception $e) {
            nx_redirect('admincenter.php?site=plugin_manager','danger','alert_plugin_action_failed',false);
        }
    }

/* ==========================================================================
    PLUGIN MANAGER - SAVE LOGIC (ADD & EDIT)
   ========================================================================== */

if ((isset($_POST['add']) || isset($_POST['edit'])) && isset($_POST['modulname'])) {
    
    // 1. Grunddaten initialisieren
    $pluginID    = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
    $isEdit      = ($pluginID > 0);
    $acti        = isset($_POST['activate']) ? 1 : 0;
    $currentLang = strtolower($languageService->detectLanguage());

    // Fix: Fallback-Werte (direkter Zugriff auf Array-Keys der aktuellen Sprache)
    $name        = escape($_POST['plugin_name'][$currentLang] ?? '');
    $info        = escape($_POST['plugin_info'][$currentLang] ?? '');
    $modulnameRaw = normalize_plugin_modulname((string)($_POST['modulname'] ?? ''));
    $modulname   = escape($modulnameRaw);
    $admin_file  = escape($_POST['admin_file'] ?? '');
    $index_file  = escape($_POST['index_link'] ?? '');
    $author      = escape($_POST['author'] ?? '');
    $website     = escape($_POST['website'] ?? '');
    $version     = escape($_POST['version'] ?? '1.0.0');
    $path        = escape($_POST['path'] ?? '');
    $hiddenfiles = escape($_POST['hiddenfiles'] ?? '');

    // Admin Navigation bleibt (vorerst) einzeln
    $admin_cat_id    = (int)($_POST['nav_admin_cat'] ?? 0);
    $admin_file_url  = escape($_POST['nav_admin_link'] ?? '');

    try {
        // 2. Plugin Hauptdaten
        if ($isEdit) {
            safe_query("UPDATE `settings_plugins` SET 
                `activate` = '$acti', 
                `admin_file` = '$admin_file', `index_link` = '$index_file', `hiddenfiles` = '$hiddenfiles'
                WHERE pluginID = $pluginID");

            safe_query("UPDATE `settings_plugins_installed` SET 
                `name` = '$name', `description` = '$info' 
                WHERE modulname = '$modulname'");
        } else {
            safe_query("INSERT INTO `settings_plugins` (
                `modulname`, `activate`, `admin_file`, `author`, `website`, `index_link`,
                `hiddenfiles`, `version`, `path`, `status_display`, `plugin_display`, `widget_display`, `delete_display`, `sidebar`
            ) VALUES (
                '$modulname', '1', '$admin_file', '$author', '$website', '$index_file',
                '$hiddenfiles', '$version', '$path', '1', '1', '1', '1', 'deactivated'
            )");
            $pluginID = mysqli_insert_id($GLOBALS['up_db']);

            safe_query("INSERT INTO `settings_plugins_installed` 
                (`name`, `modulname`, `description`, `version`, `author`, `url`, `folder`, `installed_date`)
                VALUES ('$name', '$modulname', '$info', '$version', '$author', '$website', '$modulname', NOW())");
            
            safe_query("INSERT INTO `user_role_admin_navi_rights` (`roleID`, `type`, `modulname`) VALUES (1, 'link', '$modulname')");
        }



// =====================================================
// SETTINGS_PLUGINS_LANG â€“ PLUGIN NAME + INFO
// =====================================================
foreach ($_POST['plugin_name'] ?? [] as $iso => $value) {

    $iso  = escape(strtolower($iso));
    $name = escape($value ?? '');
    $info = escape($_POST['plugin_info'][$iso] ?? '');

    if ($name !== '') {
        safe_query("
            INSERT INTO settings_plugins_lang (content_key, language, content)
            VALUES ('plugin_name_$modulname', '$iso', '$name')
            ON DUPLICATE KEY UPDATE content = '$name'
        ");
    }

    if ($info !== '') {
        safe_query("
            INSERT INTO settings_plugins_lang (content_key, language, content)
            VALUES ('plugin_info_$modulname', '$iso', '$info')
            ON DUPLICATE KEY UPDATE content = '$info'
        ");
    }
}

// =====================================================
// SETTINGS_PLUGINS_LANG â€“ WEBSITE NAV TITLES (1â€“3)
// =====================================================
for ($i = 1; $i <= 3; $i++) {

    if (!isset($_POST['nav_website_title_lang'][$i])) {
        continue;
    }

    foreach ($_POST['nav_website_title_lang'][$i] as $iso => $title) {

        $isoTitle = escape($title ?? '');
        $iso      = escape(strtolower($iso));

        if ($isoTitle === '') {
            continue;
        }

        // eindeutiger Key pro Modul + Slot
        $key = 'nav_website_' . $modulname . '_' . $i;

        safe_query("
            INSERT INTO settings_plugins_lang (content_key, language, content)
            VALUES ('$key', '$iso', '$isoTitle')
            ON DUPLICATE KEY UPDATE content = '$isoTitle'
        ");
    }
}



        // 3. Admin-Navigation
        if ($admin_cat_id > 0) {

    // ---------------------------------------------
    // 1. PrÃ¼fen, ob Admin-Navi-Eintrag existiert
    // ---------------------------------------------
    $res = safe_query("
        SELECT linkID
        FROM navigation_dashboard_links
        WHERE modulname = '$modulname'
        LIMIT 1
    ");

    if ($row = mysqli_fetch_assoc($res)) {

        // ---------- UPDATE ----------
        $linkID = (int)$row['linkID'];

        safe_query("
            UPDATE navigation_dashboard_links
            SET catID = $admin_cat_id,
                url   = '$admin_file_url'
            WHERE linkID = $linkID
        ");

    } else {

        // ---------- INSERT ----------
        safe_query("
            INSERT INTO navigation_dashboard_links
                (catID, modulname, url, sort)
            VALUES
                ($admin_cat_id, '$modulname', '$admin_file_url', 1)
        ");

        // WICHTIG: neue linkID holen
        $linkID = mysqli_insert_id($GLOBALS['up_db']);
    }

    // ---------------------------------------------
    // 2. Mehrsprachige Titel speichern
    // ---------------------------------------------
    if ($linkID > 0) {

        foreach (($_POST['nav_admin_title_lang'] ?? []) as $iso => $title) {

            $isoTitle = escape($title ?? '');
            $iso      = escape(strtolower($iso));

            if ($isoTitle === '') {
                continue;
            }

            $isoKey = 'nav_link_' . $linkID;

            safe_query("
                INSERT INTO navigation_dashboard_lang
                    (language, content_key, content)
                VALUES
                    ('$iso', '$isoKey', '$isoTitle')
                ON DUPLICATE KEY UPDATE
                    content = '$isoTitle'
            ");
        }
    }
}


        // 4. WEBSITE-NAVIGATION (Schleife fÃ¼r 3 EintrÃ¤ge)
        // Zuerst bestehende EintrÃ¤ge lÃ¶schen oder via modulname identifizieren
        // Wenn dein System pro Modul mehrere EintrÃ¤ge erlaubt, brauchen wir eine Referenz.
        // Falls du sie Ã¼berschreiben willst:
        if ($isEdit) {
            // Optional: Alte Sprachen lÃ¶schen, falls snavID bekannt ist
            // Hier gehen wir davon aus, dass wir neue hinzufÃ¼gen oder updaten
        }

        // 4. WEBSITE-NAVIGATION (Schleife fÃ¼r 3 EintrÃ¤ge)
        // 4. WEBSITE-NAVIGATION (Schleife fÃ¼r 3 EintrÃ¤ge)
global $_database;
error_log('NAV SAVE START');

for ($i = 1; $i <= 3; $i++) {

    $web_cat_id = (int)($_POST['nav_website_cat'][$i] ?? 0);
    $web_url    = trim($_POST['nav_website_link'][$i] ?? '');

    // PATCH: nur Ã¼berspringen, wenn BEIDES leer ist
    if ($web_cat_id <= 0 && $web_url === '') {
        continue;
    }

    // Kategorie darf 0 sein
    $mnavID = ($web_cat_id > 0) ? $web_cat_id : 0;

    $escapedUrl = escape($web_url);
    $existingNavRes = safe_query("
        SELECT snavID
        FROM navigation_website_sub
        WHERE modulname = '$modulname' AND sort = $i
        LIMIT 1
    ");
    $existingNav = mysqli_fetch_assoc($existingNavRes);

    if (!empty($existingNav['snavID'])) {
        $snavID = (int)$existingNav['snavID'];
        safe_query("
            UPDATE navigation_website_sub
            SET mnavID = $mnavID,
                url = '$escapedUrl',
                indropdown = 1
            WHERE snavID = $snavID
        ");
    } else {
        safe_query("
            INSERT INTO navigation_website_sub
                (mnavID, modulname, url, sort, indropdown)
            VALUES
                ($mnavID, '$modulname', '$escapedUrl', $i, 1)
        ");
        $snavID = (int)mysqli_insert_id($_database);
    }

    // -----------------------------------------
    // snavID sicher holen
    // -----------------------------------------
    if (empty($snavID)) {
        $resID = safe_query("
            SELECT snavID
            FROM navigation_website_sub
            WHERE modulname = '$modulname' AND sort = $i
            LIMIT 1
        ");

        $rowID = mysqli_fetch_assoc($resID);
        $snavID = (int)($rowID['snavID'] ?? 0);
    }

    // -----------------------------------------
    // Sprach-Titel speichern
    // -----------------------------------------
    if ($snavID > 0 && isset($_POST['nav_website_title_lang'][$i])) {

        foreach ($_POST['nav_website_title_lang'][$i] as $iso => $title) {

            $isoTitle = trim($title ?? '');
            if ($isoTitle === '') {
                continue;
            }

            $isoKey = 'nav_sub_' . $snavID;

            safe_query("
                INSERT INTO navigation_website_lang
                    (language, content_key, content)
                VALUES
                    ('" . escape($iso) . "', '$isoKey', '" . escape($isoTitle) . "')
                ON DUPLICATE KEY UPDATE
                    content = '" . escape($isoTitle) . "'
            ");
        }
    }
}

error_log('NAV SAVE END');



        // 5. Dateisystem-Logik
        if (!$isEdit) {
            if (!function_exists('sanitizeFilename')) {
                function sanitizeFilename(string $filename): string {
                    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
                }
            }
            $cleanPath = trim(str_replace(['includes/plugins/', '/includes/plugins/'], '', $path), '/');
            $baseDir   = realpath(__DIR__ . '/../includes/plugins');
            $pluginDir = $baseDir . '/' . $cleanPath;

            if ($baseDir && !empty($cleanPath)) {
                if (!is_dir($pluginDir)) mkdir($pluginDir, 0755, true);
                if (!is_dir($pluginDir . '/admin')) mkdir($pluginDir . '/admin', 0755, true);
                $adminFile = sanitizeFilename(preg_replace('/\.php$/i', '', $admin_file ?: 'admin')) . '.php';
                $indexFile = sanitizeFilename(preg_replace('/\.php$/i', '', $index_file ?: 'index')) . '.php';

                if (!file_exists($pluginDir . '/admin/' . $adminFile)) {
                    file_put_contents($pluginDir . '/admin/' . $adminFile, "<?php\necho 'Admin Section'; ?>");
                }
                if (!file_exists($pluginDir . '/' . $indexFile)) {
                    file_put_contents($pluginDir . '/' . $indexFile, "<?php\necho 'Plugin Index'; ?>");
                }
            }
        }

        $target = $isEdit ? 'admincenter.php?site=plugin_manager&action=edit&id='.$pluginID : 'admincenter.php?site=plugin_manager';
        nx_redirect($target, 'success', 'alert_saved', false);

    } catch (Exception $e) {
        nx_redirect('admincenter.php?site=plugin_manager', 'danger', 'alert_save_failed', false);
    }
}







#Erstellt eine neue Plugin-Einstellung END
if (isset($_GET['action']) && $_GET['action'] == 'delete_plugin' && isset($_GET['modulname'])) {

    $modulname = normalize_plugin_modulname((string)($_GET['modulname'] ?? ''));
    $modulname_safe = mysqli_real_escape_string($_database, $modulname);

    $plugin_name_query = safe_query("SELECT modulname FROM settings_plugins WHERE modulname = '" . $modulname_safe . "'");

    if ($plugin_name_query && mysqli_num_rows($plugin_name_query) > 0) {

        $plugin_name = mysqli_fetch_assoc($plugin_name_query)['modulname'];
        $plugin_name_escaped = escape($plugin_name); // Einmal escapen fÃ¼r alle Queries

        // 1. Hauptdaten lÃ¶schen
        safe_query("DELETE FROM `settings_widgets` WHERE `modulname` = '" . $plugin_name_escaped . "'");
        safe_query("DELETE FROM `settings_widgets_positions` WHERE `modulname` = '" . $plugin_name_escaped . "'");
        safe_query("DELETE FROM `settings_plugins` WHERE `modulname` = '" . $plugin_name_escaped . "'");
        safe_query("DELETE FROM `navigation_dashboard_links` WHERE `modulname` = '" . $plugin_name_escaped . "'");
        safe_query("DELETE FROM `navigation_website_sub` WHERE `modulname` = '" . $plugin_name_escaped . "'");
        safe_query("DELETE FROM `user_role_admin_navi_rights` WHERE `modulname` = '" . $plugin_name_escaped . "'");
        safe_query("DELETE FROM `settings_plugins_installed` WHERE `modulname` = '" . $plugin_name_escaped . "'");

        /* =====================================================
         * NEU: SPRACH-EINTRÃ„GE LÃ–SCHEN
         * ===================================================== */
        safe_query("DELETE FROM `navigation_dashboard_lang` WHERE `modulname` = '" . $plugin_name_escaped . "'");
        safe_query("DELETE FROM `navigation_website_lang` WHERE `modulname` = '" . $plugin_name_escaped . "'");
        safe_query("DELETE FROM `settings_plugins_lang` WHERE `modulname` = '" . $plugin_name_escaped . "'");

        // Audit & Redirect
        nx_audit_delete('plugin_manager', (string)$plugin_name, (string)$plugin_name, 'admincenter.php?site=plugin_manager');
        nx_redirect('admincenter.php?site=plugin_manager', 'success', sprintf($languageService->get('alert_plugin_deleted'), htmlspecialchars($plugin_name, ENT_QUOTES, 'UTF-8')), false, true);

    } else {
        nx_redirect('admincenter.php?site=plugin_manager', 'danger', 'alert_plugin_not_found', false);
    }
}
    #Erstellt eine neue Widget-Einstellung START

// =====================================================
// WIDGET ADD / EDIT â€“ COMBINED HANDLER
// =====================================================
if (isset($_POST['widget_add']) || isset($_POST['edit_widget'])) {

    try {

        $isEdit = isset($_POST['edit_widget']);

        // =========================
        // COMMON INPUT
        // =========================
        $id        = (int)($_POST['id'] ?? 0);
        $title     = escape($_POST['title'] ?? '');
        $modulname = escape(normalize_plugin_modulname((string)($_POST['modulname'] ?? '')));

        if ($id <= 0 || $title === '') {
            nx_redirect(
                'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit',
                'warning',
                'alert_transaction_invalid',
                false
            );
        }

        // =========================
        // ADD MODE
        // =========================
        if (!$isEdit) {

            $widget_key = escape($_POST['widget_key'] ?? '');

            if ($widget_key === '' || $modulname === '') {
                nx_redirect(
                    'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit',
                    'warning',
                    'alert_transaction_invalid',
                    false
                );
            }

            $res = safe_query("
                INSERT INTO settings_widgets
                    (widget_key, title, plugin, modulname)
                VALUES
                    ('$widget_key', '$title', '$modulname', '$modulname')
            ");

            if ($res === false) {
                nx_redirect(
                    'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit',
                    'danger',
                    'alert_db_error',
                    false
                );
            }

            nx_audit_create(
                'plugin_manager',
                (string)$widget_key,
                $title,
                'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit'
            );
        }

        // =========================
        // EDIT MODE
        // =========================
        else {

            $new_widget_key      = escape($_POST['new_widget_key'] ?? '');
            $original_widget_key = escape($_POST['original_widget_key'] ?? '');

            if ($new_widget_key === '' || $original_widget_key === '') {
                nx_redirect(
                    'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit',
                    'warning',
                    'alert_transaction_invalid',
                    false
                );
            }

            $res = safe_query("
                UPDATE settings_widgets
                SET
                    widget_key = '$new_widget_key',
                    title      = '$title'
                WHERE widget_key = '$original_widget_key'
            ");

            if ($res === false) {
                nx_redirect(
                    'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit',
                    'danger',
                    'alert_db_error',
                    false
                );
            }

            nx_audit_update(
                'plugin_manager',
                (string)$original_widget_key,
                true,
                $title,
                'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit',
                ['new_widget_key' => $new_widget_key]
            );
        }

        // =========================
        // SUCCESS
        // =========================
        nx_redirect(
            'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit',
            'success',
            'alert_saved',
            false
        );

    } catch (Exception $e) {

        nx_redirect(
            'admincenter.php?site=plugin_manager&action=edit&id=' . (int)($_POST['id'] ?? 0) . '&do=edit',
            'danger',
            'alert_save_failed',
            false
        );
    }
}

#Erstellt eine neue Widget-Einstellung END

if (isset($_GET['delete'])) {

    $CAPCLASS = new \nexpell\Captcha();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'] ?? '')) nx_redirect('admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit', 'danger', 'alert_transaction_invalid', false);

    $widget_key = escape((string)($_GET['widget_key'] ?? ''));
    if ($widget_key === '') nx_redirect('admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit', 'warning', 'alert_transaction_invalid', false);

    $res = safe_query("SELECT modulname FROM settings_widgets WHERE widget_key = '" . $widget_key . "'");
    if (!$res || !mysqli_num_rows($res)) nx_redirect('admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit', 'warning', 'alert_not_found', false);

    $data = mysqli_fetch_assoc($res);
    $modulname = escape(normalize_plugin_modulname((string)($data['modulname'] ?? '')));
    if ($modulname === '') nx_redirect('admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit', 'warning', 'alert_not_found', false);

    $del1 = safe_query("DELETE FROM settings_widgets WHERE widget_key='" . $widget_key . "'");
    $del2 = safe_query("DELETE FROM settings_widgets_positions WHERE modulname='" . $modulname . "'");
    if ($del1 === false || $del2 === false) nx_redirect('admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit', 'danger', 'alert_db_error', false);
    nx_audit_delete('plugin_manager', (string)$widget_key, (string)$widget_key, 'admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit');
    nx_redirect('admincenter.php?site=plugin_manager&action=edit&id=' . $id . '&do=edit', 'success', 'alert_deleted', false);
}














































if ($action === 'widget_add' || $action === 'edit_widget') {

    $id = (int)($_GET['id'] ?? 0);

    // =========================
    // CAPTCHA
    // =========================
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    // =========================
    // MODE SWITCH
    // =========================
    $isEdit = ($action === 'edit_widget');

    if ($isEdit) {
        $widget_key = $_GET['widget_key'] ?? '';
        $result = safe_query("
            SELECT *
            FROM settings_widgets
            WHERE widget_key = '" . escape($widget_key) . "'
        ");
        $db = mysqli_fetch_array($result);

        $headlineSmall = $languageService->get('edit');
        $submitName    = 'edit_widget';
        $submitValue   = '1';
        $submitClass   = 'btn-warning';
        $submitIcon    = '<i class="bi bi-pencil-square"></i> ';
        $submitText    = $languageService->get('edit_widget');

        $titleValue       = escape($db['title']);
        $widgetKeyValue   = escape($db['widget_key']);
        $widgetKeyName    = 'new_widget_key';
        $hiddenExtraField = '
            <input type="hidden" name="original_widget_key" value="' . escape($db['widget_key']) . '">
        ';

    } else {
        $result = safe_query("
            SELECT *
            FROM settings_plugins
            WHERE pluginID = '" . $id . "'
        ");
        $db = mysqli_fetch_array($result);

        $headlineSmall = $languageService->get('add');
        $submitName    = 'widget_add';
        $submitValue   = '1';
        $submitClass   = 'btn-primary';
        $submitIcon    = '';
        $submitText    = $languageService->get('add');

        $titleValue       = '';
        $widgetKeyValue   = '';
        $widgetKeyName    = 'widget_key';
        $hiddenExtraField = '';
    }

    // =========================
    // OUTPUT
    // =========================
    echo '
    <div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title">
                <i class="bi bi-puzzle"></i>
                <span>' . $languageService->get('plugin_manager') . '</span>
                <small class="text-muted">' . $headlineSmall . '</small>
            </div>
        </div>

        <div class="card-body">

            <div class="mb-4">
                <h6 class="mb-3">Plugin</h6>
                <div class="row g-3">
                    <div class="col-auto">
                        <div class="alert alert-info fw-semibold mb-0 py-2">
                            ' . escape($db['modulname'] ?? $db['name']) . '
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <form method="post"
                  action="admincenter.php?site=plugin_manager"
                  enctype="multipart/form-data"
                  onsubmit="return chkFormular();">

                <div class="mb-4">
                    <h6 class="mb-3">' . $languageService->get('settings') . '</h6>

                    <div class="row g-3">

                        <div class="col-lg-6">
                            <label class="form-label">
                                ' . $languageService->get('widget_name') . '
                                <span class="text-danger">*</span>
                            </label>
                            <div class="form-text mb-1">
                                (' . $languageService->get('for_widgetname') . ')
                            </div>
                            <input type="text"
                                   class="form-control"
                                   name="title"
                                   value="' . $titleValue . '"
                                   required>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">
                                ' . $languageService->get('modulname') . '
                            </label>
                            <div class="form-text mb-1">
                                (' . $languageService->get('for_plugin') . ')
                            </div>
                            <input type="text"
                                   class="form-control"
                                   value="' . escape($db['modulname']) . '"
                                   disabled>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">
                                ' . $languageService->get('widget_datei') . '
                                <span class="text-danger">*</span>
                            </label>
                            <div class="form-text mb-1">
                                (' . $languageService->get('widgetdatei_nophp') . ')
                            </div>
                            <input type="text"
                                   class="form-control"
                                   name="' . $widgetKeyName . '"
                                   value="' . $widgetKeyValue . '"
                                   required>
                        </div>

                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 pt-2">
                    <input type="hidden" name="modulname" value="' . escape($db['modulname']) . '">
                    <input type="hidden" name="id" value="' . $id . '">
                    ' . $hiddenExtraField . '

                    <button class="btn ' . $submitClass . '" type="submit"
                            name="' . $submitName . '" value="' . $submitValue . '">
                        ' . $submitIcon . $submitText . '
                    </button>

                    <small class="text-muted">
                        <span class="text-danger">*</span>
                        ' . $languageService->get('fields_star_required') . '
                    </small>
                </div>

            </form>

        </div>
    </div>';
}




















/* ===============================
    ABSCHNITT: BASISDATEN LADEN
=============================== */

elseif ($action == "add" || $action == "edit" || $action == "new") {
    $currentLang = strtolower($languageService->detectLanguage());
    $isEdit = ($action == "edit");
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $ds = [];
    $modulname = "";
    $navAdminCatID = 0;
    $navAdminLink = "";
    $pluginNames = [];
    $pluginInfos = [];
    $adminNavTitles = [];
    
    // Arrays fÃ¼r bis zu 3 Website-Navigations-Instanzen
    $navLinks_inst = [];
    $navWebsiteCats = [];
    $titles_inst = []; 

    // Sprachen laden
    $languages = [];
    $resLang = safe_query("SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $languages[strtolower($row['iso_639_1'])] = $row['name_de'];
    }
    if (!isset($languages[$currentLang])) {
        $currentLang = array_key_first($languages) ?: 'de';
    }

    if ($isEdit && $id > 0) {
        $ergebnis = safe_query("SELECT * FROM `settings_plugins` WHERE `pluginID`=" . $id . " LIMIT 1");
        $ds = mysqli_fetch_array($ergebnis);
        $modulname = escape(normalize_plugin_modulname((string)($ds['modulname'] ?? '')));

        // Admin-Navi laden
        $adminNavQuery = safe_query("SELECT url, catID FROM navigation_dashboard_links WHERE modulname = '$modulname' LIMIT 1");
        $adminNav = mysqli_fetch_assoc($adminNavQuery);
        $navAdminCatID = (int)($adminNav['catID'] ?? 0);
        $navAdminLink = $adminNav['url'] ?? '';

        $pluginLangQuery = safe_query("
            SELECT content_key, language, content
            FROM settings_plugins_lang
            WHERE content_key IN ('plugin_name_$modulname', 'plugin_info_$modulname')
        ");
        while ($langRow = mysqli_fetch_assoc($pluginLangQuery)) {
            $iso = strtolower((string)($langRow['language'] ?? ''));
            $contentKey = (string)($langRow['content_key'] ?? '');
            $content = (string)($langRow['content'] ?? '');

            if ($contentKey === 'plugin_name_' . $modulname) {
                $pluginNames[$iso] = $content;
            } elseif ($contentKey === 'plugin_info_' . $modulname) {
                $pluginInfos[$iso] = $content;
            }
        }

        if (!empty($adminNav['catID'])) {
            $adminLinkIdQuery = safe_query("SELECT linkID FROM navigation_dashboard_links WHERE modulname = '$modulname' LIMIT 1");
            $adminLinkRow = mysqli_fetch_assoc($adminLinkIdQuery);
            $adminLinkId = (int)($adminLinkRow['linkID'] ?? 0);
            if ($adminLinkId > 0) {
                $adminTitleQuery = safe_query("SELECT language, content FROM navigation_dashboard_lang WHERE content_key = 'nav_link_$adminLinkId'");
                while ($titleRow = mysqli_fetch_assoc($adminTitleQuery)) {
                    $adminNavTitles[strtolower((string)($titleRow['language'] ?? ''))] = (string)($titleRow['content'] ?? '');
                }
            }
        }

        // Website-Navi Instanzen laden (bis zu 3)
        $websiteNavQuery = safe_query("SELECT snavID, url, mnavID FROM navigation_website_sub WHERE modulname = '$modulname' ORDER BY snavID ASC LIMIT 3");
        $instCounter = 1;
        while ($wNav = mysqli_fetch_assoc($websiteNavQuery)) {
            $navLinks_inst[$instCounter] = $wNav['url'];
            $navWebsiteCats[$instCounter] = $wNav['mnavID'];
            $currentSnavID = $wNav['snavID'];

            $resT = safe_query("SELECT language, content FROM navigation_website_lang WHERE content_key = 'nav_sub_$currentSnavID'");
            while ($tRow = mysqli_fetch_assoc($resT)) {
                $titles_inst[$instCounter][strtolower($tRow['language'])] = $tRow['content'];
            }
            $instCounter++;
        }
    }

    // Kategorien fÃ¼r Selects laden
    $adminCatsMultilang = [];
    $websiteCatsMultilang = [];
    foreach ($languages as $iso => $label) {
        $acQuery = safe_query("SELECT c.catID, COALESCE(l.content, '---') as name FROM navigation_dashboard_categories c LEFT JOIN navigation_dashboard_lang l ON l.content_key = CONCAT('nav_cat_', c.catID) AND l.language = '$iso' ORDER BY name");
        while($ac = mysqli_fetch_assoc($acQuery)) $adminCatsMultilang[$iso][] = $ac;

        $wcQuery = safe_query("SELECT m.mnavID, COALESCE(l.content, '---') as name FROM navigation_website_main m LEFT JOIN navigation_website_lang l ON l.content_key = CONCAT('nav_main_', m.mnavID) AND l.language = '$iso' ORDER BY name");
        while($wc = mysqli_fetch_assoc($wcQuery)) $websiteCatsMultilang[$iso][] = $wc;
    }

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();
    
    $themeergebnis = safe_query("SELECT * FROM settings_themes WHERE active = '1'");
    $db_theme = mysqli_fetch_array($themeergebnis);

    /* ===============================
        JAVASCRIPT VALIDIERUNG
    =============================== */
    ?>
    <script>
        function chkFormular() {
            const name = document.querySelector('input[name="plugin_name[<?php echo $currentLang; ?>]"]');
            if (name && !name.value.trim()) {
                alert('<?php echo $languageService->get('no_plugin_name'); ?>');
                name.focus();
                return false;
            }
            return true;
        }
    </script>
    <?php

    /* ===============================
        HTML STRUKTUR START
    =============================== */
    if ($isEdit && !empty($ds['admin_file'])) {
        echo '<div class="mb-3 d-flex gap-2">
                <a class="btn btn-secondary" href="admincenter.php?site=' . $ds['admin_file'] . '">' . $languageService->get('tooltip_7') . '</a>
                <a href="admincenter.php?site=plugin_manager&action=widget_add&id=' . $id . '" class="btn btn-secondary">' . $languageService->get('new_widget') . '</a>
              </div>';
    }

    echo '<div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title">
                    <i class="bi bi-puzzle"></i> ' . $languageService->get($isEdit ? 'plugin_manager_edit' : 'plugin_manager_add') . '
                    ' . ($isEdit ? '<small class="text-muted ms-2">Modul: ' . htmlspecialchars($modulname) . '</small>' : '') . '
                </div>
                <div class="ms-auto btn-group">';
                foreach ($languages as $iso => $label) {
                    $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                    echo '<button type="button" class="btn ' . $activeClass . ' lang-switch-btn" data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
                }
    echo '      </div>
            </div>
            <div class="card-body">';

    $formAction = $isEdit ? "admincenter.php?site=plugin_manager&id=$id&do=edit" : "admincenter.php?site=plugin_manager&do=add";
    echo '<form method="post" id="post" action="' . $formAction . '" enctype="multipart/form-data" onsubmit="return chkFormular();">';

    /* ABSCHNITT 1: NAME & BESCHREIBUNG */
    echo '<div class="alert alert-secondary p-4 mb-4">

            <h5 class="mb-0">
                    <button class="btn btn-link btn-alert-link p-0 collapsed" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#pluginDetailsCollapse"
                            aria-expanded="false"
                            aria-controls="pluginDetailsCollapse">
                        ' . $languageService->get('plugin_details') . '
                    </button>
                </h5>
            <div class="mt-3 collapse" id="pluginDetailsCollapse">


            <h6 class="mb-3"><i class="bi bi-info-circle"></i> ' . $languageService->get('name') . ' / ' . $languageService->get('description') . '</h6>
            <div class="row g-3">';
            foreach ($languages as $iso => $label) {
                $display = ($iso !== $currentLang ? 'display:none' : '');
                $valName = $isEdit ? ($pluginNames[$iso] ?? ($pluginNames[$currentLang] ?? '')) : '';
                $valInfo = $isEdit ? ($pluginInfos[$iso] ?? ($pluginInfos[$currentLang] ?? '')) : '';
                
                echo '<div class="col-12 lang-content lang-' . $iso . '" style="' . $display . '" data-type="base">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Plugin ' . $languageService->get('name') . ' (' . strtoupper($iso) . ') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="plugin_name[' . $iso . ']" value="' . htmlspecialchars($valName, ENT_QUOTES, 'UTF-8') . '">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">' . $languageService->get('description') . ' (' . strtoupper($iso) . ')</label>
                            <textarea class="form-control" name="plugin_info[' . $iso . ']" rows="2">' . htmlspecialchars($valInfo, ENT_QUOTES, 'UTF-8') . '</textarea>
                        </div>
                      </div>';
            }
    

    /* ABSCHNITT 2: STAMMDATEN, VERSION, AUTOR */
    echo '<div class="mb-4">
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label fw-bold">' . $languageService->get('modulname') . ' *</label>
                    <input type="text" class="form-control" name="modulname" value="' . htmlspecialchars($modulname) . '" ' . ($isEdit ? 'readonly' : 'required') . '>
                </div>
                <div class="col-lg-6">
                    <label class="form-label">' . $languageService->get('version_file') . '</label>
                    <input type="text" class="form-control" name="version" value="' . htmlspecialchars($ds['version'] ?? '1.0.0') . '" ' . ($isEdit ? 'readonly' : 'required') . '>
                </div>
                <div class="col-lg-6">
                    <label class="form-label">' . $languageService->get('author') . '</label>
                    <input type="text" class="form-control" name="author" value="' . htmlspecialchars($ds['author'] ?? '') . '" ' . ($isEdit ? 'readonly' : 'required') . '>
                </div>
                <div class="col-lg-6">
                    <label class="form-label">' . $languageService->get('website') . '</label>
                    <input type="url" class="form-control" name="website" value="' . htmlspecialchars($ds['website'] ?? '') . '" placeholder="https://" ' . ($isEdit ? 'readonly' : 'required') . '>
                </div>
            </div>
          </div><hr class="my-4">';

    /* ABSCHNITT 3: PFADE & DATEIEN */
    echo '<div class="mb-4">
            <h6 class="mb-3"><i class="bi bi-folder"></i> ' . $languageService->get('folder_file') . ' & ' . $languageService->get('index_file') . '</h6>
            <div class="row g-3">';
            
                echo '<div class="col-lg-6">
                        <label class="form-label">' . $languageService->get('folder_file') . ' (Pfad) *</label>
                        <input type="text" class="form-control" value="' . htmlspecialchars($ds['path'] ?? '') . '">
                      </div>
                            <div class="col-lg-6">
                    <label class="form-label">' . $languageService->get('admin_file') . '</label>
                    <input type="text" class="form-control" name="admin_file" value="'.htmlspecialchars($ds['admin_file'] ?? '').'">
                </div>
                <div class="col-lg-6">
                    <label class="form-label">' . $languageService->get('index_file') . '</label>
                    <input type="text" class="form-control" name="index_link" value="'.htmlspecialchars($ds['index_link'] ?? '').'">
                </div>
                <div class="col-lg-6">
                    <label class="form-label">' . $languageService->get('hidden_file') . '</label>
                    <input type="text" class="form-control" name="hiddenfiles" value="'.htmlspecialchars($ds['hiddenfiles'] ?? '').'" placeholder="file1,file2" readonly>
                </div>
            </div>
          </div><hr class="my-4">';

          echo '</div>  </div>
          </div>';

    /* ABSCHNITT 4: NAVIGATION (ADMIN & WEBSITE) */
    echo '<div class="mb-4">
            <h6 class="mb-3"><i class="bi bi-list"></i> ' . $languageService->get('navigation') . '</h6>
            <div class="row g-4">
                <div class="col-12 col-xl-6">
                    <div class="p-3 border rounded bg-light" style="height: 320px">
                        <h6 class="text-primary" style="margin-bottom: 20px;"><i class="bi bi-speedometer2"></i> Admincenter</h6>';
                        foreach ($languages as $iso => $label) {
                            $display = ($iso != $currentLang ? 'display:none' : '');
                            echo '<div class="lang-content lang-'.$iso.'" style="'.$display.'" data-type="admin">
                                    <label class="form-label small">' . $languageService->get('category') . '</label>
                                    <select name="nav_admin_cat" class="form-select mb-3">';
                                    foreach ($adminCatsMultilang[$iso] as $cat) {
                                        $sel = ($cat['catID'] == $navAdminCatID ? 'selected' : '');
                                        echo '<option value="'.$cat['catID'].'" '.$sel.'>'.htmlspecialchars($cat['name']).'</option>';
                                    }
                            echo '  </select>
                                    <label class="form-label small">Nav-Titel (' . strtoupper($iso) . ')</label>
                                    <input type="text" class="form-control" name="nav_admin_title_lang['.$iso.']" value="'.htmlspecialchars($adminNavTitles[$iso] ?? ($pluginNames[$iso] ?? ''), ENT_QUOTES, 'UTF-8').'">
                                  </div>';
                        }
                        echo '<label class="form-label mt-2 small">Direkt-Link (Admin)</label>
                              <input type="text" class="form-control" name="nav_admin_link" value="'.htmlspecialchars($navAdminLink).'">
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <div class="p-3 border rounded bg-light" id="nav-container-website">';
                        $maxLinks = 3;
                        $initialCount = 1;
                        for ($k = 1; $k <= $maxLinks; $k++) { if (!empty($navLinks_inst[$k])) $initialCount = $k; }

    echo '              <div class="d-flex justify-content-between align-items-center">
                            <h6 class="text-success" style="margin-bottom: 19px;"><i class="bi bi-globe"></i> Website Navigation</h6>
                            <div class="d-flex gap-2">
                                <div class="btn-group btn-group-sm instance-switcher" id="switcher-group" data-current-count="' . $initialCount . '">';
                                for ($n = 1; $n <= $maxLinks; $n++) {
                                    $btnDisplay = ($n <= $initialCount) ? '' : 'display:none;';
                                    echo '<button type="button" class="btn btn-outline-success ' . ($n==1?'active':'') . ' btn-inst-' . $n . '" style="'.$btnDisplay.'" onclick="switchNavInstance(this, '.$n.')">'.$n.'</button>';
                                }
    echo '                      </div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-success" onclick="modifyInstanceCount(1)"><i class="bi bi-plus-lg"></i></button>
                                    <button type="button" class="btn btn-danger" onclick="modifyInstanceCount(-1)"><i class="bi bi-dash-lg"></i></button>
                                </div>
                            </div>
                        </div>';
                        
    #                    for ($i = 1; $i <= $maxLinks; $i++) {

    #echo '<div id="nav-inst-' . $i . '" class="nav-instance-block" style="' . ($i > 1 ? 'display:none' : '') . '">';

    /* ===============================
       KATEGORIE â€“ SPRACHNEUTRAL (NUR 1Ã—!)
    =============================== */
  



for ($i = 1; $i <= $maxLinks; $i++) {
    $instDisplay = ($i > 1 ? 'display:none' : '');
    echo '<div id="nav-inst-' . $i . '" class="nav-instance-block" style="' . $instDisplay . '">';

    /* KATEGORIE â€“ Mit spezieller Klasse fÃ¼r JS-Ãœbersetzung */
    echo '<div class="nav-global-field mb-3">
            <label class="form-label small fw-bold">' . $languageService->get('category') . ' (Eintrag ' . $i . ')</label>
            <select name="nav_website_cat[' . $i . ']" 
                    class="form-select category-select-dynamic inst-input-' . $i . '">
                <option value="0">-- ' . $languageService->get('none') . ' --</option>';

    foreach ($websiteCatsMultilang[$currentLang] ?? [] as $cat) {
        $savedID = 0;
        if (isset($navWebsiteCats[$i])) {
            $savedID = is_array($navWebsiteCats[$i]) ? (int)reset($navWebsiteCats[$i]) : (int)$navWebsiteCats[$i];
        }
        $sel = ((int)$cat['mnavID'] === $savedID) ? 'selected' : '';
        echo '<option value="' . $cat['mnavID'] . '" ' . $sel . '>' . htmlspecialchars($cat['name']) . '</option>';
    }
    echo '  </select>
          </div>';

    /* TITEL â€“ Bleiben in lang-content Containern */
    foreach ($languages as $iso => $label) {
        $langDisplay = ($iso !== $currentLang ? 'display:none' : '');
        echo '<div class="lang-content lang-' . $iso . '" style="' . $langDisplay . '" data-type="inst-' . $i . '">
                <label class="form-label small">' . $languageService->get('nav_title') . ' (' . strtoupper($iso) . ')</label>
                <input type="text" name="nav_website_title_lang[' . $i . '][' . $iso . ']" 
                       value="' . htmlspecialchars($titles_inst[$i][$iso] ?? '') . '" class="form-control mb-3">
              </div>';
    }

    /* LINK â€“ Ebenfalls in nav-global-field */
    echo '<div class="nav-global-field mb-3">
            <label class="form-label small">' . $languageService->get('nav_link') . '</label>
            <input type="text" name="nav_website_link[' . $i . ']" 
                   value="' . htmlspecialchars($navLinks_inst[$i] ?? '') . '" class="form-control">
          </div>';

    echo '</div>';
}



    echo '          </div>
                </div>
            </div>
          </div>';
          ########################


echo'        <hr>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="path">Widgets: <br><small>(' . $languageService->get('widget_included_with_plugin') . ')</small></label>  
            ';

        $moduleForWidgets = escape((string)($ds['modulname'] ?? ''));
        $widgetsergebnis = safe_query("SELECT * FROM settings_widgets WHERE plugin = '" . $moduleForWidgets . "'");
        $widget = '';
        while ($df = mysqli_fetch_array($widgetsergebnis)) {
    $widget_key = $df['widget_key'];
    $widgetname = $df['title'];
    $modulname = $df['plugin'];

    $widget .= '
    <div class="col-sm-12">
        <div class="mb-3 row">
            <div class="col-sm-5 text-end">
                <button type="button"
                    class="btn btn-info"
                    data-bs-toggle="popover"
                    data-bs-placement="left"
                    data-bs-html="true"
                    data-bs-content="<img src=\'../includes/plugins/' . $modulname . '/images/' . $widget_key . '.jpg\' class=\'img-fluid\'>"
                    title="Widget">
                    <i class="bi bi-image"></i> ' . $languageService->get('preview_widget') . '
                </button>
            </div>
            <div class="col-sm-4">
                <div class="form-control">' . $widgetname . '</div>
            </div>
            <div class="col-sm-3">
                <a href="admincenter.php?site=plugin_manager&action=edit_widget&id=' . $id . '&widget_key=' . urlencode($widget_key) . '"
                   class="btn btn-warning">
                   <i class="bi bi-pencil-square"></i> ' . $languageService->get('edit_widget') . '
                </a>
                <button type="button"
                    class="btn btn-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#confirmDeleteModal"
                    data-href="admincenter.php?site=plugin_manager&delete=true&widget_key=' . urlencode($widget_key) . '&modulname=' . urlencode($modulname) . '&id=' . $id . '&captcha_hash=' . $hash . '"
                    title="' . $languageService->get('tooltip_6') . '">
                    <i class="bi bi-trash3"></i> ' . $languageService->get('delete_widget') . '
                </button>

                <!-- Modal -->
                <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                    
                      <div class="modal-header">
                        <h5 class="modal-title" id="confirmDeleteModalLabel">' . $languageService->get('delete_widget') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $languageService->get('close') . '"></button>
                      </div>
                      
                      <div class="modal-body">
                        ' . $languageService->get('really_delete') . '
                      </div>
                      
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                          <i class="bi bi-x-square"></i> ' . $languageService->get('close') . '
                        </button>
                        <a class="btn btn-danger btn-ok">
                          <i class="bi bi-trash3"></i> ' . $languageService->get('delete_widget') . '
                        </a>
                      </div>
                      
                    </div>
                  </div>
                </div>

                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var confirmDeleteModal = document.getElementById("confirmDeleteModal");
                    confirmDeleteModal.addEventListener("show.bs.modal", function(event) {
                        var button = event.relatedTarget;
                        var href = button.getAttribute("data-href");
                        var confirmBtn = confirmDeleteModal.querySelector(".btn-ok");
                        confirmBtn.setAttribute("href", href);
                    });
                
                    // Bootstrap Popover initialisieren
                    var popoverTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="popover"]\'));
                    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                        return new bootstrap.Popover(popoverTriggerEl);
                    });
                });
                </script>
            </div>
        </div>
    </div>
';

        }
        if ((string)($ds['modulname'] ?? '') === (string)($modulname ?? '')) {
            $xwidget = $widget;
        } else {
            $xwidget = $languageService->get('no_widget_available');
        }

        echo '' . $xwidget . '
            
        </div>';

        #################



$isActive = (!empty($ds['activate']) && (int)$ds['activate'] === 1) ? 'checked' : '';
?>
    <div class="mb-3 row">
    <label class="col-sm-2 col-form-label">
        <?= $languageService->get('plugin_activate') ?>
        <span class="ms-2 badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
    <?= $isActive ? 'aktiv' : 'inaktiv' ?>
</span>
    </label>
    <div class="col-sm-10">
        <input
            class="form-check-input"
            type="checkbox"
            name="activate"
            value="1"
            <?= $isActive ?>
        >
    </div>
</div>

<?php
    /* FOOTER */
    echo '<div class="d-flex align-items-center gap-3 pt-4 border-top">
            <input type="hidden" name="captcha_hash" value="'.$hash.'">
            <input type="hidden" name="id" value="'.$id.'">
            <input type="hidden" name="modulname" value="' . ($ds['modulname'] ?? '') . '">
            <button class="btn btn-primary px-5 shadow-sm" type="submit" name="'.($isEdit ? 'edit' : 'add').'" value="1">
                <i class="bi bi-check-lg"></i> '.$languageService->get('save').'
            </button>
          </div>
        </form>
    </div></div>';

    /* JAVASCRIPT LOGIK */
echo '<script>
/* =====================================================
   VISIBILITY & LANGUAGE HANDLING
===================================================== */
// Kategorien als JSON bereitstellen
const allWebsiteCats = ' . json_encode($websiteCatsMultilang) . ';

function updateVisibility() {
    const activeLang = document.querySelector(".lang-switch-btn.btn-primary")?.getAttribute("data-lang") || "de";
    const activeInstBtn = document.querySelector(".instance-switcher .btn.active");
    const visibleInstNum = activeInstBtn ? activeInstBtn.innerText.trim() : "1";

    /* 1. Alles GrundsÃ¤tzliche ausblenden */
    document.querySelectorAll(".lang-content").forEach(el => el.style.display = "none");

    /* 2. Basis- & Admin-Daten einblenden (global) */
    document.querySelectorAll(".lang-content[data-type=\'base\'].lang-" + activeLang + ", .lang-content[data-type=\'admin\'].lang-" + activeLang)
            .forEach(el => el.style.display = "block");

    /* 3. Website-Navigation (Instanz-BlÃ¶cke) */
    // Zuerst alle BlÃ¶cke verstecken
    document.querySelectorAll(".nav-instance-block").forEach(block => block.style.display = "none");
    
    // Dann nur den aktiven Block zeigen
    const instBlock = document.getElementById("nav-inst-" + visibleInstNum);
    if (instBlock) {
        instBlock.style.display = "block";
        
        // SprachabhÃ¤ngige Titel innerhalb des Blocks zeigen
        instBlock.querySelectorAll(".lang-" + activeLang).forEach(el => el.style.display = "block");

        // Globale Felder (Kategorie & Link) innerhalb des Blocks zeigen
        instBlock.querySelectorAll(".nav-global-field, input[name^=\'nav_website_link\']").forEach(el => {
            el.style.display = "block";
        });
    }

    /* 4. LIVE-ÃœBERSETZUNG der Kategorie-Dropdowns */
    document.querySelectorAll(".category-select-dynamic").forEach(select => {
        const currentValue = select.value;
        const langData = allWebsiteCats[activeLang] || [];
        
        const noSelectionText = "-- ' . $languageService->get('none') . ' --";
        select.innerHTML = `<option value="0">${noSelectionText}</option>`;

        langData.forEach(cat => {
            const opt = document.createElement("option");
            opt.value = cat.mnavID;
            opt.text = cat.name;
            if (cat.mnavID == currentValue) opt.selected = true;
            select.add(opt);
        });
    });
}

/* =====================================================
   EVENT LISTENER & SWITCHER
===================================================== */
document.querySelectorAll(".lang-switch-btn").forEach(btn => {
    btn.addEventListener("click", function () {
        document.querySelectorAll(".lang-switch-btn").forEach(b => {
            b.classList.replace("btn-primary", "btn-secondary");
        });
        this.classList.replace("btn-secondary", "btn-primary");
        updateVisibility();
    });
});

function switchNavInstance(btn, num) {
    document.querySelectorAll(".instance-switcher .btn").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    updateVisibility();
}

function modifyInstanceCount(change) {
    const switcher = document.getElementById("switcher-group");
    let count = parseInt(switcher.getAttribute("data-current-count"));
    let newCount = count + change;

    if (newCount < 1 || newCount > 3) return;

    if (change > 0) {
        const nextBtn = switcher.querySelector(".btn-inst-" + newCount);
        if (nextBtn) {
            nextBtn.style.display = "inline-block";
            nextBtn.click();
        }
    } else {
        const currentBtn = switcher.querySelector(".btn-inst-" + count);
        if (currentBtn) currentBtn.style.display = "none";
        const prevBtn = switcher.querySelector(".btn-inst-" + newCount);
        if (prevBtn) prevBtn.click();
    }
    switcher.setAttribute("data-current-count", newCount);
}

document.addEventListener("DOMContentLoaded", updateVisibility);


/* =====================================================
   LANGUAGE SWITCH BUTTONS
===================================================== */
document.querySelectorAll(".lang-switch-btn").forEach(btn => {
    btn.addEventListener("click", function () {

        document
            .querySelectorAll(".lang-switch-btn")
            .forEach(b => {
                b.classList.remove("btn-primary");
                b.classList.add("btn-secondary");
            });

        this.classList.remove("btn-secondary");
        this.classList.add("btn-primary");

        updateVisibility();
    });
});

/* =====================================================
   WEBSITE NAV INSTANCE SWITCH
===================================================== */
function switchNavInstance(btn, num) {

    document
        .querySelectorAll(".nav-instance-block")
        .forEach(el => el.style.display = "none");

    const target = document.getElementById("nav-inst-" + num);
    if (target) target.style.display = "block";

    document
        .querySelectorAll(".instance-switcher .btn")
        .forEach(b => b.classList.remove("active"));

    btn.classList.add("active");

    updateVisibility();
}

/* =====================================================
   INSTANCE COUNT CONTROL (1â€“3)
===================================================== */
function modifyInstanceCount(change) {

    const switcher = document.getElementById("switcher-group");
    let count = parseInt(switcher.getAttribute("data-current-count"));
    let newCount = count + change;

    if (newCount < 1 || newCount > 3) return;

    if (change > 0) {
        const nextBtn = switcher.querySelector(".btn-inst-" + newCount);
        if (nextBtn) {
            nextBtn.style.display = "inline-block";
            nextBtn.click();
        }
    } else {
        const currentBtn = switcher.querySelector(".btn-inst-" + count);
        if (currentBtn) currentBtn.style.display = "none";

        const prevBtn = switcher.querySelector(".btn-inst-" + newCount);
        if (prevBtn) prevBtn.click();
    }

    switcher.setAttribute("data-current-count", newCount);
}

/* =====================================================
   INIT
===================================================== */
document.addEventListener("DOMContentLoaded", updateVisibility);
</script>';


    return false;
}
































else {
echo '<div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-puzzle"></i> <span>' . $languageService->get('plugin_manager') . '</span>
                <small class="text-muted">' . $languageService->get('settings') . '</small>
            </div>
        </div>
        <div class="card-body">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <a href="admincenter.php?site=plugin_manager&action=new" class="btn btn-secondary" type="button">
                ' . $languageService->get('new_plugin') . '
            </a>

            <div class="input-group input-group-sm" style="min-width: 260px; max-width: 360px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input id="pluginSearch" type="search" class="form-control"
                       placeholder="' . $languageService->get('search') . '">
            </div>
        </div>';
                    $thergebnis = safe_query("SELECT * FROM settings_themes WHERE active = '1'");
                    $db = mysqli_fetch_array($thergebnis);

                    $CAPCLASS = new \nexpell\Captcha;
                    $CAPCLASS->createTransaction();
                    $hash = $CAPCLASS->getHash();

                    echo '<table id="plugini" class="table">
                            <thead>
                                <th>' . $languageService->get('id') . '</strong></th>
                                <th width="10%">' . $languageService->get('plugin') . ' ' . $languageService->get('name') . '</th>
                                <th>' . $languageService->get('plugin') . ' ' . $languageService->get('description') . '</th>
                                <th class="text-center" width="12%">' . $languageService->get('plugin_status') . '</th>
                                <th class="text-center" width="12%">' . $languageService->get('plugin_setting') . '</th>
                                <th class="text-center" width="12%">' . $languageService->get('action') . '</th>
                            </thead>';
                    $ergebnis = safe_query("SELECT * FROM settings_plugins");
                    while ($ds = mysqli_fetch_array($ergebnis)) {
                        $dsModulname = (string)($ds['modulname'] ?? '');
                        $dsPluginId = (int)($ds['pluginID'] ?? 0);

                        $dx = mysqli_fetch_array(safe_query("SELECT * FROM settings_plugins WHERE pluginID='" . $ds['pluginID'] . "'"));

                        if ($ds['activate'] == "1") {
                            $actions = '<div class="d-grid gap-2"><a href="admincenter.php?site=plugin_manager&id=' . $dsPluginId . '&modulname=' . urlencode($dsModulname) . '&do=dea" class="btn btn-info d-inline-flex align-items-center gap-1 w-auto" data-bs-toggle="tooltip" data-bs-title="' . $languageService->get('tooltip_2') . '" title="' . $languageService->get('tooltip_2') . ' " type="button"><i class="bi bi-toggle-off"></i> ' . $languageService->get('deactivate') . '</a></div>';
                        } else {
                            $actions = '<div class="d-grid gap-2"><a href="admincenter.php?site=plugin_manager&id=' . $dsPluginId . '&modulname=' . urlencode($dsModulname) . '&do=act" class="btn btn-success d-inline-flex align-items-center gap-1 w-auto" data-bs-toggle="tooltip" data-bs-title="' . $languageService->get('tooltip_1') . '" title="' . $languageService->get('tooltip_1') . ' " type="button"><i class="bi bi-toggle-on"></i> ' . $languageService->get('activate') . '</a></div>';
                        }

                        $currentLang = strtolower($languageService->detectLanguage());
                        $moduleNameRaw = (string)($ds['modulname'] ?? '');
                        $moduleName = trim($moduleNameRaw, " \t\n\r\0\x0B,");
                        $moduleCandidates = array_values(array_unique(array_filter([
                            $moduleNameRaw,
                            $moduleName
                        ])));
                        $langCandidates = array_values(array_unique(array_filter([
                            $currentLang, 'de', 'en', 'it'
                        ])));

                        $ds['name'] = (string)($ds['name'] ?? '');
                        $ds['info'] = (string)($ds['info'] ?? ($ds['description'] ?? ''));

                        foreach ($moduleCandidates as $candidateModule) {
                            $modulnameEsc = escape($candidateModule);
                            foreach ($langCandidates as $langIso) {
                                $nameLangRes = safe_query("SELECT content FROM settings_plugins_lang WHERE content_key = 'plugin_name_" . $modulnameEsc . "' AND language = '" . escape($langIso) . "' LIMIT 1");
                                if ($nameLangRes && mysqli_num_rows($nameLangRes) > 0) {
                                    $nameLangRow = mysqli_fetch_assoc($nameLangRes);
                                    $translated = trim((string)($nameLangRow['content'] ?? ''));
                                    if ($translated !== '') {
                                        $ds['name'] = $translated;
                                        break 2;
                                    }
                                }
                            }
                        }

                        foreach ($moduleCandidates as $candidateModule) {
                            $modulnameEsc = escape($candidateModule);
                            foreach ($langCandidates as $langIso) {
                                $infoLangRes = safe_query("SELECT content FROM settings_plugins_lang WHERE content_key = 'plugin_info_" . $modulnameEsc . "' AND language = '" . escape($langIso) . "' LIMIT 1");
                                if ($infoLangRes && mysqli_num_rows($infoLangRes) > 0) {
                                    $infoLangRow = mysqli_fetch_assoc($infoLangRes);
                                    $translated = trim((string)($infoLangRow['content'] ?? ''));
                                    if ($translated !== '') {
                                        $ds['info'] = $translated;
                                        break 2;
                                    }
                                }
                            }
                        }

                        if (trim((string)$ds['name']) === '') {
                            $ds['name'] = $moduleName !== '' ? $moduleName : $moduleNameRaw;
                        }

                        echo '<tr>
                    <td>' . $ds['pluginID'] . '</td>
                    <td class="fw-semibold">' . $ds['name'] . '</td>
                    <td>' . ($ds['info'] ?? '') . '</td>';

                        if ($dx['status_display'] == "1") {
                            echo '<td class="text-center">' . $actions . '</div>';
                        } else {

                            echo '<td class="text-center">
                                <div class="d-grid gap-2">
                            <button type="button" class="btn btn-secondary d-inline-flex align-items-center gap-1 w-auto" disabled><i class="bi bi-slash-circle"></i> ' . $languageService->get('cannot_assigned') . '</button>
                                 </div></td>';
                        }
                        if ($dx['plugin_display'] == "1") {
                            echo '
                            <td class="text-center">
                            <div class="d-grid gap-2">
                            <a href="admincenter.php?site=plugin_manager&action=edit&id=' . $ds['pluginID'] . '&do=edit" class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto" data-bs-toggle="tooltip" data-bs-title="' . $languageService->get('tooltip_4') . '" title="' . $languageService->get('tooltip_4') . '" type="button"><i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '</a></div></td>';
                        } else {

                            echo '<td class="text-center">
                            <div class="d-grid gap-2">
                        <button type="button" class="btn btn-secondary d-inline-flex align-items-center gap-1 w-auto" disabled><i class="bi bi-slash-circle"></i> ' . $languageService->get('cannot_assigned') . '</button>
                        </div></td>';
                        }
                        if ($dx['delete_display'] != "1") {
                            echo '<td class="text-center">
                            <div class="d-grid gap-2">
                            <button type="button" class="btn btn-secondary d-inline-flex align-items-center gap-1 w-auto" disabled><i class="bi bi-slash-circle"></i> ' . $languageService->get('cannot_assigned') . '</button>
                            </div></td>';
                        } else {

                           echo '<td class="text-center">
                                <div class="d-grid gap-2">
                                    <span class="d-inline-block w-100"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="' . htmlspecialchars($languageService->get('tooltip_8'), ENT_QUOTES, 'UTF-8') . '">
                                        <a href="#"
                                           class="btn btn-danger"
                                           data-bs-toggle="modal"
                                           data-bs-target="#confirmDeleteModal"
                                           data-plugin-id="' . $dsPluginId . '"
                                           data-plugin-name="' . htmlspecialchars($dsModulname, ENT_QUOTES, 'UTF-8') . '"
                                           title="' . $languageService->get('tooltip_8') . '">
                                           <i class="bi bi-trash3"></i> ' . $languageService->get('delete_plugin') . '
                                        </a>
                                        <!-- Bootstrap Modal for Confirm Delete -->
                            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                    
                                        <div class="modal-header">
                                            <h5 class="modal-title">' . $languageService->get('modulname') . ': 
                                                <span id="modalPluginTitle"></span>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        
                                        <div class="modal-body">
                                            ' . $languageService->get('really_delete_plugin') . '
                                        </div>
                                        
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="bi bi-x-square"></i> ' . $languageService->get('close') . '
                                            </button>
                                            <a id="confirmDeleteBtn" href="#" class="btn btn-danger">
                                                <i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '
                                            </a>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                                    </span>
                                </div>
                            </td>';
                        }
                        echo '</tr>';
                    }
                        echo '</table></div>
            <script>
            document.addEventListener("DOMContentLoaded", function () {
                var input = document.getElementById("pluginSearch");
                if (!input) return;

                function applyFilter() {
                    var q = (input.value || "").toLowerCase().trim();
                    var rows = document.querySelectorAll("#plugini tbody tr");

                    for (var i = 0; i < rows.length; i++) {
                        var row = rows[i];
                        var txt = (row.textContent || "").toLowerCase();
                        var show = (!q || txt.indexOf(q) !== -1);
                        row.style.display = show ? "table-row" : "none";
                    }
                }

                input.addEventListener("input", applyFilter);
                applyFilter();
            });
            </script>
        </div>
    </div>
    </div>';
        }



?>
<script>
function switchNavInstance(btn, num) {
    const container = document.getElementById('nav-container-website');
    
    // Alle Inhalts-BlÃ¶cke verstecken
    container.querySelectorAll('.nav-instance-block').forEach(el => {
        el.style.display = 'none';
    });
    
    // Den gewÃ¤hlten Block anzeigen
    const targetBlock = document.getElementById('nav-inst-' + num);
    if (targetBlock) targetBlock.style.display = 'block';
    
    // Buttons in der Gruppe deaktivieren und den aktuellen aktivieren
    const switcher = document.getElementById('switcher-group');
    switcher.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function modifyInstanceCount(change) {
    const switcher = document.getElementById('switcher-group');
    let count = parseInt(switcher.getAttribute('data-current-count'));
    let newCount = count + change;

    // Limit zwischen 1 und 3 Instanzen
    if (newCount >= 1 && newCount <= 3) {
        if (change > 0) {
            // HINZUFÃœGEN
            const nextBtn = switcher.querySelector('.btn-inst-' + newCount);
            if (nextBtn) {
                nextBtn.style.display = 'inline-block';
                nextBtn.click(); // Springt direkt zum neuen Tab
            }
        } else {
            

            const currentBtn = switcher.querySelector('.btn-inst-' + count);
            const prevBtn = switcher.querySelector('.btn-inst-' + newCount);
            
            if (currentBtn) currentBtn.style.display = 'none';
            if (prevBtn) prevBtn.click(); // Springt zum Tab davor
        }
        // Attribut aktualisieren
        switcher.setAttribute('data-current-count', newCount);
    }
}
</script>

