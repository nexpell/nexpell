<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService,$_database;

$lang = $languageService->detectLanguage();
$languageService->readModule('terms_of_service');

global $hp_title;
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

/* =====================================================
   HEAD TEMPLATE
===================================================== */
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('terms_of_service'),
    'subtitle' => 'Terms of Service',
    'terms_of_service' => $languageService->get('terms_of_service'),
];

echo $tpl->loadTemplate(
    "terms_of_service",
    "head",
    $data_array,
    'theme'
);

/* =====================================================
   NUTZUNGSBEDINGUNGEN LADEN (settings_content_lang)
===================================================== */

$content = '';
$contentKey = 'terms_of_service';

// 1. Wunsch-Sprache
$stmt = $_database->prepare("
    SELECT content, updated_at
    FROM settings_content_lang
    WHERE content_key = ? AND language = ?
    LIMIT 1
");
$stmt->bind_param('ss', $contentKey, $lang);
$stmt->execute();
$stmt->bind_result($content, $updated_at);
$stmt->fetch();
$stmt->close();

// 2. Fallback DE
if (empty($content) && $lang !== 'de') {
    $stmt = $_database->prepare("
        SELECT content, updated_at
        FROM settings_content_lang
        WHERE content_key = ? AND language = 'de'
        LIMIT 1
    ");
    $stmt->bind_param('s', $contentKey);
    $stmt->execute();
    $stmt->bind_result($content, $updated_at);
    $stmt->fetch();
    $stmt->close();
}

if (!empty($content)) {

    $timestamp = $updated_at ? strtotime($updated_at) : time();
    $date = date('d.m.Y H:i:s', $timestamp);

    $data_array = [
        'page_title' => $hp_title,
        'terms_of_service_text' => $content,
        'stand1' => $languageService->get('stand1'),
        'stand2' => $languageService->get('stand2'),
        'date'   => $date,
    ];

    echo $tpl->loadTemplate(
        "terms_of_service",
        "content",
        $data_array,
        'theme'
    );

} else {

    echo '
    <div class="alert alert-info">
        ' . htmlspecialchars($languageService->get('no_terms_of_service')) . '
    </div>';
}

