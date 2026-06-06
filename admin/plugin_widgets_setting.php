<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action = $_GET['action'] ?? '';

// Seitenliste aufbauen
$pages = ['index' => 'Startseite'];
$res = safe_query("SELECT modulname FROM settings_plugins ORDER BY modulname ASC");
$exclude = ['navigation','carousel','error_404','footer','login','register','lostpassword','profile','edit_profile','lastlogin'];
$currentLang = strtolower((string)$languageService->detectLanguage());
while ($row = mysqli_fetch_assoc($res)) {
  $module = (string)($row['modulname'] ?? '');
  if ($module === '' || in_array($module, $exclude, true)) {
    continue;
  }

  $name = $module;
  $candidates = [$module];

  foreach (array_unique($candidates) as $candidate) {
    $candidateEsc = escape($candidate);
    $nameRes = safe_query("SELECT content FROM settings_plugins_lang WHERE content_key = 'plugin_name_" . $candidateEsc . "' AND language = '" . escape($currentLang) . "' LIMIT 1");
    if ($nameRes && mysqli_num_rows($nameRes) > 0) {
      $nameRow = mysqli_fetch_assoc($nameRes);
      $translated = trim((string)($nameRow['content'] ?? ''));
      if ($translated !== '') {
        $name = $translated;
        break;
      }
    }
  }

  $pages[$module] = $name;
}

// Zonen-Restriktions-Logik START
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
// Zonen-Restriktions-Logik ENDE

// LIST- UND BEARBEITUNGSMODUS
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

    // POST speichern
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_widget'])) {
        if (isset($_POST['csrf_token'])) { if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) nx_redirect('admincenter.php?site=plugin_widgets_setting', 'danger', 'alert_transaction_invalid', false); }

        $widget_key = trim((string)($_POST['widget_key'] ?? ''));
        if ($widget_key === '') nx_redirect('admincenter.php?site=plugin_widgets_setting', 'warning', 'alert_widget_key_missing', false);

        $allowed_str = nxb_normalize_allowed_zones($_POST['allowed_zones'] ?? null);
        $ekey = escape($widget_key);
        $eallow = escape($allowed_str);

        safe_query("
            UPDATE settings_widgets
            SET allowed_zones = '$eallow'
            WHERE widget_key = '$ekey'
            LIMIT 1
        ");
        nx_audit_action('plugin_widgets_setting', 'audit_action_widget_zones_updated', $widget_key, null, 'admincenter.php?site=plugin_widgets_setting&action=edit&edit=' . urlencode($widget_key));
        nx_redirect('admincenter.php?site=plugin_widgets_setting&action=edit&edit=' . urlencode($widget_key), 'success', 'alert_zones_updated', false);
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
        // Formular zur Bearbeitung
        echo '<div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-header">
                  <div class="card-title">
                    <i class="bi bi-journal-text"></i> <span>' . $languageService->get('page_title') . '</span>
                    <small class="text-muted">' . $languageService->get('edit') . '</small>
                  </div>
                </div>

          <div class="card-body">
            <form method="post" action="admincenter.php?site=plugin_widgets_setting">
              <input type="hidden" name="csrf_token" value="' . htmlspecialchars($CSRF) . '">
              <input type="hidden" name="widget_key" value="' . htmlspecialchars($edit_data['widget_key']) . '">

              <div class="row mb-3"> 
                <div class="col-md-6"> 
                  <label class="form-label fw-semibold">' . $languageService->get('widget_key') . '</label> 
                  <input type="text" value="' . htmlspecialchars($edit_data['widget_key']) . '" class="form-control" readonly> 
                </div> 
                <div class="col-md-6"> 
                  <label class="form-label fw-semibold">' . $languageService->get('title') . '</label> 
                  <input type="text" value="' . htmlspecialchars($edit_data['title']) . '" class="form-control" readonly> 
                </div> 
              </div> 

              <div class="row mb-3"> 
                <div class="col-md-6"> 
                  <label class="form-label fw-semibold">' . $languageService->get('plugin') . '</label> 
                  <input type="text" value="' . htmlspecialchars($edit_data['plugin']) . '" class="form-control" readonly> 
                </div> 
                <div class="col-md-6"> 
                  <label class="form-label fw-semibold">' . $languageService->get('modulname') . '</label> 
                  <input type="text" value="' . htmlspecialchars($edit_data['modulname']) . '" class="form-control" readonly> 
                </div> 
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">' . $languageService->get('allowed_zones') . '</label>

                <div class="alert alert-info d-flex align-items-center gap-3 py-2" role="alert">
                  <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
                  <div>' . $languageService->get('info_changes') . '</div>
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
                <button type="submit" name="save_widget" class="btn btn-primary">
                  ' . $languageService->get('save') . '
                </button>
              </div>
            </form>
            </div>
        </div>';
      }elseif (($action ?? '') === 'list') {
        // Ãœbersicht
        echo '<div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-header">
                  <div class="card-title">
                    <i class="bi bi-journal-text"></i> <span>' . $languageService->get('page_title') . '</span>
                    <small class="text-muted">' . $languageService->get('widget_list') . '</small>
                  </div>
                </div>
          <div class="card-body">';
        echo '<div class="d-flex flex-wrap justify-content-end align-items-center gap-3 mb-3">
                <div class="input-group input-group-sm" style="min-width: 260px; max-width: 360px;">
                  <span class="input-group-text"><i class="bi bi-search"></i></span>
                  <input id="widgetSearch" type="search" class="form-control" placeholder="' . $languageService->get('search') . '">
                </div>
              </div>';

        $res = safe_query("SELECT widget_key, title, plugin, modulname, allowed_zones FROM settings_widgets ORDER BY widget_key ASC");

        echo '
              <table class="table" id="widgetTable">
                <thead>
                  <tr>
                    <th>' . $languageService->get('widget_key') . '</th>
                    <th>' . $languageService->get('title') . '</th>
                    <th>' . $languageService->get('plugin') . '</th>
                    <th>' . $languageService->get('modulname') . '</th>
                    <th>' . $languageService->get('allowed_zones') . '</th>
                    <th>' . $languageService->get('actions') . '</th>
                  </tr>
                </thead>
                <tbody>';

        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
              $zones = trim((string)($row['allowed_zones'] ?? ''));

              if ($zones === '') {
                  $zonesLabel = '<span class="badge bg-secondary">' . $languageService->get('all') . '</span>';
              } else {
                  $zoneList = array_map('trim', explode(',', $zones));
                  $zoneList = array_values(array_unique(array_filter($zoneList)));

                  $badges = [];
                  foreach ($zoneList as $z) {
                      $badges[] = '<span class="badge bg-info me-1">'
                          . htmlspecialchars($z, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                          . '</span>';
                  }

                  $zonesLabel = implode('', $badges);
              }
                echo '<tr>
                  <td><code>' . htmlspecialchars($row['widget_key']) . '</code></td>
                  <td>' . htmlspecialchars($row['title'] ?? '') . '</td>
                  <td>' . htmlspecialchars($row['plugin'] ?? '') . '</td>
                  <td>' . htmlspecialchars($row['modulname'] ?? '') . '</td>
                  <td>' . $zonesLabel . '</td>
                  <td>
                    <a href="admincenter.php?site=plugin_widgets_setting&action=edit&edit=' . urlencode($row['widget_key']) . '" class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto">
                      <i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '
                    </a>
                  </td>
                </tr>';
            }
        } else {
            echo '<tr><td colspan="6" class="text-center text-muted py-4">' . $languageService->get('no_widgets_found') . '</td></tr>';
        }

        echo '</tbody></table>
            <script>
            document.addEventListener("DOMContentLoaded", function () {
                var input = document.getElementById("widgetSearch");
                if (!input) return;

                function applyFilter() {
                    var q = (input.value || "").toLowerCase().trim();
                    var rows = document.querySelectorAll("#widgetTable tbody tr");

                    for (var i = 0; i < rows.length; i++) {
                        var row = rows[i];
                        var txt = (row.textContent || "").toLowerCase();
                        var show = (!q || txt.indexOf(q) !== -1);
                        row.style.display = show ? "table-row" : "none";
                    }
                }

                input.addEventListener("input", applyFilter);
                applyFilter();
            });
            </script>
        </div></div>
          <div class="card-footer small text-muted">
            ' . $languageService->get('empty_code') . '
          </div>';
    #}

    #echo '</div></div></div>';

} else {
?>
<!-- BUILDER-VORSCHAU / LIVE-UI -->
<div class="card shadow-sm border-0 mb-4 mt-4">
  <div class="card-header">
    <div class="card-title">
      <i class="bi bi-journal-text"></i> <span><?=$languageService->get('page_title') ?></span>
      <small class="text-muted"><span><?=$languageService->get('overview') ?></small>
    </div>
    <a href="admincenter.php?site=plugin_widgets_setting&action=list" class="btn btn-secondary mt-2"><span><?=$languageService->get('widget_list') ?></a>
  </div>

  <div class="d-flex flex-wrap gap-3 align-items-center p-2 border-bottom">
    <span class="fw-semibold"><?=$languageService->get('widget_manage') ?></span>
    <div class="d-flex align-items-center gap-2">
      <label for="page" class="form-label mb-0"><?=$languageService->get('site') ?>:</label>
      <select id="page" class="form-select form-select-sm" style="max-width:260px">
        <?php foreach ($pages as $v=>$label): ?>
          <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="d-flex align-items-center gap-2">
      <label class="form-label mb-0"><?=$languageService->get('modus') ?>:</label>
      <div class="btn-group btn-group-sm w-100 gapped" role="group">
        <input type="radio" class="btn-check" name="builderMode" id="modeLive" value="live" checked>
        <label class="btn btn-outline-primary flex-fill text-center" for="modeLive"><?=$languageService->get('live') ?></label>

        <input type="radio" class="btn-check" name="builderMode" id="modePreview" value="preview">
        <label class="btn btn-outline-primary flex-fill text-center" for="modePreview"><?=$languageService->get('preview') ?></label>
      </div>
    </div>

    <button class="btn btn-secondary ms-auto me-4" id="btn-reload"><?=$languageService->get('reload') ?></button>
  </div>

  <div class="card-body p-0">
    <iframe id="previewFrame" src="about:blank" title="Vorschau / Builder"></iframe>
  </div>

  <div class="card-footer small text-muted">
    <ul class="mb-0">
      <li><span class="fw-semibold"><?=$languageService->get('live') ?>:</span> <?=$languageService->get('live_info') ?></li>
      <li><span class="fw-semibold"><?=$languageService->get('preview') ?>:</span> <?=$languageService->get('preview_info') ?></li>
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

// Zonen-Restriktions-Logik
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
}
?>

