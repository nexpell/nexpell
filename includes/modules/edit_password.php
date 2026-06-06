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
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (!isset($_SESSION['userID'])) {
        $_SESSION['error_message'] = $languageService->get('user_not_logged_in');
        goto output;
    }

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $_SESSION['error_message'] = $languageService->get('password_fields_required');
        goto output;
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = $languageService->get('password_mismatch');
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

    /* ---------- PASSWORT PRÜFEN ---------- */
    $decrypted_pepper = LoginSecurity::decryptPepper($db_pepper);
    if ($decrypted_pepper === null) {
        $_SESSION['error_message'] = $languageService->get('pepper_decrypt_failed');
        goto output;
    }

    if (!LoginSecurity::verifyPassword(
        $current_password,
        $db_email,
        $decrypted_pepper,
        $db_hash
    )) {
        $_SESSION['error_message'] = $languageService->get('current_password_wrong');
        goto output;
    }

    /* ---------- UPDATE ---------- */
    $new_pepper        = LoginSecurity::generateReadablePassword(32);
    $encrypted_pepper  = LoginSecurity::encryptPepper($new_pepper);
    $new_password_hash = LoginSecurity::createPasswordHash(
        $new_password,
        $db_email,
        $new_pepper
    );

    $stmt = $_database->prepare("
        UPDATE users
        SET password_hash = ?, password_pepper = ?
        WHERE userID = ?
    ");
    $stmt->bind_param("ssi", $new_password_hash, $encrypted_pepper, $userID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = $languageService->get('edit_password_success');
    } else {
        $_SESSION['error_message'] = $languageService->get('edit_password_failed');
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
    'csrf_token'                    => $_SESSION['csrf_token'],
    'edit_text'                     => $languageService->get('edit_text'),
    'edit_password_headline'        => $languageService->get('edit_password_headline'),
    'welcome_edit_password_only'    => $languageService->get('welcome_edit_password_only'),
    'lang_current_password'         => $languageService->get('current_password'),
    'lang_new_password'             => $languageService->get('new_password'),
    'lang_confirm_password'         => $languageService->get('confirm_password'),
    'edit'                          => $languageService->get('edit_password_button'),
    'error_message'                 => $message,
];

echo $tpl->loadTemplate("edit_password", "content", $data_array, 'theme');
