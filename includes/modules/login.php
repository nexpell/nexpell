<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$message = '';

use nexpell\LoginSecurity;
use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

// Initialisieren
global $_database, $languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('login', false);

$ip = $_SERVER['REMOTE_ADDR'];
$message_zusatz = '';
$isIpBanned = '';
$is_active = '';
$is_locked = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password_hash = $_POST['password_hash'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = $languageService->get('error_invalid_email');
        header("Location: " . SeoUrlHandler::convertToSeoUrl('index.php?site=login'));
        exit;
    }

    $ip = $_SERVER['REMOTE_ADDR'];
    $loginResult = LoginSecurity::verifyLogin($email, $password_hash, $ip, $is_active, $is_locked);

    if ($loginResult['success']) {
        if (LoginSecurity::isIpBanned($ip)) {
            $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_ip_banned') . '</div>';
            $isIpBanned = true;
        }

        $stmt = $_database->prepare("SELECT userID, username, email, is_locked FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        /*if ($user) {
            if (!empty($user['is_locked']) && (int)$user['is_locked'] === 1) {
                $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_account_locked') . '</div>';
                $isIpBanned = true;
            } else {
                // Session setzen
                $_SESSION['userID']   = (int)$user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email']    = $user['email'];

                // Rolle auslesen (Beispiel mit Tabelle user_role_assignments)
                $stmtRole = $_database->prepare("SELECT roleID FROM user_role_assignments WHERE userID = ? LIMIT 1");
                $stmtRole->bind_param("i", $user['userID']);
                $stmtRole->execute();
                $resultRole = $stmtRole->get_result();
                if ($resultRole && $rowRole = $resultRole->fetch_assoc()) {
                    $_SESSION['roleID'] = (int)$rowRole['roleID'];
                } else {
                    $_SESSION['roleID'] = null; // oder Default-Rolle
                }
                $stmtRole->close();

                // Session absichern
                LoginSecurity::saveSession($user['userID']);

                // --- Login erfolgreich → Zeitstempel setzen ---
                $login_time = date('Y-m-d H:i:s');
                $is_online  = 1;

                $updateStmt = $_database->prepare("
                    UPDATE users 
                    SET 
                        lastlogin = ?,       -- Datum des letzten Logins
                        login_time = ?,      -- Start der aktuellen Session
                        last_activity = ?,   -- erste Aktivität = Loginzeit
                        is_online = ?        -- User ist eingeloggt
                    WHERE userID = ?
                ");
                $updateStmt->bind_param("sssii", $login_time, $login_time, $login_time, $is_online, $user['userID']);
                $updateStmt->execute();
                $updateStmt->close();

                // Erfolgsmeldung
                $_SESSION['success_message'] = $languageService->get('success_login');

                // Weiterleitung
                header("Location: /");
                exit;
            }
        } else {
            $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_not_found') . '</div>';
        }*/
        if ($user) {
            if (!empty($user['is_locked']) && (int)$user['is_locked'] === 1) {
                $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_account_locked') . '</div>';
                $isIpBanned = true;
            } else {
                // ===========================================
                // 🧩 Session setzen (Basisdaten)
                // ===========================================
                $_SESSION['userID']   = (int)$user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email']    = $user['email'];

                // ===========================================
                // 🧩 Rollen-System: alle Rollen + Namen laden
                // ===========================================
                $roles = [];
                $roleNames = [];

                $stmtRole = $_database->prepare("
                    SELECT r.roleID, r.role_name
                    FROM user_role_assignments ura
                    JOIN user_roles r ON ura.roleID = r.roleID
                    WHERE ura.userID = ?
                ");
                $stmtRole->bind_param("i", $user['userID']);
                $stmtRole->execute();
                $resultRole = $stmtRole->get_result();

                while ($rowRole = $resultRole->fetch_assoc()) {
                    $roles[] = (int)$rowRole['roleID'];
                    $roleNames[] = $rowRole['role_name'];
                }
                $stmtRole->close();

                // In Session speichern
                $_SESSION['roles']       = $roles;
                $_SESSION['role_names']  = $roleNames;

                // ===========================================
                // 🧩 Automatische Flags nach RolleID
                // ===========================================
                $_SESSION['is_admin']       = in_array(1,  $roles, true);
                $_SESSION['is_coadmin']     = in_array(2,  $roles, true);
                $_SESSION['is_leader']      = in_array(3,  $roles, true);
                $_SESSION['is_coleader']    = in_array(4,  $roles, true);
                $_SESSION['is_squadleader'] = in_array(5,  $roles, true);
                $_SESSION['is_warorg']      = in_array(6,  $roles, true);
                $_SESSION['is_moderator']   = in_array(7,  $roles, true);
                $_SESSION['is_editor']      = in_array(8,  $roles, true);
                $_SESSION['is_member']      = in_array(9,  $roles, true);
                $_SESSION['is_trial']       = in_array(10, $roles, true);
                $_SESSION['is_guest']       = in_array(11, $roles, true);
                $_SESSION['is_registered']  = in_array(12, $roles, true);
                $_SESSION['is_honor']       = in_array(13, $roles, true);
                $_SESSION['is_streamer']    = in_array(14, $roles, true);
                $_SESSION['is_designer']    = in_array(15, $roles, true);
                $_SESSION['is_technician']  = in_array(16, $roles, true);

                // ===========================================
                // 🧩 Rückwärtskompatibilität + textbasierte Rolle
                // ===========================================
                $_SESSION['roleID'] = $_SESSION['is_admin'] ? 1 : (($_SESSION['is_registered'] ?? false) ? 12 : null);

                if ($_SESSION['is_admin']) {
                    $_SESSION['userrole'] = 'admin';
                } elseif ($_SESSION['is_moderator']) {
                    $_SESSION['userrole'] = 'moderator';
                } elseif ($_SESSION['is_editor']) {
                    $_SESSION['userrole'] = 'editor';
                } elseif ($_SESSION['is_registered']) {
                    $_SESSION['userrole'] = 'user';
                } else {
                    $_SESSION['userrole'] = 'guest';
                }

                // ===========================================
                // 🧩 Fallback – falls User keine Rolle hat
                // ===========================================
                if (empty($roles)) {
                    $res = safe_query("SELECT roleID FROM user_roles WHERE is_default = 1 LIMIT 1");
                    if ($row = mysqli_fetch_assoc($res)) {
                        $defaultRole = (int)$row['roleID'];
                        safe_query("INSERT INTO user_role_assignments (userID, roleID, created_at)
                                    VALUES (" . (int)$user['userID'] . ", $defaultRole, NOW())");
                        $_SESSION['roles'] = [$defaultRole];
                        $_SESSION['is_registered'] = true;
                        $_SESSION['userrole'] = 'user';
                    }
                }

                // ===========================================
                // 🧩 Session absichern + Login-Status aktualisieren
                // ===========================================
                LoginSecurity::saveSession($user['userID']);

                $login_time = date('Y-m-d H:i:s');
                $is_online  = 1;

                $updateStmt = $_database->prepare("
                    UPDATE users 
                    SET 
                        lastlogin = ?,       -- Datum des letzten Logins
                        login_time = ?,      -- Start der aktuellen Session
                        last_activity = ?,   -- erste Aktivität = Loginzeit
                        is_online = ?        -- User ist eingeloggt
                    WHERE userID = ?
                ");
                $updateStmt->bind_param("sssii", $login_time, $login_time, $login_time, $is_online, $user['userID']);
                $updateStmt->execute();
                $updateStmt->close();

                // Erfolgsmeldung
                $_SESSION['success_message'] = $languageService->get('success_login');

                // Weiterleitung
                header("Location: /");
                exit;
            }
        } else {
            $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_not_found') . '</div>';
        }


    } else {
        $userID = null;
        $stmt = $_database->prepare("SELECT userID, is_active FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $userID = (int)$row['userID'];

            if ((int)$row['is_active'] === 0) {
                $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_account_inactive') . '</div>';
                $isIpBanned = true;
            } else {
                if (!LoginSecurity::isEmailOrIpBanned($email, $ip)) {
                    LoginSecurity::trackFailedLogin($userID, $email, $ip);

                    $failCount = LoginSecurity::getFailCount($ip, $email);
                    if ($failCount >= 5) {
                        LoginSecurity::banIp($ip, $userID, "Zu viele Fehlversuche", $email);
                        $_SESSION['error_message'] = $languageService->get('error_login_locked');
                    } else {
                        $_SESSION['error_message'] = str_replace('{failcount}', $failCount, $languageService->get('error_invalid_login'));
                    }
                } else {
                    $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_email_or_ip_banned') . '</div>';
                    $isIpBanned = true;
                }
            }
        } else {
            $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_not_found') . '</div>';
            $isIpBanned = true;
        }
    }

    if (isset($_SESSION['error_message'])) {
        $message = '<div class="alert alert-danger" role="alert">' . $_SESSION['error_message'] . '</div>';
        unset($_SESSION['error_message']);
    }
}

if (!empty($email)) {
    $isEmailBanned = LoginSecurity::isEmailBanned($ip, $email);
} else {
    $isEmailBanned = false;
}

if ($isEmailBanned) {
    $message = '<div class="alert alert-danger" role="alert">' . $languageService->get('error_email_banned') . '</div>';
    $isIpBanned = true;
}

$registerlink = '<a href="' . SeoUrlHandler::convertToSeoUrl('index.php?site=register') . '">' . $languageService->get('register_link') . '</a>';
$lostpasswordlink = '<a href="' . SeoUrlHandler::convertToSeoUrl('index.php?site=lostpassword') . '">' . $languageService->get('lostpassword_link') . '</a>';

$data_array = [
    'login_headline' => $languageService->get('title'),
    'email_label' => $languageService->get('email_label'),
    'your_email' => $languageService->get('your_email'),
    'pass_label' => $languageService->get('pass_label'),
    'your_pass' => $languageService->get('your_pass'),
    'remember_me' => $languageService->get('remember_me'),
    'login_button' => $languageService->get('login_button'),
    'register_link' => $languageService->get('register_link'),
    'registerlink' => $registerlink,
    'lostpasswordlink' => $lostpasswordlink,
    'error_message' => $message,
    'message_zusatz' => $message_zusatz,
    'isIpBanned' => $isIpBanned,
    'welcome_back' => $languageService->get('welcome_back'),
    'reg_text' => $languageService->get('reg_text'),
    'login_text' => $languageService->get('login_text'),
];

echo $tpl->loadTemplate("login", "content", $data_array, 'theme');
