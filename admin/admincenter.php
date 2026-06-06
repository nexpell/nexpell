<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e) {
        echo "<pre style='background:#300;color:#fff;padding:10px'>";
        print_r($e);
        echo "</pre>";
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


define('BASE_PATH', __DIR__ . '/../');
define('SYSTEM_PATH', BASE_PATH . 'system/');

// CORE
require SYSTEM_PATH . 'config.inc.php';
require SYSTEM_PATH . 'settings.php';
require SYSTEM_PATH . 'functions.php';
//require SYSTEM_PATH . 'multi_language.php';
require SYSTEM_PATH . 'classes/Template.php';
require SYSTEM_PATH . 'classes/TextFormatter.php';
require SYSTEM_PATH . 'classes/AdminAudit.php';

if (!class_exists(\nexpell\PluginManager::class)) {
    require SYSTEM_PATH . 'classes/PluginManager.php';
}

use nexpell\PluginManager;
use nexpell\LanguageService;
use nexpell\AccessControl;

/* =========================
   GLOBALS
========================= */
global $_database;
$_database->set_charset("utf8mb4");
/* =========================
   SITE bestimmen (aus URL)
========================= */
$currentSite = $_GET['site'] ?? 'admincenter';
$GLOBALS['nx_active_module'] = $currentSite;

/* =========================
   LANGUAGE SERVICE
========================= */
$languageService = new LanguageService($_database);
global $languageService;

/* =========================
   PLUGIN MANAGER
========================= */
$pluginManager = new PluginManager($_database);

/* =========================
   AUTOLOAD MODULSPRACHE
========================= */
$languageService->autoLoadActiveModule(true);



$GLOBALS['_database'] = $_database;


// LOGIN HANDLING (ALT / BACKWARD-COMPAT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ws_user'], $_POST['password'])) {

    $result = loginCheck(trim($_POST['ws_user']), $_POST['password']);

    if ($result->state === "success") {

        $_SESSION['userID']   = $result->userID;
        $_SESSION['username'] = $result->username;

        $url = $_SESSION['login_redirect'] ?? '/admin/admincenter.php';
        unset($_SESSION['login_redirect']);

        if (!preg_match('#^/admin/#', $url)) {
            $url = '/admin/admincenter.php';
        }

        header("Location: $url");
        exit;
    }

    $GLOBALS['__nx_alerts'][] = [
        'type'        => 'warning',
        'message'     => (string)$result->message,
        'dismissible' => true,
    ];
}

// USER / ROUTER
$userID = (int)($_SESSION['userID'] ?? 0);

$site = $_GET['site'] ?? 'info';
$site = preg_replace('/[^a-z0-9_]/i', '', (string)$site);

if ($site === 'info' || $site === 'dashboard') {
    $GLOBALS['__pageTitle']    = 'Dashboard';
    $GLOBALS['__pageCategory'] = null;
}

// NICHT EINGELOGGT → ADMIN-LOGIN
if ($userID <= 0) {

    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code(401);

    echo '
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="SHORTCUT ICON" href="/admin/images/favicon.ico">
        <title>' . htmlspecialchars($languageService->get('access-denied-title'), ENT_QUOTES, 'UTF-8') . '</title>
        <link href="/admin/css/bootstrap.min.css" rel="stylesheet">
        <link href="/admin/css/page.css" rel="stylesheet">
    </head>
    <body>

    <div class="login-page">
        <div class="login-wrap">
            <div class="card login-card">
                <div class="login-card-header access">
                    <div class="brand">
                        <img src="/admin/images/logo.png" alt="Logo">
                    </div>
                    <h4 class="mb-1">
                        ' . $languageService->get('access-denied-title') . '
                    </h4>
                </div>

                <div class="card-body text-center">
                    <p class="py-4">
                        ' . $languageService->get('access-denied-desc_nologin') . '
                    </p>

                    <div class="d-grid">
                        <a href="login.php" class="btn btn-danger">
                            ' . $languageService->get('access-denied-login') . '
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </body>
    </html>';
    exit;
}

// EINGELOGGT, ABER KEINE ADMIN-RECHTE
if (!AccessControl::canAccessAdmin($_database, $userID)) {

    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code(403);

    echo '
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . htmlspecialchars($languageService->get('access-denied-title'), ENT_QUOTES, 'UTF-8') . '</title>
        <link href="/admin/css/bootstrap.min.css" rel="stylesheet">
        <link href="/admin/css/page.css" rel="stylesheet">
    </head>
    <body>

    <div class="login-page">
        <div class="login-wrap">
            <div class="card login-card">
                <div class="login-card-header access">
                    <div class="brand">
                        <img src="/admin/images/logo.png" alt="Logo">
                    </div>
                    <h4 class="mb-1">
                        ' . $languageService->get('access-denied-title') . '
                    </h4>
                </div>

                <div class="card-body text-center">
                    <p class="py-4">
                        ' . $languageService->get('access-denied-desc') . '
                    </p>

                    <p class="text-muted small">
                        ' . $languageService->get('error_support_admin') . '
                    </p>

                    <div class="d-grid">
                        <a href="/" class="btn btn-secondary mt-2">
                            ' . $languageService->get('back_to_website') . '
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </body>
    </html>';
    exit;
}

// HILFSWERTE (KEIN DOPPELTES $userID!)
$lang = $_SESSION['language'];
$current_site = $_GET['site'] ?? '';


//  Übersetzt einen Key über den globalen LanguageService.
function nx_translate(string $key): string
{
    if (isset($GLOBALS['languageService']) && is_object($GLOBALS['languageService']) && method_exists($GLOBALS['languageService'], 'get')) {
        $t = (string)$GLOBALS['languageService']->get($key);
        return $t !== '' ? $t : $key;
    }
    return $key;
}

// Adds an alert.
function nx_add_alert(
    string $type,
    string $keyOrMessage,
    bool $persist = true,
    bool $dismissible = true,
    bool $isRaw = false
): void {
    if (!$isRaw && (strpos($keyOrMessage, ' ') !== false || preg_match('/[^A-Za-z0-9_.-]/', $keyOrMessage))) {
        $isRaw = true;
    }

    $message = $isRaw
        ? $keyOrMessage
        : (string)$GLOBALS['languageService']->get($keyOrMessage);

    $alert = [
        'type'        => $type,
        'message'     => $message,
        'dismissible' => $dismissible,
    ];

    if ($persist) {
        $_SESSION['__nx_alerts'][] = $alert;
    } else {
        $GLOBALS['__nx_alerts'][] = $alert;
    }
}

// Kompakter Alias für Alerts.
function nx_alert(
    string $type,
    string $keyOrMessage,
    bool $persist = true,
    bool $dismissible = true,
    bool $isRaw = false
): void {
    nx_add_alert($type, $keyOrMessage, $persist, $dismissible, $isRaw);
}

// Rendert alle alerts (flash + request) als Bootstrap 5 markup.
function nx_render_alerts(bool $escapeHtml = true): string
{
    $alerts = [];

    if (!empty($_SESSION['__nx_alerts'])) {
        $alerts = $_SESSION['__nx_alerts'];
        unset($_SESSION['__nx_alerts']);
    }
    if (!empty($GLOBALS['__nx_alerts'])) {
        $alerts = array_merge($alerts, $GLOBALS['__nx_alerts']);
        unset($GLOBALS['__nx_alerts']);
    }

    if (!$alerts) return '';

    $out = '<div class="nx-alert-stack mb-3">';
    foreach ($alerts as $a) {
        $type = preg_replace('/[^a-z]/', '', strtolower((string)($a['type'] ?? 'info'))) ?: 'info';

        $msg = (string)($a['message'] ?? '');
        $msg = $escapeHtml ? htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') : $msg;

        $dismissible = !empty($a['dismissible']);
        $classes = 'alert alert-' . $type . ($dismissible ? ' alert-dismissible fade show' : '');

        $out .= '<div class="' . $classes . '" role="alert">' . $msg;
        if ($dismissible) {
            $out .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        }
        $out .= '</div>';
    }
    return $out . '</div>';
}

// Redirect
function nx_redirect(string $url, ?string $type=null, ?string $keyOrMessage=null,
                     bool $dismissible=true, bool $isRaw=false, int $status=303): void
{
    if ($type && $keyOrMessage) {
        nx_add_alert($type, $keyOrMessage, true, $dismissible, $isRaw);
    }

    if (!headers_sent()) {
        header('Location: ' . $url, true, $status);
        exit;
    }

    if (ob_get_level() > 0) {
        @ob_clean();
    }

    $uJson = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $uHtml = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="de"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . $uHtml . '">';
    echo '<title>Weiterleitung…</title>';
    echo '</head><body>';
    echo '<script>window.location.replace(' . $uJson . ');</script>';
    echo '<noscript><p>Weiterleitung: <a href="' . $uHtml . '">' . $uHtml . '</a></p></noscript>';
    echo '</body></html>';
    exit;
}


function dashnavi()
{
    global $_database, $languageService;

    // GRUNDWERTE ABSICHERN
    $uid = (int)($_SESSION['userID'] ?? 0);
    if ($uid <= 0) {
        return '<li>Kein Benutzer angemeldet.</li>';
    }

    $lang = $_SESSION['language'] ?? 'de';
    $current_site = $_GET['site'] ?? '';

    // ROLLEN DES USERS LADEN (MULTI-ROLE SICHER)
    $roleIDs = [];

    $resRoles = $_database->query("
        SELECT roleID
        FROM user_role_assignments
        WHERE userID = {$uid}
    ");

    if (!$resRoles) {
        return '<li>Fehler beim Laden der Benutzerrollen.</li>';
    }

    while ($r = $resRoles->fetch_assoc()) {
        $roleIDs[] = (int)$r['roleID'];
    }

    if (empty($roleIDs)) {
        return '<li>' . $languageService->get('error_no_roles_found') . '</li>';
    }

    $roleList = implode(',', array_map('intval', $roleIDs));

    // RECHTE DER ROLLEN LADEN

    $rights = [
        'category' => [],
        'link'     => []
    ];

    $resRights = $_database->query("
        SELECT type, modulname
        FROM user_role_admin_navi_rights
        WHERE roleID IN ($roleList)
    ");

    if (!$resRights) {
        return '<li>Fehler beim Laden der Navigationsrechte.</li>';
    }

    while ($r = $resRights->fetch_assoc()) {
        if (!isset($rights[$r['type']])) {
            continue;
        }
        $rights[$r['type']][] = $r['modulname'];
    }

    if (empty($rights['category']) || empty($rights['link'])) {
        return '
            <div class="alert alert-info mb-0 small d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div>
                    <strong>' . $languageService->get('error_no_areas') . '</strong><br>
                    ' . $languageService->get('error_no_linked') . '<br>
                    <span class="text-muted">' . $languageService->get('error_support_admin') . '</span>
                </div>
            </div>';
    }

    // KATEGORIEN LADEN
    $out = '';

    $cats = $_database->query("
        SELECT *
        FROM navigation_dashboard_categories
        ORDER BY sort
    ");

    if (!$cats) {
        return '<li>Fehler beim Laden der Kategorien.</li>';
    }

    while ($cat = $cats->fetch_assoc()) {

        // Kategorie-Rechte prüfen
        if (!in_array($cat['modulname'], $rights['category'], true)) {
            continue;
        }

        $resCatTitle = $_database->query("
            SELECT content
            FROM navigation_dashboard_lang
            WHERE content_key = 'nav_cat_" . (int)$cat['catID'] . "'
              AND language = '" . $_database->real_escape_string($lang) . "'
            LIMIT 1
        ");

        $catName = ($resCatTitle && ($r = $resCatTitle->fetch_assoc()))
            ? $r['content']
            : 'Kategorie ' . (int)$cat['catID'];


        $linksHtml = '';
        $activeCat = false;

        // LINKS DER KATEGORIE LADEN
        $lq = $_database->query("
            SELECT *
            FROM navigation_dashboard_links
            WHERE catID = " . (int)$cat['catID'] . "
            ORDER BY sort
        ");

        if (!$lq) {
            continue;
        }

        while ($link = $lq->fetch_assoc()) {

            // Link-Rechte prüfen
            if (!in_array($link['modulname'], $rights['link'], true)) {
                continue;
            }

            $resLinkTitle = $_database->query("
                SELECT content
                FROM navigation_dashboard_lang
                WHERE content_key = 'nav_link_" . (int)$link['linkID'] . "'
                  AND language = '" . $_database->real_escape_string($lang) . "'
                LIMIT 1
            ");

            $linkName = ($resLinkTitle && ($r2 = $resLinkTitle->fetch_assoc()))
                ? $r2['content']
                : 'Link ' . (int)$link['linkID'];


            // Aktive Seite ermitteln
            $urlParts = parse_url($link['url']);
            parse_str($urlParts['query'] ?? '', $qs);

            $isActive = isset($qs['site']) && $qs['site'] === $current_site;

            if ($isActive) {
                $activeCat = true;
                $GLOBALS['__pageTitle']    = $linkName;
                $GLOBALS['__pageCategory'] = $catName;
            }

            $linksHtml .= "
                <li class='nav-item'>
                    <a class='nav-link" . ($isActive ? " active" : "") . "'
                       " . ($isActive ? "aria-current='page'" : "") . "
                       href='" . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . "'>
                        <span>" . htmlspecialchars($linkName, ENT_QUOTES, 'UTF-8') . "</span>
                    </a>
                </li>";
        }

        // Kategorie nur ausgeben, wenn Links sichtbar sind
        if ($linksHtml === '') {
            continue;
        }

        // ACCORDION-AUSGABE
        $catId      = (int)$cat['catID'];
        $headingId  = "sidebarHeading{$catId}";
        $collapseId = "sidebarCollapse{$catId}";
        $expanded   = $activeCat ? 'true' : 'false';

        $out .= "
        <div class='accordion-item'>
            <h2 class='accordion-header' id='{$headingId}'>
                <button class='accordion-button" . ($activeCat ? "" : " collapsed") . "' type='button'
                        data-bs-toggle='collapse'
                        data-bs-target='#{$collapseId}'
                        aria-expanded='{$expanded}'
                        aria-controls='{$collapseId}'>
                    <i class='" . htmlspecialchars($cat['fa_name'], ENT_QUOTES, 'UTF-8') . " me-2'></i>
                    <span class='flex-grow-1'>" . htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') . "</span>
                </button>
            </h2>
            <div id='{$collapseId}' 
             class='accordion-collapse collapse" . ($activeCat ? " show" : "") . "'
             aria-labelledby='{$headingId}'
             data-bs-parent='#sidebarAccordion'>
                <div class='accordion-body p-0'>
                    <ul class='nav flex-column ac-sidebar-subnav'>
                        {$linksHtml}
                    </ul>
                </div>
            </div>
        </div>";
    }

    // FALLBACK
    return $out ?: '
        <div class="alert alert-info mb-0 small d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>
                <strong>' . $languageService->get('error_no_areas') . '</strong><br>
                ' . $languageService->get('error_no_linked') . '<br>
                <span class="text-muted">' . $languageService->get('error_support_admin') . '</span>
            </div>
        </div>';
}


if (ob_get_level() > 0) { @ob_clean(); }
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($languageService->currentLanguage) ?>">

<head>
    <meta charset="utf-8">
    <title>Nexpell Admincenter</title>
    <link rel="SHORTCUT ICON" href="/admin/images/favicon.ico">
    <link href="/admin/css/bootstrap.min.css" rel="stylesheet">
    <link href="/admin/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/admin/css/page.css" rel="stylesheet">
</head>

<body>

<div id="wrapper">

<!-- SIDEBAR -->
<nav class="sidebar">
    <div class="sidebar-logo-wrap">
        <div class="sidebar-logo">
            <a href="/admin/admincenter.php">
                <img src="/admin/images/logo_dark.png">
            </a>
        </div>
    </div>
        <div class="accordion ac-sidebar-accordion" id="sidebarAccordion">
            <?php
            $userID = (int)($_SESSION['userID'] ?? 0);

            $unreadCount = 0;
            $hasMessengerTable = false;

            if ($userID > 0 && isset($_database) && $_database instanceof mysqli) {

                // Prüfen ob Tabelle existiert
                $res = $_database->query("SHOW TABLES LIKE 'plugins_messages'");
                $hasMessengerTable = ($res && $res->num_rows > 0);

                // Nur wenn Plugin Messages existiert -> ungelesene zählen
                if ($hasMessengerTable) {
                    $uid = $_database->real_escape_string((string)$userID);

                    $res2 = $_database->query("
                        SELECT COUNT(*) AS cnt
                        FROM plugins_messages
                        WHERE receiver_id = '{$uid}'
                        AND is_read = 0
                    ");

                    if ($res2 && ($row = $res2->fetch_assoc())) {
                        $unreadCount = (int)$row['cnt'];
                    }
                }
            }
            $profileID = $userID;
            $avatar    = getavatar($userID);
            $username  = trim(strip_tags(getusername($userID)));
            ?>

            <!-- User-Panel -->
            <div class="accordion-item ac-user-item">
            <h2 class="accordion-header" id="userHeading">
                <button class="accordion-button collapsed ac-user-button" type="button"
                        data-bs-toggle="collapse" data-bs-target="#userCollapse"
                        aria-expanded="false" aria-controls="userCollapse">
                <img src="../<?= $avatar ?>" class="ac-user-avatar" alt="">
                <span class="ac-user-name"><?= htmlspecialchars($username) ?></span>
                </button>
            </h2>

            <div id="userCollapse" class="accordion-collapse collapse"
                aria-labelledby="userHeading" data-bs-parent="#sidebarAccordion">
                <div class="accordion-body p-0">
                    <ul class="nav flex-column ac-user-links">
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php?site=profile&userID=<?= $profileID ?>" target="_blank">
                                <?= $languageService->get('profile') ?>
                            </a>
                        </li>
                        <?php if ($hasMessengerTable): ?>
                            <a class="nav-link" href="../index.php?site=messenger" target="_blank">
                                <?= $languageService->get('messages') ?>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge bg-danger ms-1"><?= (int)$unreadCount ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php?site=edit_profile" target="_blank">
                                <?= $languageService->get('settings') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-danger" href="/admin/admincenter.php?site=logout">
                                <?= $languageService->get('logout') ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <hr>
            </div>
            <div class="sidebar-nav navbar-collapse">
                <div class="accordion-item">
                <h2 class="accordion-header" id="sidebarDashboard">
                    <?php
                        $isDashboard = !isset($_GET['site']) || $_GET['site'] === 'dashboard';
                    ?>
                    <a href="/admin/admincenter.php" id="sidebarDashboard" class="ac-sidebar-link <?= $isDashboard ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                        <span class="ac-sidebar-link-text">Dashboard</span>
                    </a>
                </h2>
                <!-- Accordion Sidebar Navigation -->
                    <?= dashnavi() ?>
                </div>
            </div>
    </div>
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        Admin Template powered by <a href="https://www.nexpell.de">Nexpell</a>
    </div>
</nav>

<!-- CONTENT -->
<div id="page-wrapper">
    <div class="container-fluid p-4">
<?php
// Impressum/Datenschutz
$impressumOk = false;
$datenschutzOk = false;

/* -----------------------------
 * Impressum (Disclaimer)
 * ----------------------------- */
$res = $_database->query("
    SELECT 1
    FROM settings_content_lang
    WHERE content_key = 'imprint'
    LIMIT 1
");
if ($res && $res->num_rows > 0) {
    $impressumOk = true;
}

/* -----------------------------
 * Datenschutz
 * ----------------------------- */
$res = $_database->query("
    SELECT 1
    FROM settings_content_lang
    WHERE content_key = 'privacy_policy'
    LIMIT 1
");
if ($res && $res->num_rows > 0) {
    $datenschutzOk = true;
}

/* -----------------------------
 * Ausgabe
 * ----------------------------- */
if (!$impressumOk || !$datenschutzOk): ?>
    <div class="alert alert-warning">
        <?= !$impressumOk ? "Impressum fehlt. " : "" ?>
        <?= !$datenschutzOk ? "Datenschutzerklärung fehlt." : "" ?>
    </div>
<?php endif; ?>
<?php
$subParamName = '';
$subParamVal  = '';
foreach (['action', 'tab', 'step'] as $p) {
    if (!empty($_GET[$p])) {
        $subParamName = $p;
        $subParamVal  = preg_replace('/[^a-z0-9_]/i', '', (string)$_GET[$p]);
        break;
    }
}

ob_start();

$local = __DIR__ . '/' . $site . '.php';
if (file_exists($local)) {
    include $local;
} else {
    // Plugins
    chdir("../");
    $plugin = $pluginManager->plugin_data($site, 0, true);
    if (!$plugin && str_starts_with((string)$site, 'admin_')) {
        // Compatibility fallback: map site=admin_x to admin_file=x when needed.
        $plugin = $pluginManager->plugin_data(substr((string)$site, 6), 0, true);
    }
    $path   = (string)($plugin['path'] ?? "");
    $baseAdminPath = rtrim($path, "/\\") . "/admin/";
    $pFile  = $baseAdminPath . $site . ".php";
    $pFileAlt = $baseAdminPath . "admin_" . $site . ".php";

    // Normalize plugin include path to absolute path when a relative plugin path is stored.
    if (!preg_match('~^([a-zA-Z]:[\\\\/]|/)~', $pFile)) {
        $pFile = dirname(__DIR__) . '/' . ltrim($pFile, "/\\");
    }
    if (!preg_match('~^([a-zA-Z]:[\\\\/]|/)~', $pFileAlt)) {
        $pFileAlt = dirname(__DIR__) . '/' . ltrim($pFileAlt, "/\\");
    }

    if (file_exists($pFile)) {
        include $pFile;
    } elseif (file_exists($pFileAlt)) {
        include $pFileAlt;
    } else {
        $errorPage = __DIR__ . '/error_page.php';
        if (file_exists($errorPage)) {
            include $errorPage;
        } else {
            echo "<div class='alert alert-danger'>Admin page not found: " . htmlspecialchars((string)$site, ENT_QUOTES) . "</div>";
        }
    }
}

$pageHtml = ob_get_clean();
$subTitle = null;

// dynamische Titelausgabe
if ($subParamName !== '' && $subParamVal !== '') {
    $patternLinks = '~<a\b([^>]*?)href="[^\"]*' . preg_quote($subParamName, '~') . '=' . preg_quote($subParamVal, '~') . '[^\"]*"([^>]*)>(.*?)</a>~is';
    if (preg_match_all($patternLinks, $pageHtml, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $attrs = trim(($match[1] ?? '') . ' ' . ($match[2] ?? ''));
            $text  = trim(strip_tags(html_entity_decode($match[3] ?? '', ENT_QUOTES, 'UTF-8')));

            if ($text === '') continue;
            if (preg_match('~\bclass\s*=\s*"[^\"]*\b(btn|text-danger|link-danger)\b[^\"]*"~i', $attrs)) continue;
            if (stripos($attrs, 'data-delete-url') !== false) continue;
            if (preg_match('~^\[[a-z0-9_]+\]$~i', $text)) continue;

            $subTitle = $text;
            break;
        }
    }

    if (empty($subTitle)) {
        $patternSmall = '~<div\b[^>]*class="[^\"]*card-title[^\"]*"[^>]*>.*?<small\b[^>]*>(.*?)</small>~is';
        if (preg_match($patternSmall, $pageHtml, $m2)) {
            $candidate = trim(strip_tags(html_entity_decode($m2[1], ENT_QUOTES, 'UTF-8')));
            if ($candidate !== '') {
                $subTitle = $candidate;
            }
        }
    }

    if (empty($subTitle)) {
        $patternSmall2 = '~<small\b[^>]*class="[^\"]*small-muted[^\"]*"[^>]*>(.*?)</small>~is';
        if (preg_match($patternSmall2, $pageHtml, $m3)) {
            $candidate = trim(strip_tags(html_entity_decode($m3[1], ENT_QUOTES, 'UTF-8')));
            if ($candidate !== '') {
                $subTitle = $candidate;
            }
        }
    }
}

if (empty($subTitle) && $subParamVal !== '') {
    $subTitle = ucwords(str_replace('_', ' ', $subParamVal));
}

$baseTitle = $GLOBALS['__pageTitle'] ?? ucwords(str_replace('_', ' ', $site));
$cat       = $GLOBALS['__pageCategory'] ?? null;
$GLOBALS['__pageCategoryUrl'] = 'admincenter.php?site=' . rawurlencode($qs['site'] ?? $current_site);
$h1Title = $baseTitle;

if (!empty($subTitle) && $subParamName !== 'action') {
    $h1Title = $subTitle;
}

// Fallback: falls baseTitle leer ist
if (empty($h1Title) && !empty($subTitle)) {
    $h1Title = $subTitle;
}
echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
        <h1 class="h3 mb-0">' . htmlspecialchars($h1Title) . '</h1>
            <div class="position-relative me-4" style="min-width:260px;max-width:380px; width: 100%;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="globalAdminSearch" type="search" class="form-control" autocomplete="off" placeholder="' . htmlspecialchars($languageService->get('search')) . '">
                <div id="globalAdminSearchResults" class="list-group shadow-sm" style="display:none; position:absolute; z-index:1050; left:0; right:0; top: calc(100% + 6px); max-height: 320px; overflow:auto;"></div>
            </div>
        </div>
    </div>';

// Breadcrumb: nur wenn Kategorie vorhanden (Dashboard bleibt ohne Breadcrumb)
if (!empty($cat)) {
    echo '<nav aria-label="breadcrumb" class="mb-2">';
    echo '  <ol class="breadcrumb mb-0 mt-0 small">';
    echo '    <li class="breadcrumb-item">' . htmlspecialchars($cat) . '</li>';

    if (!empty($subTitle) && mb_strtolower(trim($subTitle)) !== mb_strtolower(trim($baseTitle))) {
        echo '    <li class="breadcrumb-item"><a href="admincenter.php?site=' . urlencode($_GET['site'] ?? $site) . '">' . htmlspecialchars($baseTitle) . '</a></li>';

        echo '    <li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($subTitle) . '</li>';
    } else {
        echo '    <li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($baseTitle) . '</li>';
    }

    echo '  </ol>';
    echo '</nav>';
}

// Content der Seite
echo nx_render_alerts();
echo $pageHtml;
?>
        </div>
    </div>
</div>

<!-- zentrales Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <?= $languageService->get('delete_entry') ?>
                </h5>
                <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="modal"
                aria-label="Schließen"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-0">
                    <?= $languageService->get('confirm_delete') ?><br><br>
                    <small class="text-muted"><?= $languageService->get('confirm_undone') ?></small>
                </p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $languageService->get('cancel') ?></button>
                <a href="#" class="btn btn-danger" id="confirmDeleteBtn"><?= $languageService->get('delete') ?></a>
            </div>
        </div>
    </div>
</div>

<!-- Toast für Messages (ungelesene) -->
<?php if (!empty($hasMessengerTable) && (int)$unreadCount > 0): ?>
    <div class="toast-container position-fixed top-0 end-0 p-5" style="z-index: 1100;">
        <div id="unreadMessagesToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="10000">
            <div class="toast-header">
                <strong class="me-auto"><i class="bi bi-bell-fill"></i> <?= $languageService->get('toast_notification') ?></strong>
                <button type="button" class="btn-close me-1" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?php
                    $count = (int)$unreadCount;
                    $key = ($count === 1) ? 'toast_unread_messages_one' : 'toast_unread_messages_other';
                ?>
                <?= sprintf($languageService->get($key), $count) ?>
                <br>
                <a href="../index.php?site=messenger" target="_blank">
                    <?= $languageService->get('toast_goto_messenger') ?>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="../components/js/nx_editor.js"></script>
<script src="../components/js/nx-lang-editor.js"></script>
<?php if (!empty($_SESSION['userID'])): ?>
<script>
window.nexpellPresence = {
    enabled: true,
    endpoint: "/system/user_presence.php",
    heartbeatMs: 60000
};
</script>
<script src="/components/js/user_presence.js"></script>
<?php endif; ?>
<script src="/admin/js/jquery.min.js"></script>
<script src="/admin/js/bootstrap.bundle.min.js"></script>
<script src="/admin/js/page.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

</body>
</html>
