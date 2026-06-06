<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$requestToken = (string)($_POST['csrf_token'] ?? '');

if ($sessionToken !== '' && $requestToken !== '' && !hash_equals($sessionToken, $requestToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (
    !isset($_FILES['image']) ||
    !is_array($_FILES['image']) ||
    (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image uploaded']);
    exit;
}

$tmpName = (string)($_FILES['image']['tmp_name'] ?? '');
$fileSize = (int)($_FILES['image']['size'] ?? 0);

if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid upload']);
    exit;
}

if ($fileSize > 8 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
if ($finfo) {
    finfo_close($finfo);
}

$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

if (!isset($allowedMime[$mime])) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Unsupported image type']);
    exit;
}

$uploadDir = dirname(__DIR__) . '/images/uploads/nx_editor/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload directory unavailable']);
    exit;
}

$filename = 'nx_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedMime[$mime];
$target = $uploadDir . $filename;

if (!move_uploaded_file($tmpName, $target)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'url' => '/images/uploads/nx_editor/' . rawurlencode($filename),
]);
