<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LoginSecurity;

global $_database, $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('edit_password');
$message_zusatz = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ungültiger CSRF-Token.");
    }

    $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error_message'] = "Bitte alle Felder ausfüllen.";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = "Die neuen Passwörter stimmen nicht überein.";
    }

    if (!isset($_SESSION['userID'])) {
        $_SESSION['error_message'] = "Benutzer ist nicht eingeloggt.";
    }

    $userID = $_SESSION['userID'];

    // Benutzer-Daten laden (inkl. E-Mail, Passwort-Hash, Pepper)
    $stmt = $_database->prepare("SELECT email, password_hash, password_pepper FROM users WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $_SESSION['error_message'] = "Benutzer nicht gefunden.";
    }

    $stmt->bind_result($db_email, $db_password_hash, $db_password_pepper);
    $stmt->fetch();

    // Pepper entschlüsseln
    $decrypted_pepper = LoginSecurity::decryptPepper($db_password_pepper);
    if (is_null($decrypted_pepper)) {
        $_SESSION['error_message'] = "Pepper konnte nicht entschlüsselt werden.";
    }

    // Aktuelles Passwort prüfen (mit gespeicherter E-Mail und Pepper)
    if (!LoginSecurity::verifyPassword($current_password, $db_email, $decrypted_pepper, $db_password_hash)) {
        $_SESSION['error_message'] = "Das aktuelle Passwort ist falsch.";
    }

    if (!isset($_SESSION['error_message'])) {
        // Neuen Pepper generieren
        $new_pepper = LoginSecurity::generateReadablePassword(32);
        $encrypted_pepper = LoginSecurity::encryptPepper($new_pepper);

        // Neuen Passwort-Hash erstellen (mit gleicher E-Mail und neuem Pepper)
        $new_password_hash = LoginSecurity::createPasswordHash($new_password, $db_email, $new_pepper);

        // Passwort + Pepper updaten
        $stmt_update = $_database->prepare("UPDATE users SET password_hash = ?, password_pepper = ? WHERE userID = ?");
        $stmt_update->bind_param("ssi", $new_password_hash, $encrypted_pepper, $userID);

        if ($stmt_update->execute()) {
            $_SESSION['success_message'] = "Passwort erfolgreich geändert.";
        } else {
            $_SESSION['error_message'] = "Fehler beim Ändern des Passworts.";
        }
    }
} else {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

$message = '';
if (isset($_SESSION['error_message'])) {
    $message = '<div class="alert alert-danger" role="alert">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
} elseif (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success" role="alert">' . $_SESSION['success_message'] . '</div>';
    unset($_SESSION['success_message']);
}

$data_array = [
    'csrf_token' => $_SESSION['csrf_token'],
    'edit_text'              => $languageService->get('edit_text'),
    'edit_password_headline' => $languageService->get('edit_password_headline'),
    'welcome_edit_password_only' => $languageService->get('welcome_edit_password_only'),
    'lang_current_password' => $languageService->get('current_password'),
    'lang_new_password' => $languageService->get('new_password'),
    'lang_confirm_password' => $languageService->get('confirm_password'),
    'edit' => $languageService->get('edit_password_button'),
    'error_message' => $message,
];

echo $tpl->loadTemplate("edit_password", "content", $data_array, 'theme');
?>
