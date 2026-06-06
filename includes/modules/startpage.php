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
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

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

// DB-Abfrage
$ergebnis = safe_query("SELECT * FROM `settings_startpage`");
if (mysqli_num_rows($ergebnis)) {
    $ds = mysqli_fetch_array($ergebnis);

    $title = $ds['title'];

    // Übersetzung mit multiLanguage
    $translate = new multiLanguage($lang);
    $translate->detectLanguages($title);
    $title = $translate->getTextByLanguage($title);
    
    $startpage_text = $ds['startpage_text'];

    $translate->detectLanguages($startpage_text);
    $startpage_text = $translate->getTextByLanguage($startpage_text);

    $data_array = [
        'startpage_text' => $startpage_text,
    ];

    echo $tpl->loadTemplate("startpage", "content", $data_array, 'theme');

} else {
    echo generateAlert($languageService->get('no_startpage') ?? 'Keine Startseite vorhanden', 'alert-info');
}
