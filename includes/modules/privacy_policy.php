<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService,$_database;

$lang = $languageService->detectLanguage();
$languageService->readModule('privacy_policy');

global $hp_title;
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->module['privacy_policy'], // Titel der Datenschutzrichtlinie
    'subtitle' => 'Privacy policy',
    /*'myclanname' => $myclanname, // Clanname einfÃ¼gen*/
    
    'privacy_policy' => $languageService->module['privacy_policy'],
];

// Template fÃ¼r den Kopfbereich laden
echo $tpl->loadTemplate("privacy_policy", "head", $data_array, 'theme');

/* =====================================================
   DATENSCHUTZERKLÃ„RUNG LADEN (settings_content_lang)
===================================================== */

$content = '';
$contentKey = 'privacy_policy';

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
        'privacy_policy_text' => $content,
        'stand1' => $languageService->module['stand1'],
        'stand2' => $languageService->module['stand2'],
        'date'   => $date,
    ];

    echo $tpl->loadTemplate(
        "privacy_policy",
        "content",
        $data_array,
        'theme'
    );

} else {

    $msg = $languageService->get('no_privacy_policy');
    if ($msg === '[no_privacy_policy]') {
        $msg = 'Keine Datenschutzerklaerung vorhanden.';
    }
    echo '<div class="alert alert-info">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
}


