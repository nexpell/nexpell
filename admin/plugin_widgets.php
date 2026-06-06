<?php
// === /admin/plugin_widgets_setting.php ===
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$CURRENT_LANG = $_SESSION['language'] ?? 'de';
$action = $_GET['action'] ?? '';

// Seitenliste aufbauen
$pages = ['index' => 'Startseite'];
$res = safe_query("SELECT modulname, name FROM settings_plugins ORDER BY name ASC");
$exclude = ['navigation','carousel','error_404','footer_easy','login','register','lostpassword','profile','edit_profile','lastlogin'];
while ($row = mysqli_fetch_assoc($res)) {
  if (!in_array($row['modulname'], $exclude, true)) {
    $pages[$row['modulname']] = $row['name'];
  }
}

/* === Zonen-Restriktions-Logik START === */
if (!function_exists('nx__load_widget_restrictions_map')) {
  function nx__load_widget_restrictions_map(): array {
    $map = [];
    $r = safe_query("SELECT widget_key, allowed_zones FROM settings_widgets");
    if ($r && mysqli_num_rows($r)) {
      while ($w = mysqli_fetch_assoc($r)) {
        $zones = array_filter(array_map('trim', explode(',', (string)($w['allowed_zones'] ?? ''))));
        $map[$w['widget_key']] = array_values($zones);
      }
    }
    return $map;
  }
}
$__WIDGET_RESTRICTIONS = nx__load_widget_restrictions_map();
/* === Zonen-Restriktions-Logik ENDE === */


// ============================================================================
// LIST- UND BEARBEITUNGSMODUS
// ============================================================================


    function nxb_redirect_back(string $msg = '', int $delay = 0): void {
        if (function_exists('redirect')) {
            redirect('admincenter.php?site=plugin_widgets_list', $msg, $delay);
            exit;
        }
        header('Location: admincenter.php?site=plugin_widgets_list');
        exit;
    }

    function nxb_normalize_allowed_zones(?array $zones): string {
        $ALL = ['top','undertop','left','maintop','mainbottom','right','bottom'];
        if (empty($zones)) return '';
        $in = array_map('trim', $zones);
        $in = array_values(array_unique(array_filter($in, fn($z) => in_array($z, $ALL, true))));
        $ordered = [];
        foreach ($ALL as $z) if (in_array($z, $in, true)) $ordered[] = $z;
        return implode(',', $ordered);
    }

    if (empty($_GET['action']) && isset($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $qs);
        $_GET = array_merge($_GET, $qs);
    }

    $action   = $_GET['action'] ?? '';
    $edit_key = $_GET['edit'] ?? '';

    // === POST speichern ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_widget'])) {
        if (isset($_POST['csrf_token'])) {
            if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                nxb_redirect_back('Ungültiges CSRF-Token.');
            }
        }

        $widget_key = trim($_POST['widget_key'] ?? '');
        if ($widget_key === '') {
            nxb_redirect_back('Kein Widget-Key angegeben.');
        }

        $allowed_str = nxb_normalize_allowed_zones($_POST['allowed_zones'] ?? null);
        $ekey = escape($widget_key);
        $eallow = escape($allowed_str);

        safe_query("
            UPDATE settings_widgets
            SET allowed_zones = '$eallow'
            WHERE widget_key = '$ekey'
            LIMIT 1
        ");

        nxb_redirect_back('Zonen aktualisiert.');
        exit;
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION['csrf_token'];

    $edit_data = [
        'widget_key' => '',
        'title' => '',
        'plugin' => '',
        'modulname' => '',
        'allowed_zones' => ''
    ];

    if ($edit_key !== '') {
        $res = safe_query("SELECT * FROM settings_widgets WHERE widget_key='" . escape($edit_key) . "' LIMIT 1");
        if ($res && mysqli_num_rows($res) === 1) {
            $edit_data = mysqli_fetch_assoc($res);
            $action = 'edit';
        }
    }

    

    if ($action === 'edit' && $edit_key !== '') {
        // === Formular zur Bearbeitung ===
        echo '<div class="card">
          <div class="card-header">
            <i class="bi bi-journal-text"></i> Widget bearbeiten
          </div>

          <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
              <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_widgets">Widgets verwalten</a></li>
              <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_widgets&action=list">Widget Liste</a></li>
              <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
            </ol>
          </nav>

          <div class="card-body">
          <div class="container py-5">
            <form method="post" action="admincenter.php?site=plugin_widgets_list">
              <input type="hidden" name="csrf_token" value="' . htmlspecialchars($CSRF) . '">
              <input type="hidden" name="widget_key" value="' . htmlspecialchars($edit_data['widget_key']) . '">

              <div class="row mb-3"> 
                <div class="col-md-6"> 
                  <label class="form-label fw-semibold">Widget Key</label> 
                  <input type="text" value="' . htmlspecialchars($edit_data['widget_key']) . '" class="form-control" readonly> 
                </div> 
                <div class="col-md-6"> 
                  <label class="form-label fw-semibold">Titel</label> 
                  <input type="text" value="' . htmlspecialchars($edit_data['title']) . '" class="form-control" readonly> 
                </div> 
              </div> 

              <div class="row mb-3"> 
                <div class="col-md-6"> 
                  <label class="form-label fw-semibold">Plugin</label> 
                  <input type="text" value="' . htmlspecialchars($edit_data['plugin']) . '" class="form-control" readonly> 
                </div> 
                <div class="col-md-6"> 
                  <label class="form-label fw-semibold">Modulname</label> 
                  <input type="text" value="' . htmlspecialchars($edit_data['modulname']) . '" class="form-control" readonly> 
                </div> 
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Erlaubte Zonen</label>

                <div class="alert alert-info d-flex align-items-center gap-3 py-2" role="alert">
                  <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
                  <div><strong>Hinweis:</strong> Änderungen auf eigene Gefahr – falsche Einstellungen können das Layout beschädigen.</div>
                </div>

                <div class="d-flex flex-wrap gap-3">';
        $zones = ['top','undertop','left','maintop','mainbottom','right','bottom'];
        $allowed = explode(',', (string)$edit_data['allowed_zones']);
        foreach ($zones as $z) {
            $checked = in_array($z, $allowed, true) ? 'checked' : '';
            echo '<div class="form-check">
                    <input class="form-check-input" type="checkbox" name="allowed_zones[]" value="' . $z . '" id="z_' . $z . '" ' . $checked . '>
                    <label class="form-check-label" for="z_' . $z . '">' . ucfirst($z) . '</label>
                  </div>';
        }
        echo '</div>
              </div>

              <div class="d-flex justify-content-between align-items-center">
                <button type="submit" name="save_widget" class="btn btn-success">
                  <i class="bi bi-save"></i> Änderungen speichern
                </button>
                <a href="admincenter.php?site=plugin_widgets&action=list" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-left"></i> Zurück
                </a>
              </div>
            </form>
            </div>
          </div>
        </div>';
}elseif (($action ?? '') === 'list') {
        // === Übersicht ===
        echo '<div class="card">
          <div class="card-header"><i class="bi bi-journal-text"></i> Widget Übersicht</div>

          <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
              <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_widgets">Widgets verwalten</a></li>
              <li class="breadcrumb-item active" aria-current="page">Widgets – Übersicht</li>
            </ol>
          </nav>

          <div class="card-body">';

        $res = safe_query("SELECT widget_key, title, plugin, modulname, allowed_zones FROM settings_widgets ORDER BY widget_key ASC");

        echo '
            <div class="container py-5">
              <table class="table table-bordered table-striped">
                <thead class="table-light">
                  <tr>
                    <th>Widget Key</th>
                    <th>Titel</th>
                    <th>Plugin</th>
                    <th>Modulname</th>
                    <th>Erlaubte Zonen</th>
                    <th>Aktion</th>
                  </tr>
                </thead>
                <tbody>';

        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $zones = trim((string)($row['allowed_zones'] ?? ''));
                $zonesLabel = $zones === ''
                    ? '<span class="badge bg-secondary">alle</span>'
                    : htmlspecialchars($zones, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo '<tr>
                  <td><code>' . htmlspecialchars($row['widget_key']) . '</code></td>
                  <td>' . htmlspecialchars($row['title'] ?? '') . '</td>
                  <td>' . htmlspecialchars($row['plugin'] ?? '') . '</td>
                  <td>' . htmlspecialchars($row['modulname'] ?? '') . '</td>
                  <td>' . $zonesLabel . '</td>
                  <td>
                    <a href="admincenter.php?site=plugin_widgets&action=edit&edit=' . urlencode($row['widget_key']) . '" class="btn btn-sm btn-primary">
                      <i class="bi bi-pencil-square"></i> Bearbeiten
                    </a>
                  </td>
                </tr>';
            }
        } else {
            echo '<tr><td colspan="6" class="text-center text-muted py-4">Keine Widgets vorhanden.</td></tr>';
        }

        echo '</tbody></table></div></div>
          <div class="card-footer small text-muted">
            Leerer <code>allowed_zones</code>-Wert bedeutet: in allen Zonen erlaubt.
          </div></div>';
    #}

    #echo '</div></div></div>';

} else {
?>
<!-- ======================================================================== -->
<!-- BUILDER-VORSCHAU / LIVE-UI -->
<!-- ======================================================================== -->

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div><i class="bi bi-journal-text"></i> Widgets verwalten</div>
    <div>
      <a href="admincenter.php?site=plugin_widgets&action=list" class="btn btn-success"><i class="bi bi-plus"></i> Widget Liste</a>
    </div>
  </div>

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb t-5 p-2 bg-light">
      <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_widgets">Widgets verwalten</a></li>
      <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
    </ol>
  </nav>

  <div class="d-flex flex-wrap gap-3 align-items-center p-2 border-bottom">
    <strong>Widgets verwalten</strong>
    <div class="d-flex align-items-center gap-2">
      <label for="page" class="form-label mb-0">Seite:</label>
      <select id="page" class="form-select form-select-sm" style="max-width:260px">
        <?php foreach ($pages as $v=>$label): ?>
          <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="d-flex align-items-center gap-2">
      <label class="form-label mb-0">Modus:</label>
      <div class="btn-group btn-group-sm w-100 gapped" role="group">
        <input type="radio" class="btn-check" name="builderMode" id="modeLive" value="live" checked>
        <label class="btn btn-outline-primary flex-fill text-center" for="modeLive">Live</label>

        <input type="radio" class="btn-check" name="builderMode" id="modePreview" value="preview">
        <label class="btn btn-outline-primary flex-fill text-center" for="modePreview">Preview</label>
      </div>
    </div>

    <button class="btn btn-sm btn-outline-secondary ms-auto" id="btn-reload">Neu laden</button>
  </div>

  <div class="card-body p-0">
    <iframe id="previewFrame" src="about:blank" title="Vorschau / Builder"></iframe>
  </div>

  <div class="card-footer small text-muted">
    <ul class="mb-0">
      <li><strong>Live:</strong> lädt die echte Seite mit <code>?builder=1</code>.</li>
      <li><strong>Preview:</strong> lädt <code>/plugin_widgets_preview.php?page=…</code> (Sandbox mit Drop-Zonen).</li>
    </ul>
  </div>
</div>

<script>
const LANG  = <?= json_encode($_SESSION['language'] ?? 'de') ?>;
const pageEl   = document.getElementById('page');
const frameEl  = document.getElementById('previewFrame');
const reloadEl = document.getElementById('btn-reload');

function getMode(){
  const el = document.querySelector('input[name="builderMode"]:checked');
  return el ? el.value : 'live';
}

function buildLiveUrl(page){
  const base = (page === 'index')
    ? `/${encodeURIComponent(LANG)}`
    : `/${encodeURIComponent(LANG)}/${encodeURIComponent(page)}`;
  const sep = base.includes('?') ? '&' : '?';
  return `${base}${sep}builder=1&_=${Date.now()}`;
}

function buildPreviewUrl(page){
  const url = `/admin/plugin_widgets_preview.php?page=${encodeURIComponent(page)}&_=${Date.now()}`;
  console.log('Preview URL:', url);
  return url;
}

function updateFrameSrc(){
  const page = pageEl.value || 'index';
  const mode = getMode();
  frameEl.src = (mode === 'preview') ? buildPreviewUrl(page) : buildLiveUrl(page);
}

// === Zonen-Restriktions-Logik ===
window.widgetRestrictionsParent = <?= json_encode($__WIDGET_RESTRICTIONS, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

function postRestrictionsToFrame() {
  try {
    const data = { type: 'nx:widgetRestrictions', payload: window.widgetRestrictionsParent || {} };
    frameEl.contentWindow?.postMessage(data, '*');
  } catch (e) {
    console.warn('widgetRestrictions postMessage failed:', e);
  }
}

window.addEventListener('message', (ev) => {
  if (ev?.data && ev.data.type === 'nx:requestWidgetRestrictions') postRestrictionsToFrame();
});

frameEl.addEventListener('load', () => postRestrictionsToFrame());

pageEl.addEventListener('change', updateFrameSrc);
document.querySelectorAll('input[name="builderMode"]').forEach(el => el.addEventListener('change', updateFrameSrc));
reloadEl.addEventListener('click', updateFrameSrc);

pageEl.value = 'index';
document.getElementById('modePreview').checked = true;
updateFrameSrc();
</script>

<style>
#previewFrame{width:100%;height:128vh;border:0;background:#fff;}
.btn-group.gapped{gap:.5rem;}
.btn-group.gapped>.btn{margin-left:0!important;}
.btn-group.gapped>.btn{display:inline-flex;align-items:center;justify-content:center;}
.btn-group.gapped .btn-outline-primary{
  --nx-orange:#fe821d;
  color:var(--nx-orange);
  border-color:var(--nx-orange);
}
.btn-group.gapped .btn-outline-primary:hover,
.btn-group.gapped .btn-check:checked+ .btn-outline-primary,
.btn-group.gapped .btn-outline-primary.active{
  color:#fff;background-color:var(--nx-orange);border-color:var(--nx-orange);
}
.btn-group.gapped .btn-outline-primary:focus,
.btn-group.gapped .btn-check:focus+ .btn-outline-primary{
  box-shadow:0 0 0 .25rem rgba(254,130,29,.25);
}
</style>
<?php
} // Ende else
?>
