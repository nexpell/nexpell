<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LoginSecurity;
use nexpell\SeoUrlHandler;
use nexpell\Email;

global $_database, $tpl, $languageService;

$languageService->readModule('reset_password');

$res = safe_query("SELECT * FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($res);

$hp_title    = $settings['hptitle'] ?? 'nexpell';
$hp_url      = $settings['hpurl'] ?? ('https://' . $_SERVER['HTTP_HOST']);
$admin_email = $settings['adminemail'] ?? ('info@' . $_SERVER['HTTP_HOST']);

/* =========================================================
   TOKEN PRÜFEN
========================================================= */
$token = $_GET['token'] ?? '';
$error = '';

if ($token === '') {
    $error = $languageService->get('invalid_link');
}

$reset = null;

if ($error === '') {
    $stmt = $_database->prepare("
        SELECT 
            pr.id            AS reset_id,
            pr.userID,
            u.email,
            u.password_pepper
        FROM password_resets pr
        JOIN users u ON u.userID = pr.userID
        WHERE pr.token = ?
          AND pr.used = 0
          AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reset) {
        $error = $languageService->get('invalid_or_expired');
    }
}

/* =========================================================
   FORM SUBMIT
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {

    $pw1 = $_POST['password'] ?? '';
    $pw2 = $_POST['password_repeat'] ?? '';

    if ($pw1 === '' || $pw2 === '') {
        $error = $languageService->get('error_required');
    } elseif ($pw1 !== $pw2) {
        $error = $languageService->get('password_mismatch');
    } elseif (strlen($pw1) < 8) {
        $error = $languageService->get('password_too_short');
    }

    if ($error === '') {

        $pepper = LoginSecurity::decryptPepper($reset['password_pepper']);
        if (!$pepper) {
            $error = $languageService->get('security_error');
        }
    }

    if ($error === '') {

        $hash = password_hash(
            $pw1 . $reset['email'] . $pepper,
            PASSWORD_BCRYPT
        );

        try {

            /* 🔐 TRANSAKTION START (NUR EINMAL!) */
            $_database->begin_transaction();

            // ✅ Passwort setzen
            $stmt = $_database->prepare("
                UPDATE users
                SET password_hash = ?
                WHERE userID = ?
            ");
            $stmt->bind_param("si", $hash, $reset['userID']);
            $stmt->execute();
            $stmt->close();

            // ✅ Token verbrauchen
            $stmt = $_database->prepare("
                UPDATE password_resets
                SET used = 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $reset['reset_id']);
            $stmt->execute();
            $stmt->close();

            /* ✅ COMMIT */
            $_database->commit();

            /* ===============================
               🔔 BESTÄTIGUNGSMAIL
            =============================== */

            $ip = trim(explode(',', 
                $_SERVER['HTTP_CF_CONNECTING_IP']
                ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
            )[0]);


            $subject = str_replace(
                '%pagetitle%',
                $hp_title,
                $languageService->get('password_changed_subject')
            );

            $body = str_replace(
                ['%hp_title%', '%hp_url%', '%ip%'],
                [$hp_title, $hp_url, $ip],
                $languageService->get('password_changed_text')
            );

            Email::sendEmail(
                $admin_email,     // From-Mail
                $hp_title,        // From-Name
                $reset['email'],  // Empfänger
                $subject,
                $body
            );

            /* ===============================
               REDIRECT
            =============================== */
            $_SESSION['password_reset_success'] = true;

            header(
                "Location: " .
                SeoUrlHandler::convertToSeoUrl('index.php?site=login&reset=success')
            );
            exit;

        } catch (Throwable $e) {

            $_database->rollback();
            $error = $languageService->get('reset_failed');
        }


    }
}

/* =========================================================
   TEMPLATE
========================================================= */
echo $tpl->loadTemplate(
    'reset_password',
    'form',
    [
        'token' => htmlspecialchars($token, ENT_QUOTES),
        'error' => $error,

        // 🔤 Labels & Texte
        'label_new_password'   => $languageService->get('label_new_password'),
        'password_hint'        => $languageService->get('password_hint'),
        'label_repeat_password'=> $languageService->get('label_repeat_password'),
        'submit_password'      => $languageService->get('submit_password'),
        'reset_password_title' => $languageService->get('reset_password_title'),
    ],
    'theme'
);
