<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * nexpell - Modern Content & Community Management System
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @version       1
 * @build         Stable Release
 * @release       2026
 * @copyright     © 2026 nexpell | https://www.nexpell.de
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
 *
 */

/* ==========================================================
   1️⃣ Fehleranzeige (Dev-Modus)
========================================================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        echo "<pre style='background:#fee;color:#900;padding:10px;border:1px solid #900;'>";
        echo "FATAL ERROR:\n";
        print_r($error);
        echo "</pre>";
    }
});

/* ==========================================================
   2️⃣ Session starten
========================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
   3️⃣ BASE_PATH (GANZ OBEN, EINMALIG)
========================================================== */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

/* ==========================================================
   4️⃣ Core-System laden
========================================================== */
require_once BASE_PATH . "/system/config.inc.php";
require_once BASE_PATH . "/system/settings.php";
require_once BASE_PATH . "/system/functions.php";
require_once BASE_PATH . "/system/themes.php";

/* 🔥 ZENTRALE Sprachinitialisierung */
require_once BASE_PATH . "/system/init_language.php";

/* Weitere Core-Klassen */
require_once BASE_PATH . "/system/classes/Template.php";
require_once BASE_PATH . "/system/classes/TextFormatter.php";
require_once BASE_PATH . "/system/classes/SeoUrlHandler.php";
require_once BASE_PATH . "/system/session_update.php";
require_once BASE_PATH . "/system/visitor_log_statistic.php";
require_once BASE_PATH . "/system/classes/PluginManager.php";

/* ==========================================================
   5️⃣ Routing (JETZT!)
========================================================== */
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments   = explode('/', $requestUri);

/* site bestimmen */
$site = $_GET['site'] ?? ($segments[1] ?? 'index');
$site = preg_replace('/[^a-zA-Z0-9_-]/', '', $site);
$_GET['site'] = $site;

/* optionale Parameter */
$action = $_GET['action'] ?? ($segments[2] ?? null);
$id     = $_GET['id']     ?? ($segments[3] ?? null);
$page   = $_GET['page']   ?? null;

if ($action) $_GET['action'] = $action;
if ($id)     $_GET['id']     = $id;
if ($page)   $_GET['page']   = $page;

/* ==========================================================
   6️⃣ Sprachmodul JETZT korrekt laden
========================================================== */
global $languageService;

/* Aktives Modul für AutoLoad setzen */
$GLOBALS['nx_active_module'] = $site;

/* 🔥 Modul automatisch laden */
$languageService->autoLoadActiveModule(false);

/* ==========================================================
   7️⃣ Template & Theme vorbereiten
========================================================== */
$tpl = new Template();
Template::setInstance($tpl);

$theme = new Theme();
$tpl->themes_path   = rtrim($theme->get_active_theme(), '/\\') . DIRECTORY_SEPARATOR;
$tpl->template_path = "templates" . DIRECTORY_SEPARATOR;

/* ==========================================================
   8️⃣ Konstanten
========================================================== */
define("MODULE", BASE_PATH . "/includes/modules/");
define("PLUGIN", BASE_PATH . "/includes/plugins/");

$theme_css = headfiles("css", $tpl->themes_path);
$theme_js  = headfiles("js",  $tpl->themes_path);

/* ==========================================================
   Komponenten JS/CSS bauen
========================================================== */

$components_css = "";
$components_js  = "";

if (!empty($components['css']) && is_array($components['css'])) {
    foreach ($components['css'] as $component) {
        $components_css .= '<link rel="stylesheet" href="' .
            htmlspecialchars($component, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
}

if (!empty($components['js']) && is_array($components['js'])) {
    foreach ($components['js'] as $component) {
        $components_js .= '<script src="' .
            htmlspecialchars($component, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }
}

/* ==========================================================
   9️⃣ Theme laden
========================================================== */
$themeFile = BASE_PATH . '/' . $tpl->themes_path . 'index.php';

if (file_exists($themeFile)) {
    require $themeFile;
} else {
    die("Theme-Datei nicht gefunden: " . $themeFile);
}
