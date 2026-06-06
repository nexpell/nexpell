<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// ==================================================
// SESSION
// ==================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================================================
// PFADE
// ==================================================
define('BASE_PATH', __DIR__ . '/../');
define('SYSTEM_PATH', BASE_PATH . 'system/');

// ==================================================
// CORE (LÄDT LanguageService + AccessControl BEREITS)
// ==================================================
require SYSTEM_PATH . 'config.inc.php';
require SYSTEM_PATH . 'settings.php';
require SYSTEM_PATH . 'functions.php';
require SYSTEM_PATH . 'multi_language.php';

// ==================================================
// NAMESPACES (KLASSEN SIND SCHON GELADEN)
// ==================================================
use nexpell\LoginSecurity;
use nexpell\LanguageService;
use nexpell\AccessControl;

// ==================================================
// SPRACHE
// ==================================================
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'de';
}

global $_database, $languageService;
$languageService = new LanguageService($_database);
$languageService->readModule('login', true);

// ==================================================
// BEREITS EINGELOGGT?
// ==================================================
$userID = (int)($_SESSION['userID'] ?? 0);

if ($userID > 0) {

    // ✅ Admin → direkt ins Admincenter
    if (AccessControl::canAccessAdmin($_database, $userID)) {
        header('Location: admincenter.php');
        exit;
    }

    // ❌ Eingeloggt, aber kein Admin
    http_response_code(403);
    echo '
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . htmlspecialchars($languageService->get('access-denied-title'), ENT_QUOTES, 'UTF-8') . '</title>
        <link href="/admin/css/bootstrap.min.css" rel="stylesheet">
        <link href="/admin/css/page.css" rel="stylesheet">
    </head>
    <body>

    <div class="login-page">
        <div class="login-wrap">
            <div class="card login-card">
                <div class="login-card-header access">
                    <div class="brand">
                        <img src="/admin/images/logo.png" alt="Logo">
                    </div>
                    <h4 class="mb-1">
                        ' . $languageService->get('access-denied-title') . '
                    </h4>
                </div>

                <div class="card-body text-center">
                    <p class="py-4">
                        ' . $languageService->get('access-denied-desc') . '
                    </p>

                    <p class="text-muted small">
                        ' . $languageService->get('error_support_admin') . '
                    </p>

                    <div class="d-grid">
                        <a href="/" class="btn btn-secondary mt-2">
                            ' . $languageService->get('back_to_website') . '
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </body>
    </html>';
    exit;
}

// ==================================================
// LOGIN
// ==================================================
$ip      = $_SERVER['REMOTE_ADDR'] ?? '';
$message = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = $languageService->get('error_invalid_email');
        goto render;
    }

    $loginResult = LoginSecurity::verifyLogin($email, $password, $ip, null, null);

    if (!$loginResult['success']) {
        $message = $loginResult['error'] ?? $languageService->get('error_invalid_login');
        goto render;
    }

    $stmt = $_database->prepare("
        SELECT userID, username, email, is_locked
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !empty($user['is_locked'])) {
        $message = $languageService->get('error_account_locked');
        goto render;
    }

    // Session setzen
/*    $_SESSION['userID']   = (int)$user['userID'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];

    // 🔐 ADMIN-RECHT PRÜFEN
    if (!AccessControl::canAccessAdmin($_database, $_SESSION['userID'])) {

        session_unset();
        session_destroy();
        session_start();

        $message = 
            $languageService->get('access-denied-desc') . '<br><small>' .
            $languageService->get('error_support_admin') .
            '</small>';
        goto render;
    }*/

    // Session setzen
$_SESSION['userID']   = (int)$user['userID'];
$_SESSION['username'] = $user['username'];
$_SESSION['email']    = $user['email'];

LoginSecurity::saveSession($user['userID']);

header('Location: admincenter.php');
exit;

    LoginSecurity::saveSession($user['userID']);

    $now = date('Y-m-d H:i:s');
    $upd = $_database->prepare("
        UPDATE users SET lastlogin = ? WHERE userID = ?
    ");
    $upd->bind_param('si', $now, $user['userID']);
    $upd->execute();
    $upd->close();

    header('Location: admincenter.php');
    exit;
}

render:
?>
<!DOCTYPE html>
<html lang="<?= $languageService->language ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="nexpell CMS Admin Login">
    <title>nexpell - Admin Login</title>

    <link href="/admin/css/bootstrap.min.css" rel="stylesheet">
    <link href="/admin/css/page.css" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-wrap">

        <div class="card login-card">

            <div class="login-card-header">
                <div class="brand">
                    <img src="/admin/images/logo.png" alt="Logo">
                </div>

                <h4 class="mb-1">
                    <?= $languageService->get('login'); ?>
                </h4>

                <div class="text-muted-small">
                    <?= $languageService->get('login_info'); ?>
                </div>
            </div>

            <div class="card-body">

                <?php if (!empty($message)) : ?>
                    <div class="alert alert-danger text-center" role="alert">
                        <?= $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>

                    <div class="mb-3">
                        <label class="form-label" for="email">
                            <?= $languageService->get('email_address'); ?>
                        </label>
                        <input
                            id="email"
                            class="form-control"
                            name="email"
                            type="email"
                            placeholder="name@example.com"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">
                            <?= $languageService->get('password'); ?>
                        </label>
                        <input
                            id="password"
                            class="form-control"
                            name="password"
                            type="password"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="d-grid">
                        <button
                            type="submit"
                            name="submit"
                            value="1"
                            class="btn btn-primary mt-3"
                        >
                            <?= $languageService->get('login'); ?>
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>
</body>
</html>
