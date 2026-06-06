<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_site_lock');

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

// Aktuellen Status laden
$res_settings = safe_query("SELECT closed FROM settings LIMIT 1");
$row_settings = mysqli_fetch_assoc($res_settings);
$closed = (int)($row_settings['closed'] ?? 0);

// Content
echo '<div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-lock"></i>
                    <span>' . $languageService->get('title_lock') . '</span>
                </div>
            </div>
        <div class="card-body p-0">';
if (!$closed) {
    // Seite ist nicht gesperrt – Sperrformular anzeigen/verarbeiten
    if (isset($_POST['submit'])) {

        if (empty($_POST['reason'])) { nx_alert('danger', 'alert_lock_reason_required', false); return; }

        $CAPCLASS = new \nexpell\Captcha;

        if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {

            $now = time();

            $res_lock = safe_query("SELECT * FROM settings_site_lock");
            if (mysqli_num_rows($res_lock)) {
                safe_query("UPDATE settings_site_lock SET reason = '" . escape($_POST['reason']) . "', time = '" . $now . "'");
            } else {
                safe_query("INSERT INTO settings_site_lock (time, reason) VALUES ('" . $now . "', '" . escape($_POST['reason']) . "')");
            }

            safe_query("UPDATE settings SET closed = '1'");

            // AUDIT: Site Lock aktiviert
            nx_audit(
                'UPDATE',
                'settings',
                'site_lock',
                'Seite gesperrt',
                [
                    'lock_time' => $now,
                    'reason_len' => strlen((string)($_POST['reason'] ?? ''))
                ]
            );

            nx_redirect('admincenter.php?site=site_lock', 'success', 'page_locked', false);

        } else {
            nx_alert('danger', 'transaction_invalid', false);
        }

    } else {
        // Formular zur Sperrung anzeigen
        $res_lock = safe_query("SELECT * FROM settings_site_lock");
        $ds = mysqli_fetch_assoc($res_lock);
        $reason = $ds['reason'] ?? '';

        $CAPCLASS = new \nexpell\Captcha;
        $CAPCLASS->createTransaction();
        $hash = $CAPCLASS->getHash();

        echo '<form method="post" action="">
            <div class="mb-3">
                <small class="form-text text-muted d-block mb-2">' . $languageService->get('you_can_use_html') . '</small>
                <textarea class="form-control" data-editor="nx_editor" id="reason" name="reason" rows="10">' . htmlspecialchars($reason) . '</textarea>
            </div>
            <input type="hidden" name="captcha_hash" value="' . $hash . '" />
            <button class="btn btn-danger" type="submit" name="submit">
                ' . $languageService->get('lock') . '
            </button>
        </form>';
    }

} else {
    // Seite ist gesperrt – Entsperrformular anzeigen/verarbeiten
    if (isset($_POST['submit']) && !isset($_POST['unlock'])) {
        nx_alert('danger', 'alert_unlock_activation_required', false);
    }

    // Entsperren nur dann verarbeiten, wenn Checkbox aktiv ist.
    if (isset($_POST['submit']) && isset($_POST['unlock'])) {
        $CAPCLASS = new \nexpell\Captcha;

        if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
            safe_query("UPDATE settings SET closed = '0'");

            // AUDIT: Site Lock deaktiviert
            nx_audit(
                'UPDATE',
                'settings',
                'site_lock',
                'Seite entsperrt',
                ['unlock_time' => time()]
            );

            nx_redirect('admincenter.php?site=site_lock', 'success', 'page_unlocked', false);
        } else {
            nx_alert('danger', 'transaction_invalid', false);
        }
    }

    // Formular zur Entsperrung anzeigen (immer, solange die Seite gesperrt ist)
    $res_lock = safe_query("SELECT * FROM settings_site_lock");
    $ds = mysqli_fetch_assoc($res_lock);
    $locked_since = isset($ds['time']) ? date("d.m.Y - H:i", $ds['time']) : '-';

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    echo '<form method="post" action="">
        <h5>' . $languageService->get('locked_since') . ' <strong>' . $locked_since . '</strong></h5>
        <div class="alert alert-info" role="alert">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="unlock" id="unlockCheck" />
                <label class="form-check-label" for="unlockCheck"> ' . $languageService->get('unlock_activation') . '</label>
            </div>
        </div>
        <input type="hidden" name="captcha_hash" value="' . $hash . '" />
        <button class="btn btn-success" type="submit" name="submit">
            ' . $languageService->get('unlock') . '
        </button>
    </form>';
}
?>
