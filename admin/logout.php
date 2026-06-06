<?php

// Überprüfen, ob die Session bereits gestartet wurde
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Namespace verwenden (falls vorhanden)
use nexpell\LoginCookie;

// Session starten, falls noch nicht aktiv
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cache-Header setzen, um alte Session nicht wiederherzustellen
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Session-Daten löschen
$_SESSION = [];

// Session-Cookie löschen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"] ?? '/',
        $params["domain"] ?? '',
        $params["secure"] ?? false,
        $params["httponly"] ?? true
    );
}

// Session endgültig zerstören
session_destroy();

// Login-Cookie (Auto-Login) löschen, wenn LoginCookie-Klasse verwendet wird
if (class_exists(LoginCookie::class)) {
    LoginCookie::clear('ws_auth'); // oder je nach Cookie-Name anpassen
}

// Zusätzliche benutzerdefinierte Cookies löschen
setcookie('ws_session', '', time() - 3600, '/');
setcookie('ws_cookie', '', time() - 3600, '/');

// Optional: Benutzer-Logout loggen
// logLogout($_SESSION['userID']); // Funktion definieren, falls Logging gewünscht

// Logout-Funktion
function logout() {
    // Falls du noch eine Datenbankfunktion zum Löschen der Session-Daten benötigst
    if (isset($_SESSION['userID'])) {
        deleteSessionFromDatabase($_SESSION['userID']);
    }

    // Session-Daten aus der aktuellen Sitzung löschen
    session_unset();
    session_destroy();

    // Auch sicherstellen, dass alle relevanten Cookies gelöscht werden
    setcookie(session_name(), '', time() - 3600, '/'); // Session-Cookie
    setcookie('ws_auth', '', time() - 3600, '/'); // Auto-Login-Cookie, falls verwendet
    setcookie('ws_session', '', time() - 3600, '/'); // Benutzerdefiniertes Cookie

    // Weiterleitung zur Start- oder Loginseite
    header("Location: /admin/login.php"); // Weiterleitung zur Login-Seite
    exit;
}

// Aufruf der Logout-Funktion, wenn erforderlich
logout();
