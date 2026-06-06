<?php
// === /admin/plugin_widgets_preview.php ===
declare(strict_types=1);

// Basis & DB
if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__)); // /admin → eine Ebene höher → /nexpell
}
require_once BASE_PATH . '/system/core/builder_live.php';

// === Seite bestimmen ===
$page = trim($_GET['page'] ?? 'index');
if ($page === '' || strtolower($page) === 'startseite') {
  $page = 'index';
}
$page = preg_replace('/[^a-z0-9_-]/i', '', $page);

// Widgets & Positionen laden
$available = nx_load_available_widgets();
$assigned  = nx_load_widgets_for_page($page);

// Ziel-Endpoint + Live-Link
$SAVE_ENDPOINT = '/admin/plugin_widgets_save.php';
$liveHref = '/?site=' . urlencode($page) . '&builder=1';
?>
<!doctype html>
<html lang="de" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Widget Preview – <?= htmlspecialchars($page) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<style>
body{background:#fff}
.widget-list{min-height:46px;border:1px dashed #bbb;border-radius:.5rem;background:#fff;padding:6px;list-style:none;margin:0}
.widget-item{position:relative;margin:6px;padding:8px 38px 8px 30px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:.4rem;user-select:none}
.widget-handle{position:absolute;left:8px;top:8px;cursor:grab;opacity:.75}
.widget-actions{position:absolute;right:6px;top:6px;display:flex;gap:.25rem}
.widget-actions .btn{ --bs-btn-padding-y:.1rem; --bs-btn-padding-x:.35rem; --bs-btn-font-size:.75rem; }
.ghost{opacity:.5}
.bg-zone{background:#f6f8fa}
.nx-zone{position:relative;min-height:80px}
.nx-zone-allowed   { outline:2px dashed #28a745 !important; background:rgba(40,167,69,.10) !important; }
.nx-zone-forbidden { outline:2px dashed #dc3545 !important; background:rgba(220,53,69,.10) !important; cursor:not-allowed !important; }
</style>
</head>

<body>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-end mb-2 gap-2">
    <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= htmlspecialchars($liveHref) ?>">
      <i class="bi bi-box-arrow-up-right"></i> Live bearbeiten
    </a>
    <button type="button" id="nx-save-all" class="btn btn-sm btn-light"><i class="bi bi-save"></i> Speichern</button>
  </div>

  <div class="row g-3">
    <!-- Palette -->
    <aside class="col-lg-3">
      <div class="p-3 border rounded bg-zone">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0"><i class="bi bi-box-seam"></i> Available Widgets</h6>
          <span class="badge text-bg-light">Drag & Clone</span>
        </div>
        <ul id="available" class="widget-list">
          <?php if(!$available): ?>
            <li class="text-muted" style="list-style:none">(leer)</li>
          <?php else: foreach($available as $w): ?>
            <li class="widget-item"
                data-id="<?= htmlspecialchars($w['widget_key']) ?>"
                data-title="<?= htmlspecialchars($w['title']) ?>"
                data-allowed="<?= htmlspecialchars($w['allowed_zones'] ?? '') ?>">
              <span class="widget-handle">⋮⋮</span>
              <span class="widget-title"><?= htmlspecialchars($w['title']) ?></span>
            </li>
          <?php endforeach; endif; ?>
        </ul>
        <div class="small text-secondary mt-2">Ziehen in eine Zone. Bereits zugewiesene Widgets erscheinen hier nicht.</div>
      </div>
    </aside>

    <!-- Zonen -->
    <section class="col-lg-9">
      <!-- Header -->
      <div class="mb-3 p-3 border rounded bg-zone">
        <h6 class="mb-2">Header</h6>
        <ul class="widget-list nx-zone" data-pos="top">
          <?php foreach(($assigned['top']??[]) as $w): ?>
            <li class="widget-item"
                data-id="<?= htmlspecialchars($w['widget_key']) ?>"
                data-iid="<?= htmlspecialchars($w['instance_id']) ?>"
                data-title="<?= htmlspecialchars($w['title']) ?>"
                data-settings='<?= htmlspecialchars(json_encode($w['settings'], JSON_UNESCAPED_UNICODE)) ?>'
                data-allowed="<?= htmlspecialchars($w['allowed_zones'] ?? '') ?>">
              <span class="widget-handle">⋮⋮</span><span class="widget-title"><?= htmlspecialchars($w['title']) ?></span>
              <span class="widget-actions">
                <button type="button" class="btn btn-light btn-sm btn-settings" title="Einstellungen"><i class="bi bi-sliders"></i></button>
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-x-lg"></i></button>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Content Header -->
      <div class="mb-3 p-3 border rounded bg-zone">
        <h6 class="mb-2">Content Header</h6>
        <ul class="widget-list nx-zone" data-pos="undertop">
          <?php foreach(($assigned['undertop']??[]) as $w): ?>
            <li class="widget-item" data-id="<?= htmlspecialchars($w['widget_key']) ?>" data-iid="<?= htmlspecialchars($w['instance_id']) ?>" data-title="<?= htmlspecialchars($w['title']) ?>" data-settings='<?= htmlspecialchars(json_encode($w['settings'], JSON_UNESCAPED_UNICODE)) ?>' data-allowed="<?= htmlspecialchars($w['allowed_zones'] ?? '') ?>">
              <span class="widget-handle">⋮⋮</span><span class="widget-title"><?= htmlspecialchars($w['title']) ?></span>
              <span class="widget-actions">
                <button type="button" class="btn btn-light btn-sm btn-settings"><i class="bi bi-sliders"></i></button>
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-x-lg"></i></button>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- 3-Spalten-Layout -->
      <div class="row g-3">
        <!-- Left -->
        <div class="col-md-3">
          <div class="p-3 border rounded bg-zone h-100">
            <h6 class="mb-2">Left Sidebar</h6>
            <ul class="widget-list nx-zone h-100" data-pos="left">
              <?php foreach(($assigned['left']??[]) as $w): ?>
                <li class="widget-item" data-id="<?= htmlspecialchars($w['widget_key']) ?>" data-iid="<?= htmlspecialchars($w['instance_id']) ?>" data-title="<?= htmlspecialchars($w['title']) ?>" data-settings='<?= htmlspecialchars(json_encode($w['settings'], JSON_UNESCAPED_UNICODE)) ?>' data-allowed="<?= htmlspecialchars($w['allowed_zones'] ?? '') ?>">
                  <span class="widget-handle">⋮⋮</span><span class="widget-title"><?= htmlspecialchars($w['title']) ?></span>
                  <span class="widget-actions">
                    <button type="button" class="btn btn-light btn-sm btn-settings"><i class="bi bi-sliders"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-x-lg"></i></button>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <!-- Main -->
        <div class="col-md-6">
          <div class="mb-3 p-3 border rounded bg-zone">
            <h6 class="mb-2">Main Content Top</h6>
            <ul class="widget-list nx-zone" data-pos="maintop">
              <?php foreach(($assigned['maintop']??[]) as $w): ?>
                <li class="widget-item" data-id="<?= htmlspecialchars($w['widget_key']) ?>" data-iid="<?= htmlspecialchars($w['instance_id']) ?>" data-title="<?= htmlspecialchars($w['title']) ?>" data-settings='<?= htmlspecialchars(json_encode($w['settings'], JSON_UNESCAPED_UNICODE)) ?>' data-allowed="<?= htmlspecialchars($w['allowed_zones'] ?? '') ?>">
                  <span class="widget-handle">⋮⋮</span><span class="widget-title"><?= htmlspecialchars($w['title']) ?></span>
                  <span class="widget-actions">
                    <button type="button" class="btn btn-light btn-sm btn-settings"><i class="bi bi-sliders"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-x-lg"></i></button>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="mb-3 p-3 border rounded bg-zone">
            <h6 class="mb-2">Main Content Bottom</h6>
            <ul class="widget-list nx-zone" data-pos="mainbottom">
              <?php foreach(($assigned['mainbottom']??[]) as $w): ?>
                <li class="widget-item" data-id="<?= htmlspecialchars($w['widget_key']) ?>" data-iid="<?= htmlspecialchars($w['instance_id']) ?>" data-title="<?= htmlspecialchars($w['title']) ?>" data-settings='<?= htmlspecialchars(json_encode($w['settings'], JSON_UNESCAPED_UNICODE)) ?>' data-allowed="<?= htmlspecialchars($w['allowed_zones'] ?? '') ?>">
                  <span class="widget-handle">⋮⋮</span><span class="widget-title"><?= htmlspecialchars($w['title']) ?></span>
                  <span class="widget-actions">
                    <button type="button" class="btn btn-light btn-sm btn-settings"><i class="bi bi-sliders"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-x-lg"></i></button>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <!-- Right -->
        <div class="col-md-3">
          <div class="p-3 border rounded bg-zone h-100">
            <h6 class="mb-2">Right Sidebar</h6>
            <ul class="widget-list nx-zone h-100" data-pos="right">
              <?php foreach(($assigned['right']??[]) as $w): ?>
                <li class="widget-item" data-id="<?= htmlspecialchars($w['widget_key']) ?>" data-iid="<?= htmlspecialchars($w['instance_id']) ?>" data-title="<?= htmlspecialchars($w['title']) ?>" data-settings='<?= htmlspecialchars(json_encode($w['settings'], JSON_UNESCAPED_UNICODE)) ?>' data-allowed="<?= htmlspecialchars($w['allowed_zones'] ?? '') ?>">
                  <span class="widget-handle">⋮⋮</span><span class="widget-title"><?= htmlspecialchars($w['title']) ?></span>
                  <span class="widget-actions">
                    <button type="button" class="btn btn-light btn-sm btn-settings"><i class="bi bi-sliders"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-x-lg"></i></button>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-3 border rounded bg-zone">
        <h6 class="mb-2">Footer</h6>
        <ul class="widget-list nx-zone" data-pos="bottom">
          <?php foreach(($assigned['bottom']??[]) as $w): ?>
            <li class="widget-item" data-id="<?= htmlspecialchars($w['widget_key']) ?>" data-iid="<?= htmlspecialchars($w['instance_id']) ?>" data-title="<?= htmlspecialchars($w['title']) ?>" data-settings='<?= htmlspecialchars(json_encode($w['settings'], JSON_UNESCAPED_UNICODE)) ?>' data-allowed="<?= htmlspecialchars($w['allowed_zones'] ?? '') ?>">
              <span class="widget-handle">⋮⋮</span><span class="widget-title"><?= htmlspecialchars($w['title']) ?></span>
              <span class="widget-actions">
                <button type="button" class="btn btn-light btn-sm btn-settings"><i class="bi bi-sliders"></i></button>
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-x-lg"></i></button>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="widgetSettingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Widget-Einstellungen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <strong id="settingsWidgetTitle"></strong>
        <textarea id="widgetSettingsJson" class="form-control mt-2" rows="10" spellcheck="false">{}</textarea>
        <div class="form-text">Beispiel: {"title":"Meine Box","limit":5}</div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnSettingsSave" class="btn btn-primary">Speichern</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>


<script>
const CSRF = <?= json_encode($CSRF) ?>;
const PAGE = <?= json_encode($page) ?>;
const SAVE_ENDPOINT = <?= json_encode($SAVE_ENDPOINT) ?>;

/* === Helper === */
function getAllowedZones(widgetEl) {
  const zones = widgetEl.dataset.allowed?.split(',').map(z => z.trim()) || [];
  return zones.filter(z => z.length > 0);
}

/* === Init Sortable === */
document.querySelectorAll('.nx-zone').forEach(zone => {
  new Sortable(zone, {
    group: {
      name: 'nx',
      pull: true,
      put: (to, from, dragged) => {
        const allowed = getAllowedZones(dragged);
        const pos = to.el.dataset.pos;
        const ok = !allowed.length || allowed.includes(pos);
        to.el.classList.remove('nx-zone-allowed', 'nx-zone-forbidden');
        to.el.classList.add(ok ? 'nx-zone-allowed' : 'nx-zone-forbidden');
        return ok;
      }
    },
    animation: 150,

    onStart: () => {
      document.querySelectorAll('.nx-zone')
        .forEach(z => z.classList.remove('nx-zone-allowed','nx-zone-forbidden'));
    },

    onEnd: async evt => {
      document.querySelectorAll('.nx-zone')
        .forEach(z => z.classList.remove('nx-zone-allowed','nx-zone-forbidden'));
      await saveState(true);
    },

    onAdd: async evt => {
      const el = evt.item;
      if (evt.from.id === 'available') {
        el.dataset.iid = 'w_' + Math.random().toString(36).substr(2, 8);
        el.dataset.settings = '{}';
        el.innerHTML = `
          <span class="widget-handle">⋮⋮</span>
          <span class="widget-title">${el.dataset.title || el.dataset.id}</span>
          <span class="widget-actions">
            <button type="button" class="btn btn-light btn-sm btn-settings" title="Einstellungen"><i class="bi bi-sliders"></i></button>
            <button type="button" class="btn btn-outline-danger btn-sm btn-remove" title="Entfernen"><i class="bi bi-x-lg"></i></button>
          </span>`;
      }
      await saveState(true);
    }
  });
});

/* === Widget-Liste (Clone) === */
new Sortable(document.getElementById('available'), {
  group: { name: 'nx', pull: 'clone', put: false },
  sort: false,
  animation: 150
});

/* === SAVE === */
async function saveState(reloadAfter = false) {
  const data = {};
  document.querySelectorAll('.nx-zone').forEach(zone => {
    const pos = zone.dataset.pos;
    data[pos] = Array.from(zone.querySelectorAll('.widget-item')).map((el, idx) => ({
      widget_key: el.dataset.id,
      instance_id: el.dataset.iid || ('w_' + Math.random().toString(36).substr(2, 8)),
      settings: el.dataset.settings ? JSON.parse(el.dataset.settings) : {},
      sort_order: idx
    }));
  });

  const payload = { page: PAGE, data, csrf: CSRF };
  try {
    const res = await fetch(SAVE_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify(payload)
    });
    const j = await res.json().catch(() => ({ ok: false }));
    console.log(j.ok ? '✅ Saved' : '❌ Save failed', j);

    if (j.ok && reloadAfter) {
      // Widgets direkt neu laden, damit sie angezeigt werden
      setTimeout(() => location.reload(), 250);
    }
  } catch (err) {
    console.error('❌ Save error', err);
  }
}

/* === Delete Widget === */
document.addEventListener('click', async e => {
  const btn = e.target.closest('.btn-remove');
  if (!btn) return;
  const li = btn.closest('.widget-item');
  if (!li) return;

  const iid = li.dataset.iid;
  li.remove();

  try {
    const delPayload = { page: PAGE, removedInstanceIds: [iid], csrf: CSRF };
    await fetch(SAVE_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify(delPayload)
    });
  } catch (err) {
    console.error('❌ Remove error', err);
  }
  await saveState(true);
});

/* === Settings Modal === */
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn-settings');
  if (!btn) return;
  const li = btn.closest('.widget-item');
  const title = li.dataset.title;
  const json = li.dataset.settings || '{}';
  document.getElementById('settingsWidgetTitle').textContent = title;
  document.getElementById('widgetSettingsJson').value = json;
  const modal = new bootstrap.Modal(document.getElementById('widgetSettingsModal'));
  modal.show();
  document.getElementById('btnSettingsSave').onclick = async () => {
    try {
      const val = document.getElementById('widgetSettingsJson').value.trim() || '{}';
      JSON.parse(val);
      li.dataset.settings = val;
      await saveState(true);
      modal.hide();
    } catch (err) {
      alert('Ungültiges JSON: ' + err.message);
    }
  };
});

document.getElementById('nx-save-all').addEventListener('click', () => saveState(true));
</script>

</body>
</html>
