<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LoginSecurity;

global $_database,$languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('edit_mail');
$message_zusatz = '';
$message = '';
// CSRF-Token validieren
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token validieren
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ungültiger CSRF-Token.");
    }

    // Eingabefelder bereinigen
    $current_email = isset($_POST['current_email']) ? filter_var(trim($_POST['current_email']), FILTER_SANITIZE_EMAIL) : '';
    $new_email = isset($_POST['new_email']) ? filter_var(trim($_POST['new_email']), FILTER_SANITIZE_EMAIL) : '';
    $confirm_email = isset($_POST['confirm_email']) ? filter_var(trim($_POST['confirm_email']), FILTER_SANITIZE_EMAIL) : '';
    $password_hash = isset($_POST['password_hash']) ? trim($_POST['password_hash']) : '';
    $password_pepper = isset($_POST['password_pepper']) ? trim($_POST['password_pepper']) : ''; // Sicherstellen, dass Pepper gesetzt ist

    // E-Mail-Validierung
    if (!filter_var($current_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Ungültige aktuelle E-Mail-Adresse.";
    }
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Ungültige neue E-Mail-Adresse.";
    }
    if ($new_email !== $confirm_email) {
        $_SESSION['error_message'] = "Die neue E-Mail-Adresse und die Bestätigung stimmen nicht überein.";
    }

    // Hier kannst du die Benutzer-ID aus der Session holen
    if (!isset($_SESSION['userID'])) {
        $_SESSION['error_message'] = "Benutzer ist nicht eingeloggt.";
    }

    // Datenbankverbindung
    $userID = $_SESSION['userID'];

    // Überprüfen, ob die aktuelle E-Mail-Adresse korrekt ist
    $stmt = $_database->prepare("SELECT email, password_hash, password_pepper FROM users WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $_SESSION['error_message'] = "Benutzer nicht gefunden.";
    }

    $stmt->bind_result($db_current_email, $db_password_hash, $db_password_pepper);
    $stmt->fetch();

    // Überprüfen, ob die aktuelle E-Mail-Adresse korrekt ist
    if ($db_current_email !== $current_email) {
        $_SESSION['error_message'] = "Die angegebene aktuelle E-Mail-Adresse ist falsch.";
    }

    // Entschlüsselten Pepper laden
    $decrypted_pepper = LoginSecurity::decryptPepper($db_password_pepper);

    if (is_null($decrypted_pepper)) {
        $_SESSION['error_message'] = "Pepper konnte nicht entschlüsselt werden.";
    }

    // Passwort prüfen
    if (!LoginSecurity::verifyPassword($password_hash, $current_email, $decrypted_pepper, $db_password_hash)) {
        $_SESSION['error_message'] = "Das Passwort ist falsch.";
    }

    // Neue E-Mail und Pepper verwenden
    $new_pepper = LoginSecurity::generateReadablePassword(32); // Einen neuen zufälligen Pepper generieren
    $encrypted_pepper = LoginSecurity::encryptPepper($new_pepper); // Den neuen Pepper verschlüsseln

    // Neues Passwort-Hash mit der neuen E-Mail und dem neuen Pepper erstellen
    $new_password_hash = LoginSecurity::createPasswordHash($password_hash, $new_email, $new_pepper);

    // E-Mail und Passwort-Hash + Pepper aktualisieren
    $stmt = $_database->prepare("UPDATE users SET email = ?, password_hash = ?, password_pepper = ? WHERE userID = ?");
    $stmt->bind_param("sssi", $new_email, $new_password_hash, $encrypted_pepper, $userID);

    if ($stmt->execute()) {
        $_SESSION['error_message'] = "E-Mail-Adresse und Passwort erfolgreich geändert.";
        // Optional: Bestätigungsmail an den Benutzer senden
        // mail($new_email, "E-Mail geändert", "Ihre E-Mail-Adresse wurde erfolgreich geändert.");
    } else {
        $_SESSION['error_message'] = "Fehler beim Ändern der E-Mail-Adresse oder des Passworts.";
    }
} else {
    // CSRF-Token generieren, wenn es noch nicht existiert
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

// CSRF-Token an das Template übergeben
$data_array = [
    'csrf_token'             => $_SESSION['csrf_token'], // CSRF-Token im Template verfügbar machen
    'edit_text'              => $languageService->get('edit_text'),
    'edit_mail_headline'     => $languageService->get('edit_mail_headline'),
    'welcome_edit'           => $languageService->get('welcome_edit'),
    'lang_current_email'     => $languageService->get('current_email'),
    'lang_new_email'         => $languageService->get('new_email'),
    'lang_confirm_email'     => $languageService->get('confirm_email'),
    'lang_password_confirm'  => $languageService->get('password_confirm'),
    'edit'                   => $languageService->get('edit'),
    'error_message'          => $message,
    'message_zusatz'         => $message_zusatz,
];

echo $tpl->loadTemplate("edit_email", "content", $data_array, 'theme');
?>
