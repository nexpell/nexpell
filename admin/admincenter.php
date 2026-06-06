<?php
/******************************************************************************
 * nexpell Admincenter – kompakte, stabile und sichere Version
 ******************************************************************************/

if (session_status() === PHP_SESSION_NONE) session_start();

define('BASE_PATH', __DIR__ . '/../');
define('SYSTEM_PATH', BASE_PATH . 'system/');

// Core-Dateien
require SYSTEM_PATH . 'config.inc.php';
require SYSTEM_PATH . 'settings.php';
require SYSTEM_PATH . 'functions.php';
require SYSTEM_PATH . 'multi_language.php';
require SYSTEM_PATH . 'classes/Template.php';
require SYSTEM_PATH . 'classes/TextFormatter.php';
#require SYSTEM_PATH . 'classes/PluginManager.php';

use nexpell\PluginManager;
use nexpell\LanguageService;

// Sprachsystem
$pluginManager   = new PluginManager($_database);
$languageService = new LanguageService($_database);
$languageService->readModule('admincenter', true);

if (!isset($_SESSION['language'])) $_SESSION['language'] = 'de';

// Login Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ws_user'], $_POST['password'])) {
    $result = loginCheck(trim($_POST['ws_user']), $_POST['password']);
    if ($result->state === "success") {
        $_SESSION['userID']   = $result->userID;
        $_SESSION['username'] = $result->username;
        $_SESSION['email']    = $result->email;

        $url = $_SESSION['login_redirect'] ?? '/admin/admincenter.php';
        unset($_SESSION['login_redirect']);
        if (!preg_match('#^/admin/#', $url)) $url = "/admin/admincenter.php";

        header("Location: $url"); exit;
    }
    echo "<div class='alert alert-warning'>{$result->message}</div>";
}

// Redirect-Fehler
if (isset($_GET['error']) && $_GET['error'] === 'login_required') {
    echo "<div class='alert alert-warning'>Bitte melde dich zuerst an.</div>";
}

// Rollenprüfung
if (empty($_SESSION['userID']) || !checkUserRoleAssignment($_SESSION['userID'])) {
    ?>
    <div style="background:#e74c3c;color:white;padding:20px;text-align:center;margin:50px auto;
         max-width:600px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,.2);">
        <img src="images/logo.png" style="width:300px;margin-bottom:20px;">
        <h2>Zugriff verweigert</h2>
        <p>Keine gültige Benutzerrolle vorhanden.</p>
        <p>Weiterleitung in 5 Sekunden…</p>
    </div>
    <script>setTimeout(()=>location.href="login.php",5000);</script>
    <?php exit;
}

// Hilfswerte
$userID = $_SESSION['userID'];
$lang   = $_SESSION['language'];

// Navigation active Fix
$current_site = $_GET['site'] ?? '';

function dashnavi() {
    global $_database, $current_site, $lang;

    $roleIDs = $_SESSION['roles'] ?? [];
    if (empty($roleIDs) && !empty($_SESSION['roleID'])) {
        $roleIDs = [(int)$_SESSION['roleID']];
    }
    if (empty($roleIDs)) return '<li>Keine Rollen gefunden, Zugriff verweigert.</li>';

    $roleList = implode(',', array_map('intval', $roleIDs));

    // Rechte der Rolle
    $rights = ['category'=>[],'link'=>[]];
    $res = $_database->query("SELECT type,modulname FROM user_role_admin_navi_rights 
                              WHERE roleID IN ($roleList)");
    while ($r = $res->fetch_assoc()) $rights[$r['type']][] = $r['modulname'];

    $out = "";

    // Kategorien
    $cats = $_database->query("SELECT * FROM navigation_dashboard_categories ORDER BY sort");
    while ($cat = $cats->fetch_assoc()) {
        if (!in_array($cat['modulname'], $rights['category'])) continue;

        $ml  = new multiLanguage($lang);
        $ml->detectLanguages($cat['name']);
        $catName = $ml->getTextByLanguage($cat['name']);

        $links = "";
        $activeCat = false;

        // Links pro Kategorie
        $lq = $_database->query("SELECT * FROM navigation_dashboard_links 
                                 WHERE catID={$cat['catID']} ORDER BY sort");
        while ($link = $lq->fetch_assoc()) {
            if (!in_array($link['modulname'], $rights['link'])) continue;

            $ml2 = new multiLanguage($lang);
            $ml2->detectLanguages($link['name']);
            $name = $ml2->getTextByLanguage($link['name']);

            // URL auswerten
            $urlParts = parse_url($link['url']);
            parse_str($urlParts['query'] ?? '', $qs);

            $isActive = isset($qs['site']) && $qs['site'] === $current_site;
            if ($isActive) $activeCat = true;

            $links .= "
                <li class='".($isActive?"active":"")."'>
                    <a href='".htmlspecialchars($link['url'])."'>
                        <i class='bi ".($isActive?"bi-arrow-right":"bi-plus-lg")."'></i> $name
                    </a>
                </li>";
        }

        if ($links) {
            $out .= "
            <li class='".($activeCat?"mm-active":"")."'>
                <a class='has-arrow' href='#'>
                    <i class='{$cat['fa_name']}'></i> $catName
                </a>
                <ul class='nav nav-third-level' ".($activeCat?"style='display:block'":"").">
                    $links
                </ul>
            </li>";
        }
    }

    return $out ?: "<li><div class='alert alert-info mb-0 small d-flex align-items-start gap-2' role='alert' style='border-radius:0.5rem;'>
                <i class='bi bi-info-circle-fill fs-5'></i>
                <div>
                    <strong>Keine zugriffsberechtigten Bereiche gefunden.</strong><br>
                    Dir sind aktuell keine Menüpunkte im Admincenter zugewiesen.<br>
                    <span class='text-muted'>
                        Bitte wende dich an einen Administrator, um entsprechende Rollen oder Zugriffsrechte zu erhalten.
                    </span>
                </div>
            </div></li>";
}



?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($languageService->detectLanguage()) ?>">

<head>
    <meta charset="utf-8">
    <title>Nexpell Admincenter</title>
    <link rel="SHORTCUT ICON" href="/admin/images/favicon.ico">
    <link href="/admin/css/bootstrap.min.css" rel="stylesheet">
    <link href="/admin/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/admin/css/page.css" rel="stylesheet">
    <link href="/admin/css/metisMenu.css" rel="stylesheet">
    <link href="/admin/css/bootstrap-switch.css" rel="stylesheet">
    <link href="/admin/css/bootstrap-colorpicker.min.css" rel="stylesheet">
</head>

<body>

<div id="wrapper">

<!-- TOPBAR -->
<ul class="nav justify-content-between" style="width:100%;background:#eaeaea;margin-bottom:25px;">
    <li class="nav-item" style="width:80%;margin-left:6px;">
        <a class="navbar-brand" href="/admin/admincenter.php">
            <img src="/admin/images/logo.png" style="width:230px;margin:7px 0;">
        </a>
    </li>
    <li class="nav-item"><a class="nav-link"><?= $languageService->module['welcome'] ?></a></li>
    <li class="nav-item"><?= getusername($userID) ?></li>

    <li class="nav-item dropdown" style="margin-right:18px;">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><?= $languageService->module['logout'] ?></a>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="../index.php"><i class="bi bi-arrow-left text-success"></i> Zur Website</a></li>
            <li><a class="dropdown-item" href="/admin/admincenter.php?site=logout"><i class="bi bi-x-lg text-danger"></i> Logout</a></li>
        </ul>
    </li>
</ul>

<!-- SIDEBAR -->
<nav class="navbar-default sidebar navbar-dark" style="margin-top:5px;">
    <div style="padding-bottom:10px;" id="ws-image">

        <?php $avatar = getavatar($userID); ?>
        <img src="../<?= $avatar ?>" class="rounded-circle profile_img"
             style="height:90px;margin:9px auto;display:block;box-shadow:2px 2px 15px rgba(0,0,0,.5);border:3px solid #fe821d;border-radius:25px;">

        <div class="sidebar-nav navbar-collapse">
            <a class="link-head" href="admincenter.php">Dashboard</a>
            <ul class="nav metismenu" id="side-bar">
                <?= dashnavi() ?>
            </ul>
        </div>

        <div class="copy"><em>Admin Template powered by <a href="https://www.nexpell.de">nexpell</a></em></div>
    </div>
</nav>

<!-- CONTENT -->
<div id="page-wrapper">


<?php
// Impressum/Datenschutz
$impressumOk = (bool) ($_database->query("SELECT disclaimer FROM settings_imprint LIMIT 1")->fetch_assoc()['disclaimer'] ?? "");
$datenschutzOk = (bool) ($_database->query("SELECT privacy_policy_text FROM settings_privacy_policy LIMIT 1")->fetch_assoc()['privacy_policy_text'] ?? "");
if (!$impressumOk || !$datenschutzOk): ?>
<div class="alert alert-warning">
    <?= !$impressumOk ? "Impressum fehlt. " : "" ?>
    <?= !$datenschutzOk ? "Datenschutzerklärung fehlt." : "" ?>
</div>
<?php endif; 
// Router (Original-Verhalten)

$site = $_GET['site'] ?? "info";
$site = preg_replace('/[^a-z0-9_]/i', '', $site); // action wird NICHT zerstört

$local = __DIR__ . '/' . $site . '.php';
if (file_exists($local)) {
    include $local;
} else {
    // Plugins
    chdir("../");
    $plugin = $pluginManager->plugin_data($site, 0, true);
    $path   = $plugin['path'] ?? "";
    $pFile  = $path . "admin/" . $site . ".php";

    include file_exists($pFile) ? $pFile : "admin/info.php";
}



?>

</div>
</div>
<script src="../components/ckeditor/ckeditor.js"></script>
<script src="../components/ckeditor/config.js"></script>
<script src="/admin/js/jquery.min.js"></script>
<script src="/admin/js/bootstrap.bundle.min.js"></script>
<script src="/admin/js/metisMenu.min.js"></script>
<script src="/admin/js/page.js"></script>
<script src="/admin/js/bootstrap-colorpicker.min.js"></script>
<script src="/admin/js/bootstrap-switch.js"></script>
<script src="/admin/js/side-bar.js"></script>

<script>
const tooltipTriggerList=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
tooltipTriggerList.map(el=>new bootstrap.Tooltip(el,{html:true}));
</script>

</body>
</html>
