<?php

#use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
#AccessControl::checkAdminAccess('ac_theme_save');

require_once __DIR__ . '/../system/config.inc.php';

// DB-Verbindung
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    http_response_code(500);
    die("DB-Verbindungsfehler: " . $_database->connect_error);
}

// POST-Werte holen
$theme = $_POST['theme'] ?? '';
$navbar = $_POST['navbar'] ?? '';

if ($theme === '') {
    http_response_code(400);
    echo "Fehlerhafte Eingabe: 'theme' fehlt oder ist leer.";
    exit;
}

// Standardwerte
$navbar_class = null;
$navbar_theme = null;

// Navbar-String verarbeiten
if ($navbar !== '') {
    $parts = explode('|', $navbar);
    if (count($parts) === 2) {
        $navbar_class = $parts[0];
        $navbar_theme = $parts[1];
    } else {
        http_response_code(400);
        echo "Ungültiges Format für 'navbar'.";
        exit;
    }
}

// Sonderregel: Lux + bg-primary → dark Theme
if ($theme === 'lux' && $navbar_class === 'bg-primary') {
    $navbar_theme = 'dark';
}
if ($theme === 'flatly' && $navbar_class === 'bg-primary') {
    $navbar_theme = 'light';
}

// Theme + Navbar speichern
$stmt = $_database->prepare("
    UPDATE settings_themes 
    SET themename = ?, navbar_class = ?, navbar_theme = ? 
    WHERE modulname = 'default'
");

if ($stmt) {
    $stmt->bind_param("sss", $theme, $navbar_class, $navbar_theme);
    $stmt->execute();
    $stmt->close();
    echo "OK";
} else {
    http_response_code(500);
    echo "Datenbankfehler beim Speichern.";
}
