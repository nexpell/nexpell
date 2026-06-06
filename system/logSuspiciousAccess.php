<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================
// OUTPUT-PUFFER UND SETTINGS
// ==========================
ob_start(); // Puffer für alle Ausgaben
ini_set('display_errors', 0); // Fehler nicht direkt ausgeben

// ==========================
// IP ANONYMISIERUNG FÜR DSGVO
// ==========================
function anonymize_ip($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            //$parts[2] = 'xxx';
            //$parts[3] = 'xxx';
            return implode('.', $parts);
        }
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        if (count($parts) >= 8) {
            //$parts[4] = 'xxxx';
            //$parts[5] = 'xxxx';
            $parts[6] = 'xxxx';
            $parts[7] = 'xxxx';
            return implode(':', $parts);
        }
    }
    return $ip;
}

// ==========================
// FUNKTIONEN
// ==========================

// Sensible Keys dynamisch erkennen und maskieren
function maskSensitiveData(array $data): array {
    foreach ($data as $key => &$value) {
        if (is_array($value)) {
            $value = maskSensitiveData($value); // Rekursiv prüfen
        } elseif (stripos($key, 'pass') !== false) { // "pass" im Namen?
            $value = '[HIDDEN]';
        }
    }
    return $data;
}

// Verdächtigen Zugriff protokollieren
function logSuspiciousAccess(string $reason = '', array $details = []): void {
    $logDir = __DIR__ . '/../admin/logs';
    $logfile = $logDir . '/suspicious_access.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // IP anonymisieren
    $ip = anonymize_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    // Daten maskieren
    $maskedGet = maskSensitiveData($_GET);
    $maskedPost = maskSensitiveData($_POST);
    $maskedDetails = maskSensitiveData($details);

    $logEntry  = date('Y-m-d H:i:s') . " - Grund: $reason - IP: " . $ip . PHP_EOL;
    $logEntry .= "URL: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . PHP_EOL;
    $logEntry .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . PHP_EOL;
    $logEntry .= "GET: " . json_encode($maskedGet, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $logEntry .= "POST: " . json_encode($maskedPost, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    if (!empty($maskedDetails)) {
        $logEntry .= "Details: " . json_encode($maskedDetails, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    $logEntry .= str_repeat('-', 40) . PHP_EOL;

    file_put_contents($logfile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Prüfen auf verdächtige Eingaben
function detectSuspiciousInput(array $input): ?array {
    // SQL-Injection relevante Muster (entschärft)
    #$pattern = '/\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP)\b\s+\w+/i';
    $pattern = '/\b(UNION\s+SELECT|SELECT\s+\*|INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|DROP\s+(TABLE|DATABASE))\b/i';


    // Felder, die Content enthalten dürfen
    #$whitelist = [
    #    'message', 'post_text', 'comment', 'content', 'body', 'description',
    #    'csrf_token', 'token', 'site', 'action'
    #];

    $whitelist = [
        'message', 'post_text', 'comment', 'content', 'body', 'description',
        'csrf_token', 'token', 'site', 'action', 'search', 'q'
    ];

    foreach ($input as $key => $value) {
        // Passwortfelder komplett ignorieren
        if (stripos($key, 'pass') !== false) {
            continue;
        }

        // Whitelist Felder überspringen
        if (in_array(strtolower($key), $whitelist, true)) {
            continue;
        }

        // Token / Hash Felder überspringen
        if (preg_match('/token|hash|nonce/i', $key)) {
            continue;
        }

        if (is_array($value)) {
            $result = detectSuspiciousInput($value);
            if ($result !== null) {
                return $result;
            }
        } elseif (preg_match($pattern, (string)$value)) {
            return ['param' => $key, 'value' => $value, 'reason' => 'SQL pattern detected'];
        }
    }
    return null;
}


// ==========================
// IP-BLOCK SYSTEM
// ==========================
$ip = anonymize_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'); // anonymisierte IP
$logDir = __DIR__ . '/../admin/logs';
$blockfile = $logDir . '/blocked_ips.json';
$logfile = $logDir . '/block_log.txt';

if (!is_dir($logDir)) mkdir($logDir, 0755, true);

// JSON laden, falls vorhanden
$blocked = file_exists($blockfile) ? json_decode(file_get_contents($blockfile), true) : [];

$now = time();
$blocked = array_filter($blocked, function($b) use ($now) {
    return isset($b['until']) && $b['until'] > $now;
});

// zurückschreiben
file_put_contents($blockfile, json_encode(array_values($blocked), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Prüfen, ob IP aktuell blockiert ist
foreach ($blocked as $b) {
    if ($b['ip'] === $ip) {
        $entry = date('Y-m-d H:i:s') . " - Blocked access from IP: $ip, reason: " 
                 . ($b['reason'] ?? 'unknown') 
                 . ", level: " . ($b['level'] ?? 'warning') . PHP_EOL;
        file_put_contents($logfile, $entry, FILE_APPEND);

        http_response_code(403);
        exit;
    }
}

// Funktion zum Sperren einer IP (mit Details ins Log)
function blockIP($ip, $reason = '', $level = 'warning', $duration = 3600, $details = []) {
    global $blockfile, $blocked, $logfile;

    $entry = [
        'ip' => $ip,
        'reason' => $reason,
        'level' => $level,
        'date' => date('Y-m-d H:i:s'),
        'until' => time() + $duration
    ];

    $blocked[] = $entry;
    file_put_contents($blockfile, json_encode($blocked, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $extra = '';
    if (!empty($details)) {
        $extra = " | Details: " . json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $logEntry = date('Y-m-d H:i:s') 
        . " - IP blocked: $ip, reason: $reason, level: $level, until: " 
        . date('Y-m-d H:i:s', $entry['until'])
        . $extra . PHP_EOL;

    file_put_contents($logfile, $logEntry, FILE_APPEND);
}

// ==========================
// Verdächtige Eingaben prüfen
// ==========================
foreach (['GET' => $_GET, 'POST' => $_POST] as $method => $data) {
    if ($suspicious = detectSuspiciousInput($data)) {
        $level = 'critical';
        $reason = "Verdächtige Eingabe in $method";

        logSuspiciousAccess($reason, $suspicious);
        blockIP($ip, $reason, $level, 3600, $suspicious);

        http_response_code(403);
        exit;
    }
}

// ==========================
// URL-PRÜFUNG & WHITELIST FÜR ALLE INPUTS
// ==========================

// Optional: Whitelist für erlaubte Domains   muss ich noch mal verfeinern!!!
//Blockiert das Profil beim eingen von URL, Sperre sollte man nur bei bestimmten Seiten mit einbauen (Kommentare zB)
/*$allowed_domains = [
    // Eigene Seite
    'nexpell.de',
    'www.nexpell.de',

    // Social Media
    'facebook.com',
    'www.facebook.com',
    'twitter.com',
    'www.twitter.com',
    'instagram.com',
    'www.instagram.com',
    'tiktok.com',
    'www.tiktok.com',

    // Video/Streaming
    'youtube.com',
    'www.youtube.com',
    'youtu.be',
    'twitch.tv',
    'www.twitch.tv',
    'vimeo.com',
    'www.vimeo.com',

    // Andere mögliche Dienste
    'discord.com',
    'www.discord.com',
    'github.com',
    'www.github.com'
];


// Prüft beliebige Eingaben rekursiv
function checkInputForUrls(array $input, string $method = 'GET') {
    global $allowed_domains;

    foreach ($input as $key => $value) {
        if (is_array($value)) {
            checkInputForUrls($value, $method);
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {

            if (!validate_url($value)) {
                // ungültige URL
                logSuspiciousAccess("Ungültige URL in $method: $key", ['value' => $value]);
                blockIP($_SERVER['REMOTE_ADDR'] ?? 'unknown', "Ungültige URL: $value", 'critical', 3600);
                http_response_code(403);
                exit;
            }

            // Domain prüfen
            $host = parse_url($value, PHP_URL_HOST);
            if (!in_array($host, $allowed_domains)) {
                logSuspiciousAccess("Nicht erlaubte Domain in $method: $key", ['value' => $value]);
                blockIP($_SERVER['REMOTE_ADDR'] ?? 'unknown', "Nicht erlaubte Domain: $host", 'critical', 3600);
                http_response_code(403);
                exit;
            }
        }
    }
}

// ==========================
// ALLE INPUTS PRÜFEN
// ==========================
checkInputForUrls($_GET, 'GET');
checkInputForUrls($_POST, 'POST');

// Optional: JSON-Input prüfen (z.B. bei AJAX)
$raw = file_get_contents('php://input');
if ($raw && $json = json_decode($raw, true)) {
    checkInputForUrls($json, 'JSON');
}*/

// Ausgabe-Puffer freigeben
ob_end_flush();
