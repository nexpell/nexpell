<?php
declare(strict_types=1);

/**
 * Dynamische Sitemap (XML + hreflang), Domain aus settings.hpurl
 * - SITEMAP_EMIT=true  -> sendet XML + exit
 * - SITEMAP_EMIT=false -> gibt XML-String via return zurück
 */
if (!defined('SITEMAP_EMIT')) define('SITEMAP_EMIT', true);

// Fehler-/Output-Handling nur im Emit-Modus
error_reporting(E_ALL);
if (SITEMAP_EMIT) {
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
    if (!ob_get_level()) ob_start();
}

require_once __DIR__ . '/system/config.inc.php';

/* ----------------- Helpers (global für Provider) ----------------- */
function xmlEscape(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
function joinUrl(string $base, string ...$segments): string {
    $base = rtrim($base, "/");
    $parts = [];
    foreach ($segments as $seg) { if ($seg !== '' && $seg !== '/') $parts[] = trim($seg, "/"); }
    $tail = implode('/', $parts);
    return $tail === '' ? $base . '/' : $base . '/' . $tail;
}
/** Query in Kleinbuchstaben-Schlüssel parsen (für site=staticID etc.) */
function parseQueryLower(string $query): array {
    $tmp = []; parse_str($query, $tmp);
    $out = [];
    foreach ($tmp as $k => $v) $out[strtolower((string)$k)] = $v;
    return $out;
}
/** Y-m-d aus Kandidatenfeldern */
function pickDate(array $row, array $cands): string {
    foreach ($cands as $c) {
        if (!empty($row[$c])) {
            $ts = strtotime((string)$row[$c]);
            if ($ts !== false) return date('Y-m-d', $ts);
        }
    }
    return date('Y-m-d');
}

/**
 * Baut aus contentKey + Sprache eine finale URL (SEO / non-SEO).
 * contentKey: z.B. "static/staticid/11", "articles/slug", "wiki/page/3"
 * $queryBase: zusätzliche Query-Parameter (non-SEO).
 */
function sitemap_build_loc(
    string $contentKey,
    string $lang,
    string $BASE,
    bool $useSeoUrls,
    array $SLUG_MAP,
    array $queryBase = []
): string {
    // 1. Segment evtl. mappen (de/en/it)
    $parts = explode('/', $contentKey);
    if (!empty($parts[0]) && !empty($SLUG_MAP[$lang][$parts[0]])) {
        $parts[0] = $SLUG_MAP[$lang][$parts[0]];
    }
    $slugPath = implode('/', $parts);

    if ($useSeoUrls) {
        return joinUrl($BASE, $lang, $slugPath);
    }

    // non-SEO – site + erkannte IDs
    $q = $queryBase;
    $q['lang'] = $lang;
    if (empty($q['site']) && !empty($parts[0])) $q['site'] = $parts[0];

    // bekannte ID-Muster
    if (count($parts) >= 3 && $parts[1] === 'staticid'    && is_numeric($parts[2] ?? null)) $q['staticid']  = $parts[2];
    if (count($parts) >= 3 && $parts[0] === 'wiki'       && $parts[1] === 'page'      && is_numeric($parts[2] ?? null)) $q['page']      = $parts[2];
    if (count($parts) >= 3 && $parts[0] === 'articles'   && $parts[1] === 'articleid' && is_numeric($parts[2] ?? null)) $q['articleid'] = $parts[2];
    if (count($parts) >= 3 && $parts[0] === 'downloads'  && $parts[1] === 'downloadid'&& is_numeric($parts[2] ?? null)) $q['downloadid']= $parts[2];

    return joinUrl($BASE, 'index.php') . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Provider-Hilfs-API: registriert eine Seite für 1..n Sprachen.
 * $langsToBuild: [] = alle aktiven Sprachen
 */
function sitemap_register_page(
    array &$pages,
    string $contentKey,
    string $lastmod,
    array $langsToBuild,
    array $languages,
    string $BASE,
    bool $useSeoUrls,
    array $SLUG_MAP,
    array $queryBase = []
): void {
    if ($contentKey === '') return;

    if (!$langsToBuild) $langsToBuild = $languages;
    foreach ($langsToBuild as $lang) {
        $loc = sitemap_build_loc($contentKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP, $queryBase);
        if (!isset($pages[$contentKey])) $pages[$contentKey] = ['lastmods'=>[],'langs'=>[]];
        $pages[$contentKey]['langs'][$lang]    = $loc;
        $pages[$contentKey]['lastmods'][$lang] = $lastmod;
    }
}

/* ----------------- Konfiguration ----------------- */
$DENYLIST = ['search','counter','live_visitor','userlist','shoutbox']; // KEINE Detailseiten sperren
$SLUG_MAP = ['de'=>[], 'en'=>[], 'it'=>[]]; // optional Mapping fürs 1. Segment
$PREFERRED_LOC_LANG = 'de';

/* ----------------- DB + Settings ----------------- */
$_database = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    if (SITEMAP_EMIT) {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8', true, 500);
        echo "DB connection error"; exit;
    } else return '';
}

$languages = [];
if ($res = $_database->query("SELECT iso_639_1 FROM settings_languages WHERE active=1")) {
    while ($row = $res->fetch_assoc()) {
        $code = strtolower(trim((string)$row['iso_639_1']));
        if ($code !== '') $languages[] = $code;
    }
    $res->free();
}
$languages = array_values(array_unique($languages));
if (!$languages) $languages = ['de','en','it'];

$hpurl = ''; $useSeoUrls = false;
if ($res = $_database->query("SELECT hpurl, use_seo_urls FROM settings LIMIT 1")) {
    if ($row = $res->fetch_assoc()) {
        $hpurl = trim((string)($row['hpurl'] ?? ''));
        $useSeoUrls = (($row['use_seo_urls'] ?? '') === '1');
    }
    $res->free();
}
if ($hpurl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $hpurl = $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);
}
$BASE = rtrim($hpurl, "/");

/* ----------------- Seiten sammeln (via Provider) ----------------- */
$pages = [];  // [contentKey => ['lastmods'=>[lang=>Y-m-d], 'langs'=>[lang=>url]]]

// Provider-Kontext für die include-Dateien:
// ----------------- Seiten sammeln (via Provider) -----------------
$pages = [];
$GLOBALS['SITEMAP_CTX'] = [
    'db'         => $_database,
    'languages'  => $languages,
    'BASE'       => $BASE,
    'useSeoUrls' => $useSeoUrls,
    'SLUG_MAP'   => $SLUG_MAP,
    'DENYLIST'   => $DENYLIST,
];

$provDir = __DIR__ . '/system/sitemap_providers'; // <- bei dir so
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';
$providerStats = [];

if (is_dir($provDir)) {
    foreach (glob($provDir . '/*.php') as $providerFile) {
        $before = count($pages);
        $provider = require $providerFile;
        if (is_callable($provider)) {
            try {
                $provider($pages, $GLOBALS['SITEMAP_CTX']);
                $added = count($pages) - $before;
                $providerStats[basename($providerFile)] = $added;
                error_log("[sitemap] Provider " . basename($providerFile) . " added {$added} keys");
            } catch (Throwable $e) {
                $providerStats[basename($providerFile)] = "ERROR: " . $e->getMessage();
                error_log("[sitemap] Provider " . basename($providerFile) . " ERROR: " . $e->getMessage());
            }
        } else {
            $providerStats[basename($providerFile)] = "NO CALLABLE";
            error_log("[sitemap] Provider " . basename($providerFile) . " returned no callable");
        }
    }
} else {
    error_log("[sitemap] Provider dir missing: {$provDir}");
}

if ($debug) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== SITEMAP DEBUG ===\n";
    echo "Provider dir: {$provDir}\n\n";
    foreach ($providerStats as $name => $stat) {
        echo str_pad($name, 28) . " : " . $stat . "\n";
    }
    echo "\nTotal content keys: " . count($pages) . "\n";
    // optional: liste erste 50 Keys
    $i=0; foreach ($pages as $k => $_) { echo " - {$k}\n"; if (++$i>=50) break; }
    exit;
}


/* ----------------- XML bauen ----------------- */
ksort($pages);

$xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";

$prefLang = in_array($PREFERRED_LOC_LANG, $languages, true) ? $PREFERRED_LOC_LANG : ($languages[0] ?? 'de');

foreach ($pages as $contentKey => $data) {
    $lastmod = '1970-01-01';
    foreach ($data['lastmods'] as $d) if ($d > $lastmod) $lastmod = $d;

    $locLang = isset($data['langs'][$prefLang]) ? $prefLang : array_key_first($data['langs']);
    $locUrl  = $data['langs'][$locLang] ?? '';
    if ($locUrl === '') continue;

    $xml .= "  <url>\n";
    $xml .= "    <loc>" . xmlEscape($locUrl) . "</loc>\n";
    foreach ($data['langs'] as $lang => $href) {
        $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"" . xmlEscape($lang) . "\" href=\"" . xmlEscape($href) . "\"/>\n";
    }
    $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . xmlEscape(joinUrl($BASE)) . "\"/>\n";
    $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
    $xml .= "  </url>\n";
}
$xml .= "</urlset>";

$_database->close();

/* ----------------- Ausgabe/Rückgabe ----------------- */
if (SITEMAP_EMIT) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/xml; charset=utf-8');
    echo $xml; exit;
} else {
    return $xml;
}
