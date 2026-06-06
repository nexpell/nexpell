<?php
// ==========================================================
// Session starten (für Sprache, Login, Theme-Toggle etc.)
// ==========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================================
// Sprache per ?setlang=xx wechseln → in Session speichern
// ==========================================================

use webspell\LanguageService;
use nexpell\SeoUrlHandler;
use nexpell\PluginManager;

// ==========================================================
// SEO-URL Routing (schöne URLs wie /news/123 auflösen)
// ==========================================================
SeoUrlHandler::route();

// ==========================================================
// PluginManager initialisieren (Seiten + Widgets laden)
// ==========================================================
$pluginManager = new PluginManager($_database);
$currentSite = $_GET['site'] ?? 'start';

// ==========================================================
// Sprache erneut für Redirect-Variante ?setlang setzen
// (Erhalt der URL-Parameter ohne setlang)
// ==========================================================




// Aktuelle Seite für Widgets
$page = $_GET['site'] ?? 'index';
$page_escaped = mysqli_real_escape_string($GLOBALS['_database'], $page);

// ==========================================================
// HTML-THEME aus DB übernehmen
// auto = startet immer als light (wichtig – JS switcht später)
// ==========================================================
$settings = [];

$res = $_database->query("
    SELECT setting_key, setting_value
    FROM navigation_website_settings
");

while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$dbTheme = $settings['navbar_modus'] ?? 'auto';
$htmlTheme = ($dbTheme === 'auto') ? 'light' : $dbTheme;


// =========================================
// NX SETTINGS – GLOBAL INIT (SAFE)
// =========================================
if (!isset($GLOBALS['nx_settings'])) {

    global $_database;
    $GLOBALS['nx_settings'] = [];

    if (isset($_database)) {
        $res = $_database->query("
            SELECT setting_key, setting_value
            FROM navigation_website_settings
        ");

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $GLOBALS['nx_settings'][$row['setting_key']] = $row['setting_value'];
            }
        }
    }
}

// ==========================================================
// Widgets für die aktuelle Seite aus DB laden
// ==========================================================
$positions = [];
$res = safe_query("SELECT * FROM settings_widgets_positions WHERE page='$page_escaped' ORDER BY position, sort_order ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $positions[$row['position']][] = $row['widget_key'];
}

// Widgets rendern und nach Bereichen sortieren
$allPositions = ['top','undertop','left','maintop','mainbottom','right','bottom'];
$widgetsByPosition = [];
foreach ($allPositions as $position) {
    $widgetsByPosition[$position] = [];
    if (!empty($positions[$position])) {
        foreach ($positions[$position] as $widget_key) {
            $output = $pluginManager->renderWidget($widget_key);
            if (!empty(trim($output))) {
                $widgetsByPosition[$position][] = $output;
            }
        }
    }
}

// ==========================================================
// Aktives Website-Theme ermitteln (default, lux etc.)
// ==========================================================
$currentTheme = 'lux';
$theme_name = 'default';
$result = safe_query("SELECT * FROM settings_themes WHERE modulname='default'");
if ($row = mysqli_fetch_assoc($result)) {
    $currentTheme = $row['themename'] ?: 'lux';
}

// ==========================================================
// SEO-Metadaten der aktuellen Seite laden
// ==========================================================
require_once BASE_PATH.'/system/seo_meta_helper.php';
$meta = getSeoMeta($page);

// ==========================================================
// Plugin-CSS/JS für Startpage / Plugineinbindung
// ==========================================================

$site = detectSite();

$plugin = detectPluginForSite($site);
if ($plugin) {
    $pluginManager->loadPluginAssets($site);
} else {
    registerModuleAssets($site);
}

// ==========================================================
// Plugin-CSS/JS für späteren <head> Ausgaben vorbereiten
// ==========================================================
$pluginFile = $pluginManager->loadPluginPage($currentSite);
if ($pluginFile) {
    $pluginName = basename($pluginFile, '.php');
    $pluginManager->loadPluginAssets($pluginName);
}

$plugin_css = $pluginManager->cssOutput;
$plugin_js  = $pluginManager->jsOutput;

// ==========================================================
// Live-Visitor Statistik aktualisieren
// zählt Seitenbesuche, Online-Zeit etc.
// ==========================================================
live_visitor_track($currentSite);
