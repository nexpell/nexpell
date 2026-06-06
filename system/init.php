<?php
require_once __DIR__ . '/classes/TextFormatter.php';

global $_database;
// LanguageService einbinden (Namespace beachten!)
use nexpell\LanguageService;

// Instanz erzeugen und global verfügbar machen
global $languageService;
$languageService = new LanguageService($_database);

// Sprache ermitteln und ggf. in Session speichern
$lang = $languageService->detectLanguage();
$_SESSION['language'] = $lang;

// $lang global machen, falls du das möchtest
global $currentLanguage;
$currentLanguage = $lang;

