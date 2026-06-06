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
if (isset($_GET['setlang'])) {
    $lang = preg_replace('/[^a-z]/', '', $_GET['setlang']); // Sicherheitsfilter
    $_SESSION['language'] = $lang;

    if (isset($languageService)) {
        $languageService->setLanguage($lang);
    }

    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

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
if (isset($_GET['setlang'])) {

    $lang = strtolower(preg_replace('/[^a-z]/', '', $_GET['setlang']));
    $_SESSION['language'] = $lang;

    if (isset($languageService) && method_exists($languageService, 'setLanguage')) {
        $languageService->setLanguage($lang);
    }

    $params = $_GET;
    unset($params['setlang']);

    $target = $_SERVER['PHP_SELF'];
    if (!empty($params)) {
        $target .= '?' . http_build_query($params);
    }

    header("Location: $target", true, 302);
    exit;
}

// ==========================================================
// LanguageService initialisieren + aktive Sprache ermitteln
// ==========================================================
if (!isset($languageService)) {
    $languageService = new LanguageService($_database);
}

$currentLang = $_SESSION['language'] ?? $languageService->detectLanguage();
$languageService->setLanguage($currentLang);

// Alte Variable für Kompatibilität
$_language = $languageService;

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
// Hauptinhalt des Plugins laden (site=xyz)
// ==========================================================
if (!function_exists('get_mainContent')) {
    function get_mainContent(): string
    {
        global $pluginManager, $currentSite;

        $pluginFile = $pluginManager->loadPluginPage($currentSite);
        if ($pluginFile) {
            $pluginName = basename($pluginFile, '.php');

            // Plugin-CSS/JS registrieren, aber NICHT ausgeben
            $pluginManager->loadPluginAssets($pluginName);

            ob_start();
            include $pluginFile;
            return ob_get_clean();
        }

        return '';
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
