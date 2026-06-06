<?php
declare(strict_types=1);

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



require_once BASE_PATH . '/system/core/init.php';
require_once BASE_PATH . '/system/core/builder_live.php';

use nexpell\AccessControl;
#AccessControl::checkAdminAccess('ac_plugin_widgets_setting');


// --- Builder-Modus aktiv? ---
$isBuilder = (isset($_GET['builder']) && $_GET['builder'] === '1');

if ($isBuilder) {
    // Die Methode ruft selbst exit() auf, wenn der Zugriff fehlt.
    // Wenn der Benutzer Rechte hat, läuft der Code einfach weiter.
    AccessControl::checkAdminAccess('ac_plugin_widgets_setting');
}


// Seite bestimmen
$pageSlug = $_GET['site'] ?? 'index';
$widgetsByPosition = nxb_prepare_builder($pageSlug);

// Builder-Modus aktiv?
$isBuilder = (isset($_GET['builder']) && $_GET['builder'] === '1');

// Header laden
require_once 'header.php';
?>

<?php if ($isBuilder): ?>
<!-- === Builder-Kennzeichnung (ohne Layout-Änderung) === -->
<script>
// === Zonen-Restriktions-Logik / Builder-Flag START ===
// Klasse für CSS-Markierungen sicher setzen (falls Header <body>-Klassen anders setzt)
(function(){
  const add = cls => { try { document.documentElement.classList.add(cls); } catch(e){} try { document.body && document.body.classList.add(cls); } catch(e){} };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => add('builder-active'));
  } else {
    add('builder-active');
  }
})();
</script>
<!-- === Zonen-Restriktions-Logik / Builder-Flag ENDE === -->
<?php endif; ?>

<?php /*if (!empty($_SESSION['userID'])): ?>
<div class="card mt-3 border-warning">
    <div class="card-header bg-warning text-dark fw-bold">
        🔐 Deine Login- & Rollenrechte
    </div>
    <div class="card-body small">
        <ul class="mb-0">
            <li><strong>UserID:</strong> <?= (int)$_SESSION['userID'] ?></li>
            <li><strong>Username:</strong> <?= htmlspecialchars($_SESSION['username'] ?? '') ?></li>
            <li><strong>roleID (höchste):</strong> <?= $_SESSION['roleID'] ?? '–' ?></li>
            <li><strong>userrole (Text):</strong> <?= $_SESSION['userrole'] ?? '–' ?></li>

            <hr class="my-2">

            <li><strong>Alle Rollen (IDs):</strong>
                <?= !empty($_SESSION['roles']) ? implode(', ', $_SESSION['roles']) : 'keine' ?>
            </li>

            <li><strong>Alle Rollen (Namen):</strong>
                <?= !empty($_SESSION['role_names']) ? implode(', ', $_SESSION['role_names']) : 'keine' ?>
            </li>

            <hr class="my-2">

            <?php
            $flags = [
                'is_admin','is_coadmin','is_leader','is_coleader','is_squadleader',
                'is_warorg','is_moderator','is_editor','is_member','is_trial',
                'is_guest','is_registered','is_honor','is_streamer',
                'is_designer','is_technician'
            ];
            foreach ($flags as $flag):
                if (!empty($_SESSION[$flag])):
            ?>
                <li class="text-success">✅ <?= $flag ?></li>
            <?php endif; endforeach; ?>
        </ul>
    </div>
</div>
<?php endif;*/ ?>


  <!-- === UnderTop Widgets === -->
  <?php if ($isBuilder || !empty($widgetsByPosition['undertop'])): ?>
  <div class="nx-live-zone nx-zone" data-nx-zone="undertop">
    <?php if (!empty($widgetsByPosition['undertop'])): ?>
      <?php foreach ($widgetsByPosition['undertop'] as $widget) echo $widget; ?>
    <?php elseif ($isBuilder): ?>
      <div class="builder-placeholder">[Leere Zone: undertop]</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <main class="flex-fill">
    <div class="container">
      <div class="row g-3 builder-row-fix"><!-- Fix: Spalten bleiben nebeneinander -->

        <?php
        // Zonen prüfen
        $hasLeft  = !empty($widgetsByPosition['left']);
        $hasRight = !empty($widgetsByPosition['right']);

        // Spalten-Klassen bestimmen
        if ($isBuilder) {
            $leftClass  = 'col-md-3';
            $mainClass  = 'col-md-6';
            $rightClass = 'col-md-3';
        } else {
            if ($hasLeft && $hasRight) {
                $leftClass  = 'col-md-3';
                $mainClass  = 'col-md-6';
                $rightClass = 'col-md-3';
            } elseif ($hasLeft xor $hasRight) {
                $leftClass  = $hasLeft  ? 'col-md-3' : 'd-none';
                $rightClass = $hasRight ? 'col-md-3' : 'd-none';
                $mainClass  = 'col-md-9';
            } else {
                $leftClass  = 'd-none';
                $rightClass = 'd-none';
                $mainClass  = 'col-12';
            }
        }
        ?>

        <!-- LEFT -->
        <div class="<?= $leftClass ?> nx-live-zone nx-zone" data-nx-zone="left">
          <?php
          if ($hasLeft) {
              foreach ($widgetsByPosition['left'] as $w) echo $w;
          } elseif ($isBuilder) {
              echo '<div class="builder-placeholder">[Leere Zone: left]</div>';
          }
          ?>
        </div>

        <!-- MAIN -->
        <div class="<?= $mainClass ?> nx-live-zone nx-zone" data-nx-zone="main">

          <!-- MainTop -->
          <?php if ($isBuilder || !empty($widgetsByPosition['maintop'])): ?>
          <div class="nx-live-zone nx-zone" data-nx-zone="maintop">
            <?php if (!empty($widgetsByPosition['maintop'])): ?>
              <div class="row">
                <?php foreach ($widgetsByPosition['maintop'] as $w) echo $w; ?>
              </div>
            <?php elseif ($isBuilder): ?>
              <div class="builder-placeholder">[Leere Zone: maintop]</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Hauptinhalt -->
          <?= get_mainContent(); ?>

          <!-- MainBottom -->
          <?php if ($isBuilder || !empty($widgetsByPosition['mainbottom'])): ?>
          <div class="nx-live-zone nx-zone" data-nx-zone="mainbottom">
            <?php if (!empty($widgetsByPosition['mainbottom'])): ?>
              <div class="row">
                <?php foreach ($widgetsByPosition['mainbottom'] as $w) echo $w; ?>
              </div>
            <?php elseif ($isBuilder): ?>
              <div class="builder-placeholder">[Leere Zone: mainbottom]</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

        </div>

        <!-- RIGHT -->
        <div class="<?= $rightClass ?> nx-live-zone nx-zone" data-nx-zone="right">
          <?php
          if ($hasRight) {
              foreach ($widgetsByPosition['right'] as $w) echo $w;
          } elseif ($isBuilder) {
              echo '<div class="builder-placeholder">[Leere Zone: right]</div>';
          }
          ?>
        </div>

      </div>
    </div>
  </main>

  <!-- === Bottom Widgets === -->
  <?php if ($isBuilder || !empty($widgetsByPosition['bottom'])): ?>
  <div class="nx-live-zone nx-zone" data-nx-zone="bottom">
    <?php if (!empty($widgetsByPosition['bottom'])): ?>
      <div class="row">
        <?php foreach ($widgetsByPosition['bottom'] as $w) echo $w; ?>
      </div>
    <?php elseif ($isBuilder): ?>
      <div class="builder-placeholder">[Leere Zone: bottom]</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php require_once 'footer.php'; ?>


<!-- === BUILDER LAYOUT FIX & STYLES === -->
<style>

 /* --- Spaltenhöhe angleichen --- */
.builder-row-fix {
  display: flex;
  align-items: stretch; /* alle Spalten gleich hoch */
}

.builder-row-fix > [data-nx-zone="left"],
.builder-row-fix > [data-nx-zone="main"],
.builder-row-fix > [data-nx-zone="right"] {
  display: flex;
  flex-direction: column;
}
 
.builder-placeholder {
  border: 1px dashed #bbb;
  border-radius: 6px;
  min-height: 80px;
  padding: 0.75rem;
  margin-bottom: 0.75rem;
  text-align: center;
  font-size: 0.85rem;
  color: #555;
  background: #fafafa;
}

.nx-live-zone { min-height: 60px; }

/* Fix: Spalten im Builder bleiben nebeneinander */
body.builder-active .builder-row-fix {
  display: flex !important;
  flex-wrap: nowrap !important;
  align-items: flex-start;
}
body.builder-active .builder-row-fix > .nx-live-zone {
  flex: 1 1 auto !important;
  max-width: none !important;
}

/* Farbmarkierungen für Builder */
body.builder-active [data-nx-zone="top"]         { background-color: rgba(186,85,211,0.08); }
body.builder-active [data-nx-zone="undertop"]    { background-color: rgba(0,0,0,0.03); }
body.builder-active [data-nx-zone="left"]        { background-color: rgba(0,120,255,0.06); }
body.builder-active [data-nx-zone="main"],
body.builder-active [data-nx-zone="maintop"],
body.builder-active [data-nx-zone="mainbottom"] { background-color: rgba(0,255,0,0.05); }
body.builder-active [data-nx-zone="right"]       { background-color: rgba(255,165,0,0.08); }
body.builder-active [data-nx-zone="bottom"]      { background-color: rgba(200,200,200,0.05); }

/* Labels in jeder Zone */
body.builder-active .nx-zone {
  position: relative;
}
body.builder-active .nx-zone::before {
  content: attr(data-nx-zone);
  position: absolute;
  top: 0;
  left: 4px;
  font-size: 11px;
  color: #555;
  background: rgba(255,255,255,0.8);
  padding: 1px 4px;
  border-radius: 3px;
  z-index: 99;
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const mainZone = document.querySelector('[data-nx-zone="main"]');
  const leftZone = document.querySelector('[data-nx-zone="left"]');
  const rightZone = document.querySelector('[data-nx-zone="right"]');

  if (mainZone && (leftZone || rightZone)) {
    const mainHeight = mainZone.offsetHeight;
    if (leftZone) leftZone.style.minHeight = mainHeight + "px";
    if (rightZone) rightZone.style.minHeight = mainHeight + "px";
  }
});
</script>
