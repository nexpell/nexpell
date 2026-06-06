<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;

global $languageService;

// Adminzugriff prüfen
AccessControl::checkAdminAccess('navigation');

// DEFAULT SETTINGS
$defaults = [
    "theme_engine_enabled" => "1",
    "logo_light"           => "logo_light.png",
    "logo_dark"            => "logo_dark.png",
    "logo_center"          => "0",
    "navbar_modus"         => "auto",
    "navbar_shadow"        => "shadow-sm",
    "dropdown_animation"   => "fade",
    "navbar_density"      => "normal",
    "dropdown_width"      => "auto",
    "dropdown_trigger"    => "hover",
    "dropdown_radius"     => "0.5rem",
    "dropdown_style"      => "auto",
    "dropdown_item_hover" => "surface",
    "chevron_show"        => "1",
    "chevron_rotate"      => "1",
    "dropdown_menu_padding" => "8",
    "dropdown_item_padding_y" => "11",
    "dropdown_item_padding_x" => "16",
    "dropdown_shadow"     => "",
    "nav_height"           => "80px"
];

// SETTINGS AUS DB LADEN
$settings = $defaults;

$res = $_database->query("SELECT setting_key, setting_value FROM navigation_website_settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row["setting_key"]] = $row["setting_value"];
}

// SAVE LOGIC
if (isset($_POST["save"])) {

    $uploadPath = __DIR__ . "/../images/";
    $hasChanged = false;

    // LIGHT Logo Upload
    if (!empty($_FILES["logo_light"]["name"])) {

        $ext      = strtolower(pathinfo($_FILES["logo_light"]["name"], PATHINFO_EXTENSION));
        $fileName = "logo_light." . $ext;
        $target   = $uploadPath . $fileName;

        if (move_uploaded_file($_FILES["logo_light"]["tmp_name"], $target)) {

            $settings["logo_light"] = $fileName;

            if ($_database->query("
                UPDATE navigation_website_settings
                SET setting_value = '{$fileName}'
                WHERE setting_key = 'logo_light'
            ")) $hasChanged = true;
        }
    }

    // DARK Logo Upload
    if (!empty($_FILES["logo_dark"]["name"])) {

        $ext      = strtolower(pathinfo($_FILES["logo_dark"]["name"], PATHINFO_EXTENSION));
        $fileName = "logo_dark." . $ext;
        $target   = $uploadPath . $fileName;

        if (move_uploaded_file($_FILES["logo_dark"]["tmp_name"], $target)) {

            $settings["logo_dark"] = $fileName;

            if ($_database->query("
                UPDATE navigation_website_settings
                SET setting_value = '{$fileName}'
                WHERE setting_key = 'logo_dark'
            ")) $hasChanged = true;
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

        } elseif ($key === "chevron_show") {
            $value = isset($_POST["chevron_show"]) ? "1" : "0";

        } elseif ($key === "chevron_rotate") {
            $value = isset($_POST["chevron_rotate"]) ? "1" : "0";

        } else {
            $value = $_POST[$key] ?? $defaultValue;
        }

        $stmt = $_database->prepare("
            INSERT INTO navigation_website_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->bind_param("ss", $key, $value);
        if ($stmt->execute() && $stmt->affected_rows > 0) $hasChanged = true;
        $stmt->close();
    }

    nx_audit_update('admin_navigation_website', null, $hasChanged, null, 'admincenter.php?site=admin_navigation_website');
    nx_redirect($_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'cb=' . time(), 'success', 'alert_saved', false);
}

$cacheBuster = time();

?>

<form action="" method="post" enctype="multipart/form-data">

<!-- THEME ENGINE ROOT (PFLICHT!) -->
<div id="theme_engine_root" class="mode-<?= htmlspecialchars($settings['theme_engine_enabled'] ?? '1') ?>">

    <div class="row g-4">
      <!-- LEFT: CONFIG -->
      <div class="col-12 col-lg-7">

        <!-- Card: Theme Engine Mode -->
        <div class="card shadow-sm mt-3 mb-4">
          <div class="card-header py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div class="card-title mb-0">
                <i class="bi bi-palette me-2"></i> Theme Engine Modus
              </div>
              <div id="themeEngineStatus" class="small"></div>
            </div>
          </div>
          <div class="card-body">

            <?php $themeEngine = $settings['theme_engine_enabled'] ?? '2'; ?>

            <div class="row g-3 align-items-end">
              <div class="col-12 col-md-8">
                <label class="form-label">Modus</label>
                <select name="theme_engine_enabled" id="theme_engine_enabled" class="form-select">
                  <option value="2" <?= $themeEngine === "2" ? "selected" : "" ?>>Theme-Installer Modus (Theme Default)</option>
                  <option value="1" <?= $themeEngine === "1" ? "selected" : "" ?>>Theme Engine aktiv (Live)</option>
                  <option value="0" <?= $themeEngine === "0" ? "selected" : "" ?>>Custom-CSS Modus (Manuell)</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <div class="d-flex gap-2 justify-content-md-end">
                  <a class="btn btn-outline-secondary w-100" href="admincenter.php?site=admin_navigation_settings&cb=<?= time(); ?>">
                    <i class="bi bi-arrow-clockwise me-1"></i> Neu laden
                  </a>
                </div>
              </div>
            </div>

            <!-- INFO-BOXEN -->
            <?php
              $themePath = '/includes/themes/' . $theme_name . '/css/';
              $stylesheetFile = $themePath . 'stylesheet.css';

              $themeName = $theme_name ?? 'default';
              $fsPath = BASE_PATH . '/includes/themes/' . $themeName . '/css/';
              $webPath = '/includes/themes/' . $themeName . '/css/';

              $cssFiles = [];
              if (is_dir($fsPath)) {
                foreach (glob($fsPath . '*.css') as $file) $cssFiles[] = basename($file);
              }
            ?>

            <div class="mt-4">

              <div id="mode_info_live" class="alert alert-info border-0 p-3 mb-3"
                   style="display: <?= ($settings['theme_engine_enabled'] ?? "0") == "1" ? "block" : "none" ?>;">
                <h6 class="mb-1"><i class="bi bi-lightning-charge me-2"></i>Theme Engine aktiv</h6>
                <div class="small">Alle Design-Optionen sind verfügbar und werden live übernommen.</div>

                <div class="mt-3 small text-dark opacity-75">
                  <i class="bi bi-filetype-css me-1"></i> Geladene CSS-Dateien aus:
                  <?php if (!empty($cssFiles)): ?>
                    <ul class="mt-2 mb-0">
                      <?php foreach ($cssFiles as $css): ?>
                        <li><code><?= htmlspecialchars($webPath . $css) ?></code></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <div class="text-danger mt-2">
                      <i class="bi bi-exclamation-triangle"></i> Keine CSS-Dateien gefunden
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div id="mode_info_css" class="alert alert-warning border-0 p-3 mb-3"
                   style="display: <?= ($settings['theme_engine_enabled'] ?? "0") == "0" ? "block" : "none" ?>;">
                <h6 class="mb-1"><i class="bi bi-code-slash me-2"></i>Custom-CSS Modus</h6>
                <div class="small">Lokale Navigationseinstellungen sind deaktiviert. Layout wird über eigene CSS-Dateien gesteuert.</div>

                <div class="mt-3 small text-dark opacity-75">
                  <i class="bi bi-filetype-css me-1"></i> Geladene CSS-Dateien aus:
                  <?php if (!empty($cssFiles)): ?>
                    <ul class="mt-2 mb-0">
                      <?php foreach ($cssFiles as $css): ?>
                        <li><code><?= htmlspecialchars($webPath . $css) ?></code></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <div class="text-danger mt-2">
                      <i class="bi bi-exclamation-triangle"></i> Keine CSS-Dateien gefunden
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div id="mode_info_theme" class="alert alert-secondary border-0 p-3 mb-0"
                   style="display: <?= ($settings['theme_engine_enabled'] ?? "0") == "2" ? "block" : "none" ?>;">
                <h6 class="mb-1"><i class="bi bi-brush me-2"></i>Theme-Installer Modus</h6>
                <div class="small">Aktives Theme steuert das gesamte Navigationsdesign. Lokale Navigationseinstellungen werden ignoriert.</div>

                <div class="mt-3 small text-dark opacity-75">
                  <i class="bi bi-filetype-css me-1"></i> Es wird ausschließlich folgende Datei geladen:<br>
                  <code><?= htmlspecialchars($stylesheetFile) ?></code>
                </div>
              </div>

            </div>

          </div>
        </div>

        <!-- Card: Logos -->
        <div class="card shadow-sm mb-4">
          <div class="card-header py-3">
            <div class="card-title mb-0"><i class="bi bi-image me-2"></i>Logos</div>
          </div>
          <div class="card-body">

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label">Logo (Light)</label>
                <div class="logo-preview bg-white border rounded-3 p-3 d-flex align-items-center justify-content-center mb-2">
                  <img src="../includes/plugins/navigation/images/<?= $settings['logo_light'] ?>?v=<?= $cacheBuster ?>"
                       class="img-fluid" style="max-height:64px;">
                </div>
                <input id="logo_light" type="file" class="form-control" name="logo_light" accept="image/*">
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Logo (Dark)</label>
                <div class="logo-preview bg-dark border rounded-3 p-3 d-flex align-items-center justify-content-center mb-2">
                  <img src="../includes/plugins/navigation/images/<?= $settings['logo_dark'] ?>?v=<?= $cacheBuster ?>"
                       class="img-fluid" style="max-height:64px;">
                </div>
                <input id="logo_dark" type="file" class="form-control" name="logo_dark" accept="image/*">
              </div>
            </div>

          </div>
        </div>

        <div id="config_area">

        <!-- Card: Navigation Appearance -->
        <div class="card shadow-sm mb-4">
          <div class="card-header py-3">
            <div class="card-title mb-0"><i class="bi bi-sliders me-2"></i>Navigation Design</div>
          </div>
          <div class="card-body">

            <div class="alert alert-light border small mb-4 block-disabled-hint">
              <i class="bi bi-info-circle me-1"></i>
              Diese Einstellungen sind nur im <strong>Theme Engine aktiv (Live)</strong>-Modus verfügbar.
            </div>

            <div class="row g-4">
              <div class="col-12">
                <div class="fw-semibold mb-1">Navbar</div>
                <div class="text-muted small mb-2">Einstellungen für die Hauptnavigation</div>
                <div class="border-bottom mb-3"></div>
              </div>

              <div class="col-12 ps-3">
                <div class="row g-4">

              <div class="col-12 col-md-6 block-theme-modus">
                <label class="form-label">Theme Modus</label>
                <select name="navbar_modus" class="form-select cfg">
                  <option value="auto"  <?= $settings['navbar_modus']=="auto"?"selected":"" ?>>Auto</option>
                  <option value="light" <?= $settings['navbar_modus']=="light"?"selected":"" ?>>Hell</option>
                  <option value="dark"  <?= $settings['navbar_modus']=="dark"?"selected":"" ?>>Dunkel</option>
                </select>
                <div class="form-text">Beeinflusst die Vorschau (bei Live-Modus) und die Ausgabe, sofern Theme Engine aktiv ist.</div>
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Navbar Shadow</label>
                <select name="navbar_shadow" id="navbar_shadow" class="form-select cfg">
                  <option value="" <?= ($settings['navbar_shadow'] ?? "")==="" ? "selected" : "" ?>>Ohne</option>
                  <option value="shadow-sm" <?= ($settings['navbar_shadow'] ?? "")==="shadow-sm" ? "selected" : "" ?>>Shadow small</option>
                  <option value="shadow" <?= ($settings['navbar_shadow'] ?? "")==="shadow" ? "selected" : "" ?>>Shadow normal</option>
                  <option value="shadow-lg" <?= ($settings['navbar_shadow'] ?? "")==="shadow-lg" ? "selected" : "" ?>>Shadow large</option>
                </select>
              </div>

              <div class="col-12 block-config-rest">
                <div class="d-flex align-items-center justify-content-between">
                  <label class="form-label mb-0">Navbar Höhe</label>
                  <span class="badge text-bg-light border" id="navHeightLabel"><?= $settings['nav_height'] ?></span>
                </div>

                <input type="range" min="50" max="120" step="1"
                       id="nav_height_slider"
                       class="form-range cfg mt-2"
                       value="<?= rtrim($settings['nav_height'], 'px') ?>">

                <input type="hidden" id="nav_height" name="nav_height" value="<?= $settings['nav_height'] ?>">
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Navbar Dichte</label>
                <select name="navbar_density" class="form-select cfg">
                  <option value="compact" <?= ($settings['navbar_density'] ?? "normal")==="compact" ? "selected" : "" ?>>Kompakt</option>
                  <option value="normal" <?= ($settings['navbar_density'] ?? "normal")==="normal" ? "selected" : "" ?>>Normal</option>
                  <option value="loose" <?= ($settings['navbar_density'] ?? "normal")==="loose" ? "selected" : "" ?>>Großzügig</option>
                </select>
                <div class="form-text">Steuert Abstände (Padding), Link-Gap und Schriftgröße in der Navigation (Frontend & Live-Vorschau).</div>
              </div>
            </div>
          </div>
              <div class="col-12">
                <div class="fw-semibold mt-4 mb-1">Dropdown</div>
                <div class="text-muted small mb-2">Einstellungen nur für Dropdown-Menüs</div>
                <div class="border-bottom mb-3"></div>
              </div>

              <div class="col-12 ps-3">
                <div class="row g-4">
              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Dropdown Animation</label>
                <select name="dropdown_animation" class="form-select cfg">
                  <option value="fade" <?= ($settings['dropdown_animation'] ?? "fade")==="fade" ? "selected" : "" ?>>Fade</option>
                  <option value="fadeup" <?= ($settings['dropdown_animation'] ?? "")==="fadeup" ? "selected" : "" ?>>Fade Up</option>
                  <option value="slide" <?= ($settings['dropdown_animation'] ?? "")==="slide" ? "selected" : "" ?>>Slide</option>
                  <option value="slidefade" <?= ($settings['dropdown_animation'] ?? "")==="slidefade" ? "selected" : "" ?>>Slide + Fade</option>
                  <option value="scalefade" <?= ($settings['dropdown_animation'] ?? "")==="scalefade" ? "selected" : "" ?>>Scale Fade</option>
                  <option value="zoom" <?= ($settings['dropdown_animation'] ?? "")==="zoom" ? "selected" : "" ?>>Zoom</option>
                  <option value="slideblur" <?= ($settings['dropdown_animation'] ?? "")==="slideblur" ? "selected" : "" ?>>Slide Blur</option>
                  <option value="tilt" <?= ($settings['dropdown_animation'] ?? "")==="tilt" ? "selected" : "" ?>>Tilt</option>
                </select>
              </div>
              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Dropdown Breite</label>
                <select name="dropdown_width" class="form-select cfg">
                  <option value="auto" <?= ($settings['dropdown_width'] ?? "auto")==="auto" ? "selected" : "" ?>>Auto</option>
                  <option value="160" <?= ($settings['dropdown_width'] ?? "")==="160" ? "selected" : "" ?>>160px</option>
                  <option value="200" <?= ($settings['dropdown_width'] ?? "")==="200" ? "selected" : "" ?>>200px</option>
                  <option value="240" <?= ($settings['dropdown_width'] ?? "")==="240" ? "selected" : "" ?>>240px</option>
                  <option value="280" <?= ($settings['dropdown_width'] ?? "")==="280" ? "selected" : "" ?>>280px</option>
                  <option value="300" <?= ($settings['dropdown_width'] ?? "")==="300" ? "selected" : "" ?>>300px</option>
                  <option value="320" <?= ($settings['dropdown_width'] ?? "")==="320" ? "selected" : "" ?>>320px</option>
                  <option value="360" <?= ($settings['dropdown_width'] ?? "")==="360" ? "selected" : "" ?>>360px</option>
                  <option value="400" <?= ($settings['dropdown_width'] ?? "")==="400" ? "selected" : "" ?>>400px</option>
                  <option value="480" <?= ($settings['dropdown_width'] ?? "")==="480" ? "selected" : "" ?>>480px</option>
                </select>
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Dropdown Shadow</label>
                <select name="dropdown_shadow" id="dropdown_shadow" class="form-select cfg">
                  <option value="" <?= ($settings['dropdown_shadow'] ?? "")==="" ? "selected" : "" ?>>Ohne</option>
                  <option value="shadow-sm" <?= ($settings['dropdown_shadow'] ?? "")==="shadow-sm" ? "selected" : "" ?>>Shadow small</option>
                  <option value="shadow" <?= ($settings['dropdown_shadow'] ?? "")==="shadow" ? "selected" : "" ?>>Shadow normal</option>
                  <option value="shadow-lg" <?= ($settings['dropdown_shadow'] ?? "")==="shadow-lg" ? "selected" : "" ?>>Shadow large</option>
                </select>
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Dropdown Trigger</label>
                <select name="dropdown_trigger" class="form-select cfg" id="dropdown_trigger">
                  <option value="hover" <?= ($settings['dropdown_trigger'] ?? "hover")==="hover" ? "selected" : "" ?>>Hover</option>
                  <option value="click" <?= ($settings['dropdown_trigger'] ?? "")==="click" ? "selected" : "" ?>>Klick</option>
                </select>
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <div class="d-flex align-items-center justify-content-between">
                  <label class="form-label mb-0">Dropdown Menu Padding</label>
                  <span class="badge text-bg-light border" id="ddMenuPadLabel"><?= (int)($settings['dropdown_menu_padding'] ?? 8) ?>px</span>
                </div>
                <input type="range" min="0" max="24" step="1"
                      id="dropdown_menu_padding"
                      name="dropdown_menu_padding"
                      class="form-range cfg mt-2"
                      value="<?= (int)($settings['dropdown_menu_padding'] ?? 8) ?>">
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <div class="d-flex align-items-center justify-content-between">
                  <label class="form-label mb-0">Dropdown Item Padding (Y)</label>
                  <span class="badge text-bg-light border" id="ddItemPadYLabel"><?= (int)($settings['dropdown_item_padding_y'] ?? 11) ?>px</span>
                </div>
                <input type="range" min="6" max="20" step="1"
                      id="dropdown_item_padding_y"
                      name="dropdown_item_padding_y"
                      class="form-range cfg mt-2"
                      value="<?= (int)($settings['dropdown_item_padding_y'] ?? 11) ?>">
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <div class="d-flex align-items-center justify-content-between">
                  <label class="form-label mb-0">Dropdown Item Padding (X)</label>
                  <span class="badge text-bg-light border" id="ddItemPadXLabel"><?= (int)($settings['dropdown_item_padding_x'] ?? 16) ?>px</span>
                </div>
                <input type="range" min="8" max="28" step="1"
                      id="dropdown_item_padding_x"
                      name="dropdown_item_padding_x"
                      class="form-range cfg mt-2"
                      value="<?= (int)($settings['dropdown_item_padding_x'] ?? 16) ?>">
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <?php
                  $__rad_raw = $settings['dropdown_radius'] ?? "0.5rem";
                  $__rad_px = 8;
                  if (preg_match('/([0-9.]+)\s*rem/i', $__rad_raw, $__m)) {
                      $__rad_px = (int) round(((float)$__m[1]) * 16);
                  } elseif (preg_match('/([0-9.]+)\s*px/i', $__rad_raw, $__m)) {
                      $__rad_px = (int) round((float)$__m[1]);
                  }
                  if ($__rad_px < 0) $__rad_px = 0;
                  if ($__rad_px > 24) $__rad_px = 24;
                ?>
                <div class="d-flex justify-content-between align-items-center">
                  <label class="form-label mb-0">Dropdown Radius</label>
                  <span class="badge text-bg-light border" id="ddRadiusLabel"><?= $__rad_px ?>px</span>
                </div>
                <input type="range" min="0" max="24" step="1"
                      id="dropdown_radius_px"
                      class="form-range cfg mt-2"
                      value="<?= $__rad_px ?>">
                <input type="hidden" name="dropdown_radius" id="dropdown_radius" value="<?= htmlspecialchars($__rad_raw, ENT_QUOTES) ?>">
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Dropdown Stil</label>
                <select name="dropdown_style" class="form-select cfg" id="dropdown_style">
                  <option value="auto" <?= ($settings['dropdown_style'] ?? "auto")==="auto" ? "selected" : "" ?>>Auto (Theme)</option>
                  <option value="solid" <?= ($settings['dropdown_style'] ?? "")==="solid" ? "selected" : "" ?>>Solid</option>
                  <option value="glass" <?= ($settings['dropdown_style'] ?? "")==="glass" ? "selected" : "" ?>>Glas (Blur)</option>
                </select>
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Item Hover Stil</label>
                <select name="dropdown_item_hover" class="form-select cfg" id="dropdown_item_hover">
                  <option value="surface" <?= ($settings['dropdown_item_hover'] ?? "surface")==="surface" ? "selected" : "" ?>>Fläche</option>
                  <option value="underline" <?= ($settings['dropdown_item_hover'] ?? "")==="underline" ? "selected" : "" ?>>Underline</option>
                  <option value="slide" <?= ($settings['dropdown_item_hover'] ?? "")==="slide" ? "selected" : "" ?>>Leichter Slide</option>
                  <option value="none" <?= ($settings['dropdown_item_hover'] ?? "")==="none" ? "selected" : "" ?>>Kein Effekt</option>
                </select>
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <div class="form-check form-switch">
                  <input class="form-check-input cfg" type="checkbox" id="chevron_show" name="chevron_show" value="1"
                        <?= (($settings['chevron_show'] ?? "1")==="1") ? "checked" : "" ?>>
                  <label class="form-check-label" for="chevron_show">Chevron im Dropdown anzeigen</label>
                </div>
                <div class="small text-muted mt-1">Wenn deaktiviert, wird der Chevron im Frontend/Preview ausgeblendet.</div>
              </div>

              <div id="chevronRotateWrap" class="col-12 col-md-6 block-config-rest">
                <div class="form-check form-switch">
                  <input class="form-check-input cfg" type="checkbox" id="chevron_rotate" name="chevron_rotate" value="1"
                        <?= (($settings['chevron_rotate'] ?? "1") === "1") ? "checked" : "" ?>>
                  <label class="form-check-label" for="chevron_rotate">Chevron beim Öffnen drehen</label>
                </div>
              </div>
                </div>
              </div>

              <div class="col-12 col-md-6 block-config-rest">
                <label class="form-label">Hinweis</label>
                <div class="small text-muted">
                  Diese Einstellungen greifen nur im <strong>Live</strong>-Modus (Theme Engine aktiv). In den anderen Modi werden sie gesperrt.
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- RIGHT: LIVE PREVIEW -->
<div class="col-12 col-lg-5 align-self-start live-preview-col">
  <div class="card shadow-sm card-live-preview mt-3">
    <div class="card-header py-3">
      <div class="d-flex align-items-center justify-content-between">
        <div class="card-title mb-0"><i class="bi bi-eye me-2"></i>Live Vorschau</div>
        <button id="previewDarkToggle" type="button" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-moon-stars"></i>
        </button>
      </div>
    </div>

<div class="card-body block-live-preview">
  <div class="preview-shell border rounded-3 p-3">
    <nav id="navPreview" class="navbar <?= $settings['navbar_shadow'] ?> px-3 rounded-3 nx-density-normal"
          style="height: <?= $settings['nav_height'] ?>; background: #f8f9fa;">

      <a class="navbar-brand me-auto">
        <img id="previewLogo"
              src="../includes/plugins/navigation/images/<?= $settings['logo_light'] ?>"
              data-light="../includes/plugins/navigation/images/<?= $settings['logo_light'] ?>"
              data-dark="../includes/plugins/navigation/images/<?= $settings['logo_dark'] ?>"
              style="height: calc(<?= $settings['nav_height'] ?> - 30px);">
      </a>

      <ul class="navbar-nav d-flex flex-row gap-3 mb-0">
        <li class="nav-item"><a class="nav-link" href="#" onclick="return false;">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#" onclick="return false;">Community</a></li>
        <li class="nav-item"><a class="nav-link" href="#" onclick="return false;">Media</a></li>

        <li class="nav-item dropdown nx-preview-dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="return false;">
            Dropdown
          </a>
          <ul class="dropdown-menu dropdown-menu-end <?= htmlspecialchars($settings['dropdown_shadow'] ?? '', ENT_QUOTES) ?>">
            <li><a class="dropdown-item is-hover" href="#" onclick="return false;">Link #1</a></li>
            <li><a class="dropdown-item" href="#" onclick="return false;">Link #2</a></li>
            <li><a class="dropdown-item" href="#" onclick="return false;">Link #3</a></li>
          </ul>
        </li>

      </ul>
    </nav>
  </div>

  <div class="small text-muted mt-3">
    Tipp: Im Live-Modus aktualisieren Änderungen die Vorschau direkt (Logo, Höhe, Shadow, Animation).
  </div>
</div>

<div class="card-body block-preview-disabled">
  <div class="alert alert-light border small mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Diese Live-Vorschau ist nur im <strong>Theme Engine aktiv (Live)</strong>-Modus verfügbar.
  </div>
</div>

</div>
</div>
</div>

    <button type="submit" name="save" class="btn btn-primary">
        Speichern
    </button>

</div>
</form>

<style>
.logo-preview{ min-height: 92px; }

/* Blocks by mode */
.block-live-preview,
.block-theme-modus,
.block-config-rest { display:none; }

/* MODE 1: everything */
.mode-1 .block-live-preview,
.mode-1 .block-theme-modus,
.mode-1 .block-config-rest { display:block; }

/* MODE 2: preview only */
.mode-2 .block-live-preview { display:block; }

/* MODE 0: preview hidden (optional) */
.mode-0 .block-live-preview { display:none; }

/* Config area lock state (JS controls opacity/pointer events) */
#config_area { transition: opacity .15s ease; }

/* Preview */
.preview-shell{ background: linear-gradient(180deg, rgba(0,0,0,.02), rgba(0,0,0,0)); }

/* Disabled hint */
.block-disabled-hint{ display:block; }
.mode-1 .block-disabled-hint{ display:none; }

/* Preview disabled hint */
.block-preview-disabled{ display:block; }
.mode-1 .block-preview-disabled{ display:none; }

/* Custom-CSS Mode (0): hide live preview completely */
.mode-0 .block-live-preview { display:none !important; }
.mode-0 .block-preview-disabled { display:block; }

/* Preview theming */
#navPreview{
  --nx-prev-bg: #f8f9fa;
  --nx-prev-fg: rgba(0,0,0,.75);
  --nx-prev-border: rgba(0,0,0,.08);
  --bs-dropdown-link-hover-bg: rgba(0,0,0,.06);
  --bs-dropdown-link-hover-color: var(--nx-prev-fg);
  background: var(--nx-prev-bg) !important;
  border: 1px solid var(--nx-prev-border);
}
#navPreview .nav-link,
#navPreview .nav-item{
  color: var(--nx-prev-fg) !important;
}
#navPreview .nav-link:hover{ opacity:.85; }
#navPreview .dropdown-menu{
  --bs-dropdown-link-hover-bg: rgba(0,0,0,.06);
  --bs-dropdown-link-hover-color: var(--nx-prev-fg);

  border-radius: 14px;
  border: 1px solid rgba(0,0,0,.08);
  box-shadow: var(--nx-dd-shadow, 0 10px 30px rgba(0,0,0,.12));
  overflow: hidden;
  padding: var(--nx-dd-pad) !important;
}
#navPreview[data-theme="dark"]{
  --nx-prev-bg: #222;
  --nx-prev-fg: rgba(255,255,255,.85);
  --nx-prev-border: rgba(255,255,255,.10);
  --bs-dropdown-link-hover-bg: rgba(255,255,255,.08);
  --bs-dropdown-link-hover-color: rgba(255,255,255,.92);
}
#navPreview[data-theme="dark"] .dropdown-menu{
  --bs-dropdown-link-hover-bg: rgba(255,255,255,.08);
  --bs-dropdown-link-hover-color: rgba(255,255,255,.92);

  background: #1f1f1f;
}
#navPreview[data-theme="dark"] .dropdown-item{
  color: rgba(255,255,255,.85);
}

#navPreview .nav-link:focus,
#navPreview .nav-link:focus-visible,
#navPreview .dropdown-toggle:focus,
#navPreview .dropdown-toggle:focus-visible {
    outline: none !important;
    box-shadow: none !important;
}

/* Preview dropdown animations (Bootstrap adds .show) */
@keyframes nxFade { from{opacity:0} to{opacity:1} }
@keyframes nxFadeUp { from{opacity:0; transform: translateY(6px)} to{opacity:1; transform: translateY(0)} }
@keyframes nxSlide { from{opacity:0; transform: translateY(-6px)} to{opacity:1; transform: translateY(0)} }
@keyframes nxScaleFade { from{opacity:0; transform: scale(.96)} to{opacity:1; transform: scale(1)} }
@keyframes nxZoom { from{opacity:0; transform: scale(.98)} to{opacity:1; transform: scale(1)} }
@keyframes nxSlideBlur { from{opacity:0; transform: translateY(10px); filter: blur(6px)} to{opacity:1; transform: translateY(0); filter: blur(0)} }
@keyframes nxTilt { from{opacity:0; transform: perspective(600px) rotateX(-6deg)} to{opacity:1; transform: perspective(600px) rotateX(0deg)} }

#navPreview.nx-anim-fade .dropdown-menu.show{ animation: nxFade .16s ease-out; }
#navPreview.nx-anim-fadeup .dropdown-menu.show{ animation: nxFadeUp .18s ease-out; }
#navPreview.nx-anim-slide .dropdown-menu.show{ animation: nxSlide .18s ease-out; }
#navPreview.nx-anim-slidefade .dropdown-menu.show{ animation: nxSlide .18s ease-out; }
#navPreview.nx-anim-scalefade .dropdown-menu.show{ animation: nxScaleFade .18s ease-out; transform-origin: top; }
#navPreview.nx-anim-zoom .dropdown-menu.show{ animation: nxZoom .18s ease-out; }
#navPreview.nx-anim-slideblur .dropdown-menu.show{ animation: nxSlideBlur .20s ease-out; }
#navPreview.nx-anim-tilt .dropdown-menu.show{ animation: nxTilt .20s ease-out; transform-origin: top; }

/* ================================
   PREVIEW: Navbar Density
================================ */
#navPreview.nx-density-compact .navbar-nav{ gap: .10rem; }
#navPreview.nx-density-compact .nav-link{
  padding: .20rem .60rem;
  font-size: .95rem;
  line-height: 1.2;
}

#navPreview.nx-density-normal .navbar-nav{ gap: .25rem; }
#navPreview.nx-density-normal .nav-link{
  padding: .45rem .75rem;
  font-size: 1rem;
  line-height: 1.25;
}

#navPreview.nx-density-loose .navbar-nav{ gap: .50rem; }
#navPreview.nx-density-loose .nav-link{
  padding: .85rem 1.05rem;
  font-size: 1.05rem;
  line-height: 1.3;
}

/* ================================
   PREVIEW: Dropdown width + radius
================================ */
#navPreview{ --nx-dd-width: auto; --nx-dd-radius: .5rem; --nx-dd-pad: 8px; --nx-item-py: 11px; --nx-item-px: 16px; }
#navPreview .dropdown-menu{ min-width: var(--nx-dd-width); border-radius: var(--nx-dd-radius); }
#navPreview .dropdown-item{ padding: var(--nx-item-py) var(--nx-item-px); }

/* ================================
   PREVIEW: Dropdown close button (outside menu)
================================ */
#navPreview .nx-preview-dropdown{ position: relative; }
#navPreview .nx-preview-dd-close{
  position:absolute;
  z-index: 1060;
  display:none;
  padding: .125rem .4rem;
  line-height: 1;
  border-radius: 999px;
}
#navPreview .nx-preview-dd-close:focus{ box-shadow:none; }

/* ================================
   PREVIEW: Dropdown style
================================ */
#navPreview.nx-dd-solid .dropdown-menu{
  background: var(--bs-dropdown-bg) !important;
  background-color: var(--bs-dropdown-bg) !important;
  -webkit-backdrop-filter: none !important;
  backdrop-filter: none !important;
  border-color: var(--bs-dropdown-border-color) !important;
}
#navPreview.nx-dd-glass .dropdown-menu{
  /* make the effect clearly visible in the preview */
  background: rgba(255,255,255,.60) !important;
  background-color: rgba(255,255,255,.60) !important;
  -webkit-backdrop-filter: blur(14px) saturate(1.2) !important;
  backdrop-filter: blur(14px) saturate(1.2) !important;
  border: 1px solid rgba(0,0,0,.08) !important;
}
#navPreview[data-theme="dark"].nx-dd-glass .dropdown-menu{
  background: rgba(33,37,41,.60) !important;
  background-color: rgba(33,37,41,.60) !important;
  border: 1px solid rgba(255,255,255,.14) !important;
}
/* ================================
   PREVIEW: Item Hover style

================================ */
#navPreview.nx-itemhover-surface .dropdown-item.is-hover,
#navPreview.nx-itemhover-surface .dropdown-item:hover{
  background: var(--bs-dropdown-link-hover-bg);
}

#navPreview.nx-itemhover-underline .dropdown-item.is-hover,
#navPreview.nx-itemhover-underline .dropdown-item:hover{
  text-decoration: underline;
  background: transparent;
}

#navPreview.nx-itemhover-slide .dropdown-item{
  transition: transform .12s ease, background-color .12s ease;
}
#navPreview.nx-itemhover-slide .dropdown-item.is-hover,
#navPreview.nx-itemhover-slide .dropdown-item:hover{
  transform: translateX(2px);
}

#navPreview.nx-itemhover-none .dropdown-item.is-hover,
#navPreview.nx-itemhover-none .dropdown-item:hover{
  background: transparent;
}
/* ================================
   PREVIEW: Active item style
================================ */
/* ================================
   PREVIEW: Chevron rotation
================================ */
#navPreview.nx-chevron-rotate .dropdown.show > a.dropdown-toggle::after{ transform: rotate(180deg); }
#navPreview.nx-chevron-rotate .dropdown.show > a i.bi-chevron-down{ transform: rotate(180deg); transition: transform .15s ease; }
#navPreview.nx-chevron-hide .dropdown-toggle::after{ display:none !important; }
#navPreview .dropdown-toggle::after{ transition: transform .15s ease; display: inline-block; transform-origin: center; }
#navPreview.nx-chevron-rotate .dropdown-toggle[aria-expanded=\"true\"]::after{ transform: rotate(180deg); }
#navPreview.nx-chevron-rotate .dropdown.show > a.dropdown-toggle::after{ transform: rotate(180deg); }
#navPreview.nx-chevron-rotate .dropdown-toggle::after{ transform: rotate(180deg); transition: transform .25s ease; }
/* Preview dropdown */
#navPreview .navbar-nav .dropdown { position: relative; }
#navPreview .navbar-nav .dropdown-menu { 
  position: absolute !important;
  top: 100%;
  left: auto;
  right: 0;
  margin-top: .5rem;
}
#navPreview { overflow: visible; }
.preview-shell { overflow: visible; }

/* Preview header controls */
#previewDarkToggle { opacity: .9; }
.mode-0 #previewDarkToggle,
.mode-2 #previewDarkToggle { display: none !important; }

/* Custom-CSS Modus: Live-Vorschau wie "Navigation Design" deaktiviert */
.mode-0 .block-live-preview { display: none !important; }
.mode-0 .block-preview-disabled { display: block !important; }
.mode-1 .block-preview-disabled { display: none !important; }

/* Theme-Installer Modus: Preview als Hinweis (nicht interaktiv) */
.mode-2 .block-live-preview { display: none !important; }
.mode-2 .block-preview-disabled { display: block !important; }

/* Live preview disabled like Navigation Design (non-live modes) */
.mode-0 .card-live-preview,
.mode-2 .card-live-preview {
  opacity: .45;
}
.mode-0 .card-live-preview .card-body,
.mode-2 .card-live-preview .card-body {
  pointer-events: none;
}
/* Live preview theme styling (driven by data-theme on #navPreview) */
#navPreview[data-theme="light"]{
  background: #f8f9fa !important;
  color: #212529;
}
#navPreview[data-theme="light"] .nav-link,
#navPreview[data-theme="light"] .dropdown-toggle{
  color: rgba(33,37,41,.85) !important;
}
#navPreview[data-theme="light"] .nav-link:hover,
#navPreview[data-theme="light"] .dropdown-toggle:hover{
  color: rgba(33,37,41,1) !important;
}
#navPreview[data-theme="light"]:not(.nx-dd-solid):not(.nx-dd-glass) .dropdown-menu{
  background: #ffffff;
  border-color: rgba(0,0,0,.08);
}
#navPreview[data-theme="light"] .dropdown-item{ color:#212529; }
#navPreview[data-theme="light"] .dropdown-item:hover{ background: rgba(0,0,0,.04); }

#navPreview[data-theme="dark"]{
  background: #212529 !important;
  color: #f8f9fa;
}
#navPreview[data-theme="dark"] .nav-link,
#navPreview[data-theme="dark"] .dropdown-toggle{
  color: rgba(248,249,250,.88) !important;
}
#navPreview[data-theme="dark"] .nav-link:hover,
#navPreview[data-theme="dark"] .dropdown-toggle:hover{
  color: rgba(248,249,250,1) !important;
}
#navPreview[data-theme="dark"]:not(.nx-dd-solid):not(.nx-dd-glass) .dropdown-menu{
  background: #212529;
  border-color: rgba(255,255,255,.12);
}
#navPreview[data-theme="dark"] .dropdown-item{ color: rgba(248,249,250,.92); }
#navPreview[data-theme="dark"] .dropdown-item:hover{ background: rgba(255,255,255,.08); }
#navPreview[data-theme="dark"] .dropdown-divider{ border-top-color: rgba(255,255,255,.14); }

@media (min-width: 992px) {
  .live-preview-col{
    position: sticky;
    top: calc(var(--bs-gutter-x) * 0.5);
    align-self: flex-start;
    height: fit-content;
    z-index: 1030;
  }
}

</style>

<!-- JAVASCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", () => {

    // ELEMENTE
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

    const densitySelect   = document.querySelector("select[name='navbar_density']");
    const widthSelect     = document.querySelector("select[name='dropdown_width']");
    const ddShadowSelect  = document.getElementById("dropdown_shadow");
    const triggerSelect   = document.getElementById("dropdown_trigger");
    const ddMenuPadRange  = document.getElementById("dropdown_menu_padding");
    const ddItemPadYRange = document.getElementById("dropdown_item_padding_y");
    const ddItemPadXRange = document.getElementById("dropdown_item_padding_x");
    const ddMenuPadLabel  = document.getElementById("ddMenuPadLabel");
    const ddItemPadYLabel = document.getElementById("ddItemPadYLabel");
    const ddItemPadXLabel = document.getElementById("ddItemPadXLabel");
    const radiusHidden    = document.getElementById("dropdown_radius");
    const radiusRange     = document.getElementById("dropdown_radius_px");
    const ddRadiusLabel   = document.getElementById("ddRadiusLabel");
    const styleSelect     = document.getElementById("dropdown_style");
    const itemHoverSelect = document.getElementById("dropdown_item_hover");
    const chevronShow     = document.getElementById("chevron_show");
    const chevronCheck    = document.getElementById("chevron_rotate");
    const chevronRotateWrap = document.getElementById("chevronRotateWrap");
    const themeModeSelect = document.querySelector("select[name='navbar_modus']");
    const dropdownSelectEl = document.querySelector("select[name='dropdown_animation']");

    let autoDark = false;

    function setPreviewTheme(theme) {
        if (!preview || !logo) return;
        preview.dataset.theme = theme;

        // logo swap
        logo.src = theme === "dark" ? logo.dataset.dark : logo.dataset.light;

        // toggle icon (only visual)
        if (darkToggle) {
            const ico = darkToggle.querySelector("i");
            if (ico) {
                ico.classList.remove("bi-moon-stars","bi-sun");
                ico.classList.add(theme === "dark" ? "bi-sun" : "bi-moon-stars");
            }
        }
    }

    function applyThemeModeToPreview() {
        if (!preview) return;
        const mode = themeModeSelect?.value ?? "auto";

        if (mode === "dark") {
            autoDark = true;
            setPreviewTheme("dark");
        } else if (mode === "light") {
            autoDark = false;
            setPreviewTheme("light");
        } else {
            // auto – behält den aktiven toggle Wert
            setPreviewTheme(autoDark ? "dark" : "light");
        }
    }

    function applyDropdownAnimationToPreview(anim) {
        if (!preview) return;
        const a = anim || dropdownSelectEl?.value || "fade";
        preview.dataset.animation = a;

        preview.classList.remove(
            "nx-anim-fade","nx-anim-fadeup","nx-anim-slide","nx-anim-slidefade",
            "nx-anim-scalefade","nx-anim-zoom","nx-anim-slideblur","nx-anim-tilt"
        );
        preview.classList.add("nx-anim-" + a);
    }

    
    function pxToRem(px) {
        const v = (px / 16);
        let s = v.toFixed(3).replace(/0+$/,'').replace(/\.$/,'');
        return s + "rem";
    }

    function syncRadiusToHidden() {
        if (!radiusRange || !radiusHidden) return;
        const px = parseInt(radiusRange.value || "0", 10);
        if (ddRadiusLabel) ddRadiusLabel.textContent = px + "px";
        radiusHidden.value = pxToRem(px);
    }
    function applyAdvancedPreview() {
        if (!preview) return;

        // Dichte
        const dens = densitySelect?.value || "normal";
        const densClass = dens === "compact" ? "compact" : (dens === "loose" ? "loose" : "normal");
        preview.classList.remove("nx-density-compact","nx-density-normal","nx-density-loose");
        preview.classList.add("nx-density-" + densClass);

        // Dropdown Breite
        const w = widthSelect?.value || "auto";
        preview.style.setProperty("--nx-dd-width", (w === "auto") ? "auto" : (parseInt(w,10) + "px"));

        // Border Radius
        const rad = radiusHidden?.value || "0.5rem";
        preview.style.setProperty("--nx-dd-radius", rad);

        // Dropdown padding
        const mp = parseInt(ddMenuPadRange?.value || "8", 10);
        const py = parseInt(ddItemPadYRange?.value || "11", 10);
        const px = parseInt(ddItemPadXRange?.value || "16", 10);

        preview.style.setProperty("--nx-dd-pad", mp + "px");
        preview.style.setProperty("--nx-item-py", py + "px");
        preview.style.setProperty("--nx-item-px", px + "px");

        if (ddMenuPadLabel)  ddMenuPadLabel.textContent  = mp + "px";
        if (ddItemPadYLabel) ddItemPadYLabel.textContent = py + "px";
        if (ddItemPadXLabel) ddItemPadXLabel.textContent = px + "px";

        // Style
        const st = styleSelect?.value || "auto";
        preview.classList.remove("nx-dd-solid","nx-dd-glass");
        if (st === "solid") preview.classList.add("nx-dd-solid");
        if (st === "glass") preview.classList.add("nx-dd-glass");

        // Hover style
        const ih = itemHoverSelect?.value || "surface";
        preview.classList.remove("nx-itemhover-surface","nx-itemhover-underline","nx-itemhover-slide","nx-itemhover-none");
        preview.classList.add("nx-itemhover-" + ih);
        // Dropdown shadow
        const ddMenu = preview.querySelector(".nx-preview-dropdown .dropdown-menu");
        const ds = ddShadowSelect?.value || "";
        if (ddMenu) {
            ddMenu.classList.remove("shadow-sm","shadow","shadow-lg");
            if (ds) ddMenu.classList.add(ds);
        }
        let ddShadowCss = "none";
        if (ds === "shadow-sm") ddShadowCss = "var(--bs-box-shadow-sm)";
        if (ds === "shadow")    ddShadowCss = "var(--bs-box-shadow)";
        if (ds === "shadow-lg") ddShadowCss = "var(--bs-box-shadow-lg)";
        preview.style.setProperty("--nx-dd-shadow", ddShadowCss);

        // Chevron Pfeil (zeigen/verstecken + rotieren)
        const showChevron = !!chevronShow?.checked;
        preview.classList.toggle("nx-chevron-hide", !showChevron);
        preview.classList.toggle("nx-chevron-rotate", showChevron && !!chevronCheck?.checked);
        ensurePreviewDropdownVisible();
    }

    function syncChevronUI() {
        const show = !!chevronShow?.checked;
        if (chevronRotateWrap) chevronRotateWrap.style.display = show ? "" : "none";
        // Wenn Chevron ausgeblendet wird: Rotation logisch deaktivieren
        if (!show && chevronCheck) chevronCheck.checked = false;

        if (previewEnabled()) applyAdvancedPreview();
    }

    // FUNKTION: UI Modus anwenden
    function applyMode() {
        const mode = modeSelect?.value ?? "1";

        const root = document.getElementById("theme_engine_root");
        if (!root) return;

        root.classList.remove("mode-0", "mode-1", "mode-2");
        root.classList.add("mode-" + mode);

        const infoLive  = document.getElementById("mode_info_live");
        const infoCss   = document.getElementById("mode_info_css");
        const infoTheme = document.getElementById("mode_info_theme");

        // RESET INFO (mit Null-Check)
        if (infoLive)  infoLive.style.display  = "none";
        if (infoCss)   infoCss.style.display   = "none";
        if (infoTheme) infoTheme.style.display = "none";

        // RESET CONFIG
        if (configArea) {
            configArea.style.opacity = "0.45";
            configArea.style.pointerEvents = "none";
        }
        cfgInputs.forEach(i => i.disabled = true);

        // MODE 1 – alles an
        if (mode === "1") {
            if (configArea) {
                configArea.style.opacity = "1";
                configArea.style.pointerEvents = "auto";
            }
            cfgInputs.forEach(i => i.disabled = false);
            if (infoLive) infoLive.style.display = "block";
        }

        // MODE 2 – Theme-Installer
        else if (mode === "2") {
            if (infoTheme) infoTheme.style.display = "block";
        }

        // MODE 0 – Custom CSS
        else if (mode === "0") {
            if (infoCss) infoCss.style.display = "block";
        }
    }

    // SPEICHERUNG via AJAX
    function saveModeToServer(value) {
        if (status) status.innerHTML = "<div class='text-info'>Speichere…</div>";

        fetch("/includes/plugins/navigation/admin/save_theme_engine.php?ajax=1", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "value=" + encodeURIComponent(value)
        })
        .then(r => r.json())
        .then(data => {
            console.log("Antwort vom Server:", data);

            if (data.success) {
                if (status) status.innerHTML = "<div class='badge bg-success'>Gespeichert</div>";
                setTimeout(() => { if (status) status.innerHTML = ""; }, 1200);

                // Seite neu laden
                setTimeout(() => {
                    location.href = location.href.split("?")[0] + "?site=admin_navigation_settings&cb=" + Date.now();
                }, 400);
            } else {
                if (status) status.innerHTML = "<div class='text-danger'>Fehler: " + data.msg + "</div>";
            }
        })
        .catch(err => {
            console.error(err);
            if (status) status.innerHTML = "<div class='text-danger'>Serverfehler</div>";
        });
    }

    // LIVE PREVIEW (nur bei Mode 1 aktiv!)
    function previewEnabled() {
        return (modeSelect?.value ?? "1") === "1";
    }

    // MODE CHANGE → UI update + Auto-Save
    modeSelect?.addEventListener("change", function () {
        applyMode();

        // Preview init/refresh
        applyThemeModeToPreview();
        applyDropdownAnimationToPreview();
        syncRadiusToHidden();
        applyAdvancedPreview();
        ensurePreviewDropdownVisible();

        saveModeToServer(this.value);
    });

    // PREVIEW: Dropdown immer sichtbar für Änderungen
    const previewDropdown       = preview?.querySelector(".nx-preview-dropdown");
    const previewDropdownToggle = previewDropdown?.querySelector('[data-bs-toggle="dropdown"]');
    const previewDropdownMenu   = previewDropdown?.querySelector(".dropdown-menu");

    let previewPinned = true;
    let allowCloseOnce = false;
    let isPositioning = false;

    function positionPreviewDropdownClose() {
        if (!previewDropdown || !previewDropdownMenu || !previewDropdownClose) return;
        if (!previewDropdownMenu.classList.contains("show")) {
            previewDropdownClose.style.display = "none";
            return;
        }

        const menuLeft = previewDropdownMenu.offsetLeft;
        const menuTop  = previewDropdownMenu.offsetTop;

        previewDropdownClose.style.left = (menuLeft + previewDropdownMenu.offsetWidth + 8) + "px";
        previewDropdownClose.style.top  = (menuTop + 6) + "px";
        previewDropdownClose.style.display = "inline-flex";
    }

    function ensurePreviewDropdownVisible({ replayAnimation = false } = {}) {
        if (!previewEnabled()) return;
        if (!previewDropdownToggle) return;

        const instance = bootstrap.Dropdown.getOrCreateInstance(previewDropdownToggle);

        if (previewPinned) {
            if (replayAnimation) {
                const prevPinned = previewPinned;
                allowCloseOnce = true;
                previewPinned = false;
                instance.hide();

                setTimeout(() => {
                    allowCloseOnce = false;
                    previewPinned = prevPinned;
                    instance.show();
                    positionPreviewDropdownClose();
                }, 40);
                return;
            }

            instance.show();
            positionPreviewDropdownClose();
        } else {
            positionPreviewDropdownClose();
        }
    }

    if (previewDropdown && previewDropdownToggle) {
        previewDropdown.addEventListener("hide.bs.dropdown", (e) => {
            if (!previewEnabled()) return;
            if (previewPinned && !allowCloseOnce) e.preventDefault();
        });

        previewDropdown.addEventListener("shown.bs.dropdown", () => {
            positionPreviewDropdownClose();
        });

        window.addEventListener("resize", () => {
            if (!previewEnabled()) return;
            if (isPositioning) return;
            isPositioning = true;
            requestAnimationFrame(() => {
                positionPreviewDropdownClose();
                isPositioning = false;
            });
        });

        previewDropdownClose?.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (!previewDropdownToggle) return;
            const instance = bootstrap.Dropdown.getOrCreateInstance(previewDropdownToggle);

            allowCloseOnce = true;
            previewPinned = false;
            instance.hide();

            setTimeout(() => {
                allowCloseOnce = false;
                positionPreviewDropdownClose();
            }, 0);
        });
    }

    (function enablePreviewHoverDropdowns() {
        if (!preview) return;

        let hideTimer = null;
        let showTimer = null;

        preview.querySelectorAll(".dropdown").forEach(dd => {
            const toggle = dd.querySelector('[data-bs-toggle="dropdown"]');
            if (!toggle) return;

            const instance = bootstrap.Dropdown.getOrCreateInstance(toggle);

            dd.addEventListener("mouseenter", () => {
                if (!previewEnabled()) return;
                if (previewPinned) return;
                if ((triggerSelect?.value || "hover") !== "hover") return;

                if (hideTimer) clearTimeout(hideTimer);
                if (showTimer) clearTimeout(showTimer);
                instance.show();
            });

            dd.addEventListener("mouseleave", () => {
                if (!previewEnabled()) return;
                if (previewPinned) return;
                hideTimer = setTimeout(() => instance.hide(), 120);
            });
        });
    })();

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
        const navHeightLabel = document.getElementById("navHeightLabel");
        const navHeightInput = document.getElementById("nav_height");

        if (navHeightLabel) navHeightLabel.innerText = px;
        if (navHeightInput) navHeightInput.value = px;

        preview.style.height = px;
        if (logo) logo.style.height = `calc(${px} - 30px)`;
    });

    /* Shadow */
    shadowSelect?.addEventListener("change", e => {
        if (!previewEnabled()) return;
        preview.className = "navbar px-3 " + e.target.value;
    });

    /* Animation */
    dropdownSelect?.addEventListener("change", e => {
        if (!previewEnabled()) return;
        applyDropdownAnimationToPreview(e.target.value);
        ensurePreviewDropdownVisible({ replayAnimation: true });
    });

    /* Advanced preview controls */
    densitySelect?.addEventListener("change", () => { if (!previewEnabled()) return; applyAdvancedPreview(); });
    widthSelect?.addEventListener("change",   () => { if (!previewEnabled()) return; applyAdvancedPreview(); });
    ddShadowSelect?.addEventListener("change", () => { if (!previewEnabled()) return; applyAdvancedPreview(); });
    radiusRange?.addEventListener("input",  () => { if (!previewEnabled()) return; syncRadiusToHidden(); applyAdvancedPreview(); });
    radiusRange?.addEventListener("change", () => { if (!previewEnabled()) return; syncRadiusToHidden(); applyAdvancedPreview(); });
    styleSelect?.addEventListener("change",   () => { if (!previewEnabled()) return; applyAdvancedPreview(); });
    styleSelect?.addEventListener("input",    () => { if (!previewEnabled()) return; applyAdvancedPreview(); });
    itemHoverSelect?.addEventListener("change", () => { if (!previewEnabled()) return; applyAdvancedPreview(); });
    chevronShow?.addEventListener("change",    syncChevronUI);
    chevronShow?.addEventListener("input",     syncChevronUI);
    chevronCheck?.addEventListener("change",   () => { if (!previewEnabled()) return; applyAdvancedPreview(); });
    // Dropdown padding sliders
    ddMenuPadRange?.addEventListener("input", () => {
        if (!previewEnabled()) return;
        applyAdvancedPreview();
    });
    ddItemPadYRange?.addEventListener("input", () => {
        if (!previewEnabled()) return;
        applyAdvancedPreview();
    });
    ddItemPadXRange?.addEventListener("input", () => {
        if (!previewEnabled()) return;
        applyAdvancedPreview();
    });

    /* Darkmode Vorschau (nur Auto) */
    darkToggle?.addEventListener("click", () => {
        if (!previewEnabled()) return;

        autoDark = !autoDark;
        setPreviewTheme(autoDark ? "dark" : "light");
    });

    // Initial init
    applyMode();
    applyThemeModeToPreview();
    applyDropdownAnimationToPreview();
    syncChevronUI();
    applyAdvancedPreview();
    ensurePreviewDropdownVisible();

    // Theme mode change
    themeModeSelect?.addEventListener("change", () => {
        if (!previewEnabled()) return;
        applyThemeModeToPreview();
    });

});
</script>