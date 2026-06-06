<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LoginSecurity;

global $_database, $languageService;


/* ==========================================================
   INITIALISIERUNG
========================================================== */
$message = '';
$message_zusatz = '';

/* ==========================================================
   POST VERARBEITUNG
========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---------- CSRF ---------- */
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        $_SESSION['error_message'] = $languageService->get('csrf_invalid');
        goto output;
    }

    /* ---------- INPUT ---------- */
    $current_email  = filter_var(trim($_POST['current_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $new_email      = filter_var(trim($_POST['new_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $confirm_email  = filter_var(trim($_POST['confirm_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password_hash  = trim($_POST['password_hash'] ?? '');

    if (!isset($_SESSION['userID'])) {
        $_SESSION['error_message'] = $languageService->get('user_not_logged_in');
        goto output;
    }

    /* ---------- VALIDIERUNG ---------- */
    if (!filter_var($current_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = $languageService->get('invalid_current_email');
        goto output;
    }

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = $languageService->get('invalid_new_email');
        goto output;
    }

    if ($new_email !== $confirm_email) {
        $_SESSION['error_message'] = $languageService->get('email_mismatch');
        goto output;
    }

    /* ---------- USER LADEN ---------- */
    $userID = (int)$_SESSION['userID'];

    $stmt = $_database->prepare("
        SELECT email, password_hash, password_pepper
        FROM users
        WHERE userID = ?
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $_SESSION['error_message'] = $languageService->get('user_not_found');
        goto output;
    }

    $stmt->bind_result($db_email, $db_hash, $db_pepper);
    $stmt->fetch();

    if ($db_email !== $current_email) {
        $_SESSION['error_message'] = $languageService->get('current_email_wrong');
        goto output;
    }

    /* ---------- PASSWORT PRÜFEN ---------- */
    $decrypted_pepper = LoginSecurity::decryptPepper($db_pepper);
    if ($decrypted_pepper === null) {
        $_SESSION['error_message'] = $languageService->get('pepper_decrypt_failed');
        goto output;
    }

    if (!LoginSecurity::verifyPassword(
        $password_hash,
        $current_email,
        $decrypted_pepper,
        $db_hash
    )) {
        $_SESSION['error_message'] = $languageService->get('password_wrong');
        goto output;
    }

    /* ---------- UPDATE ---------- */
    $new_pepper        = LoginSecurity::generateReadablePassword(32);
    $encrypted_pepper  = LoginSecurity::encryptPepper($new_pepper);
    $new_password_hash = LoginSecurity::createPasswordHash(
        $password_hash,
        $new_email,
        $new_pepper
    );

    $stmt = $_database->prepare("
        UPDATE users
        SET email = ?, password_hash = ?, password_pepper = ?
        WHERE userID = ?
    ");
    $stmt->bind_param("sssi", $new_email, $new_password_hash, $encrypted_pepper, $userID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = $languageService->get('edit_mail_success');
    } else {
        $_SESSION['error_message'] = $languageService->get('edit_mail_failed');
    }
}

/* ==========================================================
   CSRF GENERIEREN
========================================================== */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ==========================================================
   OUTPUT
========================================================== */
output:

if (isset($_SESSION['error_message'])) {
    $message = '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
    unset($_SESSION['success_message']);
}

/* ==========================================================
   TEMPLATE
========================================================== */
$data_array = [
    'csrf_token'            => $_SESSION['csrf_token'],
    'edit_text'             => $languageService->get('edit_text'),
    'edit_mail_headline'    => $languageService->get('edit_mail_headline'),
    'welcome_edit'          => $languageService->get('welcome_edit'),
    'lang_current_email'    => $languageService->get('current_email'),
    'lang_new_email'        => $languageService->get('new_email'),
    'lang_confirm_email'    => $languageService->get('confirm_email'),
    'lang_password_confirm' => $languageService->get('password_confirm'),
    'edit'                  => $languageService->get('edit'),
    'error_message'         => $message,
    'message_zusatz'        => $message_zusatz,
];

echo $tpl->loadTemplate("edit_email", "content", $data_array, 'theme');
