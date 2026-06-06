<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $_database, $languageService;


/* ==========================================================
   LAYOUT START
========================================================== */
echo '<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow rounded">
                <div class="card-body">
                    <h4 class="card-title mb-4">'
                        . $languageService->get('register_activate_title')
                    . '</h4>';

$code = $_GET['code'] ?? '';

/* ==========================================================
   AKTIVIERUNG
========================================================== */
if (!empty($code)) {

    // 1. User mit Code holen
    $stmt = $_database->prepare("
        SELECT email, activation_expires
        FROM users
        WHERE activation_code = ?
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {

        $stmt->bind_result($email, $expires);
        $stmt->fetch();

        // Ablauf prüfen
        if ((int)$expires < time()) {

            echo '<div class="alert alert-danger" role="alert">'
                . $languageService->get('register_activation_expired')
                . '</div>';

            redirect("index.php", "", 5);
            exit;

        } else {

            // 2. Account aktivieren
            $stmt_update = $_database->prepare("
                UPDATE users
                SET is_active = 1,
                    activation_code = NULL,
                    activation_expires = NULL
                WHERE email = ?
            ");
            $stmt_update->bind_param("s", $email);
            $stmt_update->execute();

            // 3. Fehlversuche löschen
            $stmt_delete = $_database->prepare("
                DELETE FROM user_register_attempts
                WHERE email = ?
            ");
            $stmt_delete->bind_param("s", $email);
            $stmt_delete->execute();

            echo '<div class="alert alert-success" role="alert">'
                . $languageService->get('register_activation_success')
                . '</div>';

            redirect("index.php?site=login", "", 3);
        }

    } else {

        echo '<div class="alert alert-danger" role="alert">'
            . $languageService->get('register_activation_invalid')
            . '</div>';

        redirect("index.php", "", 3);
    }

} else {

    echo '<div class="alert alert-danger" role="alert">'
        . $languageService->get('register_activation_missing_code')
        . '</div>';

    redirect("index.php", "", 3);
}

echo '
                </div>
            </div>
        </div>
    </div>
</div>';

die();
