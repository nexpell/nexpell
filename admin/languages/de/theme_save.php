<?php

require_once __DIR__ . '/../system/config.inc.php';

// DB-Verbindung
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    http_response_code(500);
    die("DB-Verbindungsfehler: " . $_database->connect_error);
}

// POST-Werte holen
$theme  = $_POST['theme']  ?? '';
$navbar = $_POST['navbar'] ?? '';

if ($theme === '') {
    http_response_code(400);
    echo "Fehlerhafte Eingabe: 'theme' fehlt oder ist leer.";
    exit;
}

// Standardwerte
$navbar_shadow = "";
$navbar_modus  = "light";

// Navbar-String (shadow|modus)
if ($navbar !== '') {
    $parts = explode('|', $navbar);
    if (count($parts) === 2) {
        $navbar_shadow = $parts[0];   // z.B. shadow-sm
        $navbar_modus  = $parts[1];   // z.B. light / dark / auto
    } else {
        http_response_code(400);
        echo "Ungültiges Format für 'navbar'.";
        exit;
    }
}

/* ============================================================
   1) settings_themes aktualisieren → nur Theme, kein Navbar!
============================================================ */

$stmt = $_database->prepare("
    UPDATE settings_themes 
    SET themename = ?
    WHERE modulname = 'default'
");

if ($stmt) {
    $stmt->bind_param("s", $theme);
    $stmt->execute();
    $stmt->close();
} else {
    http_response_code(500);
    echo "DB-Fehler beim Speichern des Themes.";
    exit;
}

/* ============================================================
   2) ⭐ NEU: navigation_website_settings updaten
============================================================ */

$settings = [
    'navbar_shadow' => $navbar_shadow,
    'navbar_modus'  => $navbar_modus,
];

foreach ($settings as $key => $value) {

    $stmt2 = $_database->prepare("
        INSERT INTO navigation_website_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    if ($stmt2) {
        $stmt2->bind_param("ss", $key, $value);
        $stmt2->execute();
        $stmt2->close();
    } else {
        http_response_code(500);
        echo "DB-Fehler beim Speichern von $key.";
        exit;
    }
}

echo "OK";
