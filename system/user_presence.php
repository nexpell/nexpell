<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'method_not_allowed']);
    exit;
}

require_once __DIR__ . '/config.inc.php';

$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'db_error']);
    exit;
}

$userID = (int)($_SESSION['userID'] ?? 0);
if ($userID <= 0) {
    echo json_encode(['status' => 'guest']);
    exit;
}

$action = strtolower(trim((string)($_POST['action'] ?? 'ping')));
$idleLimit = 300;

if ($action === 'offline') {
    $sql = "
        UPDATE users
        SET
            total_online_seconds = total_online_seconds + LEAST(
                GREATEST(TIMESTAMPDIFF(SECOND, COALESCE(last_activity, login_time, NOW()), NOW()), 0),
                ?
            ),
            is_online = 0,
            last_activity = NULL,
            login_time = NULL
        WHERE userID = ?
    ";
    $stmt = $_database->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'prepare_error']);
        exit;
    }

    $stmt->bind_param('ii', $idleLimit, $userID);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'offline']);
    exit;
}

$sql = "
    UPDATE users
    SET
        total_online_seconds = total_online_seconds + LEAST(
            GREATEST(TIMESTAMPDIFF(SECOND, COALESCE(last_activity, login_time, NOW()), NOW()), 0),
            ?
        ),
        last_activity = NOW(),
        is_online = 1
    WHERE userID = ?
";
$stmt = $_database->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'prepare_error']);
    exit;
}

$stmt->bind_param('ii', $idleLimit, $userID);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'ok']);
exit;
