<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\Email;
use nexpell\SeoUrlHandler;

global $_database, $languageService, $tpl;

$languageService->readModule('lostpassword');

/* ===============================
   SETTINGS
=============================== */
$settings = mysqli_fetch_assoc(
    safe_query("SELECT * FROM settings LIMIT 1")
);

$hp_title    = $settings['hptitle'] ?? 'nexpell';
$hp_url      = rtrim($settings['hpurl'] ?? ('https://' . $_SERVER['HTTP_HOST']), '/');
$admin_email = $settings['adminemail'] ?? ('info@' . $_SERVER['HTTP_HOST']);

/* ===============================
   SUCCESS PAGE
=============================== */
if (isset($_GET['success'], $_SESSION['success_message'])) {

    echo $tpl->loadTemplate(
        'lostpassword',
        'success',
        [
            'title' => $languageService->get('title'),
            'forgotten_your_password' => $languageService->get('forgotten_your_password'),
            'message' =>
                '<div class="alert alert-success">' .
                htmlspecialchars($_SESSION['success_message']) .
                '</div>',
            'return_to_login' =>
                '<a href="' .
                SeoUrlHandler::convertToSeoUrl('index.php?site=login') .
                '" class="btn btn-success">' .
                $languageService->get('login') .
                '</a>'
        ],
        'theme'
    );

    unset($_SESSION['success_message']);
    return;
}

/* ===============================
   FORM SUBMIT
=============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));
    $ip    = $_SERVER['REMOTE_ADDR'];

    // ✅ IMMER gleiche Meldung (kein User-Leak)
    $genericSuccess = $languageService->get('reset_mail_sent');

    /* ---------- Rate Limit ---------- */
    $maxAttempts = 5;
    $cooldown    = 15 * 60;

    $stmt = $_database->prepare("
        SELECT attempts, last_attempt
        FROM password_reset_attempts
        WHERE ip = ?
    ");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        $attempt &&
        $attempt['attempts'] >= $maxAttempts &&
        (time() - strtotime($attempt['last_attempt'])) < $cooldown
    ) {
        $_SESSION['success_message'] = $genericSuccess;
        header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword&success=1'));
        exit;
    }

    if ($email !== '') {

        /* ---------- Attempt zählen ---------- */
        $_database->query("
            INSERT INTO password_reset_attempts (ip, attempts)
            VALUES ('$ip', 1)
            ON DUPLICATE KEY UPDATE
                attempts = attempts + 1,
                last_attempt = NOW()
        ");

        /* ---------- User suchen ---------- */
        $stmt = $_database->prepare("
            SELECT userID, email
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {

            /* ---------- TOKEN ---------- */
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 60 Minuten

            $stmt = $_database->prepare("
                INSERT INTO password_resets (userID, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iss", $user['userID'], $token, $expires);
            $stmt->execute();
            $stmt->close();

            /* ---------- RESET-LINK ---------- */
            $hp_url = rtrim($hp_url, '/');

            $seoPath = SeoUrlHandler::convertToSeoUrl(
                'index.php?site=reset_password&token=' . $token
            );

            // Sicherheitsnetz: Pfad MUSS mit /
            $seoPath = '/' . ltrim($seoPath, '/');

            $resetLink = $hp_url . $seoPath;

            /* ---------- MAIL ---------- */
            $vars = [
                '%pagetitle%',
                '%reset_link%',
                '%homepage_url%'
            ];

            $repl = [
                $hp_title,
                $resetLink,
                $hp_url
            ];

            $subject = str_replace(
                '%pagetitle%',
                $hp_title,
                $languageService->get('email_subject')
            );

            $body = str_replace(
                $vars,
                $repl,
                $languageService->get('email_text')
            );

            Email::sendEmail(
                $admin_email,
                $hp_title,
                $user['email'],
                $subject,
                $body
            );
        }

    }

    /* ---------- Immer Erfolg ---------- */
    $_SESSION['success_message'] = $genericSuccess;
    header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword&success=1'));
    exit;
}

/* ===============================
   ERROR MESSAGE
=============================== */
$errorHtml = '';
if (isset($_SESSION['error_message'])) {
    $errorHtml =
        '<div class="alert alert-danger">' .
        htmlspecialchars($_SESSION['error_message']) .
        '</div>';
    unset($_SESSION['error_message']);
}

/* ===============================
   TEMPLATE DATA
=============================== */
echo $tpl->loadTemplate(
    'lostpassword',
    'content_area',
    [
        'title' => $languageService->get('title'),
        'forgotten_your_password' => $languageService->get('forgotten_your_password'),
        'info1' => $languageService->get('info1'),
        'info2' => $languageService->get('info2'),
        'info3' => $languageService->get('info3'),
        'your_email' => $languageService->get('your_email'),
        'get_password' => $languageService->get('get_password'),

        'lastpassword_txt' => $languageService->get('lastpassword_txt'),
        'welcome_back' => $languageService->get('welcome_back'),
        'reg_text' => $languageService->get('reg_text'),
        'login_text' => $languageService->get('login_text'),


        'error_message' => $errorHtml,
        'loginlink' =>
            '<a href="' .
            SeoUrlHandler::convertToSeoUrl('index.php?site=login') .
            '">' . $languageService->get('login') . '</a>',
        'registerlink' =>
            '<a href="' .
            SeoUrlHandler::convertToSeoUrl('index.php?site=register') .
            '">' . $languageService->get('register_link') . '</a>',
    ],
    'theme'
);
