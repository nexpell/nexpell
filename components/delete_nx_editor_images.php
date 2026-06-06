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

$rawUrls = $_POST['urls'] ?? [];
if (!is_array($rawUrls)) {
    $rawUrls = [$rawUrls];
}

$baseDir = realpath(dirname(__DIR__) . '/images/uploads/nx_editor');
if ($baseDir === false) {
    echo json_encode(['success' => true, 'deleted' => 0]);
    exit;
}

$deleted = 0;

foreach ($rawUrls as $url) {
    $url = (string)$url;
    if ($url === '' || !str_starts_with($url, '/images/uploads/nx_editor/')) {
        continue;
    }

    $filename = basename(rawurldecode($url));
    if ($filename === '' || $filename !== basename($filename)) {
        continue;
    }

    $path = $baseDir . DIRECTORY_SEPARATOR . $filename;
    $realPath = realpath($path);

    if ($realPath === false || !str_starts_with($realPath, $baseDir . DIRECTORY_SEPARATOR)) {
        continue;
    }

    if (is_file($realPath) && @unlink($realPath)) {
        $deleted++;
    }
}

echo json_encode(['success' => true, 'deleted' => $deleted]);
