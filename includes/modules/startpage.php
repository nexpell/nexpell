<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

// Standard setzen, wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $_database,$languageService;
$languageService = new LanguageService($_database);
$lang = $languageService->detectLanguage();

// Admin-Modul laden
$languageService->readModule('startpage', false);

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

$data_array = [
    'class' => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Start Page',
];

echo $tpl->loadTemplate("startpage", "head", $data_array, 'theme');

/* =====================================================
   STARTPAGE LADEN (settings_content_lang)
===================================================== */

$content = '';
$titleText = '';
$contentKeyText  = 'startpage';
$contentKeyTitle = 'startpage_title';

/* ---------- Titel ---------- */

// 1. Wunsch-Sprache
$stmt = $_database->prepare("
    SELECT content
    FROM settings_content_lang
    WHERE content_key = ? AND language = ?
    LIMIT 1
");
$stmt->bind_param('ss', $contentKeyTitle, $lang);
$stmt->execute();
$stmt->bind_result($titleText);
$stmt->fetch();
$stmt->close();

// 2. Fallback DE
if (empty($titleText) && $lang !== 'de') {
    $stmt = $_database->prepare("
        SELECT content
        FROM settings_content_lang
        WHERE content_key = ? AND language = 'de'
        LIMIT 1
    ");
    $stmt->bind_param('s', $contentKeyTitle);
    $stmt->execute();
    $stmt->bind_result($titleText);
    $stmt->fetch();
    $stmt->close();
}

/* ---------- Inhalt ---------- */

// 1. Wunsch-Sprache
$stmt = $_database->prepare("
    SELECT content
    FROM settings_content_lang
    WHERE content_key = ? AND language = ?
    LIMIT 1
");
$stmt->bind_param('ss', $contentKeyText, $lang);
$stmt->execute();
$stmt->bind_result($content);
$stmt->fetch();
$stmt->close();

// 2. Fallback DE
if (empty($content) && $lang !== 'de') {
    $stmt = $_database->prepare("
        SELECT content
        FROM settings_content_lang
        WHERE content_key = ? AND language = 'de'
        LIMIT 1
    ");
    $stmt->bind_param('s', $contentKeyText);
    $stmt->execute();
    $stmt->bind_result($content);
    $stmt->fetch();
    $stmt->close();
}

/* ---------- Ausgabe ---------- */

if (!empty($content)) {

    $data_array = [
        'startpage_title' => $titleText,
        'startpage_text'  => $content,
    ];

    echo $tpl->loadTemplate(
        "startpage",
        "content",
        $data_array,
        'theme'
    );

} else {

    echo generateAlert(
        $languageService->get('no_startpage') ?? 'Keine Startseite vorhanden.',
        'alert-info'
    );
}

