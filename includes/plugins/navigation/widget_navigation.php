<?php
if (session_status() === PHP_SESSION_NONE) session_start();

use nexpell\AccessControl;
use nexpell\PluginManager;
use nexpell\SeoUrlHandler;

global $_database, $theme_name, $languageService;

$tpl = new Template();

/* ----------------------------------------------
 * SETTINGS LADEN
 * ---------------------------------------------- */
$settings = [];

$res = $_database->query("
    SELECT setting_key, setting_value
    FROM navigation_website_settings
");

while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$theme_engine = (int)($settings["theme_engine_enabled"] ?? 1);

/* ----------------------------------------------
 * MULTILANG HELPER
 * ---------------------------------------------- */
function nav_lang(string $txt): string
{
    global $languageService;

    if (strpos($txt, '[[lang:') === false) return $txt;
    return $languageService->parseMultilang($txt);
}

/* ----------------------------------------------
 * MAIN NAVIGATION
 * ---------------------------------------------- */
$mainnav_html = "";
$main = $_database->query("SELECT * FROM navigation_website_main ORDER BY sort ASC");

while ($m = $main->fetch_assoc()) {

    $name = nav_lang($m["name"]);
    $icon = ""; // keine Icons mehr
    $url  = $m["url"];
    $mnavID = (int)$m["mnavID"];

    $subres = $_database->query("
        SELECT * FROM navigation_website_sub
        WHERE mnavID = {$mnavID}
        ORDER BY sort ASC
    ");

    /* --- DROPDOWN --- */
    if ($m["isdropdown"] == 1) {

        if ($subres->num_rows == 0) continue;

        $mainnav_html .= "
        <li class='nav-item dropdown'>
            <a class='nav-link d-flex align-items-center gap-1' href='#' data-bs-toggle='dropdown'>
                {$name}
                <i class='bi bi-chevron-down ms-1'></i>
            </a>
            <ul class='dropdown-menu'>
        ";

        while ($s = $subres->fetch_assoc()) {
            $sname = nav_lang($s["name"]);
            $mainnav_html .= "
                <li>
                    <a class='dropdown-item' href='" . SeoUrlHandler::convertToSeoUrl($s["url"]) . "'>
                        {$sname}
                    </a>
                </li>
            ";
        }

        $mainnav_html .= "</ul></li>";

    } else {
        /* --- EINZEL-LINK --- */
        $mainnav_html .= "
        <li class='nav-item'>
            <a class='nav-link' href='{$url}'>{$name}</a>
        </li>";
    }
}

/* ----------------------------------------------
 * USER NAV
 * ---------------------------------------------- */
function nav_user(): string
{
    if (empty($_SESSION['userID'])) {
        return "<li class='nav-item'><a class='nav-link' href='index.php?site=login'>Login</a></li>";
    }

    $uid = (int)$_SESSION['userID'];
    $canAdmin = !empty($_SESSION['roles']) && min($_SESSION['roles']) <= 2;

    return "
    <li class='nav-item dropdown'>
        <a class='nav-link d-flex align-items-center gap-1' href='#' data-bs-toggle='dropdown'>
            <img src='" . getavatar($uid) . "' class='navbar-avatar' style='width:22px;height:22px;border-radius:4px;'>
            " . htmlspecialchars(getusername($uid)) . "
            <i class='bi bi-chevron-down ms-1'></i>
        </a>

        <ul class='dropdown-menu dropdown-menu-end'>
            <li><a class='dropdown-item' href='" . SeoUrlHandler::convertToSeoUrl("index.php?site=profile&userID={$uid}") . "'>Profil</a></li>
            " . ($canAdmin ? "<li><a class='dropdown-item' href='/admin/admincenter.php' target='_blank'>Admincenter</a></li>" : "") . "
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item' href='index.php?site=logout'>Logout</a></li>
        </ul>
    </li>";
}


/* ----------------------------------------------
 * LANGUAGE SELECTOR
 * ---------------------------------------------- */
$languages = $languageService->getActiveLanguages();
$current_lang = $languageService->currentLanguage;
$current_flag = "";
$lang_html = "";

foreach ($languages as $l) {

    $flag = $l["flag"];
    $iso  = $l["iso_639_1"];
    $name = $l["name_native"];

    $active = ($iso === $current_lang);
    if ($active) $current_flag = $flag;

    $lang_html .= "
        <li>
            <a class='dropdown-item d-flex align-items-center " . ($active ? "active-language" : "") . "'
               href='?setlang={$iso}'>
               
                <img src='{$flag}' class='me-2' style='width:20px;height:20px;border-radius:4px;'>
                <span>{$name}</span>

                " . ($active ? "<i class='bi bi-check2 ms-auto text-success'></i>" : "") . "
            </a>
        </li>";
}

/* ----------------------------------------------
 * MESSENGER BADGE
 * ---------------------------------------------- */
function nav_messenger_badge(): string
{
    // Plugin aktiv & User eingeloggt?
    if (!PluginManager::isActive('messenger')) {
        return '';
    }

    if (empty($_SESSION['userID'])) {
        return '';
    }

    global $_database;

    // 🔒 Tabelle vorhanden?
    $check = $_database->query("
        SHOW TABLES LIKE 'plugins_messages'
    ");

    if (!$check || $check->num_rows === 0) {
        return '';
    }

    $uid = (int)$_SESSION['userID'];

    // 📬 Ungelesene Nachrichten zählen
    $row = mysqli_fetch_assoc(safe_query("
        SELECT COUNT(*) AS unread
        FROM plugins_messages
        WHERE receiver_id = {$uid}
          AND is_read = 0
    "));

    $unread = (int)($row['unread'] ?? 0);

    // Badge nur wenn > 0
    $badgeHtml = '';
    if ($unread > 0) {
        $badge = ($unread > 99) ? '99+' : $unread;
        $badgeHtml = "<span class='badge rounded-pill bg-danger'>{$badge}</span>";
    }

    $messengerUrl = SeoUrlHandler::convertToSeoUrl(
        'index.php?site=messenger'
    );

    // 🔔 ICON IMMER ANZEIGEN
    return "
    <li class='nav-item'>
        <a class='nav-link nav-icon-badge' href='$messengerUrl'>
            <span class='icon-wrapper'>
                <i class='bi bi-envelope fs-5'></i>
                {$badgeHtml}
            </span>
        </a>
    </li>";
}



/* ----------------------------------------------
 * FORUM BADGE
 * ---------------------------------------------- */
function nav_forum_badge(): string
{
    // Plugin aktiv & User eingeloggt?
    if (!PluginManager::isActive('forum')) {
        return '';
    }

    if (empty($_SESSION['userID'])) {
        return '';
    }

    global $_database;

    // 🔒 Tabelle prüfen
    $check = $_database->query("SHOW TABLES LIKE 'plugins_forum_read'");
    if (!$check || $check->num_rows === 0) {
        return '';
    }

    $uid = (int)$_SESSION['userID'];

    // 🆕 Neue Beiträge zählen
    $row = mysqli_fetch_assoc(safe_query("
        SELECT COUNT(*) AS new_posts
        FROM plugins_forum_posts p
        INNER JOIN plugins_forum_threads t
            ON t.threadID = p.threadID
           AND t.is_deleted = 0
        LEFT JOIN plugins_forum_read r
            ON r.userID = {$uid}
           AND r.threadID = p.threadID
        WHERE p.is_deleted = 0
          AND p.created_at > IFNULL(r.last_read_at, '1970-01-01')
    "));

    $count = (int)($row['new_posts'] ?? 0);

    // Badge nur wenn > 0
    $badgeHtml = '';
    if ($count > 0) {
        $badge = ($count > 99) ? '99+' : $count;
        $badgeHtml = "<span class='badge rounded-pill bg-danger'>{$badge}</span>";
    }

    $forumUrl = SeoUrlHandler::convertToSeoUrl(
        "index.php?site=forum"
    );

    // 💬 ICON IMMER ANZEIGEN
    return "
    <li class='nav-item'>
        <a class='nav-link nav-icon-badge' href='$forumUrl'>
            <span class='icon-wrapper'>
                <i class='bi bi-chat-dots fs-5'></i>
                {$badgeHtml}
            </span>
        </a>
    </li>";
}




/* ------------------------------------------------------------
 * DROPDOWN ANIMATION (nur wenn Engine aktiv = 1)
 * ------------------------------------------------------------ */
if ($theme_engine === 1) {
    $dropdown = $settings["dropdown_animation"] ?? "slidefade";

    $allowedAnimations = [
        'fade'      => 'fade',
        'slide'     => 'slide',
        'zoom'      => 'zoom',
        'slidefade' => 'slidefade'
    ];

    $animation = $allowedAnimations[$dropdown] ?? 'slidefade';
    $dropdown_animation = 'data-animation="' . $animation . '"';
    
} else {
    $dropdown_animation = ""; // deaktiviert
}

/* ==========================================================
   NAVIGATION STYLE (3 MODI)
========================================================== */

/* ==========================================================
   NAVIGATION STYLE (3 MODI)
========================================================== */

$navbar_class = "";
$navbar_shadow = "";
$theme_toggle = "";
$html_theme = "light";

$nav_height_value = $settings["nav_height"] ?? "80px";

/* GLOBAL: Logo IMMER begrenzen */
//$logo_style = 'style="max-height: calc('.$nav_height_value.' - 15px);"';

/* Mode 1: Höhe dynamisch */
$nav_height_style = 'style="--nav-height: '.$nav_height_value.';"';

$logo_style = '';
$dynamic_logo_attr = '';


/* ==========================================================
   MODE 0 → Custom CSS Modus
========================================================== */
if ($theme_engine === 0) {

    $navbar_class = "";
    $navbar_shadow = "";
    $nav_height_style = "";
    $theme_toggle = "";
    $dropdown_animation = "";
    $html_theme = "";

    $logo_style = 'style="max-height: calc('.$nav_height_value.' - 15px);"';
    $dynamic_logo_attr = '';

    $navbar_modus = $settings["navbar_modus"] ?? "auto";
    if ($navbar_modus === "auto") {
        $theme_toggle = "
            <li class='nav-item ms-2'>
                <button id='themeToggle' class='btn btn-sm btn-outline-secondary border-0'>
                    <i id='themeIcon' class='bi bi-moon-stars fs-5'></i>
                </button>
            </li>";
    }
}


/* ==========================================================
   MODE 1 → Theme Engine aktiv
========================================================== */
elseif ($theme_engine === 1) {

    $nav_height_value = $settings["nav_height"];
    $nav_height_style = 'style="--nav-height: '.$nav_height_value.';"';

    $navbar_shadow = $settings["navbar_shadow"] ?? "";
    $navbar_modus  = $settings["navbar_modus"] ?? "auto";

    $logo_style = 'style="max-height: calc('.$nav_height_value.' - 10px);"';
    $dynamic_logo_attr = '';

    $html_theme = ($navbar_modus === "auto") ? "light" : $navbar_modus;

    $navbar_class = match($navbar_modus) {
        "light" => "bg-light navbar-light",
        "dark"  => "bg-dark navbar-dark",
        default => "bg-body-tertiary navbar-light",
    };

    if ($navbar_modus === "auto") {
        $theme_toggle = "
            <li class='nav-item ms-2'>
                <button id='themeToggle' class='btn btn-sm btn-outline-secondary border-0'>
                    <i id='themeIcon' class='bi bi-moon-stars fs-5'></i>
                </button>
            </li>";
    }
}



/* ==========================================================
   MODE 2 → Theme Installer Modus
========================================================== */
/*elseif ($theme_engine === 2) {

    $navbar_shadow = $settings["navbar_shadow"] ?? "";
    $navbar_shadow = "";
    $theme = $settings['navbar_theme'] ?? 'light';
    $html_theme = 'data-bs-theme="' . htmlspecialchars($theme, ENT_QUOTES) . '"';

    $navbar_modus = $settings["navbar_modus"] ?? "auto";
    /*$navbar_class = match($navbar_modus) {
        "light" => "bg-light navbar-light",
        "dark"  => "bg-dark navbar-dark",
        default => "bg-body-tertiary navbar-light",
    };*/

/*    $nav_height_style = "";
    $dropdown_animation = "";

    $logo_style = '';                         // ✅ KEIN style
    $dynamic_logo_attr = 'data-dynamic-logo="1"'; // ✅ NUR HIER

    if ($navbar_modus === "auto") {
        $theme_toggle = "
            <li class='nav-item ms-2'>
                <button id='themeToggle' class='btn btn-sm btn-outline-secondary border-0'>
                    <i id='themeIcon' class='bi bi-moon-stars fs-5'></i>
                </button>
            </li>";
    }
}*/


elseif ($theme_engine === 2) {

    // Theme bestimmt ALLES
    #$navbar_class  = $settings["navbar_class"]  ?? "bg-light navbar-light";
    $navbar_shadow = $settings["navbar_shadow"] ?? "";
    $theme = $settings['navbar_theme'] ?? 'light';
    $html_theme = 'data-bs-theme="' . htmlspecialchars($theme, ENT_QUOTES) . '"';

    // KEIN nav_height_style → Theme bestimmt Höhe
    $nav_height_style = "";
    $dropdown_animation = "";

    // NEU: JS berechnet die Höhe dynamisch
    $logo_style = 'style="max-height: calc('.$nav_height_value.' - 10px);"';                        // ✅ KEIN style
    $dynamic_logo_attr = 'data-dynamic-logo="1"'; // ✅ NUR HIER bg-primary

    $navbar_modus = $settings["navbar_modus"] ?? "auto";
    $navbar_class = match($navbar_modus) {
        "light" => "navbar-light",
        "dark"  => "navbar-dark",
        default => "bg-body-tertiary navbar-light",
    };

    $dropdown_animation = "";
}

/* ==========================================================
   TEMPLATE DATEN ÜBERGEBEN
========================================================== */
$data_array = [

    "html_theme"         => $html_theme,
    "navbar_class"       => $navbar_class,
    "navbar_shadow"      => $navbar_shadow,
    "navbar_theme"       => $html_theme,
    "nav_height_style"   => $nav_height_style,
    "logo_height"        => $logo_style,
    "dynamic_logo_attr" => $dynamic_logo_attr,
    "dropdown_animation" => $dropdown_animation,

    // Logo Position
    "logo_center"       => ($settings["logo_center"] == "1") ? "logo-center" : "",
    "left_side_pos"     => ($settings["logo_center"] == "1") ? "left-fixed" : "right-of-logo",

    // Logos
    "logo_light"        => "/includes/plugins/navigation/images/{$settings["logo_light"]}",
    "logo_dark"         => "/includes/plugins/navigation/images/{$settings["logo_dark"]}",

    // Navigation Inhalt
    "mainnav"           => $mainnav_html,
    "usernav"           => nav_user(),
    "messenger_badge"   => nav_messenger_badge(),
    "forum_badge"       => nav_forum_badge(),

    // Languages
    "current_flag"      => $current_flag,
    "language_list"     => $lang_html,

    "theme_toggle"      => $theme_toggle,
];

/* ==========================================================
   TEMPLATE LADEN
========================================================== */
echo $tpl->loadTemplate("navigation", "main", $data_array, "plugin");
