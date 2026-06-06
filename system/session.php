<?php
namespace nexpell;

// Diese Datei sehr früh einbinden, vor JEDEM Output!



if (session_status() !== PHP_SESSION_ACTIVE) {
    // Session-Name möglichst früh setzen, bevor sie gestartet wird
    if (session_name() !== 'nexpell') {
        session_name('nexpell');
    }

    // Cookie-Parameter setzen, bevor session_start()
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',        // ggf. deine Domain eintragen
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Optional: ini_set nur, wenn Header noch nicht gesendet wurden
    if (!headers_sent()) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
    }

    // Start erst, wenn sicher noch nichts gesendet wurde
    if (!headers_sent()) {
        session_start();
    } else {
        // Notfall: brich sauber ab oder logge
        error_log('Session could not start: headers already sent.');
    }
}

?>