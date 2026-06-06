<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LoginSecurity;
use nexpell\Email;
use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $_database, $languageService, $tpl;

$lang = $languageService->detectLanguage();
$languageService->readModule('lostpassword');

// Einstellungen laden
$settings_result = safe_query("SELECT * FROM `settings`");
$settings = mysqli_fetch_assoc($settings_result);

$hp_title = $settings['hptitle'] ?? 'nexpell';
$hp_url = $settings['hpurl'] ?? 'https://' . $_SERVER['HTTP_HOST'];
$admin_email = $settings['adminemail'] ?? 'info@' . $_SERVER['HTTP_HOST'];

// Erfolg nach Passwort-Zurücksetzung
$success = isset($_GET['success']) && $_GET['success'] == 1;

if ($success && isset($_SESSION['success_message'])) {
    $data_array = [
        'title' => $languageService->module['title'],
        'forgotten_your_password' => $languageService->module['forgotten_your_password'],
        'message' => '<div class="alert alert-success" role="alert">' . $_SESSION['success_message'] . '</div>',
        'return_to_login' => '<a href="' . SeoUrlHandler::convertToSeoUrl('index.php?site=login') . '" class="btn btn-success">' . $languageService->module['login'] . '</a>'
    ];
    unset($_SESSION['success_message']);
    echo $tpl->loadTemplate("lostpassword", "success", $data_array, 'theme');
    return;
}

// Formular abgeschickt
if (isset($_POST['submit'])) {
    $email_input = strtolower(trim($_POST['email']));

    if ($email_input !== '') {
        // Benutzer in der Datenbank suchen
        $query = "SELECT * FROM `users` WHERE email = '" . mysqli_real_escape_string($_database, $email_input) . "'";
        $result = safe_query($query);

        if ($result && mysqli_num_rows($result) > 0) {
            $ds = mysqli_fetch_array($result);

            if (!empty($ds['password_pepper'])) {
                // Neues lesbares Passwort
                $new_password_plain = LoginSecurity::generateReadablePassword();

                // Pepper entschlüsseln
                $pepper_plain = LoginSecurity::decryptPepper($ds['password_pepper']);
                if ($pepper_plain === false || $pepper_plain === '') {
                    $_SESSION['error_message'] = '❌ Fehler beim Entschlüsseln des Peppers.';
                    header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword'));
                    exit;
                }

                // Neues Passwort hashen
                $new_password_hash = password_hash($new_password_plain . $ds['email'] . $pepper_plain, PASSWORD_BCRYPT);

                // Passwort in der DB aktualisieren
                safe_query("
                    UPDATE `users`
                    SET `password_hash` = '" . LoginSecurity::escape($new_password_hash) . "'
                    WHERE `userID` = '" . intval($ds['userID']) . "'
                ");

                // E-Mail senden
                $vars = ['%pagetitle%', '%email%', '%new_password%', '%homepage_url%'];
                $repl = [$hp_title, $ds['email'], $new_password_plain, $hp_url];

                $subject = str_replace($vars, $repl, $languageService->module['email_subject']);
                $message = str_replace($vars, $repl, $languageService->module['email_text']);

                $sendmail = Email::sendEmail($admin_email, 'Passwort zurückgesetzt', $ds['email'], $subject, $message);

                if ($sendmail['result'] === 'fail') {
                    $_SESSION['error_message'] = $languageService->module['email_failed'] . ' ' . $sendmail['error'];
                    header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword'));
                    exit;
                } else {
                    $_SESSION['success_message'] = str_replace($vars, $repl, $languageService->module['successful']);
                    header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword&success=1'));
                    exit;
                }
            } else {
                $_SESSION['error_message'] = '❌ Kein Pepper in der Datenbank.';
                header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword'));
                exit;
            }
        } else {
            $_SESSION['error_message'] = $languageService->module['no_user_found'];
            header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword'));
            exit;
        }
    } else {
        $_SESSION['error_message'] = $languageService->module['no_mail_given'];
        header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword'));
        exit;
    }
}

// Fehlernachricht vorbereiten
$message = '';
if (isset($_SESSION['error_message'])) {
    $message = '<div class="alert alert-danger" role="alert">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}

// Links
$registerlink = '<a href="' . SeoUrlHandler::convertToSeoUrl('index.php?site=register') . '">' . $languageService->get('register_link') . '</a>';
$loginlink = '<a href="' . SeoUrlHandler::convertToSeoUrl('index.php?site=login') . '">' . $languageService->get('login') . '</a>';

// Formular anzeigen
$data_array = [
    'title' => $languageService->module['title'],
    'forgotten_your_password' => $languageService->module['forgotten_your_password'],
    'info1' => $languageService->module['info1'],
    'info2' => $languageService->module['info2'],
    'info3' => $languageService->module['info3'],
    'your_email' => $languageService->module['your_email'],
    'get_password' => $languageService->module['get_password'],
    'return_to' => $languageService->module['return_to'],
    'loginlink' => $loginlink,
    'email-address' => $languageService->module['email-address'],
    'reg' => $languageService->module['reg'],
    'need_account' => $languageService->module['need_account'],
    'error_message' => $message,
    'lastpassword_txt' => $languageService->module['lastpassword_txt'],
    'registerlink' => $registerlink,
    'welcome_back' => $languageService->module['welcome_back'],
    'reg_text' => $languageService->module['reg_text'],
    'login_text' => $languageService->module['login_text'],
];

echo $tpl->loadTemplate("lostpassword", "content_area", $data_array, 'theme');
