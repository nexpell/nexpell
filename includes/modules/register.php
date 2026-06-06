<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('NXSESSID');
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once "system/config.inc.php";
use nexpell\LoginSecurity;
use nexpell\Email;
use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $_database, $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('register');

$get = mysqli_fetch_assoc(safe_query("SELECT * FROM settings"));
$webkey = $get['webkey'];
$seckey = $get['seckey'];

/* PATCH: reCAPTCHA aktiv? */
$recaptcha_active = (!empty($webkey) && !empty($seckey));

$form_data = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    setcookie('NXCSRF', $_SESSION['csrf_token'], [
        'expires'  => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

$csrf_token = $_SESSION['csrf_token'] ?? '';

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_repeat = $_POST['password_repeat'] ?? '';
$terms = isset($_POST['terms']);
$ip_address = $_SERVER['REMOTE_ADDR'];

$registrierung_erfolgreich = false;
$errors = [];

$stmt = $_database->prepare("
    SELECT COUNT(*) FROM user_register_attempts 
    WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 30 MINUTE)
");
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$stmt->bind_result($attempt_count);
$stmt->fetch();
$stmt->close();

if ($attempt_count >= 5) {
    $errors[] = $languageService->get('too_many_attempts');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ============================
       PATCH 1: Honeypot (nur wenn reCAPTCHA AUS)
       ============================ */
    if (!$recaptcha_active && !empty($_POST['company'] ?? '')) {
        $_SESSION['error_message'] = $languageService->get('register_failed');
        header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=register'));
        exit;
    }

    /* ============================
       CSRF (unverändert)
       ============================ */
    $postToken   = (string)($_POST['csrf_token'] ?? '');
    $sessToken   = (string)($_SESSION['csrf_token'] ?? '');
    $cookieToken = (string)($_COOKIE['NXCSRF'] ?? '');

    if (
        (!$postToken || !$sessToken || !hash_equals($sessToken, $postToken)) &&
        (!$postToken || !$cookieToken || !hash_equals($cookieToken, $postToken))
    ) {
        http_response_code(400);
        die('Ungültiges Formular (CSRF-Schutz).');
    }

    unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
    setcookie('NXCSRF', '', time() - 3600, '/');

    /* ============================
       Validierung (unverändert)
       ============================ */
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $languageService->get('invalid_email');
    }

    if (!preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $username)) {
        $errors[] = $languageService->get('invalid_username');
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = $languageService->get('invalid_password');
    }

    if ($password !== $password_repeat) {
        $errors[] = $languageService->get('password_mismatch');
    }

    if (!$terms) {
        $errors[] = $languageService->get('terms_required');
    }

    /* ============================
       PATCH 2: reCAPTCHA NUR wenn aktiv
       ============================ */
    if ($recaptcha_active) {
        if (empty($_POST['g-recaptcha-response'])) {
            $errors[] = "reCAPTCHA fehlt";
        } else {
            $verify_url =
                "https://www.google.com/recaptcha/api/siteverify"
                . "?secret=" . urlencode($seckey)
                . "&response=" . urlencode($_POST['g-recaptcha-response'])
                . "&remoteip=" . urlencode($_SERVER['REMOTE_ADDR']);

            $res = json_decode(file_get_contents($verify_url), true);
            if (!($res['success'] ?? false)) {
                $errors[] = "reCAPTCHA nicht bestanden";
            }
        }
    }

    // Prüfen, ob Email schon existiert
    $stmt = $_database->prepare("SELECT userID FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = $languageService->get('email_exists');
    }
    $stmt->close();

    // Fehler vorhanden? Dann Rückgabe
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=register'));
        exit;
    }

    // Daten speichern
    $role = 1;
    $is_active = 0;

    $stmt = $_database->prepare("INSERT INTO users (username, email, registerdate, role, is_active) VALUES (?, ?, CURRENT_TIMESTAMP(), ?, ?)");
    $stmt->bind_param("ssii", $username, $email, $role, $is_active);
    if (!$stmt->execute()) {
        die("Fehler beim Einfügen: " . $stmt->error);
    }

    $userID = $_database->insert_id;

    $pepper_plain     = LoginSecurity::generatePepper();
    $pepper_encrypted = LoginSecurity::encryptPepper($pepper_plain);
    $hashed_pass      = LoginSecurity::createPasswordHash($password, $email, $pepper_plain);

    $stmt = $_database->prepare("UPDATE users SET password_hash = ?, password_pepper = ? WHERE userID = ?");
    $stmt->bind_param("ssi", $hashed_pass, $pepper_encrypted, $userID);
    $stmt->execute();

    // Registrierung versuchen speichern
    $status = 'success';
    $reason = null;
    $stmt = $_database->prepare("
        INSERT INTO user_register_attempts (ip_address, status, reason, username, email)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", $ip_address, $status, $reason, $username, $email);
    $stmt->execute();
    $stmt->close();

    $stmt = $_database->prepare("
    INSERT INTO user_username (userID, username)
    VALUES (?, ?)
    ");
    $stmt->bind_param("is", $userID, $username);
    $stmt->execute();
    $stmt->close();


    $roleID = 12;
    $assignedAt = date('Y-m-d H:i:s'); // aktuelles Datum/Zeit

    $stmt = $_database->prepare("
        INSERT INTO user_role_assignments (userID, roleID, assigned_at)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $userID, $roleID, $assignedAt);
    $stmt->execute();
    $stmt->close();

    // Aktivierungscode erstellen
    $activation_code = bin2hex(random_bytes(32));
    $activation_expires = time() + 86400;
    $stmt = $_database->prepare("UPDATE users SET activation_code = ?, activation_expires = ? WHERE userID = ?");
    $stmt->bind_param("sii", $activation_code, $activation_expires, $userID);
    $stmt->execute();

    $activation_link = 'https://' . $_SERVER['HTTP_HOST'] . '/index.php?site=activate&code=' . urlencode($activation_code);

    $settings_result = safe_query("SELECT * FROM `settings`");
    $settings = mysqli_fetch_assoc($settings_result);
    $hp_title = $settings['hptitle'] ?? 'nexpell';
    $hp_url = $settings['hpurl'] ?? 'https://' . $_SERVER['HTTP_HOST'];
    $admin_email = $settings['adminemail'] ?? 'info@' . $_SERVER['HTTP_HOST'];

    $vars = ['%username%', '%activation_link%', '%hp_title%', '%hp_url%'];
    $repl = [$username, $activation_link, $hp_title, $hp_url];

    $subject = str_replace($vars, $repl, $languageService->get('mail_subject'));
    $message = str_replace($vars, $repl, $languageService->get('mail_text'));

    $module = $languageService->get('mail_from_module');

    $sendmail = Email::sendEmail($admin_email, $module, $email, $subject, $message);

    if (is_array($sendmail) && isset($sendmail['result']) && $sendmail['result'] === 'done') {
        $_SESSION['success_message'] = $languageService->get('register_successful');
        $registrierung_erfolgreich = true;
    } else {
        $_SESSION['error_message'] = $languageService->get('mail_failed');
    }
}

$errormessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
$successmessage = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$termsofuse = '<a href="' . SeoUrlHandler::convertToSeoUrl('index.php?site=terms_of_service') . '">' . $languageService->get('terms_of_use') . '</a>';
$loginlink = '<a href="' . SeoUrlHandler::convertToSeoUrl('index.php?site=login') . '">' . $languageService->get('login_link') . '</a>';

$form_action = SeoUrlHandler::convertToSeoUrl('index.php?site=register') ?: 'index.php?site=register';
if (!$form_action) { $form_action = 'index.php?site=register'; }




$data_array = [

    
    'error_message' => $errormessage,
    'success_message' => $successmessage,
    'isreg' => $registrierung_erfolgreich,
    'username' => htmlspecialchars($username),
    'email' => htmlspecialchars($email),
    'password_repeat' => htmlspecialchars($password_repeat),


    
    'message_zusatz' => '',
    'isreg' => $registrierung_erfolgreich,
    
    #'recaptcha_site_key' => 'DEIN_SITE_KEY',  // <-- Hier dein SITE-KEY einfügen
    'security_code' => $languageService->module['security_code'],
    //'recaptcha_site_key' => '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($webkey) . '"></div>',
    'reg_title' => $languageService->get('reg_title'),
    'reg_info_text' =>  $languageService->get('reg_info_text'),
    'loginlink' => $loginlink,
    'login_text' =>  $languageService->get('login_text'),
    'mail' => $languageService->get('mail'),
    'username_label' => $languageService->get('username'),
    'password_label' => $languageService->get('password'),
    'password_repeat_label' => $languageService->get('password_repeat'),
    'email_address_label' => $languageService->get('email_address_label'),
    'enter_your_email' => $languageService->get('enter_your_email'),
    'enter_your_name' => $languageService->get('enter_your_name'),
    'enter_password' => $languageService->get('enter_password'),
    'enter_password_repeat' => $languageService->get('enter_password_repeat'),
    'password_hint' => $languageService->get('password_hint'),
    'register' => $languageService->get('register'),
    'terms_of_use_text' => $languageService->get('terms_of_use_text'),
    'termsofuse' => $termsofuse,

    'form_action' => htmlspecialchars($form_action, ENT_QUOTES),
    'csrf_token'  => htmlspecialchars($csrf_token, ENT_QUOTES),
    'session_id'  => htmlspecialchars(session_id(), ENT_QUOTES),
    
    'recaptcha_site_key' => $recaptcha_active
        ? '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($webkey) . '"></div>'
        : '',
    
];

echo $tpl->loadTemplate("register", "content", $data_array);

?>


<script>
document.getElementById("password").addEventListener("input", function () {
    const strengthText = document.getElementById("passwordStrength");
    const val = this.value;
    let strength = 0;

    if (val.length >= 8) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/\d/.test(val)) strength++;
    if (/[\W_]/.test(val)) strength++;

    const levels = ["Sehr schwach", "Schwach", "Okay", "Stark"];
    const colors = ["#f44336", "#ff9800", "#ffeb3b", "#4caf50"];

    strengthText.textContent = levels[strength - 1] || "";
    strengthText.style.color = colors[strength - 1] || "#f44336";
});
</script>