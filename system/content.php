<?php

// Funktion zur Ausgabe des Seitentitels
function get_sitetitle(): string
{
    $pluginManager = new plugin_manager();
    $site = $_GET['site'] ?? 'index';

    // Titel gezielt für Startseite setzen
    if ($site === 'index') {
        return 'nexpell – Dein CMS für moderne Webprojekte';
    }

    $updatedTitle = $pluginManager->plugin_updatetitle($site);

    // Fallback: Standardtitel (z. B. "nexpell - [news]")
    $title = $updatedTitle ?: PAGETITLE;

    // Optional: Platzhalter ersetzen
    $replacements = [
        '[news]' => 'Nachrichten',
        '[home]' => 'Startseite',
    ];

    return strtr($title, $replacements);
}

function get_mainContent()
{
    global $tpl, $languageService, $pluginManager;

    $settings = safe_query("SELECT * FROM `settings`");
    if (!$settings) {
        system_error("Fehler beim Abrufen der Einstellungen.");
    }
    $ds = mysqli_fetch_array($settings);

    // Site ermitteln
    if (!isset($_GET['site']) || $_GET['site'] === 'index' || empty($_GET['site'])) {
        $site = $ds['startpage'];
    } else {
        $site = getinput($_GET['site']);
    }

    $site = preg_replace('/[^a-zA-Z0-9_-]/', '', $site);

    $module_dir = realpath(__DIR__ . '/../includes/modules');

    /* ==========================================================
       1) CORE-MODUL
    ========================================================== */
    $module_path = $module_dir . "/{$site}.php";
    if (file_exists($module_path)) {

        // 🔥 aktives Modul merken
        $GLOBALS['nx_active_module'] = $site;

        // 🔥 Sprache automatisch laden
        if (isset($languageService)) {
            $languageService->autoLoadActiveModule(false);
        }

        // 🔥 Modul-Assets registrieren
        if (function_exists('registerModuleAssets')) {
            registerModuleAssets($site);
        }

        ob_start();
        include $module_path;
        return ob_get_clean();
    }

    /* ==========================================================
       2) PLUGIN-SEITE
    ========================================================== */
    $plugin_query = safe_query("SELECT * FROM settings_plugins WHERE activate='1'");
    while ($row = mysqli_fetch_array($plugin_query)) {

        $links = array_map('trim', explode(',', $row['index_link']));
        if (in_array($site, $links, true)) {

            // 🔥 aktives Plugin wie Modul behandeln
            $GLOBALS['nx_active_module'] = $site;

            // 🔥 Plugin-Sprache automatisch laden
            if (isset($languageService)) {
                $languageService->autoLoadActiveModule(false);
            }

            $pluginFile = $pluginManager->loadPluginPage($site);
            if ($pluginFile) {

                // 🔥 Plugin-Assets
                $pluginManager->loadPluginAssets($site);

                ob_start();
                include $pluginFile;
                return ob_get_clean();
            }
        }
    }

    /* ==========================================================
       3) 404
    ========================================================== */
    $error_page = $module_dir . "/404.php";
    if (file_exists($error_page)) {
        $GLOBALS['nx_active_module'] = '404';

        if (isset($languageService)) {
            $languageService->autoLoadActiveModule(false);
        }

        ob_start();
        include $error_page;
        return ob_get_clean();
    }

    return "<h1>404 - Seite nicht gefunden</h1>";
}



// NxEditor Konfiguration
function get_editor()
{
    echo '<script src="./components/js/nx_editor.js"></script>';
}

#Wartungsmodus wird anezeigt
function get_lock_modul()
{
    global $closed;
    $dm = mysqli_fetch_array(safe_query("SELECT * FROM settings where closed='1'"));
    if (@$closed != '1') {
    } else {
        echo '<div class="alert alert-danger" role="alert" style="margin-bottom: -5px;">
            <center>Die Seite befindet sich im Wartungsmodus | The site is in maintenance mode | Il sito è in modalità manutenzione</center>
        </div>';
    }
}

function escape(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
