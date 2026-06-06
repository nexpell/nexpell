<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
use nexpell\LanguageService;

global $languageService, $_database, $tpl;

$lang = $languageService->detectLanguage();
$languageService->readModule('static');

$staticID = isset($_GET['staticID']) ? (int)$_GET['staticID'] : 0;

/* =========================================================
   BASIS-DATENSATZ (NUR META)
========================================================= */
$ds = mysqli_fetch_array(
    safe_query("SELECT * FROM settings_static WHERE staticID = '$staticID'")
);

if (!$ds) {
    echo '<div class="alert alert-warning">Der angeforderte Inhalt wurde nicht gefunden.</div>';
    return;
}

/* =========================================================
   ZUGRIFFSRECHTE PRÜFEN
========================================================= */
$accessGranted = AccessControl::canViewStatic($ds);

/* =========================================================
   TITEL AUS settings_content_lang
========================================================= */
$title = '';
$safeLang = mysqli_real_escape_string($_database, $lang);

$resTitle = safe_query("
    SELECT content, language
    FROM settings_content_lang
    WHERE content_key = 'static_title_$staticID'
      AND language IN ('$safeLang', 'de')
    ORDER BY FIELD(language, '$safeLang', 'de')
    LIMIT 1
");

if ($row = mysqli_fetch_assoc($resTitle)) {
    $title = $row['content'];
}

/* =========================================================
   HEADER RENDERN
========================================================= */
$config = mysqli_fetch_array(
    safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id = 1")
);
$class = htmlspecialchars($config['selected_style'] ?? '');

echo $tpl->loadTemplate(
    "static",
    "head",
    [
        'class'    => $class,
        'title'    => $title,
        'subtitle' => $title
    ],
    'theme'
);

/* =========================================================
   CONTENT AUS settings_content_lang + RECHTE
========================================================= */
if ($accessGranted) {

    $content = '';

    $resContent = safe_query("
        SELECT content, language
        FROM settings_content_lang
        WHERE content_key = 'static_$staticID'
          AND language IN ('$safeLang', 'de')
        ORDER BY FIELD(language, '$safeLang', 'de')
        LIMIT 1
    ");

    if ($row = mysqli_fetch_assoc($resContent)) {
        $content = $row['content'];
    }

    echo $tpl->loadTemplate(
        "static",
        "content",
        ['content' => $content],
        'theme'
    );

} else {

    echo '<div class="container mt-4">
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-lock-fill me-2"></i> ' . $languageService->get('no_access') . '
            </div>
          </div>';
}
