<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standard setzen, wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('plugin_manager', true);

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_plugin_manager');

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    $action = '';
}

$theme_active = safe_query("SELECT * FROM settings_themes WHERE active = '1'");
$db = mysqli_fetch_array($theme_active);

if (!empty(@$db['active'] == 1) !== false) {


    #Aktive und Deaktivieren vom Plugin START

    if (isset($_GET['do'])) {
        $do = $_GET['do'];
    } else {
        $do = "";
    }
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
    } else {
        $id = "";
    }
    if (isset($_GET['modulname'])) {
        $modulname = $_GET['modulname'];
    } else {
        $modulname = "";
    }

    if ($id != "" && $do === "dea") {
        try {
            // Zuerst den modulname aus settings_plugins holen
            $res = safe_query("SELECT `modulname` FROM `settings_plugins` WHERE `pluginID` = '" . (int)$id . "'");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $modulname = $row['modulname'];

                // Plugin deaktivieren
                safe_query("UPDATE `settings_plugins` SET `activate` = '0' WHERE `pluginID` = '" . (int)$id . "'");

                // Navigationseintrag anpassen
                safe_query("UPDATE `navigation_website_sub` SET `indropdown` = '0' WHERE `modulname` = '" . escape($modulname) . "'");

                echo $languageService->get('success_deactivated');
                redirect("admincenter.php?site=plugin_manager", "", 1);
                return false;
            } else {
                echo "Plugin nicht gefunden.";
                redirect("admincenter.php?site=plugin_manager", "", 3);
                return false;
            }
        } catch (Exception $e) {
            echo $languageService->get('success_deactivated') . "<br><br>" . $e->getMessage();
            redirect("admincenter.php?site=plugin_manager", "", 5);
            return false;
        }
    }

    if ($id != "" && $do === "act") {
        try {
            // Modulname aus settings_plugins holen
            $res = safe_query("SELECT `modulname` FROM `settings_plugins` WHERE `pluginID` = '" . (int)$id . "'");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $modulname = $row['modulname'];

                // Plugin aktivieren
                safe_query("UPDATE `settings_plugins` SET `activate` = '1' WHERE `pluginID` = '" . (int)$id . "'");

                // Navigationseintrag anpassen
                safe_query("UPDATE `navigation_website_sub` SET `indropdown` = '1' WHERE `modulname` = '" . escape($modulname) . "'");

                echo $languageService->get('success_activated');
                redirect("admincenter.php?site=plugin_manager", "", 1);
                return false;
            } else {
                echo "Plugin nicht gefunden.";
                redirect("admincenter.php?site=plugin_manager", "", 3);
                return false;
            }
        } catch (Exception $e) {
            echo $languageService->get('failed_activated') . "<br><br>" . $e->getMessage();
            redirect("admincenter.php?site=plugin_manager", "", 5);
            return false;
        }
    }

    #Aktive und Deaktivieren vom Plugin END

    #Erstellt eine neue Plugin-Einstellung START
if (isset($_POST['add']) && empty($_POST['id'])) {
    $acti = isset($_POST['activate']) ? 1 : 0;

    $name           = escape($_POST['name']);
    $modulname      = escape($_POST['modulname']);
    $info           = escape($_POST['info']);
    $admin_file     = escape($_POST['admin_file']);
    $author         = escape($_POST['author']);
    $website        = escape($_POST['website']);
    $index_file     = escape($_POST['index']);
    $hiddenfiles    = escape($_POST['hiddenfiles']);
    $version        = escape($_POST['version']);
    $path           = escape($_POST['path']);

    $admin_cat_id       = (int)($_POST['nav_admin_cat'] ?? 0);
    $website_cat_id     = (int)($_POST['nav_website_cat'] ?? 0);
    $admin_title        = escape($_POST['nav_admin_title']);
    $website_title      = escape($_POST['nav_website_title']);
    $admin_file_url     = escape($_POST['nav_admin_link']);
    $index_file_url     = escape($_POST['nav_website_link']);

    try {
        // Plugin speichern
        safe_query("
            INSERT INTO `settings_plugins` (
                `pluginID`, `name`, `modulname`, `info`, `activate`, `admin_file`, `author`, `website`, `index_link`,
                `hiddenfiles`, `version`, `path`, `status_display`, `plugin_display`, `widget_display`, `delete_display`, `sidebar`
            ) VALUES (
                NULL, '$name', '$modulname', '$info', '1', '$admin_file', '$author', '$website', '$index_file',
                '$hiddenfiles', '$version', '$path', '1', '1', '1', '1', 'deactivated'
            )
        ");

        safe_query("
            INSERT INTO `settings_plugins_installed` 
                (`name`, `modulname`, `description`, `version`, `author`, `url`, `folder`, `installed_date`)
            VALUES
                ('$name', '$modulname', '$info', '$version', '$author', '$website', '$modulname', NOW())
        ");


        // Admin-Navigation
        if ($admin_title && $admin_file_url && $admin_cat_id > 0) {
            safe_query("
                INSERT INTO navigation_dashboard_links (catID, modulname, name, url, sort) VALUES (
                    $admin_cat_id,
                    '$modulname',
                    '$admin_title',
                    '$admin_file_url',
                    1
                )
            ");
        }

        // Website-Navigation
        if ($website_title && $index_file_url && $website_cat_id > 0) {
            safe_query("
                INSERT INTO navigation_website_sub (mnavID, name, modulname, url, sort, indropdown) VALUES (
                    $website_cat_id,
                    '$website_title',
                    '$modulname',
                    '$index_file_url',
                    1,
                    1
                )
            ");
        }

        safe_query("INSERT INTO `user_role_admin_navi_rights` (`roleID`, `type`, `modulname`) VALUES (1, 'link', '$modulname')");


        function sanitizeFilename(string $filename): string {
            return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
        }


        // Nach safe_query(...) für Plugin-Insert

        // Hole den Pfad aus POST, z.B. "test" oder auch "includes/plugins/test"
        $path = $_POST['path'] ?? '';

        // Entferne vorangestellte includes/plugins, falls vorhanden
        $path = str_replace(['includes/plugins/', '/includes/plugins/'], '', $path);

        // Entferne führende und abschließende Slashes
        $path = trim($path, "/");

        // Absoluter Basisordner: includes/plugins (muss existieren)
        $basePluginsDir = realpath(__DIR__ . '/../includes/plugins');
        if ($basePluginsDir === false) {
            die('Der Ordner includes/plugins existiert nicht!');
        }

        // Vollständiger Plugin-Ordner-Pfad
        $pluginDir = $basePluginsDir . '/' . $path;

        echo "Pfad zum Plugin-Ordner: $pluginDir<br>";

        // Ordner erstellen, falls nicht existent
        if (!is_dir($pluginDir)) {
            if (!mkdir($pluginDir, 0755, true)) {
                die('Ordner konnte nicht erstellt werden!');
            } else {
                echo 'Ordner erfolgreich erstellt.<br>';
            }
        } else {
            echo 'Ordner existiert bereits.<br>';
        }

        // Admin-Unterordner anlegen
        $adminDir = $pluginDir . '/admin';
        if (!is_dir($adminDir)) {
            mkdir($adminDir, 0755, true);
        }

        // Dateinamen aus POST absichern (escape und sanitizeFilename müssen definiert sein)
        $adminFileName = sanitizeFilename(escape($_POST['admin_file'])) . '.php';
        $indexFileName = sanitizeFilename(escape($_POST['index'])) . '.php';

        // Pfade zu den Dateien
        $adminFilePath = $adminDir . '/' . $adminFileName;
        $indexFilePath = $pluginDir . '/' . $indexFileName;

        // Beispielinhalt Admin-Datei anlegen, falls noch nicht existent
        if (!file_exists($adminFilePath)) {
            $adminContent = "<?php\n// Beispiel Admin-Seite\n echo 'Admin-Seite vom Plugin $path';\n";
            file_put_contents($adminFilePath, $adminContent);
            echo "Admin-Datei $adminFileName erstellt.<br>";
        } else {
            echo "Admin-Datei $adminFileName existiert bereits.<br>";
        }

        // Beispielinhalt Index-Datei anlegen, falls noch nicht existent
        if (!file_exists($indexFilePath)) {
            $indexContent = "<?php\n// Beispiel Index-Seite\n echo 'Index-Seite vom Plugin $path';\n";
            file_put_contents($indexFilePath, $indexContent);
            echo "Index-Datei $indexFileName erstellt.<br>";
        } else {
            echo "Index-Datei $indexFileName existiert bereits.<br>";
        }


        echo $languageService->get('success_save') . "<br /><br />";
        redirect("admincenter.php?site=plugin_manager", "", 5);
        return false;

    } catch (Exception $e) {
        echo $languageService->get('failed_save') . "<br /><br />" . $e->getMessage();
        redirect("admincenter.php?site=plugin_manager", "", 5);
        return false;
    }
}


if (isset($_POST['edit']) && isset($_POST['id']) && is_numeric($_POST['id'])) {
    $pluginID = (int)$_POST['id'];
    $acti = isset($_POST['activate']) ? 1 : 0;

    $name           = escape($_POST['name']);
    $modulname      = escape($_POST['modulname']);
    $info           = escape($_POST['info']);
    $admin_file     = escape($_POST['admin_file']);
    $author         = escape($_POST['author']);
    $website        = escape($_POST['website']);
    $index_file     = escape($_POST['index']);
    $hiddenfiles    = escape($_POST['hiddenfiles']);
    $version        = escape($_POST['version']);
    $path           = escape($_POST['path']);

    $admin_cat_id       = (int)($_POST['nav_admin_cat'] ?? 0);
    $website_cat_id     = (int)($_POST['nav_website_cat'] ?? 0);
    $admin_title        = escape($_POST['nav_admin_title']);
    $website_title      = escape($_POST['nav_website_title']);
    $admin_file_url     = escape($_POST['nav_admin_link']);
    $index_file_url     = escape($_POST['nav_website_link']);

    try {
        // Plugin aktualisieren
        safe_query("
            UPDATE `settings_plugins` SET
                `name` = '$name',
                `modulname` = '$modulname',
                `info` = '$info',
                `activate` = '1',
                `admin_file` = '$admin_file',
                `author` = '$author',
                `website` = '$website',
                `index_link` = '$index_file',
                `hiddenfiles` = '$hiddenfiles',
                `version` = '$version',
                `path` = '$path'
            WHERE pluginID = $pluginID
        ");

        safe_query("
            UPDATE `settings_plugins_installed`
            SET
                `name` = '$name',
                `modulname` = '$modulname',
                `description` = '$info',
                `version` = '$version',
                `author` = '$author',
                `url` = '$website',
                `folder` = '$modulname'
            WHERE modulname = '$modulname'"
        );


        // Admin-Navigation
        $adminNavExists = mysqli_num_rows(safe_query("SELECT * FROM navigation_dashboard_links WHERE modulname = '$modulname'"));
        if ($admin_title && $admin_cat_id > 0) {
            if ($adminNavExists) {
                safe_query("
                    UPDATE navigation_dashboard_links SET
                        catID = $admin_cat_id,
                        name = '$admin_title',
                        url = '$admin_file_url'
                    WHERE modulname = '$modulname'
                ");
            } else {
                safe_query("
                    INSERT INTO navigation_dashboard_links (catID, modulname, name, url, sort) VALUES (
                        $admin_cat_id,
                        '$modulname',
                        '$admin_title',
                        '$admin_file_url',
                        1
                    )
                ");
            }
        }

        // Website-Navigation
        $websiteNavExists = mysqli_num_rows(safe_query("SELECT * FROM navigation_website_sub WHERE modulname = '$modulname'"));
        if ($website_title && $website_cat_id > 0) {
            if ($websiteNavExists) {
                safe_query("
                    UPDATE navigation_website_sub SET
                        mnavID = $website_cat_id,
                        name = '$website_title',
                        url = '$index_file_url'
                    WHERE modulname = '$modulname'
                ");
            } else {
                safe_query("
                    INSERT INTO navigation_website_sub (mnavID, name, modulname, url, sort, indropdown) VALUES (
                        $website_cat_id,
                        '$website_title',
                        '$modulname',
                        '$index_file_url',
                        1,
                        1
                    )
                ");
            }
        }

        echo $languageService->get('success_save') . "<br /><br />";
        redirect("admincenter.php?site=plugin_manager&action=edit&id=" . $pluginID . "&do=edit", "", 1);
        return false;

    } catch (Exception $e) {
        echo $languageService->get('failed_save') . "<br /><br />" . $e->getMessage();
        redirect("admincenter.php?site=plugin_manager&action=edit&id=" . $pluginID . "&do=edit", "", 1);
        return false;
    }
}



    #Erstellt eine neue Plugin-Einstellung END


    if (isset($_GET['action']) && $_GET['action'] == "delete_plugin" && isset($_GET['modulname'])) {
        $modulname = $_GET['modulname']; // ACHTUNG: Idealerweise per prepared statement, aber hier:
        $modulname_safe = mysqli_real_escape_string($_database, $modulname);

        // Prüfe, ob Plugin existiert
        $plugin_name_query = safe_query("SELECT modulname FROM settings_plugins WHERE modulname = '" . $modulname_safe . "'");

        if (mysqli_num_rows($plugin_name_query) > 0) {
            $plugin_name = mysqli_fetch_assoc($plugin_name_query)['modulname'];

            echo '<div class="alert alert-info"><strong><i class="bi bi-trash3"></i> ' . $languageService->get('delete_plugin') . ':</strong> ' . htmlspecialchars($plugin_name, ENT_QUOTES, 'UTF-8') . '</div>';

            // 1) Entferne aus globaler Widget-Tabelle
            safe_query("DELETE FROM `settings_widgets` WHERE `modulname` = '" . $plugin_name . "'");

            // 2) Entferne genau die plugins_[modulname]_settings_widgets Tabelle
            safe_query("DELETE FROM `settings_widgets_positions` WHERE `modulname` = '" . $plugin_name . "'");

            // 3) Entferne Plugin aus settings_plugins
            safe_query("DELETE FROM `settings_plugins` WHERE `modulname` = '" . $plugin_name . "'");

            safe_query("DELETE FROM `navigation_dashboard_links` WHERE `modulname` = '" . $plugin_name . "'");
            safe_query("DELETE FROM `navigation_website_sub` WHERE `modulname` = '" . $plugin_name . "'");
            safe_query("DELETE FROM `user_role_admin_navi_rights` WHERE `modulname` = '" . $plugin_name . "'");

            safe_query("DELETE FROM `settings_plugins_installed` WHERE `modulname` = '" . $plugin_name . "'");

            // 4) Redirect
            flush();
            echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "admincenter.php?site=plugin_manager"; 
                    }, 2000);
                </script>';
        } else {
            echo '<div class="alert alert-danger"><strong><i class="bi bi-x-circle"></i> Error:</strong> Plugin <b>' . htmlspecialchars($modulname, ENT_QUOTES, 'UTF-8') . '</b> was not found in <b>settings_plugins</b>.</div>';
        }
    }


    // Fine della cancellazione del plugin e dei suoi widget


    #Erstellt eine neue Widget-Einstellung START
    if (isset($_POST['widget_add'])) {
        try {
            safe_query(
                "INSERT INTO `settings_widgets` (
                    `widget_key`,
                    `title`, 
                    `plugin`, 
                    `modulname`
                    ) VALUES (
                    '" . $_POST['widget_key'] . "',
                    '" . $_POST['title'] . "',
                    '" . $_POST['modulname'] . "', 
                    '" . $_POST['modulname'] . "'
                );
            "
            );

            echo $languageService->get('success_save') . "<br /><br />";
            redirect("admincenter.php?site=plugin_manager&action=edit&id=" . $_POST['id'] . "&do=edit", "", 1);
            return false;
        } catch (Exception $e) {
            echo $languageService->get('failed_save') . "<br /><br />" . $e->getMessage();
            redirect("admincenter.php?site=plugin_manager&action=edit&id=" . $_POST['id'] . "&do=edit", "", 1);
            return false;
        }
        return false;
    }


if (isset($_POST['widget_edit'])) {
    try {
        $sql = "
            UPDATE `settings_widgets` SET
                `widget_key` = '" . escape($_POST['new_widget_key']) . "',
                `title` = '" . escape($_POST['title']) . "'
            WHERE `widget_key` = '" . escape($_POST['original_widget_key']) . "'
        ";

        safe_query($sql);

        echo $languageService->get('success_save') . "<br /><br />";
        redirect("admincenter.php?site=plugin_manager&action=edit&id=" . $_POST['id'] . "&do=edit", "", 1);
        return false;
    } catch (Exception $e) {
        echo $languageService->get('failed_save') . "<br /><br />" . $e->getMessage();
        redirect("admincenter.php?site=plugin_manager&action=edit&id=" . $_POST['id'] . "&do=edit", "", 1);
        return false;
    }
}

    #Erstellt eine neue Widget-Einstellung END


    
    ###FOOTER#################################
    if (isset($_POST['sortieren'])) {
        $CAPCLASS = new \nexpell\Captcha;
        if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {

            $sort = $_POST['sort'];
            foreach ($sort as $sortstring) {
                $sorter = explode("-", $sortstring);
                safe_query("UPDATE plugins_" . $_POST['modulname'] . "_settings_widgets SET sort='" . $sorter[1] . "' WHERE id='" . $sorter[0] . "' ");
            }
        } else {
            echo $languageService->get('transaction_invalid');
        }
    }

if (isset($_GET["delete"])) {
    $CAPCLASS = new \nexpell\Captcha();
    if ($CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'])) {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $widget_key = escape($_GET['widget_key']);

        // Modulname anhand widget_key abfragen
        $res = safe_query("SELECT modulname FROM settings_widgets WHERE widget_key = '" . $widget_key . "'");
        if (mysqli_num_rows($res)) {
            $data = mysqli_fetch_assoc($res);
            $modulname = escape($data['modulname']);

            // Jetzt sicher löschen
            safe_query("DELETE FROM settings_widgets WHERE modulname='" . $modulname . "'");
            safe_query("DELETE FROM settings_widgets_positions WHERE modulname='" . $modulname . "'");

            echo '<div class="alert alert-success">Widget erfolgreich gelöscht.</div>';
        } else {
            echo '<div class="alert alert-warning">Kein passendes Widget gefunden.</div>';
        }

        // Redirect mit GET-ID
        redirect("admincenter.php?site=plugin_manager&action=edit&id=" . $id . "&do=edit", "", 1);

    } else {
        echo '<div class="alert alert-danger">Ungültiger CAPTCHA-Hash!</div>';
        redirect("admincenter.php?site=plugin_manager&action=edit&id=" . ($_GET['id'] ?? 0) . "&do=edit", "", 1);
    }
}



if ($action == "edit") {
        $id = $_GET['id'];

        $CAPCLASS = new \nexpell\Captcha;
        $CAPCLASS->createTransaction();
        $hash = $CAPCLASS->getHash();

        echo '<div class="card">
        <div class="card-header"><i class="bi bi-puzzle"></i> 
            ' . $languageService->get('plugin_manager') . '
        </div>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_manager">' . $languageService->get('plugin_manager') . '</a></li>
            <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('edit_plugin') . '</li>
          </ol>
        </nav>
        <div class="card-body">';

        $ergebnis = safe_query("SELECT * FROM `settings_plugins` WHERE `pluginID`='" . $id . "' LIMIT 1");
        $ds = mysqli_fetch_array($ergebnis);

        $translate = new multiLanguage($lang);
        $translate->detectLanguages($ds['name']);
        $name = $translate->getTextByLanguage($ds['name']);
        $translate->detectLanguages($ds['info']);
        $info = $translate->getTextByLanguage($ds['info']);

        if (@$ds['admin_file'] != '') {

            echo '<div class="mb-3 row">
            <label class="col-md-1 control-label">' . $languageService->get('options') . ':</label>
            <div class="col-md-8">
                <a class="btn btn-primary" data-toggle="tooltip" data-html="true" title="' . $languageService->get('tooltip_7') . ' " href="admincenter.php?site=' . $ds['admin_file'] . '"><i class="bi bi-gear"></i> ' . $name . '</a>

      <a href="admincenter.php?site=plugin_manager&action=widget_add&id=' . $id . '" class="btn btn-primary" type="button"><i class="bi bi-plus-circle"></i> ' . $languageService->get('new_widget') . '</a>

            </div>
        </div>';
        } else {
        }
        echo '<form class="form-horizontal" method="post" id="post" name="post" action="admincenter.php?site=plugin_manager&id=' . $id . '&do=edit" enctype="multipart/form-data" onsubmit="return chkFormular();">';

        echo '<b>' . $languageService->get('plugin_basic_setting') . ':</b>
        <hr>
        <div class="mb-3 row">
            <input type="hidden" name="pid" value="' . $ds['pluginID'] . '" />    
            <label class="col-sm-5 col-form-label" for="name">Plugin ' . $languageService->get('name') . ':<br>
                ' . $languageService->get('multi_language_info_name') . '
            </label>
            <div class="col-sm-7">
                <h4>' . $name . '</h4>
                <span class="text-muted small"><em>
                <input type="name" class="form-control" name="name" value="' . $ds['name'] . '" placeholder="plugin name"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('description') . ':<br>
                ' . $languageService->get('multi_language_info_description') . '
            </label>
            <div class="col-sm-7">
                <p style="margin-top: 7px">' . $info . '</p>
                <span class="text-muted small"><em>
                <textarea class="form-control" name="info" rows="10" cols="" style="width: 100%;" placeholder="info">' . $ds['info'] . '</textarea></em></span>
            </div>
        </div>
   
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="author">' . $languageService->get('author') . ':</label>
            <div class="col-sm-7"><span class="text-muted small"><em>
                <input type="name" class="form-control" rows="5" name="author" value="' . $ds['author'] . '" placeholder="autor"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="website">' . $languageService->get('website') . ':</label>
            <div class="col-sm-7"><span class="text-muted small"><em>
                <input type="name" class="form-control" placeholder="http://" rows="5"  value="' . $ds['website'] . '" name="website"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('modulname') . ': <br><small>(' . $languageService->get('for_uninstall') . ')</small></label>
            <div class="col-sm-7"><span class="text-muted small"><em>
                <input type="name" class="form-control" name="modulname" value="' . $ds['modulname'] . '" disabled></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="admin_file">' . $languageService->get('admin_file') . ': <br><small>(' . $languageService->get('index_file_nophp') . ')</small></label>
            <div class="col-sm-7"><span class="text-muted small"><em>
                <input type="name" class="form-control"  name="admin_file" value="' . $ds['admin_file'] . '" placeholder="admin file"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="index">' . $languageService->get('index_file') . ': <br><small>(' . $languageService->get('index_file_nophp') . ')</small></label>
            <div class="col-sm-7"><span class="text-muted small"><em>
                <input type="name" class="form-control" placeholder="index file" rows="5"  value="' . $ds['index_link'] . '" name="index"></em></span>
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="hittenfiles">' . $languageService->get('hidden_file') . ': <br><small>(' . $languageService->get('hidden_file_seperate') . ')</small></label>
            <div class="col-sm-7"><span class="text-muted small"><em>
                <input type="name" class="form-control" rows="5" placeholder="myfile,secondfile,anotherfile" value="' . $ds['hiddenfiles'] . '" name="hiddenfiles"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="version">' . $languageService->get('version_file') . ':</label>
            <div class="col-sm-7"><span class="text-muted small"><em>
                <input type="name" class="form-control" rows="5" value="' . $ds['version'] . '" name="version" placeholder="version"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="path">' . $languageService->get('folder_file') . ': <br><small>(' . $languageService->get('folder_file_slash') . ')</small></label>
            <div class="col-sm-7"><span class="text-muted small"><em>
                <input type="name" class="form-control" placeholder="includes/plugins/myplugin/"  value="' . $ds['path'] . '" rows="5" name="path"></em></span>
            </div>
        </div>';

        // Admin Kategorien laden
        // Default-Werte definieren, damit Seite nicht abbricht
        $navAdminLink   = '';
        $navAdminTitle  = '';
        $navAdminCatID  = 0;

        $navWebsiteLink   = '';
        $navWebsiteTitle  = '';
        $navWebsiteCatID  = 0;

        $modulname = escape($ds['modulname']); // z. B. aus settings_plugins

        $modulname = escape($ds['modulname']); // ACHTUNG: muss vorher korrekt befüllt sein (z. B. aus settings_plugins)

        $adminNavQuery = safe_query("SELECT name, url, catID FROM navigation_dashboard_links WHERE modulname = '$modulname' LIMIT 1");
        if ($adminNav = mysqli_fetch_assoc($adminNavQuery)) {
            $navAdminLink = $adminNav['url'];
            $navAdminTitle = $adminNav['name'];
            $navAdminCatID = (int)$adminNav['catID'];
        }

        $websiteNavQuery = safe_query("SELECT name, url, mnavID FROM navigation_website_sub WHERE modulname = '$modulname' LIMIT 1");
        if ($websiteNav = mysqli_fetch_assoc($websiteNavQuery)) {
            $navWebsiteLink = $websiteNav['url'];
            $navWebsiteTitle = $websiteNav['name'];
            $navWebsiteCatID = (int)$websiteNav['mnavID'];
        }

        // Admin Kategorien laden
        $adminCatOptions = '';
        $adminCatQuery = safe_query("SELECT catID, name FROM navigation_dashboard_categories ORDER BY name");
        while ($adminCat = mysqli_fetch_assoc($adminCatQuery)) {
            $selected = ($adminCat['catID'] == $navAdminCatID) ? ' selected' : '';
            $adminCatOptions .= '<option value="' . (int)$adminCat['catID'] . '"' . $selected . '>' . escape($adminCat['name']) . '</option>';
        }

        // Website Kategorien laden
        $websiteCatOptions = '';
        $websiteCatQuery = safe_query("SELECT mnavID, name FROM navigation_website_main ORDER BY name");
        while ($websiteCat = mysqli_fetch_assoc($websiteCatQuery)) {
            $selected = ($websiteCat['mnavID'] == $navWebsiteCatID) ? ' selected' : '';
            $websiteCatOptions .= '<option value="' . (int)$websiteCat['mnavID'] . '"' . $selected . '>' . escape($websiteCat['name']) . '</option>';
        }


        echo '<hr>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Admin Navigations-Titel:<br><small>(admincenter.php?site=)</small></label>
            <div class="col-sm-6"><input type="text" name="nav_admin_link" class="form-control" value="' . escape($navAdminLink) . '"></div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Admin Navigations-Titel:</label>
            <div class="col-sm-6"><input type="text" name="nav_admin_title" class="form-control" value="' . escape($navAdminTitle) . '"></div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Admin Kategorie:</label>
            <div class="col-sm-6">
                <select name="nav_admin_cat" class="form-control">
                    ' . $adminCatOptions . '
                </select>
            </div>
        </div>

        <hr>

        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Website Navigations-Titel:<br><small>(index.php?site=)</small></label>
            <div class="col-sm-6"><input type="text" name="nav_website_link" class="form-control" value="' . escape($navWebsiteLink) . '"></div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Website Navigations-Titel:</label>
            <div class="col-sm-6"><input type="text" name="nav_website_title" class="form-control" value="' . escape($navWebsiteTitle) . '"></div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Website Kategorie:</label>
            <div class="col-sm-6">
                <select name="nav_website_cat" class="form-control">
                    ' . $websiteCatOptions . '
                </select>
            </div>
        </div>

        <hr>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="path">Widgets: <br><small>(' . $languageService->get('widget_included_with_plugin') . ')</small></label>  
            ';

        $widgetsergebnis = safe_query("SELECT * FROM settings_widgets WHERE plugin = '" . $ds['modulname'] . "'");
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
                    <i class="bi bi-trash3"></i> ' . $languageService->get('widget_delete') . '
                </button>

                <!-- Modal -->
                <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                    
                      <div class="modal-header">
                        <h5 class="modal-title" id="confirmDeleteModalLabel">' . $languageService->get('widget_delete') . '</h5>
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
                          <i class="bi bi-trash3"></i> ' . $languageService->get('widget_delete') . '
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
        if ($ds['modulname'] == @$modulname) {
            $xwidget = $widget;
        } else {
            $xwidget = $languageService->get('no_widget_available');
        }

        echo '' . $xwidget . '
            
        </div>';

        #Plugin-Grundeinstellungen END

        echo '<div class="col-sm-12">
            <div class="mb-3 row">
                <label class="col-sm- col-form-label" for="name"></label>
                <div class="col-sm-6">
                    <input type="hidden" name="captcha_hash" value="' . $hash . '">
                
                <input type="hidden" name="modulname" value="' . $ds['modulname'] . '">
                <input type="hidden" name="id" value="' . $_GET['id'] . '">
                    <button class="btn btn-warning" type="submit" name="edit"><i class="bi bi-save"></i> ' . $languageService->get('edit_plugin_widget') . '</button>
                </div>
            </div>
        </div>
        </form>
    </div>
</div>';

        return false;
    } elseif ($action == "widget_add") {

        #$id = $_GET['id'];

        $CAPCLASS = new \nexpell\Captcha;
        $CAPCLASS->createTransaction();
        $hash = $CAPCLASS->getHash();

        echo '<div class="card">
        <div class="card-header"><i class="bi bi-puzzle"></i> 
            ' . $languageService->get('plugin_manager') . '
        </div>
            
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_manager">' . $languageService->get('plugin_manager') . '</a></li>
            <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('add_widget') . '</li>
          </ol>
        </nav>


        <div class="card-body">';

        $ergebnis = safe_query("SELECT * FROM settings_plugins WHERE pluginID = '" . $id . "'");
        $db = mysqli_fetch_array($ergebnis);
        echo '<div class="mb-12 row">
            <label class="col-md-1 control-label"><h4><i class="bi bi-plugin"></i> Plugin:</h4></label>
            <div class="col-md-3"><div class="alert alert-info" role="alert" style="padding: 0px 5px">
        <h4>' . $db['modulname'] . '</h4></div>
            </div>
          </div>
        <hr>';
        
        echo '<form class="form-horizontal" method="post" id="post" name="post" action="admincenter.php?site=plugin_manager" onsubmit="return chkFormular();" enctype="multipart/form-data">
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('widget_name') . ':<font color="#DD0000">*</font> <br><small>(' . $languageService->get('for_widgetname') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="text"" class="form-control" name="title" placeholder="' . $languageService->get('widget_name') . '"></em></span>
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('modulname') . ': <br><small>(' . $languageService->get('for_plugin') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" name="modulname" value="' . $db['modulname'] . '" disabled></em></span>
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="admin_file">' . $languageService->get('widget_datei') . ': <br><small>(' . $languageService->get('widgetdatei_nophp') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" name="widget_key" placeholder="' . $languageService->get('widgetdatei_nophp') . '"></em></span>
            </div>
        </div>

        <div class="col-sm-12">
            <div class="mb-3 row">
                <div class="col-sm-11">
                    <font color="#DD0000">*</font>' . $languageService->get('fields_star_required') . '
                </div>
                <div class="col-sm-11">

                    <input type="hidden" name="modulname" value="' . $db['modulname'] . '" />
                    <input type="hidden" name="id" value="' . $_GET['id'] . '" />
                    <button class="btn btn-success" type="submit" name="widget_add"  /><i class="bi bi-plus-circle"></i> ' . $languageService->get('add_widget') . '</button>

                </div>
            </div>
        </div>';

        echo '</form></div></div>';
    } elseif ($action == "edit_widget") {

        $id = $_GET['id'];
        $widget_key = $_GET['widget_key'];

        $CAPCLASS = new \nexpell\Captcha;
        $CAPCLASS->createTransaction();
        $hash = $CAPCLASS->getHash();

        echo '<div class="card">
        <div class="card-header"><i class="bi bi-puzzle"></i> 
            ' . $languageService->get('plugin_manager') . '
        </div>
            
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_manager">' . $languageService->get('plugin_manager') . '</a></li>
            <li class="breadcrumb-item active" aria-current="page"><i class="bi bi-pencil-square"></i> ' . $languageService->get('edit_widget') . '</li>
          </ol>
        </nav>
        <div class="card-body">';

        $ergebnis = safe_query("SELECT * FROM settings_widgets WHERE `widget_key` = '" . $widget_key . "'");
        $db = mysqli_fetch_array($ergebnis);
        echo '<div class="mb-12 row">
            <label class="col-md-1 control-label"><h4><i class="bi bi-plugin"></i> Plugin:</h4></label>
            <div class="col-md-3"><div class="alert alert-info" role="alert" style="padding: 0px 5px">
        <h4>' . $db['modulname'] . '</h4></div>
            </div>
          </div>
        <hr>';

        echo '<form class="form-horizontal" method="post" id="post" name="post" action="admincenter.php?site=plugin_manager" onsubmit="return chkFormular();" enctype="multipart/form-data">
       
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('widget_name') . ':<font color="#DD0000">*</font> <br><small>(' . $languageService->get('for_widgetname') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="text"" class="form-control" name="title" value="' . $db['title'] . '" placeholder="widget name"></em></span>
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('modulname') . ': <br><small>(' . $languageService->get('for_plugin') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" name="modulname" value="' . $db['modulname'] . '" disabled></em></span>
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="admin_file">' . $languageService->get('widget_datei') . ': <br><small>(' . $languageService->get('widgetdatei_nophp') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="text" class="form-control" name="new_widget_key" value="'.$db['widget_key'].'" />
            </div>
        </div>

       <div class="col-sm-12">
            <div class="mb-3 row">
                <div class="col-sm-11">
                    <font color="#DD0000">*</font>' . $languageService->get('fields_star_required') . '
                </div>
                <div class="col-sm-11">
                <input type="hidden" name="original_widget_key" value="'.htmlspecialchars($db['widget_key']).'">
                <input type="hidden" name="id" value="'.(int)$_GET['id'].'">
                    
                    <button class="btn btn-warning" type="submit" name="widget_edit"  /><i class="bi bi-pencil-square"></i> ' . $languageService->get('edit_widget') . '</button>
                </div>
            </div>
        </div>
';
        echo '</form></div></div>';


} elseif ($action == "new") {
        ?><script>
            <!--
            function chkFormular() {
                if (document.getElementById('name').value == "") {
                    alert('<? echo $languageService->get('no_plugin_name'); ?>');
                    document.getElementById('name').focus();
                    return false;
                }

                if (document.getElementById('modulname').value == "") {
                    alert('<? echo $languageService->get('no_modul_name'); ?>');
                    document.getElementById('modulname').focus();
                    return false;
                }

            }
            -->
        </script><?php

        // Admin-Kategorien laden



        $themeergebnis = safe_query("SELECT * FROM settings_themes WHERE active = '1'");
        $db = mysqli_fetch_array($themeergebnis);


                    echo '<div class="card">
        <div class="card-header"><i class="bi bi-puzzle"></i> 
            ' . $languageService->get('plugin_manager') . '
        </div>
            
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_manager">' . $languageService->get('plugin_manager') . '</a></li>
                <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('add_plugin') . '</li>
            </ol>
        </nav>
        <div class="card-body">';

                    echo '<form class="form-horizontal" method="post" id="post" name="post" action="admincenter.php?site=plugin_manager" onsubmit="return chkFormular();" enctype="multipart/form-data">
       <form class="form-horizontal" method="post" id="post" name="post" action="admincenter.php?site=plugin_manager" enctype="multipart/form-data" onsubmit="return chkFormular();"> 
  

        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('name') . ':<font color="#DD0000">*</font></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="text" name="name" id="name" placeholder="plugin name" maxlength="30" autocomplete="name" class="form-control"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('description') . ':</label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <textarea class="form-control" name="info" rows="5" cols="" style="width: 100%;" placeholder="info"></textarea></em></span>
            </div>
        </div>
  
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="author">' . $languageService->get('author') . ':</label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" rows="5" name="author" placeholder="author"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="website">' . $languageService->get('website') . ':</label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" placeholder="http://" rows="5" name="website"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="name">' . $languageService->get('modulname') . ': <font color="#DD0000">*</font> <br><small>(' . $languageService->get('for_uninstall') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="text" name="modulname" id="modulname" placeholder="modulname" maxlength="30" autocomplete="modulname" class="form-control"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="admin_file">' . $languageService->get('admin_file') . ': <br><small>(' . $languageService->get('index_file_nophp') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" name="admin_file" placeholder="admin_file"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="index">' . $languageService->get('index_file') . ': <br><small>(' . $languageService->get('index_file_nophp') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" placeholder="index file" rows="5" name="index"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="hittenfiles">' . $languageService->get('hidden_file') . ': <br><small>(' . $languageService->get('hidden_file_seperate') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" rows="5" placeholder="myfile,secondfile,anotherfile" name="hiddenfiles"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="version">' . $languageService->get('version_file') . ':</label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" rows="5" name="version" placeholder="version"></em></span>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label" for="path">' . $languageService->get('folder_file') . ':  <font color="#DD0000">*</font> <br><small>(' . $languageService->get('folder_file_slash') . ')</small></label>
            <div class="col-sm-6"><span class="text-muted small"><em>
                <input type="name" class="form-control" placeholder="includes/plugins/myplugin/" rows="5" name="path"></em></span>
            </div>
        </div>';

        // Admin Kategorien laden
        $adminCatOptions = '';
        $adminCatQuery = safe_query("SELECT catID, name FROM navigation_dashboard_categories ORDER BY name");
        while ($adminCat = mysqli_fetch_assoc($adminCatQuery)) {
            $adminCatOptions .= '<option value="' . (int)$adminCat['catID'] . '">' . escape($adminCat['name']) . '</option>';
        }

        // Website Kategorien laden
        $websiteCatOptions = '';
        $websiteCatQuery = safe_query("SELECT mnavID, name FROM navigation_website_main ORDER BY name");
        while ($websiteCat = mysqli_fetch_assoc($websiteCatQuery)) {
            $websiteCatOptions .= '<option value="' . (int)$websiteCat['mnavID'] . '">' . escape($websiteCat['name']) . '</option>';
        }



        echo '<hr>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Admin Navigations-Link:<br><small>(admincenter.php?site=)</small></label>
            <div class="col-sm-6"><input type="text" name="nav_admin_link" class="form-control"></div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Admin Navigations-Titel:</label>
            <div class="col-sm-6"><input type="text" name="nav_admin_title" class="form-control"></div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Admin Kategorie:</label>
            <div class="col-sm-6">
                <select name="nav_admin_cat" class="form-control">
                    ' . $adminCatOptions . '
                </select>
            </div>
        </div>
        <hr>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Website Navigations-Link:<br><small>(index.php?site=)</small></label>
            <div class="col-sm-6"><input type="text" name="nav_website_link" class="form-control"></div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Website Navigations-Titel:</label>
            <div class="col-sm-6"><input type="text" name="nav_website_title" class="form-control"></div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-5 col-form-label">Website Kategorie:</label>
            <div class="col-sm-6">
                <select name="nav_website_cat" class="form-control">
                    ' . $websiteCatOptions . '
                </select>
            </div>
        </div>


        <div class="col-sm-12">
            <div class="mb-3 row">
                <div class="col-sm-11">
                    <font color="#DD0000">*</font>' . $languageService->get('fields_star_required') . '
                </div>
                <div class="col-sm-11">
                    <input type="hidden" name="themes_modulname" value="' . $db['modulname'] . '" />
                    <button class="btn btn-success" type="submit" name="add"  /><i class="bi bi-save"></i> ' . $languageService->get('save_plugin') . '</button>
                </div>
            </div>
        </div>

        </form>
    </div>
</div>';
                    return false;
                    echo '</div></div>';
} else {
                    echo '<div class="card">
        <div class="card-header"><i class="bi bi-puzzle"></i> 
            ' . $languageService->get('plugin_manager') . '
        </div>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_manager">' . $languageService->get('plugin_manager') . '</a></li>
            <li class="breadcrumb-item active" aria-current="page">new & edit</li>
          </ol>
        </nav>
        <div class="card-body">
        <div class="mb-3 row">
    <label class="col-md-1 control-label">' . $languageService->get('options') . ':</label>
    <div class="col-md-8">

      <a href="admincenter.php?site=plugin_manager&action=new" class="btn btn-primary" type="button"><i class="bi bi-plus-circle"></i> ' . $languageService->get('new_plugin') . '</a>

    </div>
  </div>';
                    $thergebnis = safe_query("SELECT * FROM settings_themes WHERE active = '1'");
                    $db = mysqli_fetch_array($thergebnis);

                    $CAPCLASS = new \nexpell\Captcha;
                    $CAPCLASS->createTransaction();
                    $hash = $CAPCLASS->getHash();

                    echo '<table id="plugini" class="table table-striped table-bordered" style="width:100%">
        <thead>
            <th><strong>' . $languageService->get('id') . '</strong></th>
            <th width="10%"><strong>' . $languageService->get('plugin') . ' ' . $languageService->get('name') . '</strong></th>
            <th><strong>' . $languageService->get('plugin') . ' ' . $languageService->get('description') . '</strong></th>
            <th class="text-center" width="12%"><strong>' . $languageService->get('plugin_status') . '</strong></th>
            <th class="text-center" width="12%"><strong>' . $languageService->get('plugin_setting') . '</strong></th>
            <th class="text-center" width="12%"><strong>' . $languageService->get('action') . '</strong></th>

        </thead>';
                    $ergebnis = safe_query("SELECT * FROM settings_plugins");
                    while ($ds = mysqli_fetch_array($ergebnis)) {

                        $dx = mysqli_fetch_array(safe_query("SELECT * FROM settings_plugins WHERE pluginID='" . $ds['pluginID'] . "'"));

                        if ($ds['activate'] == "1") {
                            $actions = '<div class="d-grid gap-2"><a href="admincenter.php?site=plugin_manager&id=' . $ds['pluginID'] . '&modulname=' . $ds['modulname'] . '&do=dea" class="btn btn-info" data-toggle="tooltip" data-html="true" title="' . $languageService->get('tooltip_2') . ' " type="button"><i class="bi bi-toggle-off"></i> ' . $languageService->get('deactivate') . '</a></div>';
                        } else {
                            $actions = '<div class="d-grid gap-2"><a href="admincenter.php?site=plugin_manager&id=' . $ds['pluginID'] . '&modulname=' . $ds['modulname'] . '&do=act" class="btn btn-success" data-toggle="tooltip" data-html="true" title="' . $languageService->get('tooltip_1') . ' " type="button"><i class="bi bi-toggle-on"></i> ' . $languageService->get('activate') . '</a></div>';
                        }

                        $translate = new multiLanguage($lang);
                        $translate->detectLanguages($ds['name']);
                        $ds['name'] = $translate->getTextByLanguage($ds['name']);
                        $translate->detectLanguages($ds['info']);
                        $ds['info'] = $translate->getTextByLanguage($ds['info']);

                        echo '<tr>
                    <td>' . $ds['pluginID'] . '</td>
                    <td><b>' . $ds['name'] . '</b></td>
                    <td>' . $ds['info'] . '</td>';


                        if ($dx['status_display'] == "1") {
                            echo '<td class="text-center">' . $actions . '</div>';
                        } else {

                            echo '<td class="text-center">
                                <div class="d-grid gap-2">
                            <button type="button" class="btn btn-danger" disabled><i class="bi bi-slash-circle"></i> ' . $languageService->get('status_cannot_assigned') . '</button>
                                 </div></td>';
                        }
                        if ($dx['plugin_display'] == "1") {
                            echo '
                    <td class="text-center">
                    <div class="d-grid gap-2">
                    <a href="admincenter.php?site=plugin_manager&action=edit&id=' . $ds['pluginID'] . '&do=edit" class="btn btn-warning" data-toggle="tooltip" data-html="true" title="' . $languageService->get('tooltip_4') . '" type="button"><i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '</a></div></td>';
                        } else {

                            echo '<td class="text-center">
                            <div class="d-grid gap-2">
                        <button type="button" class="btn btn-danger" disabled><i class="bi bi-slash-circle"></i> ' . $languageService->get('plugin_cannot_assigned') . '</button>
                        </div></td>';
                        }


                        


                        if ($dx['delete_display'] != "1") {

                            echo '<td class="text-center">
                            <div class="d-grid gap-2">
                            <button type="button" class="btn btn-danger" disabled><i class="bi bi-slash-circle"></i> ' . $languageService->get('delete_cannot_assigned') . '</button>
                            </div></td>';
                        } else {

                           echo '
                            <td class="text-center">
                                <div class="d-grid gap-2">
                                    <a href="#"
                                       class="btn btn-danger"
                                       data-bs-toggle="modal"
                                       data-bs-target="#confirmDeleteModal"
                                       data-plugin-id="' . $ds['pluginID'] . '"
                                       data-plugin-name="' . $ds['modulname'] . '"
                                       title="' . $languageService->get('tooltip_8') . '">
                                       <i class="bi bi-trash3"></i> ' . $languageService->get('delete_plugin') . '
                                    </a>
                                </div>
                            </td>

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

                            <script>
                            document.querySelectorAll("a[data-bs-target=\'#confirmDeleteModal\']").forEach(button => {
                                button.addEventListener("click", function() {
                                    var pluginID = this.getAttribute("data-plugin-id");
                                    var pluginName = this.getAttribute("data-plugin-name");

                                    // Format plugin name
                                    function formatPluginName(name) {
                                        name = name.replace(/_/g, " ");
                                        name = name.replace(/([a-z])([A-Z])/g, "$1 $2");
                                        return name.replace(/\\b\\w/g, char => char.toUpperCase());
                                    }
                                    var formattedName = formatPluginName(pluginName);

                                    // Set formatted name into the modal title
                                    document.getElementById("modalPluginTitle").innerText = formattedName;

                                    // Compose final delete URL
                                    var deleteUrl = "admincenter.php?site=plugin_manager&action=delete_plugin&id="
                                        + pluginID
                                        + "&modulname=" + pluginName
                                        + "&do=delete";

                                    // Set href of confirm delete button
                                    document.getElementById("confirmDeleteBtn").setAttribute("href", deleteUrl);
                                });
                            });
                            </script>
                            ';
                        }

                        echo '</tr>';
                    }

                    echo '</table></div></div></div>';
                }
            } else {

                echo '<style type="text/css">
     p.test {
        font-family: Georgia, serif;
        font-size: 78px;
        font-style: italic;
    }
    .titlehead {
        border: 3px solid;
        border-color: #c4183c; 
        background-color: #fff}
    </style>
    <div class="card">
        <div class="card-body">
            <div class="titlehead"><br>
                <center>
                    <div>
                        <img class="img-fluid" src="/images/install-logo.jpg" alt="" style="height: 150px"/><br>
                          <small>Ohje !</small><br>
                          <p class="test">404 Error.</p><br>
                          ' . $languageService->get('info') . '
                    </div>
                    <br />
                    <p><a class="btn btn-warning" href="/admin/admincenter.php?site=settings_templates">' . $languageService->get('activate_template') . '</a></p>
                    <br />
                </center>
            </div>
        </div>
    </div>';
            }
?>