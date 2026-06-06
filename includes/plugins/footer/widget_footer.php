<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $languageService, $myclanname, $since, $_database;

$tpl = Template::getInstance();

// Ensure footer plugin language keys are loaded for this widget context.
if ($languageService instanceof LanguageService) {
    $languageService->readModule('footer');
}

// Schema-Compatibility: some installs still use old column names.
$footerCols = [];
try {
    $colsRes = $_database->query("SHOW COLUMNS FROM plugins_footer");
    if ($colsRes) {
        while ($c = mysqli_fetch_assoc($colsRes)) {
            $footerCols[] = (string)($c['Field'] ?? '');
        }
    }
} catch (\Throwable $e) {
    $footerCols = [];
}

$pickFooterCol = static function (array $candidates, string $fallback = '') use ($footerCols): string {
    foreach ($candidates as $c) {
        if (in_array($c, $footerCols, true)) {
            return $c;
        }
    }
    return $fallback;
};

$colRowType = $pickFooterCol(['row_type', 'type'], '');
$colCatKey  = $pickFooterCol(['category_key', 'cat_key'], '');
$colSecTit  = $pickFooterCol(['section_title', 'category_title', 'title'], '');
$colSecSort = $pickFooterCol(['section_sort', 'sort'], '');
$colLinkSort = $pickFooterCol(['link_sort', 'sort'], '');
$colName    = $pickFooterCol(['footer_link_name', 'link_name', 'name', 'title', 'value'], '');
$colUrl     = $pickFooterCol(['footer_link_url', 'link_url', 'url', 'link'], '');
$colNewTab  = $pickFooterCol(['new_tab', 'target_blank'], '');
$colId      = $pickFooterCol(['id'], '');

$currentLang = strtolower((string)($languageService->detectLanguage() ?: ($_SESSION['language'] ?? 'en')));
$footerLangEnabled = false;
try {
    $tblRes = $_database->query("SHOW TABLES LIKE 'plugins_footer_lang'");
    $footerLangEnabled = ($tblRes && $tblRes->num_rows > 0);
} catch (\Throwable $e) {
    $footerLangEnabled = false;
}
$footerLangCache = [];
$getFooterLang = function (string $contentKey, string $lang, string $fallback = '') use (&$footerLangCache, $footerLangEnabled, $_database): string {
    if (!$footerLangEnabled) {
        return $fallback;
    }
    $lang = strtolower($lang);
    $order = array_values(array_unique([$lang, 'en', 'gb', 'de', 'it']));
    $keyEsc = $_database->real_escape_string($contentKey);

    foreach ($order as $iso) {
        $cacheKey = $contentKey . '|' . $iso;
        if (array_key_exists($cacheKey, $footerLangCache)) {
            $txt = $footerLangCache[$cacheKey];
            if ($txt !== '') {
                return $txt;
            }
            continue;
        }
        $isoEsc = $_database->real_escape_string($iso);
        $res = $_database->query("
          SELECT content
          FROM plugins_footer_lang
          WHERE content_key='{$keyEsc}' AND language='{$isoEsc}'
          LIMIT 1
        ");
        $txt = '';
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $txt = trim((string)($row['content'] ?? ''));
        }
        $footerLangCache[$cacheKey] = $txt;
        if ($txt !== '') {
            return $txt;
        }
    }

    return $fallback;
};

// Footer-Template nach Auswahl setzen
$footerTemplate = 'standard';
if ($colName !== '' && $colRowType !== '') {
    $result = safe_query("
      SELECT `" . $colName . "` AS footer_link_name
      FROM plugins_footer
      WHERE `" . $colRowType . "` = 'footer_template'
      LIMIT 1
    ");
    if ($row = mysqli_fetch_assoc($result)) {
        $footerTemplate = trim((string)$row['footer_link_name']);
    }
}

// Whitelist + Fallback
$allowedTemplates = ['standard', 'simple', 'agency', 'modern'];
if (!in_array($footerTemplate, $allowedTemplates, true)) {
    $footerTemplate = 'standard';
}

$isSimpleFooter = ($footerTemplate === 'simple');
$maxSimpleLinks = 9;

$legalQuota  = 0;
$normalQuota = $maxSimpleLinks;
$legalCategoryKey = md5('Rechtliches');

$blocks = [];
$blockTitles = [];
$legal = [];
$legalTitle = '';

// Footer-Links: Links je Kategorie, wenn kein Link vorhanden -> Kategorie ausgeblendet
if (
    $colSecTit !== '' && $colSecSort !== '' && $colLinkSort !== '' &&
    $colUrl !== '' && $colRowType !== '' && $colCatKey !== ''
) {
    $orderId = ($colId !== '') ? "l.`" . $colId . "`" : "l.`" . $colLinkSort . "`";
    $newTabExpr = ($colNewTab !== '') ? "l.`" . $colNewTab . "`" : "0";
    $linkIdExpr = ($colId !== '') ? "l.`" . $colId . "`" : "0";
    $nameExpr = ($colName !== '') ? "l.`" . $colName . "`" : "''";

    $res = safe_query("
      SELECT
        c.`" . $colCatKey . "` AS category_key,
        c.`" . $colSecTit . "` AS section_title,
        c.`" . $colSecSort . "` AS section_sort,
        l.`" . $colLinkSort . "` AS link_sort,
        " . $linkIdExpr . " AS link_id,
        " . $nameExpr . " AS footer_link_name,
        l.`" . $colUrl . "` AS footer_link_url,
        " . $newTabExpr . " AS new_tab
      FROM plugins_footer l
      INNER JOIN plugins_footer c
        ON c.`" . $colRowType . "`='category'
       AND c.`" . $colCatKey . "` = l.`" . $colCatKey . "`
      WHERE l.`" . $colRowType . "`='link'
        AND l.`" . $colUrl . "`  <> ''
      ORDER BY c.`" . $colSecSort . "`, c.`" . $colSecTit . "`, l.`" . $colLinkSort . "`, " . $orderId . "
    ");

    while ($r = mysqli_fetch_assoc($res)) {
        $catKey = trim((string)($r['category_key'] ?? ''));
        $titleFallback = trim((string)$r['section_title']);
        $title = ($catKey !== '') ? $getFooterLang('cat_title_' . $catKey, $currentLang, $titleFallback) : $titleFallback;
        if ($title === '') { continue; }

        if ($catKey === $legalCategoryKey || $titleFallback === 'Rechtliches') {
            $legal[] = $r;
            if ($legalTitle === '') {
                $legalTitle = $title;
            }
        } else {
            $blockKey = (int)$r['section_sort'] . '|' . $catKey;
            $blocks[$blockKey][] = $r;
            if (!isset($blockTitles[$blockKey])) {
                $blockTitles[$blockKey] = $title;
            }
        }
    }
}

// Simple-Limit: Legal zÃ¤hlt immer zuerst, wird aber spÃ¤ter ausgegeben
if ($isSimpleFooter) {
    $legalValid = 0;
    if (!empty($legal)) {
        foreach ($legal as $lq) {
        $lqId = (int)($lq['link_id'] ?? 0);
        $nameFallback = trim((string)($lq['footer_link_name'] ?? ''));
        $name = ($lqId > 0) ? $getFooterLang('link_name_' . $lqId, $currentLang, $nameFallback) : $nameFallback;
        $url  = trim((string)($lq['footer_link_url'] ?? ''));
            if ($name === '' || $url === '') { continue; }
            $legalValid++;
        }
    }

    $legalQuota  = min($maxSimpleLinks, $legalValid);
    $normalQuota = $maxSimpleLinks - $legalQuota;
}

// Footer Links
$footerBlocksHtml = '';
$normalCount = 0;

foreach ($blocks as $k => $rows) {

    // Simple: keine weiteren Kategorien, wenn normales Budget aufgebraucht ist
    if ($isSimpleFooter && $normalCount >= $normalQuota) {
        break;
    }

    $parts = explode('|', $k, 2);
    $title = $blockTitles[$k] ?? 'Links';

    $blockHtml = '
      <div class="col-12 col-md-6 col-lg-4 col-xl-3 footer-nav-col">
        <div class="footer-nav-block footer-block">
          <h6 class="footer-title">' . htmlspecialchars($title, ENT_QUOTES) . '</h6>
          <ul class="footer-links footer-links-stack">
    ';

    $addedInBlock = 0;

    foreach ($rows as $l) {

        if ($isSimpleFooter && $normalCount >= $normalQuota) {
            break;
        }

        $linkId = (int)($l['link_id'] ?? 0);
        $nameFallback = trim((string)($l['footer_link_name'] ?? ''));
        $name = ($linkId > 0) ? $getFooterLang('link_name_' . $linkId, $currentLang, $nameFallback) : $nameFallback;
        $url  = trim((string)($l['footer_link_url'] ?? ''));
        if ($name === '' || $url === '') { continue; }

        $href = htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url), ENT_QUOTES);
        $txt  = htmlspecialchars($name, ENT_QUOTES);
        $attr = !empty($l['new_tab']) ? ' target="_blank" rel="noopener nofollow"' : ' rel="nofollow"';

        $blockHtml .= '<li class="footer-link-item"><a class="footer-link" href="'.$href.'"'.$attr.'>'.$txt.'</a></li>';

        $addedInBlock++;
        $normalCount++;
    }

    $blockHtml .= '
          </ul>
        </div>
      </div>
    ';

    if ($addedInBlock > 0) {
        $footerBlocksHtml .= $blockHtml;
    }
}

// Rechtliche Links
$legalBlockHtml = '';

if (!empty($legal) && $legalTitle !== '') {

    $legalAdded = 0;

    $legalBlockHtml = '
      <div class="footer-legal-block footer-links text-start">
        <div class="text-uppercase text-white-50 small fw-semibold mb-3">'
          . htmlspecialchars($legalTitle, ENT_QUOTES) .
        '</div>
        <ul class="footer-links footer-links-inline footer-links-legal">
    ';

    foreach ($legal as $l) {

        if ($isSimpleFooter && $legalAdded >= $legalQuota) {
            break;
        }

        $legalId = (int)($l['link_id'] ?? 0);
        $legalFallback = trim((string)($l['footer_link_name'] ?? ''));
        $name = ($legalId > 0) ? $getFooterLang('link_name_' . $legalId, $currentLang, $legalFallback) : $legalFallback;
        $url  = trim((string)$l['footer_link_url']);
        if ($name === '' || $url === '') { continue; }

        $href = htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url), ENT_QUOTES);
        $txt  = htmlspecialchars($name, ENT_QUOTES);
        $attr = !empty($l['new_tab'])
            ? ' target="_blank" rel="noopener nofollow"'
            : ' rel="nofollow"';

        $legalBlockHtml .=
            '<li class="footer-link-item"><a class="footer-link" href="'.$href.'"'.$attr.'>'.$txt.'</a></li>';

        $legalAdded++;
    }

    $legalBlockHtml .= '
        </ul>
      </div>
    ';

    if ($isSimpleFooter && $legalAdded === 0) {
    $legalBlockHtml = '';
}
}

$footer_logo_img = '';

// Logos (Light/Dark) aus Navigation-Settings holen
$settingsRes = safe_query("
  SELECT setting_key, setting_value
  FROM navigation_website_settings
  WHERE setting_key IN ('logo_light', 'logo_dark')
");

$navSettings = [];
while ($row = mysqli_fetch_assoc($settingsRes)) {
    $navSettings[$row['setting_key']] = trim($row['setting_value'] ?? '');
}

$logoLightFile = $navSettings['logo_light'] ?? '';
$logoDarkFile  = $navSettings['logo_dark']  ?? '';

$logoLightPath = '';
$logoDarkPath  = '';

if (!empty($logoLightFile) && $logoLightFile !== '-') {
    $tmp = '/includes/plugins/navigation/images/' . $logoLightFile;
    if (is_file($_SERVER['DOCUMENT_ROOT'] . $tmp)) {
        $logoLightPath = $tmp;
    }
}

if (!empty($logoDarkFile) && $logoDarkFile !== '-') {
    $tmp = '/includes/plugins/navigation/images/' . $logoDarkFile;
    if (is_file($_SERVER['DOCUMENT_ROOT'] . $tmp)) {
        $logoDarkPath = $tmp;
    }
}

// Fallbacks: wenn nur eins existiert, dieses als "light" benutzen
if ($logoLightPath === '' && $logoDarkPath !== '') {
    $logoLightPath = $logoDarkPath;
}
if ($logoDarkPath === '' && $logoLightPath !== '') {
    $logoDarkPath = $logoLightPath;
}

if ($logoLightPath !== '' || $logoDarkPath !== '') {

    $alt = htmlspecialchars($myclanname, ENT_QUOTES);

    // Beide ausgeben â†’ CSS entscheidet anhand :root[data-bs-theme], welches sichtbar ist
    $footer_logo_img =
        '<img class="footer-logo footer-logo-light" src="' . htmlspecialchars($logoLightPath, ENT_QUOTES) . '" alt="' . $alt . '">' .
        '<img class="footer-logo footer-logo-dark"  src="' . htmlspecialchars($logoDarkPath,  ENT_QUOTES) . '" alt="' . $alt . '">';
}

// Social Media
$social_block = '';
$social_icons = '';

// Social-Media EintrÃ¤ge aus DB holen
$socialRes = safe_query("SELECT * FROM settings_social_media WHERE socialID = 1 LIMIT 1");
$socialRow = mysqli_fetch_assoc($socialRes) ?: [];

$platforms = [
  'twitch',
  'facebook',
  'twitter',
  'youtube',
  'rss',
  'vine',
  'flickr',
  'linkedin',
  'instagram',
  'discord',
  'steam',
];

// Ausgabe in Social-Media-Icons
$iconMap = [
  'facebook'  => 'bi-facebook',
  'twitter'   => 'bi-twitter-x',
  'youtube'   => 'bi-youtube',
  'linkedin'  => 'bi-linkedin',
  'instagram' => 'bi-instagram',
  'discord'   => 'bi-discord',
  'twitch'    => 'bi-twitch',
  'rss'       => 'bi-rss',
  // vine/flickr/steam: je nach Bootstrap-Icons Version ggf. nicht vorhanden -> fallback
  'vine'      => 'bi-link-45deg',
  'flickr'    => 'bi-link-45deg',
  'steam'     => 'bi-link-45deg',
];

foreach ($platforms as $p) {
    $val = trim($socialRow[$p] ?? '');

    if ($val === '' || $val === '-') {
        continue;
    }

    $icon = $iconMap[$p] ?? 'bi-link-45deg';
    $href = htmlspecialchars($val, ENT_QUOTES);
    $label = htmlspecialchars(ucfirst($p), ENT_QUOTES);

    $social_icons .= '<a class="footer-social-chip" '
        . 'href="' . $href . '" target="_blank" rel="noopener nofollow" aria-label="' . $label . '">'
        . '<i class="bi ' . $icon . '"></i>'
        . '</a>';
}

if ($social_icons !== '') {
    $social_block = '
      <div class="footer-social-block">
        <h6 class="text-uppercase text-white-50 small fw-semibold mb-3">' . $languageService->get('title_social_follow') . '</h6>
        <div class="footer-social-icons">' . $social_icons . '</div>
      </div>
    ';
}

// Footer-Text
$footerText = '';
if ($colName !== '' && $colSecTit !== '' && $colRowType !== '') {
    $ftRes = safe_query("
      SELECT `" . $colName . "` AS footer_link_name
      FROM plugins_footer
      WHERE `" . $colSecTit . "`='footer_description'
        AND (`" . $colRowType . "`='footer_text' OR `" . $colRowType . "`='')
      LIMIT 1
    ");

if ($ftRes && ($ftRow = mysqli_fetch_assoc($ftRes))) {
    $footerText = trim((string)($ftRow['footer_link_name'] ?? ''));
}

$footerText = $getFooterLang('footer_text', $currentLang, $footerText);
}

if ($footerText === '') {
    $footerText = $languageService->get(
        'footer_default_text'
    );
}

$footerText = htmlspecialchars($footerText, ENT_QUOTES);

// Template-Daten
$data_array = [
    'myclanname'        => $myclanname ?? '',
    'date'              => date("Y"),
    'since'             => $since ?? '',
    'social_block'      => $social_block ?? '',
    'footer_logo_img'   => $footer_logo_img ?? '',
    'footer_blocks'     => $footerBlocksHtml ?? '',
    'legal_block'       => $legalBlockHtml ?? '',
    'footer_text'       => $footerText ?? '',
];

// Rendern
echo $tpl->loadTemplate("footer_" . $footerTemplate, "content", $data_array, 'plugin', 'footer');
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
  const backToTopBtn = document.getElementById("back-to-top");
  if (backToTopBtn) {
    backToTopBtn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }
});
</script>';
