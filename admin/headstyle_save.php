<?php
require_once __DIR__ . '/../system/config.inc.php';

$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$_database->set_charset("utf8mb4");

if ($_database->connect_error) {
    echo "DB Error: " . $_database->connect_error;
    exit;
}

$style = $_POST['style'] ?? '';

if (!$style) {
    echo "No style submitted";
    exit;
}

$stmt = $_database->prepare("UPDATE settings_headstyle_config SET selected_style = ? WHERE id = 1");
$stmt->bind_param("s", $style);

if ($stmt->execute()) {
    echo "OK";
} else {
    echo "SQL Error: " . $stmt->error;
}

$stmt->close();
exit;
