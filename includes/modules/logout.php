<?php
declare(strict_types=1);

use nexpell\LoginCookie;

// ⚠️ Wichtig: KEIN session_start() hier!
// Die Session sollte im Front-Entry (index.php) über system/session.php gestartet werden.

// DB-Handle holen (kann null sein)
$mysqli = $GLOBALS['_database'] ?? null;

// ---------- Benutzer-Status sauber ausloggen (wenn DB verfügbar) ----------
if (isset($_SESSION['userID']) && is_numeric($_SESSION['userID']) && $mysqli instanceof mysqli) {
    $userID = (int) $_SESSION['userID'];

    try {
        // Letzte Sessiondaten lesen
        $stmt = $mysqli->prepare(
            "SELECT login_time, last_activity, total_online_seconds, is_online 
             FROM users WHERE userID = ?"
        );
        if ($stmt) {
            $stmt->bind_param("i", $userID);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($user && !empty($user['is_online'])) {
                $login_time     = !empty($user['login_time']) ? strtotime((string)$user['login_time']) : time();
                $last_activity  = !empty($user['last_activity']) ? strtotime((string)$user['last_activity']) : $login_time;
                $session_seconds  = max(0, time() - $login_time);
                $activity_seconds = max(0, time() - $last_activity);
                $new_total        = (int)$user['total_online_seconds'] + $session_seconds + $activity_seconds;

                if ($stmt = $mysqli->prepare(
                    "UPDATE users
                        SET total_online_seconds = ?,
                            login_time = NULL,
                            is_online = 0,
                            last_activity = NULL
                      WHERE userID = ?"
                )) {
                    $stmt->bind_param("ii", $new_total, $userID);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // Falls Datensatz fehlt: trotzdem is_online resetten, aber ohne Zeiten
                if ($stmt = $mysqli->prepare("UPDATE users SET is_online = 0 WHERE userID = ?")) {
                    $stmt->bind_param("i", $userID);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    } catch (Throwable $e) {
        error_log('logout.php DB error: '.$e->getMessage());
        // Logout soll trotzdem weiterlaufen
    }
}

// ---------- Session & Cookies löschen ----------
if (session_status() === PHP_SESSION_ACTIVE) {
    // Session-Variablen leeren
    $_SESSION = [];

    // Session-Cookie invalidieren
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path']   ?? '/',
            $params['domain'] ?? '',
            !empty($_SERVER['HTTPS']),
            true
        );
    }

    // Session zerstören
    session_destroy();
}

// App-Cookies löschen (sofern gesetzt)
if (class_exists(LoginCookie::class)) {
    // dein Helper: entfernt ws_auth sicher inkl. Path/Domain
    LoginCookie::clear('ws_auth');
}
// Fallback/weitere Cookies (Pfad evtl. anpassen)
setcookie('ws_session', '', time() - 3600, '/');
setcookie('ws_cookie',  '', time() - 3600, '/');

// ---------- Antwort/Redirect ----------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // z.B. fetch/sendBeacon-Logout
    http_response_code(204);
    exit;
}

// Nur redirecten, wenn noch keine Header gesendet wurden
if (!headers_sent()) {
    header('Location: /', true, 302);
}
exit;
