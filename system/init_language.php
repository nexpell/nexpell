<?php

use nexpell\LanguageService;

global $_database;
global $languageService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
   1️⃣ Aktive Sprachen laden
========================================================== */
$availableLangs = [];

$res = $_database->query("
    SELECT iso_639_1 
    FROM settings_languages 
    WHERE active = 1
");

while ($row = $res->fetch_assoc()) {
    $availableLangs[] = $row['iso_639_1'];
}

if (empty($availableLangs)) {
    $availableLangs = ['en'];
}

/* ==========================================================
   2️⃣ Default-Sprache aus DB (Fallback)
========================================================== */
$defaultLang = 'en';

$resDefault = $_database->query("
    SELECT default_language 
    FROM settings 
    LIMIT 1
");

if ($resDefault && ($rowDefault = $resDefault->fetch_assoc())) {
    if (!empty($rowDefault['default_language'])) {
        $defaultLang = $rowDefault['default_language'];
    }
}

if (!in_array($defaultLang, $availableLangs, true)) {
    $defaultLang = $availableLangs[0];
}

/* ==========================================================
   3️⃣ Sprache aus GET oder SEO-URL übernehmen
========================================================== */

// URL-Segmente analysieren
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments   = explode('/', $requestUri);

// 1️⃣ GET hat höchste Priorität
if (
    isset($_GET['lang']) &&
    in_array($_GET['lang'], $availableLangs, true)
) {
    $_SESSION['language'] = $_GET['lang'];
}

// 2️⃣ SEO-URL prüfen (/en/forum)
elseif (
    isset($segments[0]) &&
    in_array($segments[0], $availableLangs, true)
) {
    $_SESSION['language'] = $segments[0];
}

/* ==========================================================
   4️⃣ Session validieren
========================================================== */
if (
    empty($_SESSION['language']) ||
    !in_array($_SESSION['language'], $availableLangs, true)
) {
    $_SESSION['language'] = $defaultLang;
}

$lang = $_SESSION['language'];

/* ==========================================================
   5️⃣ LanguageService initialisieren
========================================================== */
$languageService = new LanguageService($_database);
$languageService->setLanguage($lang);

/* ==========================================================
   6️⃣ Globale Template-Variable (optional)
========================================================== */
global $currentLang;
$currentLang = $lang;
