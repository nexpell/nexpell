<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * nexpell 1.0 - Modern Content & Community Management System
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @version       1.0
 * @build         Stable Release
 * @release       2025
 * @copyright     © 2025 nexpell | https://www.nexpell.de
 * 
 * @description   nexpell is a modern open source CMS designed for gaming
 *                communities, esports teams, and digital projects of any kind.
 * 
 * @author        The nexpell Team
 * 
 * @license       GNU General Public License (GPL)
 *                This software is distributed under the terms of the GPL.
 *                It is strictly prohibited to remove this copyright notice.
 *                For license details, see: https://www.gnu.org/licenses/gpl.html
 * 
 * @support       Support, updates, and plugins available at:
 *                → Website: https://www.nexpell.de
 *                → Forum:   https://www.nexpell.de/forum.html
 *                → Wiki:    https://www.nexpell.de/wiki.html
 * 
 * ─────────────────────────────────────────────────────────────────────────────
 */


// Session starten (nur einmal)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Absolute Pfade definieren (anpassen falls nötig)
define('BASE_PATH', __DIR__ . '/../');
define('SYSTEM_PATH', BASE_PATH . 'system/');

// Wichtige Systemdateien einbinden
include SYSTEM_PATH . 'config.inc.php';
include SYSTEM_PATH . 'settings.php';
include SYSTEM_PATH . 'functions.php';
include SYSTEM_PATH . 'multi_language.php';
include SYSTEM_PATH . 'classes/Template.php';
include SYSTEM_PATH . 'classes/TextFormatter.php';
#include SYSTEM_PATH . 'classes/PluginManager.php';

// Namespaces importieren
use nexpell\RoleManager;
use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\PluginManager;
global $pluginManager;

// Plugin-Manager laden und Sprachmodul für Admincenter initialisieren
$load = new \nexpell\PluginManager($_database);

global $languageService;
$languageService = new LanguageService($_database);
$languageService->readModule('admincenter', true);

// Sprache in Session setzen (Standard 'de')
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'de';
}

// Login-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ws_user'], $_POST['password'])) {
    $ws_user = trim($_POST['ws_user']);
    $password = $_POST['password'];

    // loginCheck() muss in einer der includes definiert sein
    $result = loginCheck($ws_user, $password);

    if ($result->state === "success") {
        $_SESSION['userID']   = $result->userID;
        $_SESSION['username'] = $result->username;
        $_SESSION['email']    = $result->email;

        // Redirect nur zu internen Pfaden erlauben (Vermeidung von Open Redirects)
        $redirect_url = $_SESSION['login_redirect'] ?? '/admin/admincenter.php';
        unset($_SESSION['login_redirect']);
        if (!preg_match('#^/admin/#', $redirect_url)) {
            $redirect_url = '/admin/admincenter.php';
        }

        header("Location: " . $redirect_url);
        exit;
    } else {
        // Fehlermeldung sicher ausgeben (Escaping)
        echo "<div class='alert alert-warning'>" . htmlspecialchars($result->message) . "</div>";
    }
}

// Fehlerhinweis, falls von admincheck.php weitergeleitet wurde
if (isset($_GET['error']) && $_GET['error'] === 'login_required') {
    echo "<div class='alert alert-warning'>Bitte melde dich zuerst an.</div>";
}

// Admin-Zugriffsprüfung: Nutzer muss eingeloggt und Rolle zugewiesen sein
if (!isset($_SESSION['userID']) || !checkUserRoleAssignment($_SESSION['userID'])) {
    ?>
    <div style="
        background-color: #e74c3c;
        color: white;
        padding: 20px;
        border-radius: 8px;
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 50px auto;
        text-align: center;
        box-shadow: 0 0 10px rgba(0,0,0,0.2);
    ">
        <img src="images/logo.png" alt="Logo" style="
            width: 400px;
            height: auto;
            margin-bottom: 20px;
            border-radius: 6px;
        ">
        <h2 style="margin-top: 0;">Zugriff verweigert</h2>
        <p>Sie haben derzeit <strong>keine Benutzerrolle</strong> zugewiesen und können daher nicht auf diesen Bereich zugreifen.</p>
        <p>Bitte wenden Sie sich an einen Administrator, um Ihre Zugriffsrechte zu prüfen.</p>
        <p style="margin-top: 20px;">Sie werden in <strong>10 Sekunden</strong> automatisch zur Login-Seite weitergeleitet...</p>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "login.php";
        }, 10000);
    </script>
    <?php
    exit;
}

// --- Check für Impressum & Datenschutz ---
$impressumOk = false;
$datenschutzOk = false;

// Impressum prüfen
$res1 = $_database->query("SELECT disclaimer FROM settings_imprint LIMIT 1");
if ($res1 && $row1 = $res1->fetch_assoc()) {
    if (!empty(trim($row1['disclaimer']))) {
        $impressumOk = true;
    }
}

// Datenschutz prüfen
$res2 = $_database->query("SELECT privacy_policy_text FROM settings_privacy_policy LIMIT 1");
if ($res2 && $row2 = $res2->fetch_assoc()) {
    if (!empty(trim($row2['privacy_policy_text']))) {
        $datenschutzOk = true;
    }
}


if (!$impressumOk || !$datenschutzOk): ?>
  <div class="alert alert-warning d-flex align-items-center" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
    <div>
      <strong>Hinweis:</strong>
      <?= !$impressumOk ? 'Das <b>Impressum</b> ist noch leer. ' : '' ?>
      <?= !$datenschutzOk ? 'Die <b>Datenschutzerklärung</b> ist noch leer. ' : '' ?>
      Bitte ergänze die fehlenden Inhalte unter 
      <?= !$impressumOk ? '<a href="admincenter.php?site=settings_imprint" class="alert-link">Impressum</a> ' : '' ?>
      <?= !$datenschutzOk ? '<a href="admincenter.php?site=settings_privacy_policy" class="alert-link">Datenschutz</a> ' : '' ?>
    </div>
  </div>
<?php endif;

// $_SERVER['REQUEST_URI'] absichern (normalerweise vorhanden)
if (!isset($_SERVER['REQUEST_URI'])) {
    $arr = explode('/', $_SERVER['PHP_SELF']);
    $_SERVER['REQUEST_URI'] = '/' . end($arr);
    if (!empty($_SERVER['argv'][0])) {
        $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['argv'][0];
    }
}

// Jetzt kannst du mit $userID, $languageService, etc. weiterarbeiten
$userID = $_SESSION['userID'];


#echo '<pre>';
#var_dump($_SESSION);
#echo '</pre>';

#var_dump($_SESSION['roleID']);

function dashnavi() {
    global $_database; // mysqli-Objekt

    $links = '';
    $current_query = $_GET['site'] ?? '';
    $lang = $_SESSION['language'] ?? 'de';

    // --- Rollen prüfen ---
    $roleIDs = $_SESSION['roles'] ?? [];
    if (empty($roleIDs)) {
        if (!empty($_SESSION['roleID'])) {
            $roleIDs = [ (int)$_SESSION['roleID'] ];
        } else {
            return '<li>Keine Rollen gefunden, Zugriff verweigert.</li>';
        }
    }

    // --- SQL-kompatible Liste bauen ---
    $roleList = implode(',', array_map('intval', $roleIDs));

    // --- Rechte einmalig laden ---
    $rights = [
        'category' => [],
        'link' => []
    ];
    $res = $_database->query("
        SELECT type, modulname 
        FROM user_role_admin_navi_rights 
        WHERE roleID IN ($roleList)
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rights[$r['type']][] = $r['modulname'];
        }
    }

    // --- Prüfen: Hat der User überhaupt min. 1 Kategorie & Link-Recht? ---
    $hasCategory = !empty($rights['category']);
    $hasLink = !empty($rights['link']);
    if (!$hasCategory || !$hasLink) {
        // Kein Zugriff auf irgendetwas → nichts anzeigen
        return '<li>
            <div class="alert alert-info mb-0 small d-flex align-items-start gap-2" role="alert" style="border-radius:0.5rem;">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div>
                    <strong>Keine zugriffsberechtigten Bereiche gefunden.</strong><br>
                    Dir sind aktuell keine Menüpunkte im Admincenter zugewiesen.<br>
                    <span class="text-muted">
                        Bitte wende dich an einen Administrator, um entsprechende Rollen oder Zugriffsrechte zu erhalten.
                    </span>
                </div>
            </div>
        </li>';
    }

    // --- Kategorien laden ---
    $categoriesResult = $_database->query("SELECT * FROM navigation_dashboard_categories ORDER BY sort");
    if (!$categoriesResult) {
        return '<li>Fehler beim Laden der Kategorien.</li>';
    }

    // --- Durch alle Kategorien ---
    while ($cat = $categoriesResult->fetch_assoc()) {
        $catID = (int)$cat['catID'];

        // Rechtecheck Kategorie
        if (!in_array($cat['modulname'], $rights['category'] ?? [], true)) {
            continue;
        }

        // Sprachen
        $translateCat = new multiLanguage($lang);
        $translateCat->detectLanguages($cat['name']);
        $catName = $translateCat->getTextByLanguage($cat['name']);
        $fa_name = $cat['fa_name'];

        // Links dieser Kategorie laden
        $linksResult = $_database->query("
            SELECT * FROM navigation_dashboard_links 
            WHERE catID = $catID 
            ORDER BY sort
        ");
        if (!$linksResult) {
            continue;
        }

        $cat_active = false;
        $cat_links_html = '';

        while ($link = $linksResult->fetch_assoc()) {
            // Rechtecheck Link
            if (!in_array($link['modulname'], $rights['link'] ?? [], true)) {
                continue;
            }

            // Sprachen
            $translateLink = new multiLanguage($lang);
            $translateLink->detectLanguages($link['name']);
            $linkName = $translateLink->getTextByLanguage($link['name']);

            // Aktive Seite bestimmen
            $url = $link['url'];
            $url_parts = parse_url($url);
            parse_str($url_parts['query'] ?? '', $url_query);
            $is_active = ($url_query['site'] ?? '') === $current_query;
            if ($is_active) {
                $cat_active = true;
            }

            // CSS-Klassen
            $active_class = $is_active ? 'active' : '';
            $icon_class = $is_active ? 'bi bi-arrow-right' : 'bi bi-plus-lg';

            // Link-HTML
            $cat_links_html .= '
                <li class="' . $active_class . '">
                    <a href="' . htmlspecialchars($url) . '">
                        <i class="' . $icon_class . ' ac-link"></i> ' . htmlspecialchars($linkName) . '
                    </a>
                </li>';
        }

        // Kategorie mit Links ausgeben (nur, wenn erlaubt und Links vorhanden)
        if (!empty($cat_links_html)) {
            $expand_class = $cat_active ? 'mm-active' : '';
            $aria_expanded = $cat_active ? 'true' : 'false';
            $show_class = $cat_active ? 'style="display:block;"' : '';

            $links .= '
                <li class="' . $expand_class . '">
                    <a class="has-arrow" aria-expanded="' . $aria_expanded . '" href="#">
                        <i class="' . htmlspecialchars($fa_name) . '" style="font-size: 1rem;"></i> ' . htmlspecialchars($catName) . '
                    </a>
                    <ul class="nav nav-third-level" ' . $show_class . '>
                        ' . $cat_links_html . '
                    </ul>
                </li>';
        }
    }

    // --- Falls keine Einträge sichtbar waren ---
    return $links ?: '<li>
            <div class="alert alert-info mb-0 small d-flex align-items-start gap-2" role="alert" style="border-radius:0.5rem;">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div>
                    <strong>Keine zugriffsberechtigten Bereiche gefunden.</strong><br>
                    Dir sind aktuell keine Menüpunkte im Admincenter zugewiesen.<br>
                    <span class="text-muted">
                        Bitte wende dich an einen Administrator, um entsprechende Rollen oder Zugriffsrechte zu erhalten.
                    </span>
                </div>
            </div>
        </li>';
}















if ($userID && !isset($_GET['userID']) && !isset($_POST['userID'])) {
	$ds = mysqli_fetch_array(safe_query("SELECT registerdate FROM `users` WHERE userID='" . $userID . "'"));
	$username = '<a class="nav-link nav-link-3" href="../index.php?site=profile&amp;id=' . $userID . '">' . getusername($userID) . '</a>';
	$lastlogin = !empty($ds['lastlogin']) ? date("d.m.Y H:i", strtotime($ds['lastlogin'])) : '-';
    $registerdate = date("d.m.Y H:i", strtotime($ds['registerdate']));
    
	$data_array = array();
	$data_array['$username'] = $username;
	$data_array['$lastlogin'] = $lastlogin;
	$data_array['$registerdate'] = $registerdate;
}

if ($getavatar = getavatar($userID)) {
	$l_avatar = $getavatar;
} else {
	$l_avatar = "noavatar.png";
}




header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($languageService->detectLanguage(), ENT_QUOTES, 'UTF-8') ?>">

<head>

	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="Website using nexpell CMS">
	<meta name="copyright" content="Copyright &copy; 2017-2025 by nexpell.de">
	<meta name="author" content="nexpell.de">

	<link rel="SHORTCUT ICON" href="/admin/images/favicon.ico">

	<title>nexpell - Bootstrap Admin Theme</title>

	<!-- Bootstrap Core CSS -->
	<link href="/admin/css/bootstrap.min.css" rel="stylesheet">
	<link href="/admin/css/bootstrap-switch.css" rel="stylesheet">

	<!-- side-bar CSS -->
	<link href="/admin/css/page.css" rel="stylesheet">
	<link href="/admin/css/metisMenu.css" rel="stylesheet" />

	<!-- Custom Fonts -->
	<link href="/admin/css/bootstrap-icons.min.css" rel="stylesheet">

	<!-- colorpicker -->
	<link href="/admin/css/bootstrap-colorpicker.min.css" rel="stylesheet">
    

</head>

<body>

	<div id="wrapper">
		<!-- Navigation -->

		<ul class="nav justify-content-between" style="width: 100%; margin-bottom: 25px; margin-top: 0px;background: #eaeaea;box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 6px -1px, rgba(0, 0, 0, 0.06) 0px 2px 4px -1px; !important;">
   
            <li class="nav-item" style="width: 80%;margin-left: 6px;">
                <a class="navbar-brand" href="/admin/admincenter.php">
        		            <img src="/admin/images/logo.png" style="width: 230px;margin-top: 7px; margin-bottom: 7px;" alt="setting">
        		        </a>
            </li>
            <li class="nav-item">
                <a class="nav-link nav-link-2"><?= $languageService->module['welcome'] ?> </a>
            </li>
            <li class="nav-item">
                <?php echo @$username ?>
            </li>
            <li class="nav-item dropdown" style="margin-right: 18px;">
                <a class="nav-link nav-link-3 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo $languageService->module['logout'] ?>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="../index.php"><i class="bi bi-arrow-clockwise text-success"></i> <?php echo $languageService->module['back_to_website'] ?></a></li>
                    <li><a class="dropdown-item" href="/admin/admincenter.php?site=logout"><i class="bi bi-x-lg text-danger"></i> <?php echo $languageService->module['logout'] ?></a></li>
                </ul>
            </li>
        </ul>



		<!-- /.navbar-top-links -->
		<!-- sidebar-links -->
		<nav class="navbar-default sidebar navbar-dark" role="navigation" style="margin-top: 5px;">
		    <div style="padding: 0 0 10px 0;" id="ws-image">
		        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
		            <span class="navbar-toggler-icon"></span>
		        </button>
                <?php
                $avatar_url = getavatar($userID);
                $username = getusername($userID);
                ?>
                <img id="avatar-big"
                     src="../<?php echo $avatar_url ?>"
                     class="rounded-circle profile_img"
                     style="height: 90px; margin-top: 9px; margin-bottom: 9px; -webkit-box-shadow: 2px 2px 15px 3px rgba(0,0,0,0.54); box-shadow: 2px 2px 15px 3px rgba(0,0,0,0.54); border: 3px solid #fe821d; border-radius: 25px; --bs-tooltip-bg: #fe821d;"
                     data-bs-toggle="tooltip"
                     data-bs-placement="right"
                     title="<?php echo $username ?>"
                     data-bs-html="true">
		        <div class="sidebar-nav col1lapse navbar-collapse" id="navbarNavDropdown">
                    <a class="link-head" href="admincenter.php">Dashboard</a>
		            <ul class="nav metismenu text-start navbar-nav" id="side-bar">
		                <?php echo dashnavi(); ?>
		            </ul>
		        </div>
		        <div class="copy">
                    <em>Admin Template powered by <a href="https://www.nexpell.de" target="_blank" rel="noopener">nexpell</a></em>
                </div>
		    </div>
		</nav>

		<!-- /.navbar-static-side -->

		<div id="page-wrapper">
			<?php
			if (isset($site) && $site != "news") {
    $invalide = array('\\', '/', '//', ':', '.');
    $site = str_replace($invalide, ' ', $site); // Entferne ungültige Zeichen

    if (file_exists($site . '.php')) {
        include($site . '.php');
    } else {
        chdir("../"); // <<< WICHTIG: Hier wechselst du ins Elternverzeichnis (aus admin/ raus)
        
        $plugin = $load->plugin_data($site, 0, true);
        $plugin_path = @$plugin['path'];  // z.B. "includes/plugins/news/"
        @$ifiles = $plugin['admin_file']; // z.B. "news.php"
        @$tfiles = explode(",", $ifiles);

        if (file_exists($plugin_path . "admin/" . $site . ".php")) {
            include($plugin_path . "admin/" . $site . ".php");
        } else {
            #echo '<div class="alert alert-danger" role="alert">' . $languageService->module['plugin_not_found'] . '</div>';
            include('info.php');
        }
    }
} else {
    include('info.php');
}
			?>
		</div><!-- /#wrapper -->
		
		<?php
		

		#$roleID = RoleManager::getUserRoleID($userID);

        #if ($roleID !== null && RoleManager::roleHasPermission($roleID, 'ckeditor_full')) {
            echo '<script src="../components/ckeditor/ckeditor.js"></script>';
            echo '<script src="../components/ckeditor/config.js"></script>';
        #} else {
        #    echo '<script src="../components/ckeditor/ckeditor.js"></script>';
        #    echo '<script src="../components/ckeditor/user_config.js"></script>';
        #}
		?>

		<!-- jQuery -->
		<script src="/admin/js/jquery.min.js"></script>

		<script src="/admin/js/page.js"></script>

		<!-- colorpicker -->
		<script src="/admin/js/bootstrap-colorpicker.min.js"></script>
		<script src="/admin/js/colorpicker-rm.js"></script>

		<!-- Bootstrap -->
		<script src="/admin/js/bootstrap.bundle.min.js"></script>
		<script src="/admin/js/bootstrap-switch.js"></script>

		<!-- Menu Plugin JavaScript -->
		<script src="/admin/js/metisMenu.min.js"></script>
		<script src="/admin/js/side-bar.js"></script>

		<script>
			var calledfrom = 'admin';
		</script>
		<!-- dataTables -->
		<!--<script type="text/javascript" src="/admin/js/jquery.dataTables.min.js"></script>
		<script type="text/javascript" src="/admin/js/dataTables.bootstrap5.min.js"></script>-->
		

		<script type="text/javascript">
            // setup tooltips trigger
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    html: true // erlaubt HTML-Inhalt
                })
            });
        </script>
        
</body>

</html>
