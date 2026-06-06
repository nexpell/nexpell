<?php
declare(strict_types=1);

require_once BASE_PATH . '/system/core/builder_core.php';

$page = $_GET['site'] ?? 'index';
$available = nx_load_available_widgets();
$assigned  = nx_load_widgets_for_page($page);

if (session_status() === PHP_SESSION_NONE) session_start();

use nexpell\SeoUrlHandler;

if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__, 2)); // zwei Ebenen hoch: /system/core → /
}
require_once BASE_PATH . '/system/config.inc.php';

// DB
global $_database;
$_database = $_database ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_errno) {
  throw new RuntimeException('DB connect error: ' . $_database->connect_error);
}
$_database->set_charset('utf8mb4');

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Konfig
const NXB_ZONE_SELECTORS = ['.nx-live-zone', '.nx-zone'];
const NXB_POSITIONS      = ['top','undertop','left','maintop','mainbottom','right','bottom'];

function nxb_is_builder(): bool { return isset($_GET['builder']) && $_GET['builder'] === '1'; }
function nxb_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Daten laden
function nxb_db_fetch_palette(): array {
  global $_database;
  $out = [];
  if ($res = $_database->query("SELECT widget_key, COALESCE(NULLIF(title,''), widget_key) AS title FROM settings_widgets ORDER BY title ASC")) {
    while ($row = $res->fetch_assoc()) {
      $out[] = ['widget_key'=>(string)$row['widget_key'], 'title'=>(string)$row['title']];
    }
    $res->close();
  }
  return $out;
}
function nxb_db_fetch_widgets(string $page): array {
  global $_database;
  $out = [];
  $sql = "SELECT position, widget_key, instance_id, settings, title, modulname
          FROM settings_widgets_positions
          WHERE page = ?
          ORDER BY position ASC, sort_order ASC, id ASC";
  if (!$st = $_database->prepare($sql)) return $out;
  $st->bind_param('s', $page);
  if (!$st->execute()) { $st->close(); return $out; }
  $st->bind_result($pos, $wkey, $iid, $settings, $title, $modulname);
  while ($st->fetch()) {
    $cfg = [];
    if ($settings) { $tmp = json_decode($settings, true); if (is_array($tmp)) $cfg = $tmp; }
    $out[$pos][] = [
      'position'=>(string)$pos,
      'widget_key'=>(string)$wkey,
      'instance_id'=>(string)($iid ?? ''),
      'settings'=>$cfg,
      'title'=>(string)($title ?: $wkey),
      'modulname'=>(string)($modulname ?? '')
    ];
  }
  $st->close();
  return $out;
}

/* === Zonen-Restriktions-Logik START (serverseitige Map laden) ========= */
function nxb_db_fetch_allowed_zones_map(): array {
  global $_database;
  $map = [];
  if ($res = $_database->query("SELECT widget_key, allowed_zones FROM settings_widgets")) {
    while ($row = $res->fetch_assoc()) {
      $zones = array_filter(array_map('trim', explode(',', (string)($row['allowed_zones'] ?? ''))));
      // Vereinbarung: leeres Array = überall erlaubt
      $map[(string)$row['widget_key']] = array_values($zones);
    }
    $res->close();
  }
  return $map;
}
$__NX_ALLOWED_ZONES_MAP = nxb_db_fetch_allowed_zones_map();
/* === Zonen-Restriktions-Logik ENDE ==================================== */

// Serverseitiges Rendering (initial) über PluginManager
function nxb_render_widget_content(string $widget_key, string $instance_id, array $settings, string $title): string {
  // PluginManager aus init.php steht i.d.R. via Autoloader bereit:
  require_once BASE_PATH . '/system/core/init.php';
  $pm = new \nexpell\PluginManager($GLOBALS['_database']);
  return $pm->renderWidget($widget_key, [
    'instanceId' => $instance_id,
    'settings'   => $settings,
    'title'      => $title,
    'ctx'        => ['builder'=>nxb_is_builder(), 'widget_key'=>$widget_key, 'instance_id'=>$instance_id, 'title'=>$title]
  ]);
}

// Wrapper für Live-Controls
function nxb_live_wrap(array $w, string $innerHtml): string {
  if (!nxb_is_builder()) return $innerHtml;
  $attrs = sprintf(
    'class="nx-live-item" data-nx-iid="%s" data-nx-key="%s" data-nx-title="%s" data-nx-settings="%s"',
    nxb_h($w['instance_id']),
    nxb_h($w['widget_key']),
    nxb_h($w['title']),
    nxb_h(json_encode($w['settings'], JSON_UNESCAPED_UNICODE))
  );
  return '<div '.$attrs.'>
    <div class="nx-drag-handle" title="Ziehen">⋮⋮</div>
    <div class="nx-live-controls btn-group btn-group-sm" role="group">
      <!--<button type="button" class="btn btn-light btn-settings" title="Einstellungen"><i class="bi bi-sliders"></i></button>-->
      <button type="button" class="btn btn-outline-danger btn-remove" title="Entfernen"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="nx-live-content">'.$innerHtml.'</div>
  </div>';
}

function nxb_build_widgets_html(string $page): array {
  $rows = nxb_db_fetch_widgets($page);
  $out  = [];
  foreach (NXB_POSITIONS as $pos) {
    $out[$pos] = [];
    foreach ($rows[$pos] ?? [] as $w) {
      $content = nxb_render_widget_content($w['widget_key'], $w['instance_id'], $w['settings'], $w['title']);
      $out[$pos][] = nxb_live_wrap($w, $content);
    }
  }
  return $out;
}

function nxb_inject_live_overlay_with_palette(string $page): void {
  if (!nxb_is_builder()) return;

  global $__NX_ALLOWED_ZONES_MAP;

  $lang    = $_SESSION['language'] ?? 'de';
  $csrf    = $_SESSION['csrf_token'];
  $palette = nxb_db_fetch_palette();

  echo '<style>
    .nx-live-zone, .nx-zone{ min-height:56px; position:relative; border:2px dashed rgba(0,0,0,.25); border-radius:.6rem; padding:.5rem; margin:.5rem; /*background:#fff;*/ }
    .nx-live-item{ position:relative; outline:1px dashed transparent; padding-left:0px; }
    .nx-live-item:hover{ outline-color:#fe821d; }
    .nx-drag-handle{ position:absolute; left:10px; top:8px; cursor:grab; opacity:.75; user-select:none; font-weight:600; line-height:1; }
    .nx-live-controls{ position:absolute; top:.25rem; right:-2.25rem; display:flex; gap:.25rem; z-index:2147480000; pointer-events:auto; opacity:0; transition:opacity .12s }
    .nx-live-item:hover .nx-live-controls{ opacity:1 }
    .nx-drop-hint{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; opacity:.35; font-size:.9rem; }
    .nx-live-toolbar{ position:sticky; top:0; background:#fff; z-index:2147480001; border-bottom:1px solid #eee; padding:.5rem; margin-bottom:.75rem; display:flex; gap:.5rem; align-items:center }
    .nx-badge{ font-size:.75rem }

    /* Palette (verschiebbar + toggelbar) */
    #nx-palette{
      position:fixed; top:80px; left:12px; width:300px; max-height:calc(100vh - 100px);
      overflow:auto; background:#f8f9fa; border:1px solid #dee2e6; border-radius:.75rem;
      z-index:2147480002; box-shadow:0 4px 20px rgba(0,0,0,.08);
      transition: transform .18s ease, opacity .18s ease;
    }
    #nx-pal-head{ cursor:move; user-select:none; padding:.5rem .75rem; background:#e9ecef; border-bottom:1px solid #dee2e6; display:flex; align-items:center; gap:.5rem; }
    #nx-pal-body{ padding:.5rem; }
    .nx-pal-list{ list-style:none; padding:0; margin:0; }
    .nx-pal-item{ position:relative; margin:.4rem; padding:.5rem .5rem .5rem 1.75rem; background:#fff; border:1px solid #ced4da; border-radius:.5rem; user-select:none; cursor:grab; }
    .nx-pal-handle{ position:absolute; left:.5rem; top:.5rem; opacity:.75; cursor:grab; }
    .ghost{ opacity:.5 }
    .nx-dragging #nx-palette{ pointer-events:none; }

    /* Ein-/Ausblenden */
    #nx-palette.is-hidden{
      transform: translateX(-110%);
      opacity: 0;
      pointer-events: none;
    }
    #nx-toggle-palette[aria-expanded="false"] { opacity: .75; }

    /* === Zonen-Restriktions-Logik START (optische Marker) ============== */
    /*.nx-zone-allowed   { outline: 2px dashed #28a745 !important; background: rgba(40,167,69,.06) }
    .nx-zone-forbidden { outline: 2px dashed #dc3545 !important; background: rgba(220,53,69,.06); cursor: not-allowed }*/

    /* Marker robuster: gewinnt gegen andere Hintergründe */
    .nx-zone-allowed   { outline: 2px dashed #28a745 !important; background: rgba(40,167,69,.08) !important; }
    .nx-zone-forbidden { outline: 2px dashed #dc3545 !important; background: rgba(220,53,69,.08) !important; cursor: not-allowed !important; }

    /* Zonen greifen den Drag zuverlässig */
    .nx-live-zone, .nx-zone { position: relative; pointer-events: auto; }
    .nx-drop-hint { pointer-events: none; }
    /* === Zonen-Restriktions-Logik ENDE ================================= */
  </style>';

  function url_with_params(string $url, array $params): string {
    $parts = parse_url($url);
    $base  = ($parts['scheme'] ?? '') ? ($parts['scheme'].'://') : '';
    $base .= $parts['host']  ?? '';
    $base .= $parts['path']  ?? '';

    // bestehende Query lesen & mergen
    $qs = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $qs);
    $qs = array_merge($qs, $params);

    return $base . (empty($qs) ? '' : ('?' . http_build_query($qs)));
}

$liveUrl = ($page === 'index')
    ? '/'.rawurlencode($lang).'/'
    : '/'.rawurlencode($lang).'/'.rawurlencode($page).'/';

$href = url_with_params($liveUrl, ['builder' => '1']);

  echo '<div class="nx-live-toolbar container-fluid">
    <span><strong>Live-Builder</strong> — Seite: <code>'.nxb_h($page).'</code></span>
    <div class="ms-auto d-flex gap-2">
      <button id="nx-toggle-palette" class="btn btn-sm btn-outline-secondary" type="button" aria-expanded="true">
        <i class="bi bi-grid-3x3-gap"></i> Widgets
      </button>
      <a id="nx-live-save" class="btn btn-sm btn-primary" type="button" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">Speichern & Seite neu laden</a>
      <!--<button id="nx-live-save" class="btn btn-sm btn-primary" type="button">Speichern</button>-->
      <span class="badge text-bg-light nx-badge">Lang: '.nxb_h($lang).'</span>
    </div>
  </div>';

  echo '<aside id="nx-palette">
    <div id="nx-pal-head"><i class="bi bi-arrows-move"></i><strong> Widgets</strong></div>
    <div id="nx-pal-body">
      <ul id="nx-pal-list" class="nx-pal-list">';
  if ($palette) {
    foreach ($palette as $w) {
      echo '<li class="nx-pal-item"
                data-pal-key="' . htmlspecialchars($w['widget_key']) . '"
                data-pal-title="' . htmlspecialchars($w['title']) . '"
                data-allowed="' . htmlspecialchars($w['allowed_zones'] ?? '') . '">
              <span class="nx-pal-handle">⋮⋮</span> ' . htmlspecialchars($w['title']) . '
            </li>';
    }
  } else {
    echo '<li class="text-muted px-2">(Keine Widgets definiert)</li>';
  }
  echo '  </ul>
    </div>
  </aside>';

  $CSRF = json_encode($csrf, JSON_UNESCAPED_UNICODE);
  $PAGE = json_encode($page, JSON_UNESCAPED_UNICODE);
  $ZONE_SELECTORS = json_encode(NXB_ZONE_SELECTORS, JSON_UNESCAPED_UNICODE);

  echo '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>';

  /* === Zonen-Restriktions-Logik START (Map in JS bereitstellen) ======= */
  echo '<script>window.widgetRestrictions = '.json_encode($__NX_ALLOWED_ZONES_MAP, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';</script>';

  /* === Zonen-Restriktions-Logik ENDE ================================== */

echo '<script>
(function(){
  const CSRF = '.$CSRF.';
  const PAGE = '.$PAGE.';
  const BASE_URL = ' . json_encode(rtrim(dirname($_SERVER['SCRIPT_NAME'], 3), '/')) . ';
  const SAVE_ENDPOINT   = BASE_URL + "/admin/plugin_widgets_save.php";
  const RENDER_ENDPOINT = BASE_URL + "/admin/plugin_widgets_render.php";
  const ZONE_SELECTORS  = '.$ZONE_SELECTORS.';

  const $$  = (sel,root=document)=>Array.from(root.querySelectorAll(sel));
  const uid = ()=>"w_"+Math.random().toString(36).slice(2,9);
  const removedInstanceIds = [];
  let palList=null,palPanel=null,btnToggle=null;
  const PAL_VISIBLE_KEY="nxPalVisible";

  const panel = document.getElementById("nx-palette");
  const head  = document.getElementById("nx-pal-head");
  if (!panel || !head) return;

  // letzte Position wiederherstellen
  try {
    const pos = JSON.parse(localStorage.getItem("nxPalPos") || "null");
    if (pos && typeof pos.left === "number" && typeof pos.top === "number") {
      panel.style.left = pos.left + "px";
      panel.style.top  = pos.top + "px";
    }
  } catch (e) {}

  let dragging = false;
  let startX = 0, startY = 0, origLeft = 0, origTop = 0;

  head.style.cursor = "move";
  head.style.userSelect = "none";

  head.addEventListener("mousedown", (e) => {
    // nur linke Maustaste
    if (e.button !== 0) return;
    dragging = true;
    startX = e.clientX;
    startY = e.clientY;
    const rect = panel.getBoundingClientRect();
    origLeft = rect.left;
    origTop = rect.top;
    document.body.style.userSelect = "none";
    document.body.style.cursor = "grabbing";
  });

  window.addEventListener("mousemove", (e) => {
    if (!dragging) return;
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;
    panel.style.left = Math.max(0, origLeft + dx) + "px";
    panel.style.top  = Math.max(0, origTop + dy) + "px";
  });

  window.addEventListener("mouseup", () => {
    if (!dragging) return;
    dragging = false;
    document.body.style.userSelect = "";
    document.body.style.cursor = "";
    try {
      const rect = panel.getBoundingClientRect();
      localStorage.setItem("nxPalPos", JSON.stringify({ left: rect.left, top: rect.top }));
    } catch (e) {}
  });  

  /* ---------- Helper ---------- */
  function getZones(){
    const set=new Set();
    ZONE_SELECTORS.forEach(sel=>$$(sel).forEach(el=>{
      if(el.getAttribute("data-nx-zone")) set.add(el);
    }));
    return Array.from(set);
  }
  function ensureDropHints(){
    getZones().forEach(z=>{
      let h=z.querySelector(".nx-drop-hint");
      if(!h){h=document.createElement("div");h.className="nx-drop-hint";h.textContent="Hierhin ziehen, um Widget abzulegen";z.appendChild(h);}
      h.style.display=z.querySelector(".nx-live-item")?"none":"flex";
    });
  }
  function collectState(){
    const data={};
    getZones().forEach(zone=>{
      const pos=zone.getAttribute("data-nx-zone");
      data[pos]=[];
      zone.querySelectorAll(":scope>.nx-live-item").forEach(el=>{
        let iid=el.getAttribute("data-nx-iid");
        if(!iid){iid=uid();el.setAttribute("data-nx-iid",iid);}
        let settings={};
        try{settings=JSON.parse(el.getAttribute("data-nx-settings")||"{}");}catch(e){}
        data[pos].push({widget_key:el.getAttribute("data-nx-key")||"",instance_id:iid,settings});
      });
    });
    return data;
  }
  async function saveState(){
    const body={page:PAGE,data:collectState(),removedInstanceIds:removedInstanceIds.splice(0)};
    try{
      const r=await fetch(SAVE_ENDPOINT,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":CSRF},body:JSON.stringify(body),credentials:"same-origin"});
      const j=await r.json().catch(()=>null);
      if(!j||!j.ok)console.warn("❌ Save failed",j);else console.log("✅ Saved");
    }catch(e){console.error("Save error",e);}
    finally{ensureDropHints();}
  }

  /* ---------- Restriktions-Logik ---------- */
  function nxIsAllowedUniversal(widgetElOrKey,zoneName){
    let allowed=[];
    if(widgetElOrKey instanceof HTMLElement){
      const attr=widgetElOrKey.dataset.allowed;
      if(attr){allowed=attr.split(",").map(z=>z.trim()).filter(Boolean);}
      else{
        const key=widgetElOrKey.dataset.nxKey||widgetElOrKey.dataset.palKey;
        if(window.widgetRestrictions&&window.widgetRestrictions[key])allowed=window.widgetRestrictions[key];
        // Palette-Fallback → überall erlaubt
        if(widgetElOrKey.dataset.palKey&&(!allowed||allowed.length===0))return true;
      }
    }else if(typeof widgetElOrKey==="string"){
      if(window.widgetRestrictions&&window.widgetRestrictions[widgetElOrKey])
        allowed=window.widgetRestrictions[widgetElOrKey];
    }
    if(!allowed||allowed.length===0)return true;
    return allowed.includes(zoneName);
  }

  let __nxLastMarked=null;
  function nxClearMark(el){if(el)el.classList.remove("nx-zone-allowed","nx-zone-forbidden");}
  function nxMark(el,ok){
    if(!el)return;
    if(__nxLastMarked&&__nxLastMarked!==el)nxClearMark(__nxLastMarked);
    el.classList.toggle("nx-zone-allowed",!!ok);
    el.classList.toggle("nx-zone-forbidden",!ok);
    __nxLastMarked=el;
  }
  function nxClearAllMarks(){
    document.querySelectorAll(".nx-zone-allowed,.nx-zone-forbidden").forEach(nxClearMark);
    __nxLastMarked=null;
  }

  /* ---------- Render ---------- */
  async function renderInto(el){
    const key=el.getAttribute("data-nx-key")||"";
    const iid=el.getAttribute("data-nx-iid")||uid();
    el.setAttribute("data-nx-iid",iid);
    let settings={};
    try{settings=JSON.parse(el.getAttribute("data-nx-settings")||"{}");}catch(e){}
    const parentZone=el.closest("[data-nx-zone]");
    const position=parentZone?parentZone.getAttribute("data-nx-zone"):"";
    const body={widget_key:key,instance_id:iid,title:el.getAttribute("data-nx-title")||key,settings,page:PAGE,builder:true,lang:window.NXB_LANG||"de",csrf:CSRF,position};
    try{
      const res=await fetch(RENDER_ENDPOINT+"?format=html",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":CSRF},body:JSON.stringify(body),credentials:"same-origin"});
      const html=await res.text();
      const c=el.querySelector(".nx-live-content")||el.appendChild(document.createElement("div"));
      c.className="nx-live-content";c.innerHTML=html||\'<div class="alert alert-warning small">leer</div>\';
      if(!res.ok)console.error("render error",res.status,html);
    }catch(e){console.error("render exception",e);const c=el.querySelector(".nx-live-content");if(c)c.innerHTML=\'<div class="alert alert-danger small">Render-Fehler</div>\';}
  }

  /* ---------- Palette ---------- */
  function bindPalette(){
    palList=document.getElementById("nx-pal-list");
    palPanel=document.getElementById("nx-palette");
    btnToggle=document.getElementById("nx-toggle-palette");
    if(!palList||!palPanel)return;
    const vis=(localStorage.getItem(PAL_VISIBLE_KEY)??"1")==="1";
    setPaletteVisible(vis,false);

    new Sortable(palList, {
      group: { name: "nx-builder", pull: "clone", put: false },
      draggable: ".nx-pal-item",
      handle: ".nx-pal-handle, .nx-pal-item",
      sort: false,
      animation: 150,
      ghostClass: "ghost",
      fallbackOnBody: true,
      forceFallback: true,

      onStart() {
        document.body.classList.add("nx-dragging");
        nxClearAllMarks();
      },

      onMove: (evt) => {
        // 🔧 Wenn kein Ziel (z.B. noch in der Luft) → immer true
        if (!evt.to) return true;

        const toZone = evt.to.getAttribute("data-nx-zone") || "";
        const dragged = evt.dragged;
        if (!toZone || !dragged) return true;

        const ok = nxIsAllowedUniversal(dragged, toZone);
        nxMark(evt.to, ok);
        return ok;
      },

      onEnd() {
        document.body.classList.remove("nx-dragging");
        nxClearAllMarks();
      }
    });


    btnToggle?.addEventListener("click",()=>{
      const next=!(btnToggle.getAttribute("aria-expanded")==="true");
      setPaletteVisible(next,true);
    });
  }
  function setPaletteVisible(v,p){if(!palPanel)return;if(v){palPanel.classList.remove("is-hidden");btnToggle?.setAttribute("aria-expanded","true");}else{palPanel.classList.add("is-hidden");btnToggle?.setAttribute("aria-expanded","false");}if(p)try{localStorage.setItem(PAL_VISIBLE_KEY,v?"1":"0");}catch(e){}}
  function createShellFromPalette(li){
    const key=li.getAttribute("data-pal-key")||li.getAttribute("data-nx-key")||"widget";
    const title=li.getAttribute("data-pal-title")||li.getAttribute("data-nx-title")||key;
    li.className="nx-live-item";
    li.removeAttribute("data-pal-key");li.removeAttribute("data-pal-title");
    li.setAttribute("data-nx-iid",uid());
    li.setAttribute("data-nx-key",key);
    li.setAttribute("data-nx-title",title);
    li.setAttribute("data-nx-settings","{}");
    li.innerHTML=`<div class="nx-drag-handle" title="Ziehen">⋮⋮</div>
      <div class="nx-live-controls btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-light btn-settings" title="Einstellungen"><i class="bi bi-sliders"></i></button>
        <button type="button" class="btn btn-outline-danger btn-remove" title="Entfernen"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="nx-live-content"><div class="text-muted small">Lade Widget …</div></div>`;
    return li;
  }

  /* ---------- Zones ---------- */
  function bindZone(zone){
    if(zone._nxBound)return;zone._nxBound=true;
    new Sortable(zone,{
      group:{name:"nx-builder",pull:true,put:true},
      animation:150,
      ghostClass:"ghost",
      fallbackOnBody:true,
      forceFallback:true,
      emptyInsertThreshold:12,
      draggable:".nx-live-item",
      handle:".nx-drag-handle",
      filter:".nx-live-controls, .nx-live-controls *",
      preventOnFilter:true,
      onStart(){document.body.classList.add("nx-dragging");nxClearAllMarks();},
      onMove:(evt)=>{
        const toZone=evt.to&&evt.to.getAttribute("data-nx-zone")||"";
        const key=(evt.dragged&&(evt.dragged.getAttribute("data-nx-key")||evt.dragged.getAttribute("data-pal-key")))||"";
        if(!toZone||!key){nxMark(evt.to,true);return true;}
        const ok=nxIsAllowedUniversal(evt.dragged||key,toZone);
        nxMark(evt.to,ok);return ok;
      },
      onAdd:async(evt)=>{
        try{
          const toZone=zone.getAttribute("data-nx-zone")||"";
          const key=(evt.item&&(evt.item.getAttribute("data-nx-key")||evt.item.getAttribute("data-pal-key")))||"";
          if(key&&toZone&&!nxIsAllowedUniversal(evt.item||key,toZone)){evt.item?.parentNode?.removeChild(evt.item);return;}
          if(evt.from===palList&&evt.item){createShellFromPalette(evt.item);}
          await saveState();if(evt.item)await renderInto(evt.item);
        }finally{ensureDropHints();nxClearAllMarks();}
      },
      onEnd(){ensureDropHints();nxClearAllMarks();}
    });
  }

  /* ---------- Buttons ---------- */
  document.addEventListener("click",async e=>{
    const rm=e.target.closest(".nx-live-controls .btn-remove");
    if(rm){
      const item=rm.closest(".nx-live-item");const iid=item&&item.getAttribute("data-nx-iid");
      if(iid)removedInstanceIds.push(iid);
      item?.parentNode?.removeChild(item);
      try{await saveState();}catch(err){console.error("Save after remove failed",err);}
      return;
    }
    const set=e.target.closest(".nx-live-controls .btn-settings");
    if(set){
      const item=set.closest(".nx-live-item");
      const current=item.getAttribute("data-nx-settings")||"{}";
      const json=prompt("JSON-Settings bearbeiten:",current);
      if(json===null)return;
      try{const obj=json.trim()?JSON.parse(json):{};item.setAttribute("data-nx-settings",JSON.stringify(obj));await saveState();await renderInto(item);}
      catch(err){alert("Ungültiges JSON:"+err.message);}
    }
  });

  document.getElementById("nx-live-save")?.addEventListener("click",saveState);

  function initAll(){bindPalette();getZones().forEach(bindZone);ensureDropHints();}
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",initAll);else initAll();
  new MutationObserver(()=>{getZones().forEach(bindZone);ensureDropHints();}).observe(document.documentElement,{childList:true,subtree:true});
})();
</script>';

}

function nxb_prepare_builder(string $page): array {
  $widgetsByPosition = nxb_build_widgets_html($page);
  nxb_inject_live_overlay_with_palette($page);
  return $widgetsByPosition;
}
