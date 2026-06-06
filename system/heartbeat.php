<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfiguration laden
$configPath = __DIR__ . '/config.inc.php';
if (!file_exists($configPath)) {
    die("Fehler: Konfigurationsdatei nicht gefunden.");
}
require_once $configPath;

// DB-Verbindung
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    die("DB-Verbindung fehlgeschlagen: " . $_database->connect_error);
}

$timeout_minutes = 5;
$now = date('Y-m-d H:i:s');

// === 1. Eigene Aktivität nur aktualisieren ===
if (!empty($_SESSION['userID'])) {
    $userID = (int)$_SESSION['userID'];

    $stmt = $_database->prepare("
        UPDATE users
        SET last_activity = ?, is_online = 1
        WHERE userID = ?
    ");
    $stmt->bind_param("si", $now, $userID);
    $stmt->execute();
    $stmt->close();
}

// === 2. Alle inaktiven User offline setzen und deren Online-Zeit addieren ===
$stmt = $_database->prepare("
    SELECT userID, last_activity, total_online_seconds
    FROM users
    WHERE is_online = 1 AND last_activity < NOW() - INTERVAL ? MINUTE
");
$stmt->bind_param("i", $timeout_minutes);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $uid = (int)$row['userID'];
    $last = strtotime($row['last_activity']) ?: time();
    $total = (int)$row['total_online_seconds'];
    $total += max(0, time() - $last);

    $upd = $_database->prepare("
        UPDATE users
        SET is_online = 0,
            total_online_seconds = ?,
            last_activity = NULL,
            login_time = NULL
        WHERE userID = ?
    ");
    $upd->bind_param("ii", $total, $uid);
    $upd->execute();
    $upd->close();
}

$stmt->close();

header("Content-Type: application/json");
echo json_encode(["status" => "ok"]);
exit;
