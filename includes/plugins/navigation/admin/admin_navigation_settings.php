<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;

// Sprache setzen
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService
global $languageService, $_database, $theme_name;
$languageService = new LanguageService($_database);

// Adminzugriff prüfen
AccessControl::checkAdminAccess('navigation');

/* ============================================================
   DEFAULT SETTINGS
============================================================ */
$defaults = [
    "theme_engine_enabled" => "1",
    "logo_light"           => "logo_light.png",
    "logo_dark"            => "logo_dark.png",
    "logo_center"          => "0",
    "navbar_modus"         => "auto",
    "navbar_shadow"        => "shadow-sm",
    "dropdown_animation"   => "fade",
    "nav_height"           => "80px"
];

/* ============================================================
   SETTINGS AUS DB LADEN
============================================================ */
$settings = $defaults;

$res = $_database->query("SELECT setting_key, setting_value FROM navigation_website_settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row["setting_key"]] = $row["setting_value"];
}

/* ============================================================
   SAVE LOGIC
============================================================ */
if (isset($_POST["save"])) {

    $uploadPath = __DIR__ . "/../images/";

    // LIGHT Logo Upload
    if (!empty($_FILES["logo_light"]["name"])) {

        $ext      = strtolower(pathinfo($_FILES["logo_light"]["name"], PATHINFO_EXTENSION));
        $fileName = "logo_light." . $ext;
        $target   = $uploadPath . $fileName;

        if (move_uploaded_file($_FILES["logo_light"]["tmp_name"], $target)) {

            $settings["logo_light"] = $fileName;

            $_database->query("
                UPDATE navigation_website_settings
                SET setting_value = '{$fileName}'
                WHERE setting_key = 'logo_light'
            ");
        }
    }

    // DARK Logo Upload
    if (!empty($_FILES["logo_dark"]["name"])) {

        $ext      = strtolower(pathinfo($_FILES["logo_dark"]["name"], PATHINFO_EXTENSION));
        $fileName = "logo_dark." . $ext;
        $target   = $uploadPath . $fileName;

        if (move_uploaded_file($_FILES["logo_dark"]["tmp_name"], $target)) {

            $settings["logo_dark"] = $fileName;

            $_database->query("
                UPDATE navigation_website_settings
                SET setting_value = '{$fileName}'
                WHERE setting_key = 'logo_dark'
            ");
        }
    }

    // POST überschreiben
    $_POST["logo_light"] = $settings["logo_light"];
    $_POST["logo_dark"]  = $settings["logo_dark"];

    // Alle anderen Settings speichern
    foreach ($defaults as $key => $defaultValue) {

        if ($key === "theme_engine_enabled") {
            $value = $_POST["theme_engine_enabled"] ?? "1";

        } elseif ($key === "logo_center") {
            $value = isset($_POST["logo_center"]) ? "1" : "0";

        } else {
            $value = $_POST[$key] ?? $defaultValue;
        }

        $stmt = $_database->prepare("
            INSERT INTO navigation_website_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }

    $_SESSION["nav_saved"] = true;

    // Reload mit Cache-Buster
    $sep = strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?';
    header("Location: " . $_SERVER['REQUEST_URI'] . $sep . "cb=" . time());
    exit;
}

$cacheBuster = time();

?>



<?php if (!empty($_SESSION["nav_saved"])): ?>
<div class="alert alert-success">Einstellungen gespeichert!</div>
<?php unset($_SESSION["nav_saved"]); endif; ?>

<form action="" method="post" enctype="multipart/form-data">

<div class="card mb-4">
<div class="card-header">
            <i class="bi bi-journal-text"></i> Navigation Theme Engine Modus
        </div>

<div class="card-body">

<select name="theme_engine_enabled" id="theme_engine_enabled" class="form-select mb-4">
    <option value="1" <?= $settings['theme_engine_enabled']=="1"?"selected":"" ?>>Theme Engine aktiv (Live)</option>
    <option value="0" <?= $settings['theme_engine_enabled']=="0"?"selected":"" ?>>Custom-CSS Modus (Manuell)</option>
    <option value="2" <?= $settings['theme_engine_enabled']=="2"?"selected":"" ?>>Theme-Installer Modus (Theme)</option>
</select>

<div id="themeEngineStatus" class="mt-2"></div>

<!-- ============================
     INFO-BOXEN (Theme-Engine)
============================= -->

<?php
$themePath = '/includes/themes/' . $theme_name . '/css/';
$stylesheetFile = $themePath . 'stylesheet.css';
?>
<?php
// Theme-Name (kommt bei dir ja schon aus dem System)
$themeName = $theme_name ?? 'default';

// Interner Dateisystem-Pfad (NICHT anzeigen!)
$fsPath = BASE_PATH . '/includes/themes/' . $themeName . '/css/';

// Web-Pfad (für Anzeige)
$webPath = '/includes/themes/' . $themeName . '/css/';

$cssFiles = [];

if (is_dir($fsPath)) {
    foreach (glob($fsPath . '*.css') as $file) {
        $cssFiles[] = basename($file);
    }
}
?>

<!-- ============================
     INFO-BOXEN (Theme-Engine)
============================= -->

<!-- MODE 1: Theme Engine aktiv -->
<div id="mode_info_live"
     class="alert alert-info border-0 shadow-sm p-3"
     style="display: <?= ($settings['theme_engine_enabled'] ?? "0") == "1" ? "block" : "none" ?>;">
    
    <h5><i class="bi bi-palette me-2"></i>Theme Engine aktiv</h5>
    <p>Die Theme Engine ist eingeschaltet. Alle Design-Optionen sind verfügbar und werden live übernommen.</p>

    <!-- Zusatzinfo CSS -->
    <div class="mt-2 text-dark opacity-75">
        <i class="bi bi-filetype-css me-1"></i>
        Geladene CSS-Dateien aus:<br>
        <?php if (!empty($cssFiles)): ?>
            <ul class="mt-2 mb-0">
                <?php foreach ($cssFiles as $css): ?>
                    <li>
                        <code><?= htmlspecialchars($webPath . $css) ?></code>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="text-danger mt-2">
                <i class="bi bi-exclamation-triangle"></i>
                Keine CSS-Dateien gefunden
            </div>
        <?php endif; ?>
    </div>
</div>


<!-- MODE 0: Custom-CSS -->
<div id="mode_info_css"
     class="alert alert-warning border-0 shadow-sm p-3"
     style="display: <?= ($settings['theme_engine_enabled'] ?? "0") == "0" ? "block" : "none" ?>;">
    
    <h5><i class="bi bi-code-slash me-2"></i>Custom-CSS Modus</h5>
    <p>Alle Navigationseinstellungen sind deaktiviert. Das Layout wird vollständig über eigene CSS-Dateien gesteuert.</p>

    <!-- Zusatzinfo CSS -->    
    <div class="mt-2 text-dark opacity-75">
        <i class="bi bi-filetype-css me-1"></i>
        Geladene CSS-Dateien aus:<br>
        <?php if (!empty($cssFiles)): ?>
            <ul class="mt-2 mb-0">
                <?php foreach ($cssFiles as $css): ?>
                    <li>
                        <code><?= htmlspecialchars($webPath . $css) ?></code>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="text-danger mt-2">
                <i class="bi bi-exclamation-triangle"></i>
                Keine CSS-Dateien gefunden
            </div>
        <?php endif; ?>
    </div>
</div>


<!-- MODE 2: Theme-Installer -->
<div id="mode_info_theme"
     class="alert alert-secondary border-0 shadow-sm p-3"
     style="display: <?= ($settings['theme_engine_enabled'] ?? "0") == "2" ? "block" : "none" ?>;">
    
    <h5><i class="bi bi-brush me-2"></i>Theme-Installer Modus</h5>
    <p>Das aktive Theme steuert das gesamte Navigationsdesign. Lokale Navigationseinstellungen werden ignoriert.</p>

    <!-- Zusatzinfo CSS -->
    <div class="mt-2 text-dark opacity-75">
        <i class="bi bi-filetype-css me-1"></i>
        Es wird ausschließlich folgende Datei geladen:<br>
        <code><?= $stylesheetFile ?></code>
    </div>
</div>




<!-- ===================================================== -->
<!-- IMMER SICHTBAR: LOGOS -->
<!-- ===================================================== -->

<h4>Logos</h4>

<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Logo (Light)</label>
        <div class="mb-2 p-2 rounded" style="background:#fff;">
            <img src="../includes/plugins/navigation/images/<?= $settings['logo_light'] ?>?v=<?= $cacheBuster ?>"
                 class="border p-1 rounded" style="max-height:70px;">
        </div>
        <input type="file" class="form-control" name="logo_light" accept="image/*">
    </div>

    <div class="col-md-6">
        <label class="form-label">Logo (Dark)</label>
        <div class="mb-2 p-2 rounded" style="background:#222;">
            <img src="../includes/plugins/navigation/images/<?= $settings['logo_dark'] ?>?v=<?= $cacheBuster ?>"
                 class="border p-1 rounded" style="max-height:70px;">
        </div>
        <input type="file" class="form-control" name="logo_dark" accept="image/*">
    </div>
</div>

<hr>


<!-- ===================================================== -->
<!-- KONFIGURATIONSBEREICH (nur aktiv in Mode 1) -->
<!-- ===================================================== -->



    <!-- ===================================================== -->
<!-- THEME ENGINE ROOT (PFLICHT!) -->
<!-- ===================================================== -->
<div id="theme_engine_root"
     class="mode-<?= htmlspecialchars($settings['theme_engine_enabled'] ?? '1') ?>">

    <!-- ========================= -->
    <!-- LIVE VORSCHAU -->
    <!-- ========================= -->
    <div class="block-live-preview">
        <h4 class="mb-3">Live Vorschau</h4>
        <div class="border rounded p-3 mb-3">
            <nav id="navPreview"
                 class="navbar <?= $settings['navbar_shadow'] ?> px-3"
                 style="height: <?= $settings['nav_height'] ?>;">

                <a class="navbar-brand me-auto">
                    <img id="previewLogo"
                         src="../includes/plugins/navigation/images/<?= $settings['logo_light'] ?>"
                         data-light="../includes/plugins/navigation/images/<?= $settings['logo_light'] ?>"
                         data-dark="../includes/plugins/navigation/images/<?= $settings['logo_dark'] ?>"
                         style="height: calc(<?= $settings['nav_height'] ?> - 30px);">
                </a>

                <ul class="navbar-nav d-flex flex-row gap-3">
                    <li class="nav-item">Home</li>
                    <li class="nav-item">Community</li>
                    <li class="nav-item">Media</li>
                </ul>
            </nav>
        </div>

        <button id="previewDarkToggle"
                class="btn btn-outline-secondary btn-sm mb-4">
            <i class="bi bi-moon-stars"></i> Darkmode Vorschau
        </button>

        <hr>
    </div>

    <!-- ========================= -->
    <!-- THEME MODUS -->
    <!-- ========================= -->
    <div class="block-theme-modus">

        <h4>Theme Modus</h4>
        <select name="navbar_modus" class="form-select mb-4 cfg">
            <option value="auto"  <?= $settings['navbar_modus']=="auto"?"selected":"" ?>>Auto</option>
            <option value="light" <?= $settings['navbar_modus']=="light"?"selected":"" ?>>Hell</option>
            <option value="dark"  <?= $settings['navbar_modus']=="dark"?"selected":"" ?>>Dunkel</option>
        </select>

        <hr>
    </div>

    <!-- ========================= -->
    <!-- RESTLICHE KONFIGURATION -->
    <!-- ========================= -->
    <div class="block-config-rest">

        <h4>Navbar Höhe</h4>
        <input type="range" min="50" max="120" step="1"
               id="nav_height_slider"
               class="form-range cfg"
               value="<?= rtrim($settings['nav_height'], 'px') ?>">

        <input type="hidden"
               id="nav_height"
               name="nav_height"
               value="<?= $settings['nav_height'] ?>">

        <small class="text-muted">
            Aktuelle Höhe:
            <span id="navHeightLabel"><?= $settings['nav_height'] ?></span>
        </small>

        <hr>

        <h4>Navbar Shadow</h4>
        <select name="navbar_shadow"
                id="navbar_shadow"
                class="form-select mb-4 cfg">
            <option value="">Ohne</option>
            <option value="shadow-sm">Shadow small</option>
            <option value="shadow">Shadow normal</option>
            <option value="shadow-lg">Shadow large</option>
        </select>

        <hr>

        <h4>Dropdown Animation</h4>
        <select name="dropdown_animation"
                class="form-select mb-4 cfg">
            <option value="fade">Fade</option>
            <option value="slide">Slide</option>
            <option value="slidefade">Slide + Fade</option>
            <option value="zoom">Zoom</option>
        </select>

    </div>
</div>






<!-- ===================================================== -->
<!-- IMMER SICHTBAR: SPEICHERN -->
<!-- ===================================================== -->

<button type="submit" name="save" class="btn btn-primary w-100 mt-3">
    Speichern
</button>

</div>
</div>

</form>

<style>
/* DEFAULT */
.block-live-preview,
.block-theme-modus,
.block-config-rest {
    display: none;
}

/* MODE 1 */
.mode-1 .block-live-preview,
.mode-1 .block-theme-modus,
.mode-1 .block-config-rest {
    display: block;
}

/* MODE 2 */
.mode-2 .block-live-preview {
    display: block;
}

/* MODE 0 */
.mode-0 .block-live-preview,
.mode-0 .block-theme-modus,
.mode-0 .block-config-rest {
    display: none;
}


</style>

<!-- ============================================================
   JAVASCRIPT
============================================================ -->

<script>
document.addEventListener("DOMContentLoaded", () => {

    /* ============================================
       ELEMENTE
    ============================================ */
    const modeSelect = document.getElementById("theme_engine_enabled");
    const configArea = document.getElementById("config_area");
    const cfgInputs  = document.querySelectorAll(".cfg");
    const status     = document.getElementById("themeEngineStatus");

    const preview = document.getElementById("navPreview");
    const logo    = document.getElementById("previewLogo");

    const logoLightInput = document.getElementById("logo_light");
    const logoDarkInput  = document.getElementById("logo_dark");
    const slider         = document.getElementById("nav_height_slider");
    const dropdownSelect = document.querySelector("select[name='dropdown_animation']");
    const shadowSelect   = document.getElementById("navbar_shadow");
    const darkToggle     = document.getElementById("previewDarkToggle");


    /* ============================================
       FUNKTION: UI Modus anwenden
    ============================================ */
function applyMode() {

    const mode = modeSelect?.value ?? "1";

    const root = document.getElementById("theme_engine_root");
    if (!root || !configArea) return;

    // 🔥 DAS IST DER ENTSCHEIDENDE TEIL
    root.classList.remove("mode-0", "mode-1", "mode-2");
    root.classList.add("mode-" + mode);

    const infoLive  = document.getElementById("mode_info_live");
    const infoCss   = document.getElementById("mode_info_css");
    const infoTheme = document.getElementById("mode_info_theme");

    /* RESET INFO */
    infoLive.style.display  = "none";
    infoCss.style.display   = "none";
    infoTheme.style.display = "none";

    /* RESET CONFIG */
    configArea.style.opacity = "0.45";
    configArea.style.pointerEvents = "none";
    cfgInputs.forEach(i => i.disabled = true);

    /* MODE 1 – alles an */
    if (mode === "1") {
        configArea.style.opacity = "1";
        configArea.style.pointerEvents = "auto";
        cfgInputs.forEach(i => i.disabled = false);

        infoLive.style.display  = "block";
        infoTheme.style.display = "block";
    }

    /* MODE 2 – Theme + Live */
    else if (mode === "2") {
        infoLive.style.display  = "block";
    }

    /* MODE 0 – alles aus */
    else if (mode === "0") {
        // absichtlich leer
    }
}





    /* ============================================
       FUNKTION: SPEICHERUNG via AJAX
    ============================================ */
function saveModeToServer(value) {

    status.innerHTML = "<div class='text-info'>Speichere…</div>";

    fetch("/includes/plugins/navigation/admin/save_theme_engine.php?ajax=1", {
        method: "POST",
        headers: { 
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "value=" + encodeURIComponent(value)
    })
    .then(r => r.json())
    .then(data => {

        console.log("Antwort vom Server:", data);

        if (data.success) {
            status.innerHTML = "<div class='text-success'>Gespeichert ✔</div>";

            setTimeout(() => status.innerHTML = "", 1200);

            // Seite neu laden
            setTimeout(() => {
                location.href = location.href.split("?")[0] + "?site=admin_navigation_settings&cb=" + Date.now();
            }, 400);

        } else {
            status.innerHTML = "<div class='text-danger'>Fehler: " + data.msg + "</div>";
        }

    })
    .catch(err => {
        console.error(err);
        status.innerHTML = "<div class='text-danger'>Serverfehler</div>";
    });
}



    /* ============================================
       MODE CHANGE → UI update + Auto-Save
    ============================================ */
    modeSelect?.addEventListener("change", function () {
        applyMode();                  
        saveModeToServer(this.value); 
    });

    applyMode();



    /* ============================================
       LIVE PREVIEW (nur bei Mode 1 aktiv!)
    ============================================ */
    function previewEnabled() {
        return modeSelect.value === "1";
    }

    /* Logo Light */
    logoLightInput?.addEventListener("change", e => {
        if (!previewEnabled()) return;
        if (e.target.files[0]) {
            logo.dataset.light = URL.createObjectURL(e.target.files[0]);
            if (preview.dataset.theme !== "dark") logo.src = logo.dataset.light;
        }
    });

    /* Logo Dark */
    logoDarkInput?.addEventListener("change", e => {
        if (!previewEnabled()) return;
        if (e.target.files[0]) {
            logo.dataset.dark = URL.createObjectURL(e.target.files[0]);
            if (preview.dataset.theme === "dark") logo.src = logo.dataset.dark;
        }
    });

    /* Höhe */
    slider?.addEventListener("input", () => {
        if (!previewEnabled()) return;

        const px = slider.value + "px";
        document.getElementById("navHeightLabel").innerText = px;
        document.getElementById("nav_height").value = px;

        preview.style.height = px;
        logo.style.height = `calc(${px} - 30px)`;
    });

    /* Shadow */
    shadowSelect?.addEventListener("change", e => {
        if (!previewEnabled()) return;
        preview.className = "navbar px-3 " + e.target.value;
    });

    /* Animation */
    dropdownSelect?.addEventListener("change", e => {
        if (!previewEnabled()) return;
        preview.dataset.animation = e.target.value;
    });

    /* Darkmode Vorschau */
    let dark = false;
    darkToggle?.addEventListener("click", () => {
        if (!previewEnabled()) return;

        dark = !dark;
        preview.dataset.theme = dark ? "dark" : "light";
        preview.style.background = dark ? "#222" : "#f8f9fa";
        logo.src = dark ? logo.dataset.dark : logo.dataset.light;
    });

});
</script>
