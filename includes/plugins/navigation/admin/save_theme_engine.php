<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEBUG: direkt JSON-Header setzen
header('Content-Type: application/json; charset=utf-8');

// Konfigurationsdatei laden
$configPath = dirname(__FILE__, 5) . '/system/config.inc.php';

if (!file_exists($configPath)) {
    echo json_encode(["success" => false, "msg" => "config.inc.php nicht gefunden", "path" => $configPath]);
    exit;
}

require_once $configPath;

// DB-Verbindung testen
$_database = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($_database->connect_error) {
    echo json_encode(["success" => false, "msg" => "DB Fehler: " . $_database->connect_error]);
    exit;
}

// POST-Wert prüfen
if (!isset($_POST["value"])) {
    echo json_encode(["success" => false, "msg" => "Kein POST value erhalten", "POST" => $_POST]);
    exit;
}

$value = trim($_POST["value"]);

// Sicherheit: Nur 0,1,2 erlauben
if (!in_array($value, ["0", "1", "2"])) {
    echo json_encode(["success" => false, "msg" => "Ungültiger Wert: $value"]);
    exit;
}

// Eintrag speichern
$stmt = $_database->prepare("
    INSERT INTO navigation_website_settings (setting_key, setting_value)
    VALUES ('theme_engine_enabled', ?)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "msg" => "Prepare fehlgeschlagen",
        "mysqli_error" => $_database->error
    ]);
    exit;
}

$stmt->bind_param("s", $value);
$stmt->execute();
$stmt->close();

echo json_encode(["success" => true, "msg" => "Gespeichert", "value" => $value]);
