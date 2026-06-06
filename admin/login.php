<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LoginSecurity;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

global $_database;

// Absolute Pfade definieren (anpassen falls nötig)
define('BASE_PATH', __DIR__ . '/../');
define('SYSTEM_PATH', BASE_PATH . 'system/');

// Wichtige Systemdateien einbinden
include SYSTEM_PATH . 'config.inc.php';
include SYSTEM_PATH . 'settings.php';
include SYSTEM_PATH . 'functions.php';
include SYSTEM_PATH . 'plugin.php';
include SYSTEM_PATH . 'widget.php';
include SYSTEM_PATH . 'multi_language.php';

// Sprachmodul laden
use nexpell\LanguageService;

// Sprachauswahl setzen (falls noch nicht)
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'de';
}

// Objekt erstellen (ggf. $database übergeben)
$languageService = new LanguageService($_database);
$languageService->readModule('login', true);

$ip = $_SERVER['REMOTE_ADDR'];
$message = '';
$isIpBanned = false;
$email = '';

// Login-Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password_hash = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "❌ Ungültige E-Mail-Adresse.";
        header("Location: login.php");
        exit;
    }
    $is_active = isset($is_active) ? $is_active : null; // oder ein Standardwert, je nach Bedarf
    $is_locked = isset($is_locked) ? $is_locked : null; // oder ein Standardwert

    $loginResult = LoginSecurity::verifyLogin($email, $password_hash, $ip, $is_active, $is_locked);

    if ($loginResult['success']) {
        if (LoginSecurity::isIpBanned($ip)) {
            $message = 'Diese IP-Adresse wurde gesperrt. Bitte kontaktiere den Support.';
            $isIpBanned = true;
        }

        $stmt = $_database->prepare("SELECT userID, username, email, is_locked FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            if (!empty($user['is_locked']) && (int)$user['is_locked'] === 1) {
                $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_account_locked') . '</div>';
                $isIpBanned = true;
            } else {
                $_SESSION['userID'] = (int)$user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];

                // Rolle auslesen (Beispiel mit Tabelle user_role_assignments)
                $stmtRole = $_database->prepare("SELECT roleID FROM user_role_assignments WHERE userID = ? LIMIT 1");
                $stmtRole->bind_param("i", $user['userID']);
                $stmtRole->execute();
                $resultRole = $stmtRole->get_result();
                if ($resultRole && $rowRole = $resultRole->fetch_assoc()) {
                    $_SESSION['roleID'] = (int)$rowRole['roleID'];
                } else {
                    // Keine Rolle gefunden, evtl. default Rolle setzen oder null
                    $_SESSION['roleID'] = null;
                }

                LoginSecurity::saveSession($user['userID']);

                $now = date('Y-m-d H:i:s');
                $updateStmt = $_database->prepare("UPDATE users SET lastlogin = ? WHERE userID = ?");
                $updateStmt->bind_param("si", $now, $user['userID']);
                $updateStmt->execute();

                $_SESSION['success_message'] = "Login erfolgreich!";
                header("Location: admincenter.php");
                exit;
            }
        } else {
            $message = 'Benutzer nicht gefunden oder falsche E-Mail-Adresse.';
        }
    } else {
        $stmt = $_database->prepare("SELECT userID, is_active FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $userID = (int)$row['userID'];

            if ((int)$row['is_active'] === 0) {
                $message = 'Dein Konto wurde noch nicht aktiviert. Bitte überprüfe deine E-Mail.';
                $isIpBanned = true;
            } else {
                if (!LoginSecurity::isEmailOrIpBanned($email, $ip)) {
                    LoginSecurity::trackFailedLogin($userID, $email, $ip);
                    $failCount = LoginSecurity::getFailCount($ip, $email);

                    if ($failCount >= 5) {
                        LoginSecurity::banIp($ip, $userID, "Zu viele Fehlversuche", $email);
                        $_SESSION['error_message'] = "Zu viele Fehlversuche – Deine IP wurde gesperrt.";
                    } else {
                        $_SESSION['error_message'] = "Falsche E-Mail oder Passwort. Versuche: $failCount / 5";
                    }
                } else {
                    $message = 'Diese E-Mail-Adresse oder IP wurde gesperrt. Bitte kontaktiere den Support.';
                    $isIpBanned = true;
                }
            }
        } else {
            $message = 'Benutzer nicht gefunden oder falsche E-Mail.';
            $isIpBanned = true;
        }
    }

    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
    }
}

// Letzte Prüfung auf gebannte E-Mail
if (!empty($email) && LoginSecurity::isEmailBanned($ip, $email)) {
    $message = 'Diese E-Mail-Adresse wurde gesperrt. Bitte kontaktiere den Support.';
    $isIpBanned = true;
}

?>

<!DOCTYPE html>
<html lang="<?= $languageService->language ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="nexpell CMS Admin Login">
    <title>nexpell - Admin Login</title>

    <link href="/admin/css/bootstrap.min.css" rel="stylesheet">
    <link href="/admin/css/style.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid">
  <div class="row no-gutter">
    <div class="d-none d-md-flex col-md-4 col-lg-6 bg-image position-relative">
        <!-- Overlay für Nebel -->
        <div class="fog-overlay position-absolute top-0 start-0 w-100 h-100"></div>

        <div class="logo position-relative z-1">
            <img class="mw-100 mh-100" src="/admin/images/logo.png" alt="Logo">
            <p class="text1">ne<span>x</span>pell – Dein System. Deine Kontrolle.</p>
        </div>
    </div>
    <div class="col-md-8 col-lg-6 no-bg">
      <div class="login d-flex align-items-center py-5">
        <div class="container">
          <div class="row">
            <div class="col-md-9 col-lg-8 mx-auto">
                <h2 class="login-heading mb-4"><span><?= $languageService->module['login'] ?></span></h2>
                <div>
                    <h5><?= $languageService->module['dashboard'] ?></h5><br />
                    <div class="alert alert-info">
                        <?= $languageService->module['welcome2'] ?> Login<br><br>
                        <?= $languageService->module['insertmail'] ?>
                    </div>
                    <?php if ($closed === 1): ?>
                        <div class="alert alert-warning">
                            Die Seite ist derzeit gesperrt. Nur Administratoren können sich anmelden.
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email"><?= $languageService->module['email_address'] ?></label>
                        <input class="form-control" name="email" type="email" placeholder="Email" required>
                    </div>

                    <div class="mb-3">
                        <label for="password">Passwort</label>
                        <input class="form-control" name="password" type="password" placeholder="Passwort" required>
                    </div>

                    <input type="submit" name="submit" value="<?= $languageService->module['login'] ?>" class="btn btn-primary btn-block">
                </form>

                <?php if (!empty($message)) : ?>
                    <div class="alert alert-danger mt-3"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>