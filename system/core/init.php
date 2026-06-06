<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;
use nexpell\SeoUrlHandler;
use nexpell\PluginManager;

// URL-Routing
SeoUrlHandler::route();

// PluginManager initialisieren
$pluginManager = new PluginManager($_database);
$currentSite = $_GET['site'] ?? 'start';

// Sprache aus Session laden oder Standard setzen
$currentLang = $_SESSION['language'] ?? 'de';
$_SESSION['language'] = $currentLang;

if (!isset($languageService)) {
    $languageService = new LanguageService($_database);
}
$languageService->setLanguage($currentLang);
$_language = $languageService;

// Aktuelle Seite für Widgets
$page = $_GET['site'] ?? 'index';
$page_escaped = mysqli_real_escape_string($GLOBALS['_database'], $page);

// Widgets laden
$positions = [];
$res = safe_query("SELECT * FROM settings_widgets_positions WHERE page='$page_escaped' ORDER BY position, sort_order ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $positions[$row['position']][] = $row['widget_key'];
}

// Widgets rendern
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

// Plugin nur im Main-Content rendern
if (!function_exists('get_mainContent')) {
    function get_mainContent(): string
    {
        global $pluginManager, $currentSite;

        $pluginFile = $pluginManager->loadPluginPage($currentSite);
        if ($pluginFile) {
            $pluginName = basename($pluginFile, '.php');

            // Plugin-Assets **registrieren**, aber noch nicht ausgeben
            $pluginManager->loadPluginAssets($pluginName);

            ob_start();
            include $pluginFile;
            return ob_get_clean();
        }

        return '';
    }
}

// Theme laden
$currentTheme = 'lux';
$theme_name = 'default';
$result = safe_query("SELECT * FROM settings_themes WHERE modulname='default'");
if ($row = mysqli_fetch_assoc($result)) {
    $currentTheme = $row['themename'] ?: 'lux';
}

// SEO/Meta laden
require_once BASE_PATH.'/system/seo_meta_helper.php';
$meta = getSeoMeta($page);

// CSS/JS im <head>
// Plugin im Main-Content registrieren, aber noch nicht rendern
$pluginFile = $pluginManager->loadPluginPage($currentSite);
if ($pluginFile) {
    $pluginName = basename($pluginFile, '.php');
    $pluginManager->loadPluginAssets($pluginName); // registriert CSS/JS
}

// CSS für <head> vorbereiten
$plugin_css = $pluginManager->cssOutput;
$plugin_js  = $pluginManager->jsOutput;

// Live-Visitor Tracking
live_visitor_track($currentSite);