<?php
use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Angemeldeter Benutzer
$userID = (int)$_SESSION['userID'];
$adminName = $_SESSION['username'] ?? 'Admin';

// Frontend-URL
define('FRONTEND_URL', '../index.php');

/**
 * Prüft, ob eine Tabelle existiert.
 */
function nx_table_exists(mysqli $db, string $table): bool {
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return (int)($row['c'] ?? 0) > 0;
}

/**
 * Activity-Objekt
 */
function nx_activity_item(array $a): array {
    return [
        'ts'       => (int)($a['ts'] ?? 0),
        'source'   => (string)($a['source'] ?? 'Info'),
        'severity' => (string)($a['severity'] ?? 'info'),
        'title'    => (string)($a['title'] ?? ''),
        'meta'     => (string)($a['meta'] ?? ''),
        'title_html'=> (string)($a['title_html'] ?? ''),
        'meta_html' => (string)($a['meta_html'] ?? ''),
        'url'      => (string)($a['url'] ?? ''),
        'icon'     => (string)($a['icon'] ?? 'bi-activity'),

        // User UI
        'user_id'      => (int)($a['user_id'] ?? 0),
        'user_display' => (string)($a['user_display'] ?? ''),
        'profile_url'  => (string)($a['profile_url'] ?? ''),
        'avatar_src'   => (string)($a['avatar_src'] ?? ''),
        'user_roles'   => (string)($a['user_roles'] ?? ''),

        'action'       => (string)($a['action'] ?? ''),
        'action_text'  => (string)($a['action_text'] ?? ''),
    ];
}

/**
 * Security Summary inkl. Gründe (Zeitfenster).
 */
function nx_security_summary(mysqli $db, array $opts = []): array {
    global $languageService;

    $opts = array_merge([
        'fail_table'  => 'failed_login_attempts',
        'fail_time'   => 'attempt_time',
        'fail_ip'     => 'ip',

        'ban_table'   => 'banned_ips',
        'ban_time'    => 'deltime',
        'ban_ip'      => 'ip',

        'warn_fails_60m' => 10,
        'crit_fails_60m' => 30,

        'warn_fails_24h' => 50,
        'crit_fails_24h' => 150,

        'warn_bans_24h'  => 1,
        'crit_bans_24h'  => 5,

        'details_href'   => '?site=security_overview',
    ], $opts);

    $now = time();
    $since60  = date('Y-m-d H:i:s', $now - 3600);
    $since24h = date('Y-m-d H:i:s', $now - 86400);

    $fails60 = 0;  $ips60 = 0;
    $fails24 = 0;  $ips24 = 0;
    $bans24  = 0;  $banIps24 = 0;

    // failed logins
    if (nx_table_exists($db, $opts['fail_table'])) {
        $t  = $opts['fail_table'];
        $ts = $opts['fail_time'];
        $ip = $opts['fail_ip'];

        $sql = "SELECT COUNT(*) AS c, COUNT(DISTINCT `$ip`) AS ips
                FROM `$t`
                WHERE `$ts` >= ?";

        // 60m
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $since60);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            $fails60 = (int)($row['c'] ?? 0);
            $ips60   = (int)($row['ips'] ?? 0);
        }

        // 24h
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $since24h);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            $fails24 = (int)($row['c'] ?? 0);
            $ips24   = (int)($row['ips'] ?? 0);
        }
    }

    // bans
    if (nx_table_exists($db, $opts['ban_table'])) {
        $t  = $opts['ban_table'];
        $ts = $opts['ban_time'];
        $ip = $opts['ban_ip'];

        $sql = "SELECT COUNT(*) AS c, COUNT(DISTINCT `$ip`) AS ips
                FROM `$t`
                WHERE `$ts` >= ?";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $since24h);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            $bans24   = (int)($row['c'] ?? 0);
            $banIps24 = (int)($row['ips'] ?? 0);
        }
    }

    $severity = 'ok';
    if ($fails60 >= (int)$opts['crit_fails_60m'] || $fails24 >= (int)$opts['crit_fails_24h'] || $bans24 >= (int)$opts['crit_bans_24h']) {
        $severity = 'critical';
    } elseif ($fails60 >= (int)$opts['warn_fails_60m'] || $fails24 >= (int)$opts['warn_fails_24h'] || $bans24 >= (int)$opts['warn_bans_24h']) {
        $severity = 'warn';
    }

    $reasons = [];
    if ($fails60 > 0) {$reasons[] = sprintf($languageService->get('security_failed_logins_60min'),$fails60,$ips60); }
    if ($fails24 > 0) {$reasons[] = sprintf($languageService->get('security_failed_logins_24h'),$fails24,$ips24); }
    if ($bans24 > 0) {$reasons[] = sprintf($languageService->get('security_new_ip_bans_24h'),$bans24,$banIps24); }
    if (empty($reasons)) {$reasons[] = $languageService->get('security_no_incidents_24h'); }

    $badge = $severity === 'critical' ? 'bg-danger'
           : ($severity === 'warn' ? 'bg-warning text-dark' : 'bg-success');

    $label = $severity === 'critical'
        ? $languageService->get('severity_critical')
        : ($severity === 'warn'
        ? $languageService->get('severity_warning')
        : $languageService->get('status_ok')
        );

    return [
        'severity' => $severity,
        'badge'    => $badge,
        'label'    => $label,
        'reasons'  => $reasons,
        'why'      => implode(' · ', array_slice($reasons, 0, 2)),
        'href'     => (string)$opts['details_href'],
        'metrics'  => [
            'fails60' => $fails60, 'ips60' => $ips60,
            'fails24' => $fails24, 'ips24' => $ips24,
            'bans24'  => $bans24,  'banIps24' => $banIps24,
        ],
    ];
}

/**
 * Prüft, ob eine Spalte existiert.
 */
function nx_column_exists(mysqli $db, string $table, string $column): bool {
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return (int)($row['c'] ?? 0) > 0;
}

/**
 * Liefert die erste existierende Tabelle aus einer Kandidatenliste.
 */
function nx_first_existing_table(mysqli $db, array $candidates): ?string {
    foreach ($candidates as $t) {
        $t = trim((string)$t);
        if ($t !== '' && nx_table_exists($db, $t)) return $t;
    }
    return null;
}

/**
 * Liefert die erste existierende Spalte aus einer Kandidatenliste.
 */
function nx_first_existing_column(mysqli $db, string $table, array $candidates): ?string {
    foreach ($candidates as $c) {
        $c = trim((string)$c);
        if ($c !== '' && nx_column_exists($db, $table, $c)) return $c;
    }
    return null;
}

/**
 * Ermittlung von Version/Update/Backup.
 */
function nx_system_summary(mysqli $db, $languageService = null): array {
    if ($languageService === null && isset($GLOBALS['languageService'])) {
        $languageService = $GLOBALS['languageService'];
    }
    if ($languageService === null) {
        $languageService = new class {
            public function get(string $key): string { return $key; }
        };
    }

    $out = [
        'version'       => $languageService->get('value_na'),
        'update_status' => 'unknown',
        'backup_last'   => $languageService->get('value_na'),
        'backup_status' => 'unknown',
    ];

    // Version
    // 1) system/version.php (primär)
    $version = null;
    $versionFile = __DIR__ . '/../system/version.php';
    if (is_file($versionFile)) {
        // Version-Datei kann String oder Array zurückgeben
        $v = @include $versionFile;
        if (is_string($v) && trim($v) !== '') {
            $version = trim($v);
        } elseif (is_array($v)) {
            foreach (['version', 'cms_version', 'app_version', 'nx_version'] as $k) {
                if (!empty($v[$k]) && is_string($v[$k])) { $version = trim((string)$v[$k]); break; }
            }
        }
    }

    // 2) Constants/Globals
    if (!$version) {
        foreach (['CMS_VERSION', 'NX_VERSION', 'VERSION', 'APP_VERSION'] as $c) {
            if (defined($c) && (string)constant($c) !== '') { $version = (string)constant($c); break; }
        }
    }
    if (!$version) {
        foreach (['cms_version', 'version', 'app_version', 'nx_version'] as $g) {
            if (!empty($GLOBALS[$g])) { $version = (string)$GLOBALS[$g]; break; }
        }
    }
    if ($version) $out['version'] = $version;

    // Update-Status
$current_version = $version ?: '0.0.0';

// Lokales Update-Datum
$last_update_file = __DIR__ . '/../system/last_update.txt';
$last_update_date = null;
if (is_file($last_update_file)) {
    $content = trim((string)@file_get_contents($last_update_file));
    if ($content !== '') $last_update_date = $content;
}

$local_update_info = realpath(__DIR__ . '/../update_info_v2.json') ?: (__DIR__ . '/../update_info_v2.json');
$update_info_url = "https://update.nexpell.de/updates/update_info_v2.json";
$ctx = stream_context_create([
    'http' => ['timeout' => 2],
    'https' => ['timeout' => 2],
]);
$json = '';
if (is_file($local_update_info)) {
    $json = (string)@file_get_contents($local_update_info);
}
if ($json === '') {
    $json = (string)@file_get_contents($update_info_url, false, $ctx);
}

$out['update_count'] = 0;

if ($json === false) {
    // Keine harte Fehlermeldung im UI, aber als Status markieren
    $out['update_status'] = 'unknown';
} else {
    $update_info = json_decode($json, true);
    if (!empty($update_info['updates']) && is_array($update_info['updates'])) {
        usort($update_info['updates'], static function (array $a, array $b): int {
            $verCompare = version_compare((string)($a['version'] ?? '0'), (string)($b['version'] ?? '0'));
            if ($verCompare !== 0) {
                return $verCompare;
            }

            return ((int)($a['build'] ?? 0)) <=> ((int)($b['build'] ?? 0));
        });
    }

    $user_email = strtolower(trim((string)($_SESSION['user_email'] ?? 'info@nexpell.de')));
    $client_ip  = strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));

    $channel = 'stable';
    if (nx_table_exists($db, 'settings') && nx_column_exists($db, 'settings', 'update_channel')) {
        $stCh = $db->prepare("SELECT update_channel FROM settings LIMIT 1");
        if ($stCh) {
            $stCh->execute();
            $stCh->bind_result($tmpCh);
            if ($stCh->fetch()) {
                $tmpCh = strtolower(trim((string)$tmpCh));
                if ($tmpCh !== '') $channel = $tmpCh;
            }
            $stCh->close();
        }
    }

    $updateAvailable = false;
    $updateCount = 0;

    if (isset($update_info['updates']) && is_array($update_info['updates'])) {
        foreach ($update_info['updates'] as $update) {
            if (!is_array($update)) continue;

            $uVer = trim((string)($update['version'] ?? ''));
            if ($uVer === '' || version_compare($uVer, $current_version, '<=')) {
                continue;
            }

            // 1) Channel-Check (stable/beta/dev)
            $entryChannel = strtolower(trim((string)($update['channel'] ?? 'stable')));
            $allowedByChannel = false;

            if ($channel === 'stable') {
                $allowedByChannel = ($entryChannel === 'stable');
            } elseif ($channel === 'beta') {
                $allowedByChannel = in_array($entryChannel, ['stable', 'beta'], true);
            } elseif ($channel === 'dev') {
                $allowedByChannel = in_array($entryChannel, ['stable', 'beta', 'dev'], true);
            } else {
                // unbekannter Channel => konservativ nur stable
                $allowedByChannel = ($entryChannel === 'stable');
            }

            if (!$allowedByChannel) continue;

            // 2) visible_for: leer => sichtbar
            $visible_for = [];
            if (!empty($update['visible_for'])) {
                if (is_string($update['visible_for'])) {
                    $visible_for = array_map('trim', explode(',', $update['visible_for']));
                } elseif (is_array($update['visible_for'])) {
                    foreach ($update['visible_for'] as $entry) {
                        if (is_string($entry)) {
                            $visible_for = array_merge($visible_for, array_map('trim', explode(',', $entry)));
                        }
                    }
                }
            }

            $is_visible = true;
            if (!empty($visible_for)) {
                $is_visible = false;
                foreach ($visible_for as $v) {
                    $v = strtolower(trim((string)$v));
                    if ($v === 'all') { $is_visible = true; break; }
                    if ($user_email !== '' && $v === $user_email) { $is_visible = true; break; }
                    if ($client_ip !== '' && $v === $client_ip) { $is_visible = true; break; }
                }
            }

            if (!$is_visible) continue;

            // Treffer
            $updateAvailable = true;
            $updateCount++;
        }
    }

    $out['update_count'] = $updateCount;
    $out['update_status'] = $updateAvailable ? 'available' : 'ok';
}

    // Backup
    if (nx_table_exists($db, 'backups')) {
        if ($stmt = $db->prepare("SELECT createdate FROM backups ORDER BY createdate DESC LIMIT 1")) {
            $stmt->execute();
            $stmt->bind_result($lastBackupDate);
            $stmt->fetch();
            $stmt->close();

        if (!empty($lastBackupDate)) {
            $ts = strtotime((string)$lastBackupDate);
            if ($ts) {
                $out['backup_last'] = date('d.m.Y H:i', $ts);
                $ageDays = (time() - $ts) / 86400;
                $out['backup_status'] = ($ageDays <= 14) ? 'ok' : 'error';
            }
        }
      }
    } else {
    $backupTable = nx_first_existing_table($db, ['backups', 'cms_backups', 'backup', 'backup_logs', 'backups_log']);
        if ($backupTable) {
            $dateCol = nx_first_existing_column($db, $backupTable, ['createdate', 'createDate', 'created_at', 'created', 'backup_date', 'date', 'timestamp']);
            $statusCol = nx_first_existing_column($db, $backupTable, ['status', 'backup_status', 'result', 'state', 'success', 'ok']);

            if ($dateCol) {
                $sql = "SELECT `$dateCol`" . ($statusCol ? ", `$statusCol`" : "") . " FROM `$backupTable` ORDER BY `$dateCol` DESC LIMIT 1";
                if ($stmt = $db->prepare($sql)) {
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $stmt->close();

                    if ($row && !empty($row[$dateCol])) {
                        $ts = strtotime((string)$row[$dateCol]);
                        if ($ts) {
                            $out['backup_last'] = date('d.m.Y H:i', $ts);

                            // Status
                            $status = null;
                            if ($statusCol && array_key_exists($statusCol, $row)) {
                                $status = strtolower(trim((string)$row[$statusCol]));
                            }

                            if ($status !== null && $status !== '') {
                                if (in_array($status, ['1','true','ok','success','successful','done','completed','complete'], true)) {
                                    $out['backup_status'] = 'ok';
                                } elseif (in_array($status, ['0','false','fail','failed','error','ko'], true)) {
                                    $out['backup_status'] = 'error';
                                } else {
                                    $out['backup_status'] = 'unknown';
                                }
                            } else {
                                $ageDays = (time() - $ts) / 86400;
                                $out['backup_status'] = ($ageDays <= 14) ? 'ok' : 'error';
                            }
                        }
                    }
                }
            }
        }
    }

    return $out;
}

/**
 * Auto-Detection für Security-Tabellen/Spalten
 */
function nx_detect_security_config(mysqli $db): array {
    $cfg = [];

    // Failed logins
    $failTable = nx_first_existing_table($db, [
        'failed_login_attempts',
        'failed_logins',
        'login_attempts',
        'login_attempts_failed',
        'security_failed_logins',
    ]);
    if ($failTable) {
        $cfg['fail_table'] = $failTable;
        $cfg['fail_time']  = nx_first_existing_column($db, $failTable, ['attempt_time', 'created_at', 'time', 'timestamp', 'date']) ?: 'attempt_time';
        $cfg['fail_ip']    = nx_first_existing_column($db, $failTable, ['ip', 'ip_address', 'remote_addr']) ?: 'ip';
    }

    // Banned IPs
    $banTable = nx_first_existing_table($db, [
        'banned_ips',
        'bannedip',
        'ip_bans',
        'bans',
        'security_ip_bans',
    ]);
    if ($banTable) {
        $cfg['ban_table'] = $banTable;
        $cfg['ban_time']  = nx_first_existing_column($db, $banTable, ['deltime', 'created_at', 'time', 'timestamp', 'date']) ?: 'deltime';
        $cfg['ban_ip']    = nx_first_existing_column($db, $banTable, ['ip', 'ip_address']) ?: 'ip';
    }

    return $cfg;
}

/**
 * Activity Stream aus mehreren Quellen aggregieren.
 */
function nx_fetch_activity_stream(mysqli $db, int $limit = 20, array $opts = []): array {
    global $languageService;

    if (!($languageService instanceof \nexpell\LanguageService)) {
        $languageService = null;
    }

    $opts = array_merge([
        'include_forum'    => true,
        'include_comments' => true,
        'include_shoutbox' => true,
        'include_audit'    => true,

        'forum_threads_limit' => 10,
        'forum_posts_limit'   => 12,
        'comments_limit'      => 10,
        'shoutbox_limit'      => 8,
        'audit_limit'         => 10,

        'max_age_days' => 30,

        'url_forum'     => FRONTEND_URL . '?site=forum',
        'url_comments'  => FRONTEND_URL . '?site=articles&tab=comments',
        'url_shoutbox'  => FRONTEND_URL . '?site=shoutbox',
    ], $opts);

    $events = [];
    $minTs = 0;
    if ((int)$opts['max_age_days'] > 0) {
        $minTs = time() - ((int)$opts['max_age_days'] * 86400);
    }

    $forumThreadIds = [];

    // FORUM: THREADS
    if ($opts['include_forum'] && nx_table_exists($db, 'plugins_forum_threads')) {
        $n = (int)$opts['forum_threads_limit'];

        $sql = "
            SELECT t.threadID, t.title, t.userID, t.created_at, u.username
            FROM plugins_forum_threads t
            LEFT JOIN users u ON u.userID = t.userID
            ORDER BY t.created_at DESC, t.threadID DESC
            LIMIT $n
        ";

        if ($res = $db->query($sql)) {
            // Cache für News-/Artikel-Titel
            $newsTitleCache = [];
            $articleTitleCache = [];
            while ($r = $res->fetch_assoc()) {
                $ts = (int)($r['created_at'] ?? 0);
                if ($ts <= 0) continue;
                if ($minTs > 0 && $ts < $minTs) continue;

                $threadID = (int)($r['threadID'] ?? 0);
                $title    = trim((string)($r['title'] ?? ''));
                $uid      = (int)($r['userID'] ?? 0);
                $uname    = trim((string)($r['username'] ?? ''));

                if ($threadID > 0) $forumThreadIds[$threadID] = true;

                $threadUrl = 'index.php?site=forum&action=thread&id=' . $threadID;
                $threadUrlEsc = htmlspecialchars($threadUrl, ENT_QUOTES, 'UTF-8');
                $threadTitleEsc = htmlspecialchars(($title !== '' ? $title : ('Thread #' . $threadID)), ENT_QUOTES, 'UTF-8');

                $events[] = nx_activity_item([
                  'ts'           => $ts,
                  'source'       => $languageService->get('source_forum'),
                  'severity'     => 'info',
                  'icon'         => 'bi-chat-dots',
                  'title'        => $languageService->get('activity_new_thread'),
                  'title_html'   => sprintf('%s <a class="text-decoration-none" href="%s">%s</a>',$languageService->get('activity_new_thread'),$threadUrlEsc,$threadTitleEsc),
                  'meta'         => '',
                  'url'          => $threadUrl,

                  'user_id'      => $uid,
                  'user_display' => $uname,
              ]);}
            $res->free();
        }
    }

    // FORUM: POSTS
    if ($opts['include_forum']
        && nx_table_exists($db, 'plugins_forum_posts')
        && nx_table_exists($db, 'plugins_forum_threads')
    ) {
        $n = (int)$opts['forum_posts_limit'];

        $sql = "
            SELECT
                p.postID,
                p.threadID,
                p.userID,
                p.created_at,
                p.is_deleted,
                t.title AS thread_title,
                u.username AS username,
                (
                  SELECT MIN(p2.postID)
                  FROM plugins_forum_posts p2
                  WHERE p2.threadID = p.threadID
                ) AS first_post_id
            FROM plugins_forum_posts p
            LEFT JOIN plugins_forum_threads t ON t.threadID = p.threadID
            LEFT JOIN users u ON u.userID = p.userID
            WHERE (p.is_deleted IS NULL OR p.is_deleted = 0)
            ORDER BY p.created_at DESC, p.postID DESC
            LIMIT $n
        ";

        if ($res = $db->query($sql)) {
            while ($r = $res->fetch_assoc()) {
                $ts = (int)($r['created_at'] ?? 0);
                if ($ts <= 0) continue;
                if ($minTs > 0 && $ts < $minTs) continue;

                $threadID    = (int)($r['threadID'] ?? 0);
                $postID      = (int)($r['postID'] ?? 0);
                $firstPostId = (int)($r['first_post_id'] ?? 0);

                if ($threadID > 0 && $postID > 0 && $firstPostId > 0 && $postID === $firstPostId && isset($forumThreadIds[$threadID])) {
                    continue;
                }

                $threadTitle = trim((string)($r['thread_title'] ?? ''));
                if ($threadTitle === '') {
                    $threadTitle = $languageService->get('value_unknown_thread');
                }

                $uid   = (int)($r['userID'] ?? 0);
                $uname = trim((string)($r['username'] ?? ''));

                $meta = sprintf('%s %s',$languageService->get('label_thread'),$threadTitle);

                $events[] = nx_activity_item([
                    'ts'           => $ts,
                    'source'       => $languageService->get('source_forum'),
                    'severity'     => 'info',
                    'icon'         => 'bi-reply',
                    'title'        => $languageService->get('activity_new_forum_post'),
                    'meta'         => $meta,
                    'url'          => FRONTEND_URL . '?site=forum&action=thread&id=' . $threadID,

                    'user_id'      => $uid,
                    'user_display' => $uname,
                ]);
            }
            $res->free();
        }
    }

    // COMMENTS (global)
    if ($opts['include_comments'] && nx_table_exists($db, 'comments')) {
        $n = (int)$opts['comments_limit'];

        $sql = "
            SELECT c.commentID, c.plugin, c.itemID, c.userID, c.date, c.parentID, c.modulname,
                   u.username AS username
            FROM comments c
            LEFT JOIN users u ON u.userID = c.userID
            ORDER BY c.date DESC, c.commentID DESC
            LIMIT $n
        ";

        if ($res = $db->query($sql)) {
            while ($r = $res->fetch_assoc()) {
                $dt = (string)($r['date'] ?? '');
                $ts = ($dt !== '' && strtotime($dt) !== false) ? (int)strtotime($dt) : 0;
                if ($ts <= 0) continue;
                if ($minTs > 0 && $ts < $minTs) continue;

                $plugin    = trim((string)($r['plugin'] ?? ''));
                $modulname = trim((string)($r['modulname'] ?? ''));
                $itemID    = (int)($r['itemID'] ?? 0);
                $uid       = (int)($r['userID'] ?? 0);
                $uname     = trim((string)($r['username'] ?? ''));

                $source = $languageService->get('source_comment');

                if ($plugin !== '') {
                    $source = ucfirst($plugin);
                }

                if ($source === 'Articles' || stripos($modulname, 'article') !== false) {
                    $source = $languageService->get('source_article');
                }

                // Link zum Frontend je Plugin (News/Articles)
                $commentUrl = (string)$opts['url_comments'];
                $pLower = strtolower($plugin);
                if ($itemID > 0) {
                    if ($pLower === 'news') {
                        $commentUrl = FRONTEND_URL . '?site=news&newsID=' . $itemID;
                    } elseif ($pLower === 'articles' || stripos($modulname, 'article') !== false) {
                        $commentUrl = FRONTEND_URL . '?site=articles&action=watch&id=' . $itemID;
                    }
                }// Titel des Eintrags für Breadcrumb
                $itemTitle = '';

                if ($itemID > 0) {
                    if ($pLower === 'news' && nx_table_exists($db, 'plugins_news')) {
                        if (array_key_exists($itemID, $newsTitleCache)) {
                            $itemTitle = (string)$newsTitleCache[$itemID];
                        } else {
                            $stmtT = $db->prepare("SELECT title FROM plugins_news WHERE id = ? LIMIT 1");
                            if ($stmtT) {
                                $stmtT->bind_param('i', $itemID);
                                $stmtT->execute();
                                $stmtT->bind_result($tmpTitle);
                                if ($stmtT->fetch()) $itemTitle = trim((string)$tmpTitle);
                                $stmtT->close();
                            }
                            $newsTitleCache[$itemID] = $itemTitle;
                        }
                    } elseif (($pLower === 'articles' || stripos($modulname, 'article') !== false) && nx_table_exists($db, 'plugins_articles')) {
                        if (array_key_exists($itemID, $articleTitleCache)) {
                            $itemTitle = (string)$articleTitleCache[$itemID];
                        } else {
                            $stmtT = $db->prepare("SELECT title FROM plugins_articles WHERE id = ? LIMIT 1");
                            if ($stmtT) {
                                $stmtT->bind_param('i', $itemID);
                                $stmtT->execute();
                                $stmtT->bind_result($tmpTitle);
                                if ($stmtT->fetch()) $itemTitle = trim((string)$tmpTitle);
                                $stmtT->close();
                            }
                            $articleTitleCache[$itemID] = $itemTitle;
                        }
                    }
                }

                // Meta: Quelle + Titel, kein Link, keine ID
                $metaText = $source;
                if ($itemTitle !== '') {
                    $metaText .= ': ' . $itemTitle;
                }

                $events[] = nx_activity_item([
                    'ts'           => $ts,
                    'source'       => $source,
                    'severity'     => 'warn',
                    'icon'         => 'bi-chat-left-text',
                    'title'        => $languageService->get('activity_new_comment'),
                    'meta'         => $metaText,
                    'url'          => $commentUrl,
                    'user_id'      => $uid,
                    'user_display' => $uname,
                ]);

            }
            $res->free();
        }
    }

    // SHOUTBOX
    if ($opts['include_shoutbox'] && nx_table_exists($db, 'plugins_shoutbox_messages')) {
        $n = (int)$opts['shoutbox_limit'];

        $shoutboxTimeCol = nx_first_existing_column($db, 'plugins_shoutbox_messages', ['created_at', 'date', 'created', 'timestamp']);
        $shoutboxNameCol = nx_first_existing_column($db, 'plugins_shoutbox_messages', ['username', 'name', 'poster', 'author']);

        if ($shoutboxTimeCol && $shoutboxNameCol) {
            $sql = "
                SELECT
                    s.id,
                    s.`{$shoutboxTimeCol}` AS shoutbox_created_at,
                    s.`{$shoutboxNameCol}` AS shoutbox_username,
                    u.userID
                FROM plugins_shoutbox_messages s
                LEFT JOIN users u ON u.username COLLATE utf8mb4_unicode_ci = s.`{$shoutboxNameCol}` COLLATE utf8mb4_unicode_ci
                ORDER BY s.`{$shoutboxTimeCol}` DESC, s.id DESC
                LIMIT $n
            ";

            if ($res = $db->query($sql)) {
                while ($r = $res->fetch_assoc()) {
                    $dt = (string)($r['shoutbox_created_at'] ?? '');
                    $ts = ($dt !== '' && strtotime($dt) !== false) ? (int)strtotime($dt) : 0;
                    if ($ts <= 0) continue;
                    if ($minTs > 0 && $ts < $minTs) continue;

                    $uname = trim((string)($r['shoutbox_username'] ?? ''));
                    $uid   = (int)($r['userID'] ?? 0);

                    $events[] = nx_activity_item([
                        'ts'           => $ts,
                        'source'       => $languageService->get('source_shoutbox'),
                        'severity'     => 'muted',
                        'icon'         => 'bi-megaphone',
                        'title'        => $languageService->get('activity_new_shoutbox_message'),
                        'meta'         => '',
                        'url'          => (string)$opts['url_shoutbox'],

                        'user_id'      => $uid,
                        'user_display' => $uname,
                    ]);
                }
                $res->free();
            }
        }
    }

    // ADMIN AUDIT
    if ($opts['include_audit'] && nx_table_exists($db, 'admin_audit_log')) {
        $n = (int)$opts['audit_limit'];

        $stmt = $db->prepare("
            SELECT
                a.created_at,
                a.actor_username,
                a.actor_role,
                a.page,
                a.action,
                a.object_type,
                a.object_id,
                a.message,
                a.meta_json,
                u.userID
            FROM admin_audit_log a
            LEFT JOIN users u ON u.username COLLATE utf8mb4_unicode_ci = a.actor_username COLLATE utf8mb4_unicode_ci
            ORDER BY a.created_at DESC
            LIMIT $n
        ");

        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();

            while ($e = $res->fetch_assoc()) {
                $dt = (string)($e['created_at'] ?? '');
                $ts = ($dt !== '' && strtotime($dt) !== false) ? (int)strtotime($dt) : 0;
                if ($ts <= 0) continue;
                if ($minTs > 0 && $ts < $minTs) continue;

                $uid      = (int)($e['userID'] ?? 0);
                $unameRaw = trim((string)($e['actor_username'] ?? ''));
                $roleRaw  = trim((string)($e['actor_role'] ?? ''));

                $pageRaw  = trim((string)($e['page'] ?? ''));
                $actionRaw = trim((string)($e['action'] ?? ''));
                $isDelete  = (strtoupper($actionRaw) === 'DELETE');

                // Meta JSON
                $meta = json_decode($e['meta_json'] ?? '', true);
                if (!is_array($meta)) $meta = [];

                // Default: aus page + Language-Keys
                $pageTitle = ($pageRaw !== '' ? $pageRaw : '-');
                if ($languageService && $pageRaw !== '') {
                    foreach (['nav_'.$pageRaw, 'sidebar_'.$pageRaw, 'menu_'.$pageRaw, $pageRaw] as $k) {
                        $t = (string)$languageService->get($k);
                        if ($t !== '' && $t !== $k && $t !== '['.$k.']') { $pageTitle = $t; break; }
                    }
                }

                // Override: wenn beim Loggen der echte Titel gespeichert wurde, hat der Vorrang
                if (!empty($meta['page_title'])) {
                    $pt = trim((string)$meta['page_title']);
                    if ($pt !== '') $pageTitle = $pt;
                }

                $actionCode = strtoupper(trim((string)$actionRaw));   // CREATE/UPDATE/DELETE/...
                $auditActionKey = '';

                if (!empty($meta['action'])) {
                    $auditActionKey = 'audit_action_' . strtolower(trim((string)$meta['action']));
                }

                if ($auditActionKey === '' && $actionCode !== '') {
                    $auditActionKey = 'audit_' . strtolower($actionCode);
                }

                $actionText = $actionRaw;
                if ($languageService && $auditActionKey !== '') {
                    $tmp = (string)$languageService->get($auditActionKey);
                    if ($tmp !== '' && $tmp !== $auditActionKey && $tmp !== '['.$auditActionKey.']') {
                        $actionText = $tmp;
                    }
                }

                // Objekt-Label
                $metaName = '';
                if (!empty($meta['object_label'])) $metaName = (string)$meta['object_label'];
                elseif (!empty($meta['name']))     $metaName = (string)$meta['name'];
                elseif (!empty($meta['modulname']))$metaName = (string)$meta['modulname'];
                $metaName = trim($metaName);

                $objIdRaw    = trim((string)($e['object_id'] ?? ''));
                $objLabelRaw = trim($metaName !== '' ? $metaName : $objIdRaw);
                if ($objLabelRaw === '') $objLabelRaw = '-';

                // Message
                $msgKey  = (string)($e['message'] ?? '');
                $msgText = $msgKey;

                if ($languageService && $msgKey !== '' && preg_match('/^[a-z0-9_]+$/i', $msgKey)) {
                    $t = (string)$languageService->get($msgKey);
                    if ($t !== '' && $t !== $msgKey && $t !== '['.$msgKey.']') $msgText = $t;
                }

                $pageCtx = $pageTitle;

                // Wenn der Page-Titel selbst eine Aktion ist, reduzieren wir auf die Hauptkategorie
                $pageCtx = preg_replace('/\b(bearbeiten|edit(ieren)?|speichern|ändern|aktualisieren|erstellen|löschen)\b/i', '', $pageCtx);
                $pageCtx = trim(preg_replace('/\s{2,}/', ' ', $pageCtx));

                // Fallback falls leer
                if ($pageCtx === '') $pageCtx = $pageTitle;

                // Relation auflösen
                if (preg_match('/^\d+:\d+$/', $objLabelRaw)) {

                    [$aId, $bId] = array_map('intval', explode(':', $objLabelRaw));

                    $userId = $aId;
                    $roleId = $bId;

                    // Username holen
                    $userName = '';
                    if ($userId > 0) {
                        $stU = $db->prepare("SELECT username FROM users WHERE userID = ? LIMIT 1");
                        if ($stU) {
                            $stU->bind_param('i', $userId);
                            $stU->execute();
                            $stU->bind_result($userName);
                            $stU->fetch();
                            $stU->close();
                        }
                    }

                    // Rollenname holen
                    $roleName = '';
                    if ($roleId > 0) {
                        $stR = $db->prepare("SELECT role_name FROM user_roles WHERE roleID = ? LIMIT 1");
                        if ($stR) {
                            $stR->bind_param('i', $roleId);
                            $stR->execute();
                            $stR->bind_result($roleName);
                            $stR->fetch();
                            $stR->close();
                        }
                    }

                    $userName = trim((string)$userName);
                    $roleName = trim((string)$roleName);

                    // Objektlabel sinnvoll überschreiben
                    $prettyUser = ($userName !== '') ? $userName : ('ID ' . $userId);
                    $prettyRole = ($roleName !== '') ? $roleName : ('ID ' . $roleId);

                    $objLabelRaw =
                        $languageService->get('label_user') . ' <em>' . htmlspecialchars($prettyUser, ENT_QUOTES, 'UTF-8') . '</em>'
                        . ' ' . $languageService->get('label_arrow') . ' '
                        . $languageService->get('label_role') . ' <em>' . htmlspecialchars($prettyRole, ENT_QUOTES, 'UTF-8') . '</em>';

                    $isUserRoleRelation =
                        ($pageRaw === 'user_roles' || $pageTitle === $languageService->get('page_user_roles'))
                        && preg_match('/^\d+:\d+$/', $objIdRaw);

                    if ($isUserRoleRelation) {

                      if ($actionCode === 'CREATE') {
                          $msgText = sprintf(
                              $languageService->get('user_role_assigned'),
                              htmlspecialchars($prettyUser, ENT_QUOTES, 'UTF-8'),
                              htmlspecialchars($prettyRole, ENT_QUOTES, 'UTF-8')
                          );
                      }
                      elseif ($actionCode === 'DELETE') {
                          $msgText = sprintf(
                              $languageService->get('user_role_removed'),
                              htmlspecialchars($prettyUser, ENT_QUOTES, 'UTF-8'),
                              htmlspecialchars($prettyRole, ENT_QUOTES, 'UTF-8')
                          );
                      }

                        $msgKey = '';
                    }
                }

                $vars = [
                    '{page}'   => $pageTitle,
                    '{action}' => $actionText,
                    '{object}' => ($objLabelRaw !== '' && $objLabelRaw !== 'singleton') ? $objLabelRaw : '-',
                ];
                foreach ($meta as $k => $v) {
                    if (!is_string($k) || $k === '' || $v === null) continue;
                    if (is_array($v) || is_object($v)) continue;
                    $vStr = trim((string)$v);
                    if ($vStr === '') continue;
                    $vars['{' . $k . '}'] = $vStr;
                }

                $msgText = strtr($msgText, $vars);
                $msgText = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $msgText);
                $msgText = trim(preg_replace('/\s{2,}/', ' ', $msgText));
                // Dateiname/Details nicht im Titel doppeln (stehen in Breadcrumb): generische Titel für Datei-/Backup-Aktionen
                $metaFile = '';
                if (!empty($meta['filename'])) $metaFile = (string)$meta['filename'];
                elseif (!empty($meta['file'])) $metaFile = (string)$meta['file'];
                $metaFile = trim($metaFile);

                $isFileLike = ($metaFile !== '') || preg_match('/\.(sql|zip|gz|bak|css|csv|xml|json|php|txt)$/i', (string)$objLabelRaw);
                if ($isFileLike) {
                    $ext = '';
                    $nameForExt = $metaFile !== '' ? $metaFile : (string)$objLabelRaw;
                    if (preg_match('/\.([a-z0-9]{2,6})$/i', $nameForExt, $mExt)) $ext = strtolower($mExt[1]);

                    $kind = $languageService->get('file_kind_file');

                    if ($ext === 'sql' || stripos((string)($meta['action'] ?? ''), 'backup') !== false) {$kind = $languageService->get('file_kind_backup'); }
                    elseif ($ext === 'css') { $kind = $languageService->get('file_kind_stylesheet'); }
                    elseif ($ext === 'csv') { $kind = $languageService->get('file_kind_csv'); }

                    $verb = $languageService->get('verb_saved');

                    $metaAction = strtolower(trim((string)($meta['action'] ?? '')));
                    if ($metaAction !== '' && strpos($metaAction, 'restor') !== false) {
                        $verb = $languageService->get('verb_restored');
                    } elseif ($metaAction !== '' && strpos($metaAction, 'export') !== false) {
                        $verb = $languageService->get('verb_exported');
                    } else {
                        if ($actionCode === 'CREATE') {
                            $verb = $languageService->get('verb_created');
                        } elseif ($actionCode === 'UPDATE') {
                            $verb = $languageService->get('verb_edited');
                        } elseif ($actionCode === 'DELETE') {
                            $verb = $languageService->get('verb_deleted');
                        }
                    }

                    $fileNeedle = $nameForExt !== '' ? basename($nameForExt) : '';
                    if ($fileNeedle !== '' && stripos($msgText, $fileNeedle) !== false) {
                        $msgText = sprintf(
                            $languageService->get('pattern_object_was_verb'),
                            $kind,
                            $verb
                        );
                    }
                }

                // Whitelist HTML tags
                $msg = (string)$msgText;
                $msg = strip_tags($msg, '<em><strong><b><i><u><br><span>');
                $msg = preg_replace('/<(em|strong|b|i|u|span)\b[^>]*>/i', '<$1>', $msg);
                $msg = preg_replace('/<br\b[^>]*>/i', '<br>', $msg);
                $msg = preg_replace('/<(span|em|strong|b|i|u)>\s*<\/\1>/i', '', $msg);
                $msg = trim($msg);
                if ($msg === '') $msg = '—';

                $isUserRoleRelation =
                    ($pageTitle === $languageService->get('page_user_roles') || $pageRaw === 'user_roles')
                    && preg_match('/^\d+:\d+$/', $objIdRaw);

                // Benutzerrollen: bei Einzel-ID den Rollen-Titel statt der ID in Breadcrumb anzeigen
                if (
                    ($pageRaw === 'user_roles' || $pageTitle === $languageService->get('page_user_roles'))
                    && ctype_digit((string)$objLabelRaw)
                    && nx_table_exists($db, 'user_roles')
                ) {
                    $idCol = nx_column_exists($db, 'user_roles', 'roleID') ? 'roleID' : (nx_column_exists($db, 'user_roles', 'id') ? 'id' : '');
                    if ($idCol !== '' && nx_column_exists($db, 'user_roles', 'role_name')) {
                        $rid = (int)$objLabelRaw;
                        $stR = $db->prepare("SELECT role_name FROM user_roles WHERE {$idCol} = ? LIMIT 1");
                        if ($stR) {
                            $stR->bind_param('i', $rid);
                            $stR->execute();
                            $stR->bind_result($tmpRoleName);
                            if ($stR->fetch()) {
                                $tmpRoleName = trim((string)$tmpRoleName);
                                if ($tmpRoleName !== '') $objLabelRaw = $tmpRoleName;
                            }
                            $stR->close();
                        }
                    }
                }

                // Breadcrumb
                $crumbParts = [];
                if (!empty($meta['page_category'])) {
                    $crumbParts[] = (string)$meta['page_category'];
                }

                if ($isUserRoleRelation) {
                    if ($actionCode === 'CREATE') {
                        $crumbParts[] = $languageService->get('breadcrumb_assign_role_to_user');
                    } elseif ($actionCode === 'DELETE') {
                        $crumbParts[] = $languageService->get('breadcrumb_remove_role_from_user');
                    } else {
                        $crumbParts[] = $languageService->get('page_user_roles');
                    }
                } else {
                    $crumbParts[] = $pageTitle;
                    if ($objLabelRaw !== '' && $objLabelRaw !== 'singleton' && $objLabelRaw !== '-') {
                        $crumbParts[] = $objLabelRaw;
                    }
                }

                $crumb = implode(' > ', array_map('trim', $crumbParts));

                // Severity
                $sev = 'info';
                if (strtoupper($actionRaw) === 'DELETE') $sev = 'danger';
                elseif (strtoupper($actionRaw) === 'UPDATE') $sev = 'info';
                elseif (strtoupper($actionRaw) === 'CREATE') $sev = 'info';

                // Ziel-URL: bei Delete nicht direkt auf Objekt verlinken
                if (!empty($meta['page_url'])) {
                    $targetUrl = (string)$meta['page_url'];
                } elseif ($pageRaw !== '' && $pageRaw !== '-') {
                    $targetUrl = 'admincenter.php?site=' . rawurlencode($pageRaw);
                } else {
                    $targetUrl = 'admincenter.php';
                }

                $userLine = $unameRaw !== '' ? $unameRaw : '-';
                if ($roleRaw !== '') $userLine .= ' (' . $roleRaw . ')';

                $events[] = nx_activity_item([
                    'ts'           => $ts,
                    'source'       => $languageService->get('source_admin_activity'),
                    'severity'     => $sev,
                    'icon'         => 'bi-shield-check',
                    'title'        => $msg,
                    'meta'         => $crumb,
                    'url'          => $targetUrl,

                    'user_id'      => $uid,
                    'user_display' => $unameRaw,

                    'action'       => strtoupper(trim((string)$actionRaw)),
                    'action_text'  => $actionText,
                ]);
            }

            $stmt->close();
        }
    }

    // Sort + Limit
    usort($events, function ($a, $b) {
        return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
    });

    if ($limit > 0 && count($events) > $limit) {
        $events = array_slice($events, 0, $limit);
    }

    return $events;
}

?>
<?php
// System/Meta befüllen (damit Version/Backup nicht leer bleiben)
if (!isset($system) || !is_array($system)) {
    $system = [];
}
if (isset($_database) && $_database instanceof mysqli) {
    $system = array_merge(nx_system_summary($_database, $languageService), $system);
}

// Defaults
$system = is_array($system ?? null) ? $system : [];
$system = array_merge([
  'version'       => $languageService->get('value_na'), // das ist OK (Anzeige/Fallback)
  'update_status' => 'unknown',
  'backup_last'   => $languageService->get('value_na'),
  'backup_status' => 'unknown',
], $system);

$stats = is_array($stats ?? null) ? $stats : [];
$stats = array_merge([
  'visitors_7d'  => 0,
  'pageviews_7d' => 0,
  'contact_7d'   => 0,
  'joinus_7d'    => 0,
], $stats);

$plugins = is_array($plugins ?? null) ? $plugins : [];
$audit   = is_array($audit ?? null) ? $audit : [];

// Online-Tracking: last_activity bei jedem Aufruf aktualisieren (für Online-Listen / 5-Minuten-Fenster)
if (!empty($userID)) {
    $stmt = $_database->prepare("UPDATE users SET last_activity = NOW(), is_online = 1 WHERE userID = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $stmt->close();
    }
}

// 1. Aktive Nutzer (Nutzer mit Aktivität in den letzten 5 Minuten)
$stmt = $_database->prepare("SELECT COUNT(*) FROM users WHERE (last_activity > NOW() - INTERVAL 5 MINUTE OR lastlogin > NOW() - INTERVAL 5 MINUTE)");
$stmt->execute();
$stmt->bind_result($onlineUsers);
$stmt->fetch();
$stmt->close();
$onlineUsers = $onlineUsers ?: 0;

// 1b. Aktuell online (Liste) inkl. Rollen
$stmt = $_database->prepare("
    SELECT 
        u.userID,
        u.username,
        u.last_activity,
        GROUP_CONCAT(ur.role_name ORDER BY ur.roleID SEPARATOR ', ') AS roles
    FROM users u
    LEFT JOIN user_role_assignments ura ON u.userID = ura.userID
    LEFT JOIN user_roles ur ON ura.roleID = ur.roleID
    WHERE (u.last_activity > NOW() - INTERVAL 5 MINUTE OR u.lastlogin > NOW() - INTERVAL 5 MINUTE)
    GROUP BY u.userID
    ORDER BY COALESCE(u.last_activity, u.lastlogin) DESC, u.userID DESC
    LIMIT 8
");
$stmt->execute();
$result = $stmt->get_result();
$onlineUserList = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// 6. Neueste Benutzer (inkl. Rollen)
$stmt = $_database->prepare("
    SELECT 
        u.userID, 
        u.username, 
        u.registerdate AS registered_at,
        GROUP_CONCAT(ur.role_name ORDER BY ur.roleID SEPARATOR ', ') AS roles
    FROM users u
    LEFT JOIN user_role_assignments ura ON u.userID = ura.userID
    LEFT JOIN user_roles ur ON ura.roleID = ur.roleID
    WHERE u.registerdate >= (NOW() - INTERVAL 30 DAY)
    GROUP BY u.userID
    ORDER BY u.registerdate DESC
");
$stmt->execute();
$result = $stmt->get_result();
$latestUsers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Rollen-Badges / Online- & Latest-Listen
$__nx_formatUserRow = function(array $user): array {
    // Rollen-Badges
    $roleBadges = [];
    $roles = array_map('trim', explode(',', (string)($user['roles'] ?? '')));

    foreach ($roles as $role) {
        if ($role === '') continue;
        $cleanRole = htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (stripos($role, 'admin') !== false) {
            $roleBadges[] = '<span class="badge bg-danger">' . $cleanRole . '</span>';
        } elseif (stripos($role, 'moderator') !== false) {
            $roleBadges[] = '<span class="badge bg-warning text-dark">' . $cleanRole . '</span>';
        } elseif (stripos($role, 'redakteur') !== false || stripos($role, 'editor') !== false) {
            $roleBadges[] = '<span class="badge bg-info text-dark">' . $cleanRole . '</span>';
        } else {
            $roleBadges[] = '<span class="badge bg-secondary">' . $cleanRole . '</span>';
        }
    }

    $user['role_badges'] = implode(' ', $roleBadges);
    $user['username'] = htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return $user;
};

$onlineUserList = array_map($__nx_formatUserRow, $onlineUserList);

// Formatieren der 'when'-Spalte für Neueste Benutzer
foreach ($latestUsers as &$user) {
    $user = $__nx_formatUserRow($user);

    $registeredTimestamp = strtotime((string)($user['registered_at'] ?? ''));
    $diff = $registeredTimestamp > 0 ? (time() - $registeredTimestamp) : 0;

    if ($diff < 60) {
        $user['when'] = $diff . ' ' . $languageService->get('time_seconds_short');
    } elseif ($diff < 3600) {
        $user['when'] = floor($diff / 60) . ' ' . $languageService->get('time_minutes_short');
    } elseif ($diff < 86400) {
        $user['when'] = floor($diff / 3600) . ' ' . $languageService->get('time_hours_short');
    } else {
        $user['when'] = floor($diff / 86400) . ' ' . $languageService->get('time_days');
    }
}
unset($user);

// 2. Anzahl installierter Plugins
$stmt = $_database->prepare("SELECT COUNT(*) FROM settings_plugins_installed");
$stmt->execute();
$stmt->bind_result($installedPlugins);
$stmt->fetch();
$stmt->close();
$installedPlugins = $installedPlugins ?: 0;

// 3. Anzahl installierter Themes
$stmt = $_database->prepare("SELECT COUNT(*) FROM settings_themes_installed");
$stmt->execute();
$stmt->bind_result($installedThemes);
$stmt->fetch();
$stmt->close();
$installedThemes = $installedThemes ?: 0;

// 4. Gesamtbesucher und Seitenaufrufe (Totals) + 7-Tage Werte inkl. Trend
$stats['plugin_installed'] = (int)$installedPlugins;
$stats['themes_installed'] = (int)$installedThemes;

// Totals
$totalVisitors = '0';
$totalPageviews = '0';
if (nx_table_exists($_database, 'visitor_statistics')) {
    $stmt = $_database->prepare("SELECT COUNT(DISTINCT COALESCE(NULLIF(ip_hash,''), NULLIF(ip_address,''))) AS total_visitors, COALESCE(SUM(COALESCE(pageviews,1)),0) AS total_pageviews FROM visitor_statistics");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $totals = $result ? ($result->fetch_assoc() ?: []) : [];
        $totalVisitors = number_format((int)($totals['total_visitors'] ?? 0), 0, ',', '.');
        $totalPageviews = number_format((int)($totals['total_pageviews'] ?? 0), 0, ',', '.');
        $stmt->close();
    }
}

// Aktives Theme (Name)
$theme_active_name = '—';
if (nx_table_exists($_database, 'settings_themes')) {
    $stmt = $_database->prepare("SELECT themename, name, modulname, pfad FROM settings_themes WHERE active = 1 LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? ($res->fetch_assoc() ?: []) : [];
        $stmt->close();

        $theme_active_name = (string)($row['themename'] ?? '');
        if ($theme_active_name === '') $theme_active_name = (string)($row['name'] ?? '');
        if ($theme_active_name === '') $theme_active_name = (string)($row['modulname'] ?? '');
        if ($theme_active_name === '') $theme_active_name = (string)($row['pfad'] ?? '');
        $theme_active_name = ($theme_active_name !== '') ? $theme_active_name : '—';
    }
}

// 7-Tage KPIs 
if (nx_table_exists($_database, 'visitor_statistics')) {

    $last7_start = "CURDATE() - INTERVAL 6 DAY";
    $prev7_start = "CURDATE() - INTERVAL 13 DAY";
    $prev7_end   = "CURDATE() - INTERVAL 7 DAY";

    // Letzte 7 Tage
    $sqlLast = "
        SELECT
            COALESCE(SUM(daily_visitors),0) AS visitors,
            COALESCE(SUM(daily_pageviews),0) AS pageviews
        FROM (
            SELECT
                DATE(created_at) AS d,
                COUNT(DISTINCT COALESCE(NULLIF(ip_hash,''), NULLIF(ip_address,''))) AS daily_visitors,
                COALESCE(SUM(COALESCE(pageviews,1)),0) AS daily_pageviews
            FROM visitor_statistics
            WHERE DATE(created_at) >= ($last7_start)
            GROUP BY DATE(created_at)
        ) x
    ";

    $stmt = $_database->prepare($sqlLast);
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? ($res->fetch_assoc() ?: []) : [];
        $stmt->close();

        $stats['visitors_7d']  = (int)($row['visitors'] ?? 0);
        $stats['pageviews_7d'] = (int)($row['pageviews'] ?? 0);
    }

    // Vorherige 7 Tage
    $sqlPrev = "
        SELECT
            COALESCE(SUM(daily_visitors),0) AS visitors,
            COALESCE(SUM(daily_pageviews),0) AS pageviews
        FROM (
            SELECT
                DATE(created_at) AS d,
                COUNT(DISTINCT COALESCE(NULLIF(ip_hash,''), NULLIF(ip_address,''))) AS daily_visitors,
                COALESCE(SUM(COALESCE(pageviews,1)),0) AS daily_pageviews
            FROM visitor_statistics
            WHERE DATE(created_at) >= ($prev7_start)
              AND DATE(created_at) <  ($prev7_end)
            GROUP BY DATE(created_at)
        ) x
    ";

    $prevVisitors = 0;
    $prevPageviews = 0;

    $stmt = $_database->prepare($sqlPrev);
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? ($res->fetch_assoc() ?: []) : [];
        $stmt->close();

        $prevVisitors  = (int)($row['visitors'] ?? 0);
        $prevPageviews = (int)($row['pageviews'] ?? 0);
    }

    // Trends
    $stats['visitors_trend_pct'] =
        ($prevVisitors > 0)
            ? ((($stats['visitors_7d'] - $prevVisitors) / $prevVisitors) * 100.0)
            : null;

    $stats['pageviews_trend_pct'] =
        ($prevPageviews > 0)
            ? ((($stats['pageviews_7d'] - $prevPageviews) / $prevPageviews) * 100.0)
            : null;

    // Ø Pageviews pro Besucher
    $stats['pageviews_per_visitor'] =
        ($stats['visitors_7d'] > 0)
            ? ($stats['pageviews_7d'] / $stats['visitors_7d'])
            : null;
}


// Traffic-Zeitreihe (7 Tage) für Charts
$trafficLabels = [];
$trafficVisitors = [];
$trafficPageviews = [];

$sqlTraffic = "
  SELECT
    DATE(created_at) AS d,
    COUNT(DISTINCT COALESCE(NULLIF(ip_hash,''), NULLIF(ip_address,''))) AS visitors,
    COALESCE(SUM(COALESCE(pageviews,1)),0) AS pageviews
  FROM visitor_statistics
  WHERE DATE(created_at) >= CURDATE() - INTERVAL 6 DAY
  GROUP BY DATE(created_at)
  ORDER BY d ASC
";

$stmt = $_database->prepare($sqlTraffic);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

while ($row = $res->fetch_assoc()) {
    $trafficLabels[]    = $row['d'];
    $trafficVisitors[] = (int)$row['visitors'];
    $trafficPageviews[] = (int)$row['pageviews'];
}

// Nexpell News
$news_updates = [];

// JSON von der zentralen URL abrufen
$news_updates = [];
$json = @file_get_contents('https://www.nexpell.de/admin/support_admin_news_json.php');

if ($json !== false) {
    $data = json_decode($json, true);
    if (is_array($data)) {
        $news_updates = $data;
    }
}

// --------------------------------------------------
// KPI: Security-Status aus suspicious_access.log
// --------------------------------------------------

$secLive = [
    'badge' => 'bg-success',
    'label' => $languageService->get('security_ok'),
    'why'   => $languageService->get('security_no_incidents'),
    'href'  => 'admincenter.php?site=log_viewer'
];

$attackCount = 0;
$level = null;

$logFile = __DIR__ . '/logs/suspicious_access.log';

if (file_exists($logFile)) {

    $content = file_get_contents($logFile);
    if ($content !== false) {

        // Log-Einträge trennen
        $entries = preg_split('/[-]{40,}/', $content);
        $entries = array_filter(array_map('trim', $entries));

        foreach ($entries as $entry) {

            // Zeitstempel suchen
            if (!preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $entry, $m)) {
                continue;
            }

            $ts = strtotime($m[1]);
            if ($ts === false || $ts < (time() - 86400)) {
                continue;
            }

            $attackCount++;

            if (stripos($entry, 'failed logins') !== false) {
                $attackCount++;

            } elseif ($level !== 'critical' && stripos($entry, 'test') !== false) {
                $level = 'warning';
            } else {
                $level = $level ?? 'info';
            }
        }
    }
}

// ----------------------------------------
// FIX: Severity erzwingen, wenn Angriffe da sind
// ----------------------------------------

if ($attackCount > 0) {

    if ($attackCount >= 4) {
        $level = 'critical';
    } elseif ($attackCount >= 2) {
        $level = 'warning';
    } else {
        $level = 'info';
    }

    switch ($level) {
        case 'critical':
            $secLive['badge'] = 'bg-danger';
            $secLive['label'] = $languageService->get('security_critical');
            break;

        case 'warning':
            $secLive['badge'] = 'bg-warning text-dark';
            $secLive['label'] = $languageService->get('security_warning');
            break;

        case 'info':
            $secLive['badge'] = 'bg-info text-dark';
            $secLive['label'] = $languageService->get('security_info');
            break;
    }

    $secLive['why'] = sprintf(
        $languageService->get('security_attacks_detected'),
        $attackCount
    );

    $secLive['href'] = 'admincenter.php?site=log_viewer#suspicious';
}

?>
<style>
  .card { border: 1px solid rgba(0,0,0,.06); }
  .muted { color:#667085; }
  .kpi-title { font-size:.85rem; color:#667085; margin-bottom:6px; }
  .kpi-value { font-size:1.55rem; font-weight:700; margin:0; }
  .kpi-sub { font-size:.85rem; color:#667085; }
  .table thead th { color:#667085; font-weight:600; font-size:.85rem; }
  .list-tight .list-group-item { padding-top:.65rem; padding-bottom:.65rem; }
  .news-list .list-group-item { border: 0; border-bottom: 1px solid rgba(0,0,0,.06); }
  .news-list .list-group-item:last-child { border-bottom: 0; }

  /* ApexCharts: Legend-Punkte vollständig entfernen */
.apexcharts-legend-marker {
  display: none !important;
}
  /* ApexCharts Legende: Punkte -> Linien */
/*.apexcharts-legend-marker {
  width: 32px !important;
  height: 0 !important;
  border-radius: 0 !important;
  border-top: 2px solid var(--ac-primary, #fe821d) !important;
  background: none !important;
}


.apexcharts-legend-series:nth-child(1) .apexcharts-legend-marker {
  border-top-style: solid !important;
}




.apexcharts-legend-series:nth-child(2) .apexcharts-legend-marker {
  border-top-style: dashed !important;
  border-top-width: 2px !important;
}*/



</style>

<!-- Top Strip - Version + Backup + Sicherheit -->
<div class="card shadow-sm mb-4 mt-4">
  <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div class="d-flex flex-wrap gap-3 align-items-center">

      <div>
        <div class="small muted"><?= $languageService->get('label_cms_version') ?></div>
        <div class="fw-semibold"><?= htmlspecialchars($system['version'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <div class="text-center">
          <div class="small muted"><?= $languageService->get('label_update') ?></div>
          <?php
          $u = $system['update_status'] ?? 'unknown';

          $uBadge = $u === 'available' ? 'bg-warning text-dark'
                  : ($u === 'ok' ? 'bg-success'
                  : ($u === 'error' ? 'bg-danger' : 'bg-secondary'));

          $uText  = $u === 'available' ? $languageService->get('update_available')
                  : ($u === 'ok' ? $languageService->get('status_current')
                  : ($u === 'error' ? $languageService->get('update_error')
                  : $languageService->get('status_unknown')));

          $uCount = (int)($system['update_count'] ?? 0);

          $uBadgeText = $uText;
          if ($u === 'available' && $uCount > 0) {
              $uBadgeText .= ' (' . $uCount . ')';
          }
          ?>
          <span class="badge <?= $uBadge ?>">
              <?= htmlspecialchars($uBadgeText, ENT_QUOTES, 'UTF-8') ?>
          </span>
      </div>

      <div class="vr d-none d-md-block"></div>

      <div class="text-center">
        <div class="small muted"><?= $languageService->get('label_last_backup') ?></div>
        <div class="fw-semibold"><?= htmlspecialchars($system['backup_last'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <div class="text-center">
        <div class="small muted"><?= $languageService->get('label_backup_status') ?></div>
          <?php
            $b = $system['backup_status'];

            $bBadge = $b === 'ok'
                ? 'bg-success'
                : ($b === 'error' ? 'bg-danger' : 'bg-secondary');

            $bText  = $b === 'ok'
                ? $languageService->get('status_ok')
                : ($b === 'error'
                    ? $languageService->get('status_failed')
                    : $languageService->get('status_unknown')
                );
          ?>
        <span class="badge <?= $bBadge ?>"><?= $bText ?></span>
      </div>

      <div class="vr d-none d-md-block"></div>

      <div>
        <?php
          $sec = nx_security_summary(
              $_database,
              array_merge(
                  ['details_href' => '?site=security_overview'],
                  nx_detect_security_config($_database)
              )
          );
        ?>



        <!-- BLOCK 1 -->
          <div class="d-flex align-items-center gap-3">

            <div class="text-center">
              <div class="small muted"><?= $languageService->get('label_security') ?></div>
              <span class="badge <?= $sec['badge'] ?>">
                <?= htmlspecialchars($sec['label'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>

            <div class="small text-muted lh-sm">
              <div>
                <?= htmlspecialchars($sec['why'], ENT_QUOTES, 'UTF-8') ?>
              </div>
              <a class="text-decoration-none"
                 href="<?= htmlspecialchars($sec['href'], ENT_QUOTES, 'UTF-8') ?>">
                <?= $languageService->get('label_details') ?>
              </a>
            </div>

         <div class="vr d-none d-md-block"></div>

          <!-- BLOCK 2 -->
        <div class="d-flex align-items-center gap-2">

          <div class="text-center">
            <div class="small muted"><?= $languageService->get('label_security_two') ?></div>
            <span class="badge <?= $secLive['badge'] ?>">
              <?= htmlspecialchars($secLive['label'], ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>

          <div class="small text-muted lh-sm">
            <div>
              <?= htmlspecialchars($secLive['why'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <a class="text-decoration-none"
               href="<?= htmlspecialchars($secLive['href'], ENT_QUOTES, 'UTF-8') ?>">
              <?= $languageService->get('label_details') ?>
            </a>
          </div>

        </div>

      </div>
</div>

    </div>

    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="?site=database">
            <?= $languageService->get('action_start_backup') ?>
        </a>
        <a class="btn btn-outline-primary" href="?site=update_core">
            <?= $languageService->get('action_check_updates') ?>
        </a>
    </div>
  </div>
</div>

  <!-- KPI ROW -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card position-relative shadow-sm h-100">
        <div class="card-body">

          <!-- Trend -->
          <div class="position-absolute top-0 end-0 m-3 d-flex align-items-center gap-1">
            <span class="text-muted small"><?= $languageService->get('label_trend') ?></span>

            <?php
              $trendBadgeClass = 'bg-secondary';
              $trendArrow = '';
              $trendText = $languageService->get('value_na');

              if (isset($stats['visitors_trend_pct']) && $stats['visitors_trend_pct'] !== null) {
                  $v = (float)$stats['visitors_trend_pct'];

                  if ($v > 0) {
                      $trendBadgeClass = 'bg-success';
                      $trendArrow = '▲';
                  } elseif ($v < 0) {
                      $trendBadgeClass = 'bg-danger';
                      $trendArrow = '▼';
                  } else {
                      $trendBadgeClass = 'bg-secondary';
                      $trendArrow = '►';
                  }

                  $trendText = ($v >= 0 ? '+' : '') . number_format($v, 1, ',', '.') . '%';
              }
            ?>

            <span class="badge <?= $trendBadgeClass ?>">
              <?= $trendArrow ?> <?= htmlspecialchars($trendText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </span>
          </div>

          <div class="kpi-title"><?= $languageService->get('kpi_visitors_7_days') ?></div>
          <p class="kpi-value"><?= number_format((int)$stats['visitors_7d'], 0, ',', '.') ?></p>

        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card position-relative shadow-sm h-100">
        <div class="card-body">

          <!-- Trend -->
          <div class="position-absolute top-0 end-0 m-3 d-flex align-items-center gap-1">
            <span class="text-muted small"><?= $languageService->get('label_trend') ?></span>

            <?php
              $trendBadgeClass = 'bg-secondary';
              $trendArrow = '';
              $trendText = $languageService->get('value_na');

              if (isset($stats['pageviews_trend_pct']) && $stats['pageviews_trend_pct'] !== null) {
                  $p = (float)$stats['pageviews_trend_pct'];

                  if ($p > 0) {
                      $trendBadgeClass = 'bg-success';
                      $trendArrow = '▲';
                  } elseif ($p < 0) {
                      $trendBadgeClass = 'bg-danger';
                      $trendArrow = '▼';
                  } else {
                      $trendBadgeClass = 'bg-secondary';
                      $trendArrow = '►';
                  }

                  $trendText = ($p >= 0 ? '+' : '') . number_format($p, 1, ',', '.') . '%';
              }
            ?>

            <span class="badge <?= $trendBadgeClass ?>">
              <?= $trendArrow ?> <?= htmlspecialchars($trendText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </span>
          </div>

          <div class="kpi-title"><?= $languageService->get('kpi_pageviews_7_days') ?></div>
          <p class="kpi-value"><?= number_format((int)$stats['pageviews_7d'], 0, ',', '.') ?></p>

          <div class="kpi-sub">Ø <?= $languageService->get('label_per_visit') ?>:
            <span class="badge bg-secondary">
              <?php
                if (!isset($stats['pageviews_per_visitor']) || $stats['pageviews_per_visitor'] === null) {
                    echo '—';
                } else {
                    echo number_format((float)$stats['pageviews_per_visitor'], 2, ',', '.');
                }
              ?>
            </span>
          </div>

        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="kpi-title"><?= $languageService->get('kpi_installed_plugins') ?></div>
          <p class="kpi-value"><?= number_format((int)$stats['plugin_installed'], 0, ',', '.') ?></p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="kpi-title"><?= $languageService->get('kpi_installed_themes') ?></div>
          <p class="kpi-value"><?= number_format((int)($stats['themes_installed'] ?? 0), 0, ',', '.') ?></p>
          <div class="kpi-sub">
            <?= $languageService->get('label_active_theme') ?>:
            <span class="badge bg-secondary">
                <?= htmlspecialchars((string)($theme_active_name ?? $languageService->get('value_na')), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div class="row g-4">

    <!-- MAIN -->
    <div class="col-lg-8">

    <!-- Aktivitäten -->
    <?php
    // Rohdaten holen
    $activities_all = nx_fetch_activity_stream($_database, 50, [
      'max_age_days'        => 30,
      'include_shoutbox'    => true,
      'include_audit'       => true,
      'audit_limit'         => 12,
      'forum_threads_limit' => 12,
      'forum_posts_limit'   => 12,
      'comments_limit'      => 12,
      'url_comments'        => FRONTEND_URL . '?site=articles&tab=comments',
    ]);

    function nx_badge_class(string $sev): string {
        $sev = strtolower(trim($sev));
        if ($sev === 'danger') return 'bg-danger';
        if ($sev === 'warn')   return 'bg-warning text-dark';
        if ($sev === 'muted')  return 'bg-light text-dark border';
        return 'bg-primary';
    }

    function nx_action_badge_class(string $action): string {
        $a = strtoupper(trim($action));
        if ($a === 'DELETE') return 'bg-danger';
        if ($a === 'UPDATE') return 'bg-primary';
        if ($a === 'CREATE') return 'bg-success';
        if ($a === 'LOGIN')  return 'bg-info text-dark';
        if ($a === 'LOGOUT') return 'bg-secondary';
        return 'bg-dark';
    }

  function nx_time_ago(int $ts, $languageService = null): string {
    if ($languageService === null && isset($GLOBALS['languageService'])) {
        $languageService = $GLOBALS['languageService'];
    }
    if ($languageService === null) {
        $languageService = new class {
            public function get(string $key): string { return $key; }
        };
    }

    if ($ts <= 0) return $languageService->get('value_na');
    $d = time() - $ts;

    if ($d < 60)   return $languageService->get('time_just_now');
    if ($d < 3600) return floor($d/60) . ' ' . $languageService->get('time_minutes_short');
    if ($d < 86400) return floor($d/3600) . ' ' . $languageService->get('time_hours_short');
    return date('d.m.Y H:i', $ts);
}

    // Pagination
    $perPage = 5;
    $total   = is_array($activities_all) ? count($activities_all) : 0;
    $pages   = ($total > 0) ? (int)ceil($total / $perPage) : 1;
    $page    = isset($_GET['act_page']) ? (int)$_GET['act_page'] : 1;
    if ($page < 1) $page = 1;
    if ($page > $pages) $page = $pages;

    $offset = ($page - 1) * $perPage;
    $activities = ($total > 0) ? array_slice($activities_all, $offset, $perPage) : [];

    // User-UI (Username/Rollen)
    $uids = [];
    foreach ($activities as $ev) {
        $id = (int)($ev['user_id'] ?? 0);
        if ($id > 0) $uids[$id] = true;
    }
    $uids = array_keys($uids);

    $userMap = [];
    if (!empty($uids)) {
    $in = implode(',', array_fill(0, count($uids), '?'));
    $types = str_repeat('i', count($uids));

    $sql = "
        SELECT
            u.userID,
            u.username,
            GROUP_CONCAT(DISTINCT ur.role_name ORDER BY ur.role_name SEPARATOR ', ') AS roles
        FROM users u
        LEFT JOIN user_role_assignments ura ON ura.userID = u.userID
        LEFT JOIN user_roles ur ON ur.roleID = ura.roleID
        WHERE u.userID IN ($in)
        GROUP BY u.userID
    ";

    $stmt = $_database->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$uids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $uid = (int)($row['userID'] ?? 0);
            if ($uid > 0) {
                $userMap[$uid] = [
                    'username' => (string)($row['username'] ?? ''),
                    'roles'    => (string)($row['roles'] ?? ''),
                ];
            }
        }
        $stmt->close();
    }
}

foreach ($activities as &$a) {
    $uid = (int)($a['user_id'] ?? 0);
    if ($uid <= 0) continue;

    // Username
    if (trim((string)($a['user_display'] ?? '')) === '' && isset($userMap[$uid]['username'])) {
        $a['user_display'] = $userMap[$uid]['username'];
    }

    // Rollen
    if (trim((string)($a['user_roles'] ?? '')) === '' && isset($userMap[$uid]['roles'])) {
        $a['user_roles'] = trim($userMap[$uid]['roles']);
    }

    // Profil-URL
    if (trim((string)($a['profile_url'] ?? '')) === '') {
        $a['profile_url'] = 'index.php?site=profile&id=' . $uid;
    }

    // Avatar
    if (trim((string)($a['avatar_src'] ?? '')) === '' && function_exists('getavatar')) {
        $av = trim((string)getavatar($uid));
        if ($av !== '') {
            if (!preg_match('~^https?://~i', $av) && !str_starts_with($av, '../') && !str_starts_with($av, '/')) {
                $av = '../' . ltrim($av, '/');
            }
            $a['avatar_src'] = $av;
        }
    }
}
unset($a);

    // Base Querystring
    $qs = $_GET ?? [];
    if (isset($qs['act_page'])) unset($qs['act_page']);
    $baseQs = http_build_query($qs);
    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
    $mkUrl = function(int $p) use ($baseUrl, $baseQs): string {
        $q = $baseQs;
        $q = ($q !== '' ? ($q . '&') : '') . 'act_page=' . $p;
        return htmlspecialchars($baseUrl . '?' . $q, ENT_QUOTES, 'UTF-8');
    };
    ?>

      <!-- Traffic Placeholder + Quickactions -->
      <div class="row g-4 mb-4">

        <!-- Chart -->
        <div class="col-lg-9">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
              <div class="card-title">
                <i class="bi bi-graph-up"></i> <span><?= $languageService->get('label_traffic_overview_7_days') ?></span>
              </div>
            </div>
            <div class="card-body">
              <div id="trafficChart" style="height:260px;"></div>
              <script>
                window.__traffic = {
                  labels: <?= json_encode($trafficLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
                  visitors: <?= json_encode($trafficVisitors, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
                  pageviews: <?= json_encode($trafficPageviews, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>
                };
              </script>
            </div>
          </div>
        </div>

        <!-- Quickactions -->
        <div class="col-lg-3">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column h-100">
              <div class="card-title">
                <i class="bi bi-bookmark-star"></i> <span><?= $languageService->get('label_quickactions') ?></span>
              </div>

              <div class="d-grid gap-2 flex-grow-1">
                  <a class="btn btn-outline-danger d-flex justify-content-center align-items-center"
                    href="admincenter.php?site=site_lock">
                      <?= $languageService->get('action_maintenance_mode') ?>
                  </a>

                  <a class="btn btn-outline-secondary d-flex justify-content-center align-items-center"
                    href="admincenter.php?site=user_roles">
                      <?= $languageService->get('action_user_roles') ?>
                  </a>

                  <a class="btn btn-outline-secondary d-flex justify-content-center align-items-center"
                    href="admincenter.php?site=security_overview">
                      <?= $languageService->get('action_security_activity') ?>
                  </a>

                  <a class="btn btn-outline-secondary d-flex justify-content-center align-items-center"
                    href="admincenter.php?site=log_viewer">
                      <?= $languageService->get('action_access_log') ?>
                  </a>
              </div>
            </div>
          </div>
        </div>

      </div>

    <!-- News -->
    <div class="card shadow-sm">
      <div class="card-header">
        <div class="card-title">
          <i class="bi bi-newspaper"></i> <span><?= $languageService->get('label_news_from_team') ?></span>
        </div>
      </div>
        <div class="card-body">
      
            <div class="list-group mb-4 news-list">
                <?php if (!empty($news_updates)): ?>
                    <?php foreach ($news_updates as $news): ?>
                        <?php if (!empty($news['link'])): ?>
                            <a href="<?= htmlspecialchars($news['link']); ?>" class="list-group-item list-group-item-action" target="_blank">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($news['title']); ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($news['date']); ?></small>
                                </div>
                                <p class="mb-0 small"><?= $news['summary']; ?></p>
                            </a>
                        <?php else: ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($news['title']); ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($news['date']); ?></small>
                                </div>
                                <p class="mb-0 small"><?= $news['summary']; ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="list-group-item text-muted"><?= $languageService->get('info_no_news_available') ?></div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    </div>

    <!-- SIDEBAR -->
    <div class="col-lg-4">

      <!-- Nutzer -->
      <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
          <div class="card-title">
            <i class="bi bi-people"></i> <span><?= $languageService->get('label_users') ?></span>
          </div>
        </div>
        <div class="card-body">

        <div class="mb-3">
            <?= $languageService->get('label_currently_online') ?>

        <?php if ($onlineUsers >= 1): ?>
            <span class="badge bg-success">
                <?= sprintf(
                    $languageService->get('users_online_count'),
                    (int)$onlineUsers
                ) ?>
            </span>
        <?php else: ?>
            <span class="badge bg-secondary">
                <?= $languageService->get('users_online_none') ?>
            </span>
        <?php endif; ?>
          </div>

          <?php if (!empty($onlineUserList)): ?>
            <div class="vstack gap-2 mb-3">
              <?php foreach ($onlineUserList as $u): ?>
                <?php
                  $uid = (int)($u['userID'] ?? 0);
                  $profileUrl = ($uid > 0) ? ('index.php?site=profile&id=' . $uid) : '';

                  $avatar = '';
                  if ($uid > 0 && function_exists('getavatar')) {
                      $avatar = trim((string)getavatar($uid));
                      if ($avatar !== '' && !preg_match('~^https?://~i', $avatar) && !str_starts_with($avatar, '../') && !str_starts_with($avatar, '/')) {
                          $avatar = '../' . ltrim($avatar, '/');
                      }
                  }

                  $unamePlain = html_entity_decode((string)($u['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                  if (function_exists('mb_substr')) {
                      $initial = mb_strtoupper(mb_substr($unamePlain, 0, 1, 'UTF-8'), 'UTF-8');
                  } else {
                      $initial = strtoupper(substr($unamePlain, 0, 1));
                  }
                ?>
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center gap-2" style="min-width:0;">
                    <div class="position-relative flex-shrink-0" style="width:32px;height:32px;">
                      <?php if ($avatar !== ''): ?>
                        <img src="<?= htmlspecialchars($avatar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                             class="rounded-circle"
                             style="width:32px;height:32px;object-fit:cover;"
                             alt="">
                      <?php else: ?>
                        <div class="rounded-circle border bg-light d-flex align-items-center justify-content-center"
                             style="width:32px;height:32px;font-size:.8rem;">
                          <?= htmlspecialchars($initial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2" style="min-width:0;">
                      <a class="text-decoration-none fw-semibold text-truncate"
                         style="max-width: 160px;"
                         href="<?= htmlspecialchars($profileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <?= $u['username'] ?>
                      </a>
                      <?php if (!empty($u['role_badges'])): ?>
                        <span class="d-inline-flex flex-wrap gap-1">
                          <?= $u['role_badges'] ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
              <div class="text-muted small mb-3">
                  <?= $languageService->get('users_online_none') ?>
              </div>
          <?php endif; ?>
          <hr>
          <div class="mb-3"><?= $languageService->get('label_registrations_30_days') ?></div>

          <?php if (!empty($latestUsers)): ?>
            <div class="vstack gap-2">
              <?php foreach ($latestUsers as $u): ?>
                <?php
                  $uid = (int)($u['userID'] ?? 0);
                  $profileUrl = ($uid > 0) ? ('index.php?site=profile&id=' . $uid) : '';

                  $avatar = '';
                  if ($uid > 0 && function_exists('getavatar')) {
                      $avatar = trim((string)getavatar($uid));
                      if ($avatar !== '' && !preg_match('~^https?://~i', $avatar) && !str_starts_with($avatar, '../') && !str_starts_with($avatar, '/')) {
                          $avatar = '../' . ltrim($avatar, '/');
                      }
                  }

                  $unamePlain = html_entity_decode((string)($u['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                  if (function_exists('mb_substr')) {
                      $initial = mb_strtoupper(mb_substr($unamePlain, 0, 1, 'UTF-8'), 'UTF-8');
                  } else {
                      $initial = strtoupper(substr($unamePlain, 0, 1));
                  }
                ?>
                <div class="d-flex align-items-start justify-content-between">
                  <div class="d-flex align-items-center gap-2" style="min-width:0;">
                    <div class="flex-shrink-0" style="width:32px;height:32px;">
                      <?php if ($avatar !== ''): ?>
                        <img src="<?= htmlspecialchars($avatar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                             class="rounded-circle"
                             style="width:32px;height:32px;object-fit:cover;"
                             alt="">
                      <?php else: ?>
                        <div class="rounded-circle border bg-light d-flex align-items-center justify-content-center"
                             style="width:32px;height:32px;font-size:.8rem;">
                          <?= htmlspecialchars($initial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2" style="min-width:0;">
                      <a class="text-decoration-none fw-semibold text-truncate"
                         style="max-width: 160px;"
                         href="<?= htmlspecialchars($profileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <?= $u['username'] ?>
                      </a>
                      <?php if (!empty($u['role_badges'])): ?>
                        <span class="d-inline-flex flex-wrap gap-1">
                          <?= $u['role_badges'] ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="text-muted small ms-2 flex-shrink-0"><?= htmlspecialchars((string)($u['when'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-muted small"><?= $languageService->get('info_no_data_available') ?></div>
          <?php endif; ?>

        </div>
      </div>

      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="card-title">
            <i class="bi bi-activity"></i> <span><?= $languageService->get('label_recent_activity') ?></span>
          </div>
        </div>

        <div class="list-group list-group-flush">
          <?php if (empty($activities)): ?>
            <div class="p-3 text-muted"><?= $languageService->get('info_no_activities_found') ?></div>
          <?php else: ?>
            <?php foreach ($activities as $a): ?>
              <?php
                $badge = nx_badge_class((string)($a['severity'] ?? 'info'));
                $ts    = (int)($a['ts'] ?? 0);
                $timeLabel = nx_time_ago($ts, $languageService);
                $url   = trim((string)($a['url'] ?? ''));

                if (($a['source'] ?? '') === $languageService->get('source_admin_activity')) {
                    $badge = 'bg-primary';
                }

                // User UI
                $uid        = (int)($a['user_id'] ?? 0);
                $userName   = trim((string)($a['user_display'] ?? ''));
                if ($userName === '') $userName = '—';

                // Rollen
                $rolesLabel = trim((string)($a['user_roles'] ?? ''));

                $profileUrl = trim((string)($a['profile_url'] ?? ''));
                $avatar     = trim((string)($a['avatar_src'] ?? ''));
                $hasAvatar  = ($avatar !== '');

                // Action badge
                $action = strtoupper(trim((string)($a['action'] ?? '')));
                $actionBadge = ($action !== '') ? nx_action_badge_class($action) : '';
                $actionDisplay = ($action === 'UPDATE') ? 'EDIT' : $action;
                $actionLabel = $action;
                if ($action === 'UPDATE') $actionLabel = 'EDIT';
              ?>

              <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start gap-3">

                  <!-- LEFT -->
                  <div class="flex-grow-1">

                    <!-- User line -->
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <?php if ($hasAvatar): ?>
                        <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>"
                            alt=""
                            width="22" height="22"
                            class="rounded-circle">
                      <?php else: ?>
                        <div class="rounded-circle bg-light border" style="width:22px;height:22px"></div>
                      <?php endif; ?>

                      <?php if ($uid > 0 && $profileUrl !== ''): ?>
                        <a class="text-decoration-none fw-semibold"
                          href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>">
                          <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?><?php if (!empty($rolesLabel)): ?> <span class="text-muted">(<?= htmlspecialchars($rolesLabel, ENT_QUOTES, 'UTF-8') ?>)</span><?php endif; ?>
                        </a>
                      <?php else: ?>
                        <span class="fw-semibold"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span><?php if (!empty($rolesLabel)): ?> <span class="text-muted">(<?= htmlspecialchars($rolesLabel, ENT_QUOTES, 'UTF-8') ?>)</span><?php endif; ?>
                      <?php endif; ?>
                    </div>

                    <!-- Event header -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <i class="bi <?= htmlspecialchars((string)($a['icon'] ?? 'bi-activity'), ENT_QUOTES, 'UTF-8') ?>"></i>
                      <span class="badge <?= $badge ?>"><?= htmlspecialchars((string)($a['source'] ?? 'Info'), ENT_QUOTES, 'UTF-8') ?></span>

                      <?php if ($action !== ''): ?>
                        <span class="badge <?= htmlspecialchars($actionBadge, ENT_QUOTES, 'UTF-8') ?>">
                          <?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                      <?php endif; ?>

                      <span class="fw-semibold">
                          <?php if (($a['source'] ?? '') === $languageService->get('source_admin_activity')): ?>
                              <?= (string)($a['title'] ?? '') ?>
                          <?php else: ?>
                          <?php if (!empty($a['title_html'])): ?>
                            <?= (string)$a['title_html'] ?>
                          <?php elseif ($url !== ''): ?>
                            <a class="fw-semibold text-decoration-none"
                              href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                              <?= htmlspecialchars((string)($a['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                          <?php else: ?>
                            <span class="fw-semibold">
                              <?= htmlspecialchars((string)($a['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </span>
                    </div>

                    <!-- Meta -->
                    <?php if (!empty($a['meta_html'])): ?>
                      <div class="small text-muted mt-1">
                        <?= (string)$a['meta_html'] ?>
                      </div>
                    <?php elseif (!empty($a['meta'])): ?>
                      <div class="small text-muted mt-1">
                        <?= htmlspecialchars((string)$a['meta'], ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php endif; ?>

                  </div>

                  <!-- RIGHT -->
                  <div class="text-end" style="min-width: 110px;">
                    <div class="small text-muted"><?= htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8') ?></div>

                  <?php if (!empty($a['url'])): ?>
                      <?php
                          $url = (string)$a['url'];
                          $isFrontend = defined('FRONTEND_URL') && str_starts_with($url, FRONTEND_URL);
                      ?>
                    <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                      class="btn btn-sm btn-outline-secondary mt-2"
                      <?= $isFrontend ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                        <?= $languageService->get('action_view') ?>
                    </a>
                  <?php endif; ?>
                  </div>

                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($pages > 1): ?>
          <div class="card-footer bg-white">
            <!-- Pagination -->
            <nav aria-label="Seiten-Navigation">
              <ul class="pagination justify-content-center mb-0">

                <!-- Prev -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                  <?php if ($page <= 1): ?>
                    <span class="page-link" aria-disabled="true" aria-label="Zurück">
                      <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </span>
                  <?php else: ?>
                    <a class="page-link"
                       href="<?= $mkUrl($page - 1) ?>"
                       aria-label="Zurück"
                       title="Zurück">
                      <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </a>
                  <?php endif; ?>
                </li>

                <!-- Seitenzahlen komplett -->
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                  <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                    <?php if ($p == $page): ?>
                      <span class="page-link" aria-current="page"><?= (int)$p ?></span>
                    <?php else: ?>
                      <a class="page-link" href="<?= $mkUrl($p) ?>"><?= (int)$p ?></a>
                    <?php endif; ?>
                  </li>
                <?php endfor; ?>

                <!-- Next -->
                <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                  <?php if ($page >= $pages): ?>
                    <span class="page-link" aria-disabled="true" aria-label="Weiter">
                      <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </span>
                  <?php else: ?>
                    <a class="page-link"
                       href="<?= $mkUrl($page + 1) ?>"
                       aria-label="Weiter"
                       title="Weiter">
                      <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>
                  <?php endif; ?>
                </li>

              </ul>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<script>
  window.__nxLocale = <?= json_encode((string)($currentLocale ?? 'de-DE')) ?>;
  window.__nxI18n = {
    visitors: <?= json_encode($languageService->get('kpi_visitors')) ?>,
    pageviews: <?= json_encode($languageService->get('kpi_pageviews')) ?>
  };
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.ApexCharts || !window.__traffic) return;

  const el = document.querySelector('#trafficChart');
  if (!el) return;

  const t = window.__traffic;

  const locale = window.__nxLocale || 'de-DE';
  const i18n = window.__nxI18n || { visitors: 'Besucher', pageviews: 'Seitenaufrufe' };

  function cssVar(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }

  const AC_PRIMARY = cssVar('--ac-primary', '#fe821d');

const options = {
  chart: {
    type: 'area',
    height: 260,
    toolbar: { show: false }
  },

  colors: [AC_PRIMARY, AC_PRIMARY],

  series: [
    { name: i18n.visitors, data: t.visitors || [] },
    { name: i18n.pageviews, data: t.pageviews || [] }
  ],

  yaxis: [
    {
      title: { text: i18n.visitors },
      labels: { formatter: v => Math.round(v) }
    },
    {
      opposite: true,
      title: { text: i18n.pageviews },
      labels: { formatter: v => Math.round(v) }
    }
  ],

  xaxis: {
    categories: t.labels || [],
    labels: {
      rotate: -45,
      formatter: function (value) {
        const d = new Date(value);
        return d.toLocaleDateString(locale, { day: '2-digit', month: '2-digit' });
      }
    },
    axisBorder: { show: false },
    axisTicks: { show: false }
  },

  stroke: { curve: 'smooth', width: 2, dashArray: [0, 6] },

  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      gradientToColors: ['#93C5FD', '#FCA5A5'],
      inverseColors: false,
      opacityFrom: 0.35,
      opacityTo: 0.03,
      stops: [0, 90, 100]
    }
  },

  markers: { size: 0, hover: { sizeOffset: 2 } },
  dataLabels: { enabled: false },
  tooltip: { shared: true, intersect: false },

legend: {
  show: true,
  position: 'top',
  horizontalAlign: 'center',
  offsetY: 8,

  markers: {
    show: false   // 👈 PUNKTE WEG
  },

  formatter: function(seriesName, opts) {
    const i = opts.seriesIndex;
    const dashed = i === 1;

    return `
      <span style="display:inline-flex;align-items:center;gap:6px">
        <svg width="30" height="6" viewBox="0 0 30 6" xmlns="http://www.w3.org/2000/svg">
          <line x1="0" y1="3" x2="30" y2="3"
                stroke="${AC_PRIMARY}"
                stroke-width="2"
                stroke-dasharray="${dashed ? '6,6' : '0'}"
                stroke-linecap="round" />
        </svg>
        <span>${seriesName}</span>
      </span>
    `;
  }
},



  grid: {
    show: true,
    borderColor: 'rgba(0,0,0,0.08)',
    strokeDashArray: 4,
    padding: { bottom: 20 }
  }
};


  new ApexCharts(el, options).render();
});
</script>
