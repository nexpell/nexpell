<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();

global $hp_title;
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('privacy_policy'), // Titel der Datenschutzrichtlinie
    'subtitle' => 'Privacy policy',
    /*'myclanname' => $myclanname, // Clanname einfügen*/
    
    'privacy_policy' => $languageService->get('privacy_policy'),
];

// Template für den Kopfbereich laden
echo $tpl->loadTemplate("privacy_policy", "head", $data_array, 'theme');

// Datenschutzrichtlinie direkt abrufen (ersetzt Funktion getPrivacyPolicy)
$ergebnis = safe_query("SELECT * FROM settings_privacy_policy LIMIT 1");

if (mysqli_num_rows($ergebnis)) {
    $ds = mysqli_fetch_array($ergebnis);

    // Datenschutzrichtlinien-Text aus der Datenbank holen
    $privacy_policy_text = $ds['privacy_policy_text'];

    // Übersetzungen mit der multiLanguage-Klasse
    $translate = new multiLanguage($lang);
    $translate->detectLanguages($privacy_policy_text);
    $privacy_policy_text = $translate->getTextByLanguage($privacy_policy_text);

    // Datum der Datenschutzrichtlinie formatieren
    $timestamp = strtotime($ds['date']);
    $date = date('d.m.Y H:i:s', $timestamp);

    $data_array = [
        'page_title' => $hp_title,
        'privacy_policy_text' => $privacy_policy_text,
        'stand1' => $languageService->get('stand1'),
        'stand2' => $languageService->get('stand2'),
        'date' => $date,
    ];

    // Template für den Inhalt laden
    echo $tpl->loadTemplate("privacy_policy", "content", $data_array, 'theme');
} else {
    // Wenn keine Datenschutzrichtlinie vorhanden ist
    echo generateAlert($languageService->get('no_privacy_policy'), 'alert-info');
}
?>
