<?php
use nexpell\SeoUrlHandler;

global $languageService;
global $currentLang;
global $hp_url;
global $default_language;

/* ==================================================
   1️⃣ Aktive Sprachen dynamisch holen
================================================== */
$languagesData = $languageService->getActiveLanguages();

$languages = [];
foreach ($languagesData as $row) {
    $languages[] = $row['iso_639_1'];
}

/* Default-Sprache aus Settings (Fallback: erste aktive / en) */
$defaultLang = strtolower((string)($default_language ?? 'en'));
if (!in_array($defaultLang, $languages, true)) {
    $defaultLang = $languages[0] ?? 'en';
}

/* ==================================================
   2️⃣ BASIS
================================================== */
$requestScheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$requestHost   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = trim((string)($hp_url ?? ''));

if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
    $baseUrl = $requestScheme . '://' . ltrim($baseUrl, '/');
}

if ($baseUrl === '') {
    $baseUrl = $requestScheme . '://' . $requestHost;
}

$baseParsed = parse_url($baseUrl);
$baseScheme = isset($baseParsed['scheme']) ? strtolower((string)$baseParsed['scheme']) : $requestScheme;
$baseHost   = (string)($baseParsed['host'] ?? $requestHost);
$basePort   = isset($baseParsed['port']) ? ':' . (int)$baseParsed['port'] : '';
$basePath   = trim((string)($baseParsed['path'] ?? ''), '/');

$scheme = $baseScheme . '://';
$host   = $baseHost . $basePort;
$baseRoot = rtrim($scheme . $host . ($basePath !== '' ? '/' . $basePath : ''), '/');

$activeLang = strtolower((string)($_GET['lang'] ?? $currentLang ?? $defaultLang));
if (!in_array($activeLang, $languages, true)) {
    $activeLang = $defaultLang;
}

/* ==================================================
   3️⃣ SEO-PFAD (ohne Sprache)
================================================== */
$seoPath = SeoUrlHandler::convertToSeoUrl(
    'index.php?' . http_build_query($_GET)
);

$seoPath = '/' . ltrim($seoPath, '/');

/* Sprache dynamisch aus URL entfernen */
if (!empty($languages)) {
    $langPattern = implode('|', array_map('preg_quote', $languages));
    $seoPath = preg_replace('#^/(' . $langPattern . ')(/|$)#', '/', $seoPath);
}

/* ==================================================
   4️⃣ CANONICAL
================================================== */
$canonical = $baseRoot . '/' . $activeLang . $seoPath;

/* ==================================================
   5️⃣ ROBOTS
================================================== */
$noindexParams = [
    'page','type','category','tag','q','search',
    'sort','filter','order','view'
];

$isPaginated = isset($_GET['page']) && (int)$_GET['page'] > 1;
$isFiltered  = false;

foreach ($_GET as $key => $value) {
    if (in_array($key, $noindexParams, true)) {
        $isFiltered = true;
        break;
    }
}

/* ==================================================
   6️⃣ ARTICLE ERKENNUNG
================================================== */
$isArticle = (
    ($_GET['site'] ?? '') === 'news'
    && (isset($_GET['slug']) || isset($_GET['id']))
);

/* ==================================================
   7️⃣ ROBOTS FINAL
================================================== */
$robots = ($isArticle)
    ? 'index, follow'
    : (($isPaginated || $isFiltered) ? 'noindex, follow' : 'index, follow');

/* ==================================================
   8️⃣ OG TYPE
================================================== */
$ogType = $isArticle ? 'article' : 'website';

/* ==================================================
   9️⃣ JSON-LD (WebSite, Article, BreadcrumbList)
================================================== */
$jsonLdGraphs = [];

$jsonLdGraphs[] = [
    '@context'   => 'https://schema.org',
    '@type'      => 'WebSite',
    'name'       => 'nexpell',
    'url'        => $baseRoot . '/',
    'inLanguage' => $activeLang,
];

if ($isArticle) {
    $article = [
        '@context'         => 'https://schema.org',
        '@type'            => 'Article',
        'headline'         => (string)($meta['title'] ?? ''),
        'description'      => (string)($meta['description'] ?? ''),
        'url'              => $canonical,
        'mainEntityOfPage' => $canonical,
        'inLanguage'       => $activeLang,
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => 'nexpell',
        ],
    ];

    if (!empty($meta['published_time'])) {
        $article['datePublished'] = (string)$meta['published_time'];
    }
    if (!empty($meta['modified_time'])) {
        $article['dateModified'] = (string)$meta['modified_time'];
    }

    $articleImage = $meta['image'] ?? ($baseRoot . '/includes/themes/' . $theme_name . '/images/og-image.jpg');
    if (!empty($articleImage)) {
        $article['image'] = [$articleImage];
    }

    $jsonLdGraphs[] = $article;
}

$breadcrumbs = [
    [
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => 'Home',
        'item'     => $baseRoot . '/' . $activeLang,
    ]
];

$breadcrumbPath = trim((string)$seoPath, '/');
if ($breadcrumbPath !== '') {
    $parts = explode('/', $breadcrumbPath);
    $accum = '';
    $pos = 2;
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $accum .= '/' . rawurlencode($part);
        $breadcrumbs[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => ucwords(str_replace('-', ' ', urldecode($part))),
            'item'     => $baseRoot . '/' . $activeLang . $accum,
        ];
    }
}

$jsonLdGraphs[] = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => $breadcrumbs,
];
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($activeLang, ENT_QUOTES) ?>"
      data-bs-theme="<?= $htmlTheme ?>"
      data-theme-db="<?= $dbTheme ?>">

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($meta['title'], ENT_QUOTES) ?></title>

<meta name="description"
      content="<?= htmlspecialchars($meta['description'], ENT_QUOTES) ?>">

<meta name="robots" content="<?= $robots ?>">
<meta name="googlebot" content="<?= $robots ?>">

<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>">

<!-- ===================== -->
<!-- HREFLANG -->
<!-- ===================== -->
<?php foreach ($languages as $lang): ?>
<link rel="alternate"
      hreflang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
      href="<?= htmlspecialchars($baseRoot . '/' . $lang . $seoPath, ENT_QUOTES) ?>">
<?php endforeach; ?>

<link rel="alternate"
      hreflang="x-default"
      href="<?= htmlspecialchars($baseRoot . '/' . $defaultLang . $seoPath, ENT_QUOTES) ?>">

<!-- ===================== -->
<!-- OPEN GRAPH -->
<!-- ===================== -->
<meta property="og:title"
      content="<?= htmlspecialchars($meta['title'], ENT_QUOTES) ?>">

<meta property="og:description"
      content="<?= htmlspecialchars($meta['description'], ENT_QUOTES) ?>">

<meta property="og:type" content="<?= $ogType ?>">

<meta property="og:url"
      content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>">

<meta property="og:image"
      content="<?= htmlspecialchars($baseRoot, ENT_QUOTES) ?>/includes/themes/<?= htmlspecialchars($theme_name, ENT_QUOTES) ?>/images/og-image.jpg">

<?php if ($isArticle): ?>
<meta property="article:author" content="nexpell.de">
<?php if (!empty($meta['published_time'])): ?>
<meta property="article:published_time"
      content="<?= htmlspecialchars($meta['published_time'], ENT_QUOTES) ?>">
<?php endif; ?>
<?php if (!empty($meta['modified_time'])): ?>
<meta property="article:modified_time"
      content="<?= htmlspecialchars($meta['modified_time'], ENT_QUOTES) ?>">
<?php endif; ?>
<?php endif; ?>

<?php foreach ($jsonLdGraphs as $graph): ?>
<script type="application/ld+json"><?= json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endforeach; ?>

<!-- ===================== -->
<!-- FAVICONS -->
<!-- ===================== -->
<link rel="icon" href="/includes/themes/default/images/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32"
      href="/includes/themes/default/images/favicon-32.png">
<link rel="icon" type="image/png" sizes="192x192"
      href="/includes/themes/default/images/favicon-192.png">
<link rel="apple-touch-icon" sizes="180x180"
      href="/includes/themes/default/images/favicon-180.png">

<!-- ===================== -->
<!-- CSS -->
<!-- ===================== -->
<base href="/">
<link rel="stylesheet"
      href="/includes/themes/<?= htmlspecialchars($theme_name, ENT_QUOTES) ?>/css/dist/<?= htmlspecialchars($currentTheme, ENT_QUOTES) ?>/bootstrap.min.css">

<?= $components_css ?? '' ?>
<?= $plugin_css ?? '' ?>
<?= $theme_css ?? '' ?>

</head>



<body class="<?= isset($_GET['builder']) && $_GET['builder']==='1' ? 'builder-active' : '' ?>">
<div class="d-flex flex-column sticky-footer-wrapper">
    <!-- === TOP Widgets === -->
    <?php if ($isBuilder || !empty($widgetsByPosition['top'])): ?>
        <div class="nx-live-zone nx-zone" data-nx-zone="top">
            <?php if (!empty($widgetsByPosition['top'])): ?>
                <?php foreach ($widgetsByPosition['top'] as $widget) echo $widget; ?>
            <?php elseif ($isBuilder): ?>
                <div class="builder-placeholder">[Leere Zone: top]</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?= $pluginManager->getNavigationModule(); ?>
    
