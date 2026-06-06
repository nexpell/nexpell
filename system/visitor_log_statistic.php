<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Benutzer-ID prüfen
$user_id = null;
if (!empty($_SESSION['userID'])) {
    $user_id = (int)$_SESSION['userID'];
} elseif (!empty($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
}

require_once __DIR__ . '/logSuspiciousAccess.php';

if (!defined('VISITOR_ONLINE_WINDOW_SECONDS')) {
    // One shared window for tracking, cleanup and display.
    define('VISITOR_ONLINE_WINDOW_SECONDS', 600);
}

if (!defined('VISITOR_HISTORY_WINDOW_SECONDS')) {
    define('VISITOR_HISTORY_WINDOW_SECONDS', 86400);
}

if (!defined('VISITOR_PAGEVIEW_DEDUP_SECONDS')) {
    // Refreshes of the same page inside this window should not create
    // additional pageviews for the same visitor/day bucket.
    define('VISITOR_PAGEVIEW_DEDUP_SECONDS', 60);
}

// DB-Verbindung
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    die('DB Connection failed: ' . $_database->connect_error);
}

/**
 * Prüft, ob ein Besucher ein Bot ist
 */
function isBot(string $user_agent): bool {
    $bots = ['bot', 'crawl', 'spider', 'slurp', 'mediapartners'];
    foreach ($bots as $b) {
        if (stripos($user_agent, $b) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Liefert SQL-Bedingung zum Ausschließen von Bots
 */
function getBotCondition(string $alias = ''): string {
    $bots = ['bot', 'crawl', 'spider', 'slurp', 'mediapartners'];
    $field = $alias ? "$alias.user_agent" : "user_agent";
    $bot_condition = '';
    foreach ($bots as $b) {
        $bot_condition .= " AND $field NOT LIKE '%" . $b . "%'";
    }
    return $bot_condition;
}

/**
 * IP anonymisieren
 */
if (!function_exists('anonymize_ip')) {
    function anonymize_ip(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\d+$/', '0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return substr($ip, 0, strlen($ip)-4) . '0000';
        }
        return $ip;
    }
}

if (!function_exists('is_public_ip')) {
    function is_public_ip(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}

/**
 * Liefert den Ländercode anhand der IP (mit Caching über daily_iplist)
 */
if (!function_exists('normalize_visitor_country_code')) {
    function normalize_visitor_country_code(?string $country_code): string {
        $country_code = strtolower(trim((string)$country_code));
        $country_code = str_replace(['_', '.'], '-', $country_code);
        $country_code = preg_replace('/\s+/', ' ', $country_code);

        if ($country_code === '' || $country_code === 'unknown') {
            return 'unknown';
        }

        if (preg_match('/^[a-z]{2}-[a-z0-9]+$/', $country_code)) {
            $country_code = substr($country_code, 0, 2);
        }

        $country_aliases = [
            'de' => 'de',
            'deu' => 'de',
            'ger' => 'de',
            'germany' => 'de',
            'deutschland' => 'de',
            'at' => 'at',
            'aut' => 'at',
            'austria' => 'at',
            'oesterreich' => 'at',
            'ch' => 'ch',
            'che' => 'ch',
            'switzerland' => 'ch',
            'schweiz' => 'ch',
            'fr' => 'fr',
            'fra' => 'fr',
            'france' => 'fr',
            'it' => 'it',
            'ita' => 'it',
            'italy' => 'it',
            'italien' => 'it',
            'es' => 'es',
            'esp' => 'es',
            'spain' => 'es',
            'spanien' => 'es',
            'gb' => 'gb',
            'gbr' => 'gb',
            'uk' => 'gb',
            'united kingdom' => 'gb',
            'great britain' => 'gb',
            'england' => 'gb',
            'en' => 'gb',
            'us' => 'us',
            'usa' => 'us',
            'united states' => 'us',
            'united states of america' => 'us',
            'vereinigte staaten' => 'us',
            'nl' => 'nl',
            'nld' => 'nl',
            'netherlands' => 'nl',
            'niederlande' => 'nl',
            'be' => 'be',
            'bel' => 'be',
            'belgium' => 'be',
            'belgien' => 'be',
            'pl' => 'pl',
            'pol' => 'pl',
            'poland' => 'pl',
            'polen' => 'pl',
            'pt' => 'pt',
            'por' => 'pt',
            'portugal' => 'pt',
            'tr' => 'tr',
            'tur' => 'tr',
            'turkey' => 'tr',
            'tuerkei' => 'tr',
            'ru' => 'ru',
            'rus' => 'ru',
            'russia' => 'ru',
            'russland' => 'ru',
        ];

        if (isset($country_aliases[$country_code])) {
            return $country_aliases[$country_code];
        }

        $country_code_compact = preg_replace('/[^a-z]/', '', $country_code);
        if (isset($country_aliases[$country_code_compact])) {
            return $country_aliases[$country_code_compact];
        }

        if (preg_match('/^[a-z]{2}$/', $country_code)) {
            return $country_code;
        }

        return 'unknown';
    }
}

function getCountryCode(string $ip): string {
    global $_database;
    if (!filter_var($ip, FILTER_VALIDATE_IP) || in_array($ip, ['127.0.0.1', '::1'])) return 'unknown';

    // Prüfen, ob die IP bereits heute gespeichert wurde
    $date = date('Y-m-d');
    $stmt = $_database->prepare("SELECT country_code FROM visitor_daily_iplist WHERE ip = ? AND dates = ? LIMIT 1");
    $stmt->bind_param('ss', $ip, $date);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if (!empty($res['country_code'])) {
        $cached_country = normalize_visitor_country_code($res['country_code']);
        if ($cached_country !== 'unknown') {
            return $cached_country;
        }
    }

    // API-Request nur einmal pro IP/Tag
    $url = "http://ip-api.com/json/" . rawurlencode($ip) . "?fields=status,message,countryCode";
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'ignore_errors' => true,
            'header' => "User-Agent: nexpell-visitor-tracker\r\n",
        ],
    ]);
    $data = file_get_contents($url, false, $context);
    if ($data !== false) {
        $json = json_decode($data, true);
        if (!empty($json['status']) && $json['status'] === 'success' && !empty($json['countryCode'])) {
            $country = normalize_visitor_country_code($json['countryCode']);
            $time = time();
            $stmt_insert = $_database->prepare("
                INSERT INTO visitor_daily_iplist (dates, del, ip, country_code)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE del = VALUES(del), country_code = VALUES(country_code)
            ");
            $stmt_insert->bind_param('siss', $date, $time, $ip, $country);
            $stmt_insert->execute();
            return $country;
        }
    }
    return 'unknown';
}

function getVisitorClientIp(): string {
    $header_candidates = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
    ];

    foreach ($header_candidates as $header) {
        $value = trim((string)($_SERVER[$header] ?? ''));
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    $forwarded_for = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded_for !== '') {
        $forwarded_ips = array_map('trim', explode(',', $forwarded_for));

        foreach ($forwarded_ips as $forwarded_ip) {
            if ($forwarded_ip !== '' && is_public_ip($forwarded_ip)) {
                return $forwarded_ip;
            }
        }

        foreach ($forwarded_ips as $forwarded_ip) {
            if ($forwarded_ip !== '' && filter_var($forwarded_ip, FILTER_VALIDATE_IP)) {
                return $forwarded_ip;
            }
        }
    }

    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Device-Typ erkennen
 */
function getDeviceType(string $user_agent): string {
    $ua = strtolower($user_agent);
    if (strpos($ua, 'mobile') !== false) return 'Mobile';
    if (strpos($ua, 'tablet') !== false) return 'Tablet';
    return 'Desktop';
}

/**
 * Betriebssystem erkennen
 */
function getOS(string $user_agent): string {
    $ua = strtolower($user_agent);
    if (strpos($ua, 'android') !== false) return 'Android';
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) return 'iOS';
    if (strpos($ua, 'windows') !== false) return 'Windows';
    if (strpos($ua, 'mac') !== false) return 'Mac';
    if (strpos($ua, 'linux') !== false) return 'Linux';
    return 'Unknown';
}

/**
 * Browser erkennen
 */
function getBrowser(string $user_agent): string {
    $ua = strtolower($user_agent);
    if (strpos($ua, 'edg/') !== false || strpos($ua, 'edge') !== false) return 'Edge';
    if (strpos($ua, 'opr/') !== false || strpos($ua, 'opera') !== false) return 'Opera';
    if (strpos($ua, 'firefox') !== false) return 'Firefox';
    if (strpos($ua, 'chrome') !== false || strpos($ua, 'crios') !== false) return 'Chrome';
    if (strpos($ua, 'safari') !== false) return 'Safari';
    return 'Unknown';
}

/**
 * Referer prüfen / fallback
 */
function getReferer(): string {
    return $_SERVER['HTTP_REFERER'] ?? 'direct';
}

function shouldTrackVisitorRequest(?string $request_uri = null): bool {
    $request_uri = (string)($request_uri ?? ($_SERVER['REQUEST_URI'] ?? ''));
    $path = (string)parse_url($request_uri, PHP_URL_PATH);
    $path = strtolower($path);

    if ($path === '') {
        return true;
    }

    if (preg_match('/\.(?:svg|png|jpe?g|gif|webp|ico|css|js|map|json|xml|txt|woff2?|ttf|eot|otf|mp4|webm|mp3|wav|pdf|zip)$/', $path)) {
        return false;
    }

    return true;
}

/**
 * Live-Visitor Tracking
 */
function live_visitor_track(string $default_site = 'startpage') {
    global $_database, $_SESSION;
    $time = time();
    $ip = getVisitorClientIp();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $userID = $_SESSION['userID'] ?? null;
    $site = $_SERVER['REQUEST_URI'] ?? $default_site;
    $country_code = getCountryCode($ip);

    if (isBot($user_agent)) return;

    $deltime = $time - VISITOR_ONLINE_WINDOW_SECONDS;
    $wasdeltime = $time - VISITOR_HISTORY_WINDOW_SECONDS;

    // Alte Einträge löschen
    $_database->query("DELETE FROM visitors_live WHERE time < " . (int)$deltime);
    $_database->query("DELETE FROM visitors_live_history WHERE time < " . (int)$wasdeltime);

    if (!empty($userID)) {
        $stmt = $_database->prepare("
            INSERT INTO visitors_live (time, userID, ip, site, country_code, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                time = VALUES(time),
                site = VALUES(site),
                country_code = VALUES(country_code),
                user_agent = VALUES(user_agent)
        ");
        $stmt->bind_param('iissss', $time, $userID, $ip, $site, $country_code, $user_agent);
        $stmt->execute();

        // history max 1 Eintrag pro Minute
        $one_min_ago = $time - 60;
        $stmt = $_database->prepare("
            INSERT INTO visitors_live_history (time, userID, ip, site, country_code, user_agent)
            SELECT ?, ?, ?, ?, ?, ?
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM visitors_live_history
                WHERE userID = ? AND time > ?
            )
        ");
        $stmt->bind_param('iissssii', $time, $userID, $ip, $site, $country_code, $user_agent, $userID, $one_min_ago);
        $stmt->execute();

    } else {
        $time_limit = $time - 60;
        $stmt_check = $_database->prepare("
            SELECT id FROM visitors_live WHERE ip = ? AND time > ? ORDER BY time DESC LIMIT 1
        ");
        $stmt_check->bind_param('si', $ip, $time_limit);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows === 0) {
            $stmt = $_database->prepare("
                INSERT INTO visitors_live (time, ip, site, country_code, user_agent)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    time = VALUES(time),
                    site = VALUES(site),
                    country_code = VALUES(country_code),
                    user_agent = VALUES(user_agent)
            ");
            $stmt->bind_param('issss', $time, $ip, $site, $country_code, $user_agent);
            $stmt->execute();
        }

        // history max 1 entry per minute per guest IP
        $one_min_ago = $time - 60;
        $stmt_guest_history = $_database->prepare("
            INSERT INTO visitors_live_history (time, userID, ip, site, country_code, user_agent)
            SELECT ?, NULL, ?, ?, ?, ?
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1
                FROM visitors_live_history
                WHERE userID IS NULL AND ip = ? AND time > ?
            )
        ");
        $stmt_guest_history->bind_param('isssssi', $time, $ip, $site, $country_code, $user_agent, $ip, $one_min_ago);
        $stmt_guest_history->execute();
    }
}

/**
 * Besucher in Daily-IP-Liste erfassen
 */
function track_daily_ip() {
    global $_database;
    $ip = getVisitorClientIp();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $time = time();
    $date = date('Y-m-d');

    if (isBot($user_agent)) return;

    $country_code = getCountryCode($ip);
    $deltime = $time - 86400;

    // Alte Einträge löschen
    $stmt_delete_daily = $_database->prepare("DELETE FROM visitor_daily_iplist WHERE del < ?");
    $stmt_delete_daily->bind_param('i', $deltime);
    $stmt_delete_daily->execute();

    // Eintrag nur einmal pro Tag/IP
    $stmt_check = $_database->prepare("SELECT ip FROM visitor_daily_iplist WHERE ip = ? AND dates = ?");
    $stmt_check->bind_param('ss', $ip, $date);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    if ($result->num_rows === 0) {
        $stmt_insert = $_database->prepare("INSERT INTO visitor_daily_iplist (dates, del, ip, country_code) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param('siss', $date, $time, $ip, $country_code);
        $stmt_insert->execute();
    }
}

/**
 * Update des Tageszählers
 */
function update_daily_counter() {
    global $_database, $_SESSION;
    $ip = getVisitorClientIp();
    $user_id = $_SESSION['userID'] ?? null;
    $time = time();
    $date = date('Y-m-d');
    $ten_minutes_ago = date('Y-m-d H:i:s', $time - VISITOR_ONLINE_WINDOW_SECONDS);
    $ip_hash = hash('sha256', $ip);

    if (isBot($_SERVER['HTTP_USER_AGENT'] ?? '')) return;

    if ($user_id) {
        $stmt_check_hit = $_database->prepare("SELECT id FROM visitor_daily_counter_hits WHERE date = ? AND user_id = ?");
        $stmt_check_hit->bind_param('si', $date, $user_id);
    } else {
        $stmt_check_hit = $_database->prepare("SELECT id FROM visitor_daily_counter_hits WHERE date = ? AND ip_hash = ?");
        $stmt_check_hit->bind_param('ss', $date, $ip_hash);
    }
    $stmt_check_hit->execute();
    $already_counted = $stmt_check_hit->get_result()->fetch_assoc();

    if (!$already_counted) {
        $stmt_online = $_database->prepare("
            SELECT COUNT(DISTINCT ip_hash) AS online_count
            FROM visitor_statistics
            WHERE last_seen >= ?
        ");
        $stmt_online->bind_param('s', $ten_minutes_ago);
        $stmt_online->execute();
        $online_result = $stmt_online->get_result()->fetch_assoc();
        $online_count = (int)($online_result['online_count'] ?? 0);

        $update_today = $_database->prepare("
            INSERT INTO visitor_daily_counter (date, hits, online, maxonline)
            VALUES (?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE hits = hits + 1, online = ?, maxonline = GREATEST(maxonline, ?)
        ");
        $update_today->bind_param('siiii', $date, $online_count, $online_count, $online_count, $online_count);
        $update_today->execute();

        if ($user_id) {
            $stmt_insert_hit = $_database->prepare("INSERT INTO visitor_daily_counter_hits (date, user_id) VALUES (?, ?)");
            $stmt_insert_hit->bind_param('si', $date, $user_id);
        } else {
            $stmt_insert_hit = $_database->prepare("INSERT INTO visitor_daily_counter_hits (date, ip_hash) VALUES (?, ?)");
            $stmt_insert_hit->bind_param('ss', $date, $ip_hash);
        }
        $stmt_insert_hit->execute();
    }
}

/**
 * Archiviert die Besucher des Vortags
 */
function archive_yesterday_stats() {
    global $_database;
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $stmt_check = $_database->prepare("SELECT id FROM visitor_daily_stats WHERE date = ?");
    $stmt_check->bind_param('s', $yesterday);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        $stmt_get = $_database->prepare("SELECT date, hits, online, maxonline FROM visitor_daily_counter WHERE date = ?");
        $stmt_get->bind_param('s', $yesterday);
        $stmt_get->execute();
        $row = $stmt_get->get_result()->fetch_assoc();

        if ($row) {
            $stmt_insert = $_database->prepare("INSERT INTO visitor_daily_stats (date, hits, online, maxonline) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param('siii', $row['date'], $row['hits'], $row['online'], $row['maxonline']);
            $stmt_insert->execute();
        }
    }
}

/**
 * Alte visitor_statistics (>30 Tage) löschen
 */
function clean_old_statistics() {
    global $_database;
    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmt_delete_main = $_database->prepare("DELETE FROM visitor_statistics WHERE created_at < ?");
    $stmt_delete_main->bind_param('s', $thirty_days_ago);
    $stmt_delete_main->execute();
}

/**
 * Besucher-Statistiken pro Seite loggen
 */
function log_visitor_statistics() {
    global $_database, $_SESSION;
    $user_id = $_SESSION['userID'] ?? $_SESSION['user_id'] ?? 0;
    $client_ip = getVisitorClientIp();
    $ip = anonymize_ip($client_ip);
    $ip_hash = hash('sha256', $ip . '_' . $user_id);
    $page = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $now = date('Y-m-d H:i:s');
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    if (isBot($user_agent)) return;

    $country_code = getCountryCode($client_ip) ?: 'unknown';
    $device_type  = getDeviceType($user_agent) ?: 'Unknown';
    $os           = getOS($user_agent) ?: 'Unknown';
    $browser      = getBrowser($user_agent) ?: 'Unknown';
    $referer      = getReferer() ?: 'direct';
    $pageviews    = 1;

    $bot_condition_sql = getBotCondition();
    $check = $_database->prepare("
        SELECT id, page, last_seen
        FROM visitor_statistics
        WHERE ip_hash = ? AND created_at BETWEEN ? AND ? $bot_condition_sql
        ORDER BY id DESC
        LIMIT 1
    ");
    $check->bind_param('sss', $ip_hash, $today_start, $today_end);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc() ?: null;

    if (!$existing) {
        $insert = $_database->prepare("
            INSERT INTO visitor_statistics
                (user_id, ip_address, pageviews, page, country_code, device_type, os, browser, ip_hash, referer, user_agent, last_seen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->bind_param('isssssssssss', $user_id, $ip, $pageviews, $page, $country_code, $device_type, $os, $browser, $ip_hash, $referer, $user_agent, $now);
        $insert->execute();
    } else {
        $existingId = (int)($existing['id'] ?? 0);
        $previousPage = (string)($existing['page'] ?? '');
        $previousSeen = (string)($existing['last_seen'] ?? '');
        $previousTs = ($previousSeen !== '' && strtotime($previousSeen) !== false) ? (int)strtotime($previousSeen) : 0;

        $shouldIncrementPageviews = true;
        if (
            $existingId > 0 &&
            $previousPage === $page &&
            $previousTs > 0 &&
            (time() - $previousTs) < VISITOR_PAGEVIEW_DEDUP_SECONDS
        ) {
            $shouldIncrementPageviews = false;
        }

        $sql = "
            UPDATE visitor_statistics
            SET " . ($shouldIncrementPageviews ? "pageviews = pageviews + 1," : "") . "
                page = ?,
                country_code = ?,
                device_type = ?,
                os = ?,
                browser = ?,
                referer = ?,
                user_agent = ?,
                last_seen = ?
            WHERE id = ?
        ";
        $update = $_database->prepare($sql);
        $update->bind_param('ssssssssi', $page, $country_code, $device_type, $os, $browser, $referer, $user_agent, $now, $existingId);
        $update->execute();
    }
}

// --- Alle Funktionen aufrufen ---
if (shouldTrackVisitorRequest()) {
    live_visitor_track();
    track_daily_ip();
    update_daily_counter();
    archive_yesterday_stats();
    clean_old_statistics();
    log_visitor_statistics();
}
?>
