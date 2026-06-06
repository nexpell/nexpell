<?php

global $_database;

if (isset($_GET['new_lang'])) {
    $lang = preg_replace('/[^a-z]/', '', strtolower($_GET['new_lang']));
    $_SESSION['language'] = $lang;
} elseif (!isset($_SESSION['language'])) {
    $result = $_database->query("SELECT default_language FROM settings LIMIT 1");
    if ($result && $row = $result->fetch_assoc() && !empty($row['default_language'])) {
        $_SESSION['language'] = $row['default_language'];
    } else {
        $_SESSION['language'] = 'de';
    }
}



require_once __DIR__ . '/classes/LanguageService.php';

use nexpell\LanguageService;

global $languageService;
global $_database; // die bestehende mysqli-Verbindung aus dem globalen Scope

if (!isset($languageService) || !$languageService instanceof LanguageService) {
    // mysqli-Verbindung als erstes Argument übergeben
    $languageService = new LanguageService($_database);
    
    // Sprache setzen, z.B. 'de'
    $languageService->setLanguage('de');
}