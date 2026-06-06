<?php
declare(strict_types=1);

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

// KEIN session_start() hier!
// DB-Handle und Globals holen
/** @var mysqli|null $_database */
$_database = $GLOBALS['_database'] ?? null;
global $languageService, $tpl;

// --- kleine Helper ---
function dbh(): ?mysqli {
    return $GLOBALS['_database'] ?? null;
}
function db_table_exists(string $table): bool {
    $db = dbh();
    if (!$db instanceof mysqli) return false;
    $t = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$t}'");
    return (bool)($res && $res->num_rows > 0);
}
function plugin_is_installed(string $modulname): bool {
    $db = dbh();
    if (!$db instanceof mysqli || !db_table_exists('settings_plugins_installed')) return false;
    $mod = $db->real_escape_string($modulname);
    if ($res = $db->query("SELECT COUNT(*) AS installed FROM settings_plugins_installed WHERE modulname='{$mod}'")) {
        $row = $res->fetch_assoc();
        return ((int)($row['installed'] ?? 0) > 0);
    }
    return false;
}
function plugin_is_active(string $modulname): bool {
    $db = dbh();
    if (!$db instanceof mysqli || !db_table_exists('settings_plugins')) return false;
    $mod = $db->real_escape_string($modulname);
    if ($res = $db->query("SELECT activate FROM settings_plugins WHERE modulname='{$mod}' LIMIT 1")) {
        $row = $res->fetch_assoc();
        return ((int)($row['activate'] ?? 0) === 1);
    }
    return false;
}

$lang = $languageService->detectLanguage();

// Verfügbare Sprachen (optional, falls genutzt)
$availableLangs = [];
if (db_table_exists('settings_languages')) {
    $result = safe_query("SELECT iso_639_1 FROM settings_languages WHERE active = 1");
    while ($row = mysqli_fetch_assoc($result)) {
        $availableLangs[] = $row['iso_639_1'];
    }
}

$languageService->readModule('index');

$loggedin = !empty($_SESSION['userID']) && (int)$_SESSION['userID'] > 0;
$userID   = (int)($_SESSION['userID'] ?? 0);

// -------- Navigation ohne Dropdown --------
function navigation_nodropdown(string $default_url): string {
    $db = dbh();
    global $languageService;

    $newurl = $default_url;
    $mr_res = safe_query("SELECT * FROM `settings` WHERE 1 LIMIT 1");
    $mr = mysqli_fetch_array($mr_res);
    if ($mr && isset($mr['modRewrite']) && (int)$mr['modRewrite'] === 1) {
        $urlParts = explode("/", trim($_SERVER["REQUEST_URI"] ?? '', "/"));
        if (!empty($urlParts[0])) {
            if (strpos($urlParts[0], '.') !== false) {
                $newurl = "index.php?site=" . htmlspecialchars(explode(".", $urlParts[0])[0]);
            } else {
                $newurl = "index.php?site=" . htmlspecialchars($urlParts[0]);
            }
        }
    }

    try {
        if (!$db instanceof mysqli || !db_table_exists('navigation_website_sub')) return '';
        $escapedUrl = $db->real_escape_string($newurl);
        $rex = safe_query("SELECT * FROM `navigation_website_sub` WHERE `url`='{$escapedUrl}' LIMIT 1");
        if (mysqli_num_rows($rex)) {
            $output = "";
            $rox = mysqli_fetch_array($rex);
            $mnavID = (int)$rox['mnavID'];
            $res = safe_query("SELECT * FROM `navigation_website_sub` WHERE `mnavID`='{$mnavID}' AND `indropdown`='0' ORDER BY `sort`");
            while ($row = mysqli_fetch_array($res)) {
                $nameKey = strtolower($row['name']);
                $name = $languageService->module[$nameKey] ?? $row['name'];
                $url = (string)$row['url'];
                $target = (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) ? ' target="_blank" rel="noopener noreferrer"' : '';
                $output .= '<li class="nav-item"><a class="dropdown-item" href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $target . '>' . htmlspecialchars($name, ENT_QUOTES) . '</a></li>';
            }
            return $output;
        }
        return '';
    } catch (Throwable $e) {
        if (defined('DEBUG') && DEBUG === "ON") {
            return 'Fehler: ' . $e->getMessage();
        }
        return '';
    }
}

// -------- Hauptnavigation rendern --------
try {
    if (!db_table_exists('navigation_website_main')) {
        throw new RuntimeException('navigation_website_main missing');
    }

    $res = safe_query("SELECT * FROM `navigation_website_main` ORDER BY `sort`");
    $lo  = 0;

    while ($row = mysqli_fetch_array($res)) {
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($row['name']);
        $name = $translate->getTextByLanguage($row['name']);

        $head_array = [
            'name'    => $name,
            'url'     => $row['url'],
            'windows' => $row['windows'] ? '' : '_blank',
        ];

        if ((int)$row['isdropdown'] === 1) {
            // Login/Overview Eintrag nur einmal einblenden
            $head_array['login_overview'] = ($lo === 1)
                ? ($loggedin
                    ? '<li class="nav-item"><a class="dropdown-item" href="' . htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=loginoverview')) . '">' . ($languageService->module['overview'] ?? 'Overview') . '</a></li>'
                    : '<li class="nav-item"><a class="dropdown-item" href="' . htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=login')) . '">' . ($languageService->module['login'] ?? 'Login') . '</a></li>')
                : '';
            $lo++;

            if (db_table_exists('navigation_website_sub')) {
                $mnavID = (int)$row['mnavID'];
                $rex = safe_query("SELECT * FROM `navigation_website_sub` WHERE `mnavID`='{$mnavID}' AND `indropdown`='1' ORDER BY `sort`");
                if (mysqli_num_rows($rex)) {
                    echo $tpl->loadTemplate("navigation", "dd_head", $head_array, 'theme');
                    echo $tpl->loadTemplate("navigation", "sub_open", [], 'theme');

                    while ($rox = mysqli_fetch_array($rex)) {
                        $translate->detectLanguages($rox['name']);
                        $sub_name = $translate->getTextByLanguage($rox['name']);
                        $sub_url  = (string)$rox['url'];

                        if (str_starts_with($sub_url, 'index.php?site=')) {
                            $sub_url = SeoUrlHandler::convertToSeoUrl($sub_url);
                        } elseif (str_ends_with($sub_url, '.php') && !str_starts_with($sub_url, 'http')) {
                            $sub_url = SeoUrlHandler::convertToSeoUrl('index.php?site=' . basename($sub_url, '.php'));
                        }

                        $target = (str_starts_with($sub_url, 'http://') || str_starts_with($sub_url, 'https://')) ? '_blank' : '';

                        $sub_array = [
                            'url'    => $sub_url,
                            'name'   => $sub_name,
                            'target' => $target
                        ];

                        echo $tpl->loadTemplate("navigation", "sub_nav", $sub_array, 'theme');
                    }

                    echo $tpl->loadTemplate("navigation", "sub_close", [], 'theme');
                    echo $tpl->loadTemplate("navigation", "dd_foot", [], 'theme');
                }
            }
        } else {
            $target = (str_starts_with($row['url'], 'http://') || str_starts_with($row['url'], 'https://')) ? '_blank' : '';
            $head_array['target'] = $target;
            echo $tpl->loadTemplate("navigation", "main_head", $head_array, 'theme');
        }
    }
} catch (Throwable $e) {
    error_log('navigation render error: '.$e->getMessage());
    // Kein echo hier, damit keine Header zerstört werden
}

// -------- Login + Badges (Messenger / Forum) --------
if ($loggedin) {
    $l_avatar = getavatar($userID) ?: "noavatar.png";

    $dashboard = (checkUserRoleAssignment($userID, 1))
        ? '<li><a class="dropdown-item" href="' . htmlspecialchars('/admin/admincenter.php') . '" target="_blank">&nbsp;' . ($languageService->module['admincenter'] ?? 'Admin Center') . '</a></li>'
        : '';

    $profile = '<li><a class="dropdown-item" href="' .
        htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=profile&userID='.$userID)) .
        '">&nbsp;' . ($languageService->module['to_profil'] ?? 'Profil') . '</a></li>';

    $logout = '<li><a class="dropdown-item" href="' .
        htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=logout')) .
        '">&nbsp;' . ($languageService->module['log_off'] ?? 'Logout') . '</a></li>';

    // Messenger-Badge nur, wenn Plugin installiert + Tabellen existieren
    $messenger = '';
    if (plugin_is_installed('messenger') && plugin_is_active('messenger') && db_table_exists('plugins_messages')) {
        $uid = $userID;
        $unreadCount = 0;
        if ($_database instanceof mysqli) {
            if ($res = $_database->query("SELECT COUNT(*) AS unread FROM plugins_messages WHERE receiver_id={$uid} AND is_read=0")) {
                $row = $res->fetch_assoc();
                $unreadCount = (int)($row['unread'] ?? 0);
            }
        }
        $badgeStyle   = $unreadCount > 0 ? 'inline-block' : 'none';
        $badgeContent = $unreadCount > 0 ? (string)$unreadCount : '';
        $newMailTooltip = $unreadCount > 0 ? 'Neue Nachrichten' : 'Keine neuen Nachrichten';

        $messenger = '<a class="nav-link messenger-link" 
            href="' . htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=messenger')) . '"
            data-bs-toggle="tooltip" data-bs-placement="bottom" title="'.$newMailTooltip.'">
            <i id="mail-icon" class="bi bi-envelope-dash"></i>
            <span id="total-unread-badge" style="display:' . $badgeStyle . ';">' . $badgeContent . '</span>
        </a>';
    }

    // Forum-Badge nur, wenn Plugin aktiv + Tabellen existieren
    $newPostsHtml = '';
    if (plugin_is_installed('forum') && plugin_is_active('forum') && db_table_exists('plugins_forum_posts') && db_table_exists('plugins_forum_read')) {
        $uid = $userID;
        $newPostsCount = 0;
        if ($_database instanceof mysqli) {
            $sql = "
                SELECT COUNT(*) AS new_posts
                FROM plugins_forum_posts p
                LEFT JOIN plugins_forum_read r 
                    ON r.userID = {$uid} AND r.threadID = p.threadID
                WHERE p.created_at > IFNULL(r.last_read_at, '1970-01-01 00:00:00')
            ";
            if ($res = $_database->query($sql)) {
                $row = $res->fetch_assoc();
                $newPostsCount = (int)($row['new_posts'] ?? 0);
            }
        }
        $badgeStyle   = $newPostsCount > 0 ? 'inline-block' : 'none';
        $badgeContent = $newPostsCount > 0 ? (string)$newPostsCount : '';
        $newPostsTooltip = $newPostsCount > 0 ? 'Neue Beiträge' : 'Keine neuen Beiträge';

        $newPostsHtml = '<a class="nav-link messenger-link" 
            href="' . htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum')) . '"
            data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . htmlspecialchars($newPostsTooltip, ENT_QUOTES) . '">
            <i id="mail-icon" class="bi bi-chat-dots"></i>
            <span id="total-unread-badge" style="display:' . $badgeStyle . ';">' . $badgeContent . '</span>
        </a>';
    }

    $data_array = [
        'modulepath'       => substr(MODULE, 0, -1),
        'l_avatar'         => $l_avatar,
        'nickname'         => getusername($userID),
        'profile'          => $profile,
        'dashboard'        => $dashboard,
        'logout'           => $logout,
        'messenger'        => $messenger,
        'new_forum_posts'  => $newPostsHtml,
        'lang_overview'    => $languageService->module['overview'] ?? 'Übersicht',
        'my_account'       => $languageService->module['my_account'] ?? 'Mein Konto',
    ];
    echo $tpl->loadTemplate("navigation", "login_loggedin", $data_array, 'theme');

} else {
    $loginLink = htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=login'));
    $login = '<li><a class="nav-link" href="' . $loginLink . '">' . ($languageService->module['login'] ?? 'Login') . '</a></li>';

    $data_array = [
        'modulepath' => substr(MODULE, 0, -1),
        'login'      => $login
    ];
    echo $tpl->loadTemplate("navigation", "login_login", $data_array, 'theme');
}
