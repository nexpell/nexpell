<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * nexpell 1.0 - Modern Content & Community Management System
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @version       1.0
 * @build         Stable Release
 * @release       2025
 * @copyright     © 2025 nexpell | https://www.nexpell.de
 * 
 * @description   nexpell is a modern open source CMS designed for gaming
 *                communities, esports teams, and digital projects of any kind.
 * 
 * @author        The nexpell Team
 * 
 * @license       GNU General Public License (GPL)
 *                This software is distributed under the terms of the GPL.
 *                It is strictly prohibited to remove this copyright notice.
 *                For license details, see: https://www.gnu.org/licenses/gpl.html
 * 
 * @support       Support, updates, and plugins available at:
 *                → Website: https://www.nexpell.de
 *                → Forum:   https://www.nexpell.de/forum.html
 *                → Wiki:    https://www.nexpell.de/wiki.html
 * 
 * ─────────────────────────────────────────────────────────────────────────────
 */


// === Fehleranzeige aktivieren ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        echo "<pre style='background:#fee;color:#900;padding:10px;border:1px solid #900;'>";
        echo "FATAL ERROR:\n";
        print_r($error);
        echo "</pre>";
    }
});

// === Session starten ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// === Sprachsystem vorbereiten ===
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// === System-Dateien einbinden ===
include_once("system/config.inc.php");
include_once("system/settings.php");
include_once("system/functions.php");
include_once("system/themes.php");
include_once("system/init.php");
include_once("system/multi_language.php");
include_once("system/init_language.php"); // setzt $languageService
include_once("system/classes/Template.php");
include_once("system/classes/SeoUrlHandler.php");
include_once("system/session_update.php");
include_once("system/visitor_log_statistic.php");

// === Globale Variablen ===
global $tpl;
global $_database;
global $languageService;

// === Template initialisieren ===
$tpl = new Template();
Template::setInstance($tpl);

// Jetzt kannst du getInstance() ohne Fehler aufrufen
$instance = Template::getInstance();

$theme = new Theme();

$tpl->themes_path = rtrim($theme->get_active_theme(), '/\\') . DIRECTORY_SEPARATOR;
$tpl->template_path = "templates" . DIRECTORY_SEPARATOR;

// === CSS / JS Komponenten vorbereiten ===
$components_css = "";
if (!empty($components['css'])) {
    foreach ($components['css'] as $component) {
        $components_css .= '    <link type="text/css" rel="stylesheet" href="' . htmlspecialchars($component) . '" />' . "\n";
    }
}

define("MODULE", "./includes/modules/");
define("PLUGIN", "./includes/plugins/");

$components_js = "";
if (!empty($components['js'])) {
    foreach ($components['js'] as $component) {
        $components_js .= '<script defer src="' . htmlspecialchars($component) . '"></script>' . "\n";
    }
}

$theme_css = headfiles("css", $tpl->themes_path);
$theme_js = headfiles("js", $tpl->themes_path);

$availableLangs = ['de', 'en', 'it'];

$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $requestUri);

// Sprache
if (isset($_GET['lang']) && in_array($_GET['lang'], $availableLangs)) {
    $lang = $_GET['lang'];
} elseif (isset($segments[0]) && in_array($segments[0], $availableLangs)) {
    $lang = $segments[0];
} elseif (isset($_SESSION['language']) && in_array($_SESSION['language'], $availableLangs)) {
    $lang = $_SESSION['language'];
} else {
    $lang = 'de';
}
$_SESSION['language'] = $lang;

// Seite
$site = $_GET['site'] ?? ($segments[1] ?? 'index');
$site = preg_replace('/[^a-zA-Z0-9_-]/', '', $site);
$_GET['site'] = $site;

// Action, ID, Page etc.
$action = $_GET['action'] ?? ($segments[2] ?? null);
$id     = $_GET['id'] ?? ($segments[3] ?? null);
$page   = $_GET['page'] ?? null;

if ($action) $_GET['action'] = $action;
if ($id) $_GET['id'] = $id;
if ($page) $_GET['page'] = $page;

$langfile = BASE_PATH . "/languages/{$lang}/{$site}.php";
if (!file_exists($langfile)) {
    $pluginLangFile = BASE_PATH . "/includes/plugins/{$site}/languages/{$lang}/{$site}.php";
    if (file_exists($pluginLangFile)) {
        $langfile = $pluginLangFile;
    } else {
        // Kein trigger_error
        $translations = [];
    }
}

if (file_exists($langfile)) {
    $translations = include $langfile; // Spracharray laden
} else {
    $translations = [];
}

// Sprachauswahl an LanguageService übergeben – hier nur die Sprachkennung
$languageService->setLanguage($lang);  // $lang ist ein String, z.B. 'de'

// Template laden
$themeFile = BASE_PATH . '/' . $tpl->themes_path . 'index.php';

if (file_exists($themeFile)) {
    include $themeFile;
} else {
    die("Theme-Datei nicht gefunden: " . $themeFile);
}
