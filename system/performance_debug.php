<?php
if (!defined('DEBUG_PERFORMANCE') || !DEBUG_PERFORMANCE) return;

use nexpell\PluginManager;
require_once BASE_PATH.'/system/core/init.php';

$pageStart = microtime(true);

$allPositions = ['top','undertop','left','maintop','mainbottom','right','bottom'];
$widgetsByPosition = [];

// Hilfsfunktion zum Messen
function measure($name, callable $func){
    $start = microtime(true);
    $output = $func();
    $duration = (microtime(true)-$start)*1000;
    return ['output'=>$output,'time'=>round($duration,2)];
}

// Widgets messen
global $_database;
$pluginManager = new PluginManager($_database);

foreach ($allPositions as $pos) {
    $widgetsByPosition[$pos] = [];
    if (!empty($positions[$pos])) {
        foreach ($positions[$pos] as $key) {
            $widgetsByPosition[$pos][$key] = method_exists($pluginManager, 'renderWidget')
                ? measure("Widget $key", fn() => $pluginManager->renderWidget($key))
                : ['output' => '[PluginManager::renderWidget() undefined]', 'time' => 0];
        }
    }
}

// DB-Abfrage Beispiel
$exampleQuery = measure("SELECT settings_widgets", fn()=>$_database->query("SELECT * FROM settings_widgets LIMIT 1000"));

// Funktion zum Messen lokaler Dateien (nur lesen, nicht ausführen)
function measureFile($filePath){
    $start = microtime(true);
    if(file_exists($filePath)){
        file_get_contents($filePath);
    }
    $duration = (microtime(true)-$start)*1000;
    return [
        'file' => $filePath,
        'time' => round($duration,2),
        'exists' => file_exists($filePath)
    ];
}

// Rekursive Suche nach allen PHP/HTML-Dateien in Templates, Themes und Modules
function getAllFiles(array $paths, array $exts = ['php','html']){
    $files = [];
    foreach($paths as $path){
        if(!is_dir($path)) continue;
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach($rii as $file){
            if($file->isDir()) continue;
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if(in_array($ext, $exts)){
                $files[] = $file->getPathname();
            }
        }
    }
    return $files;
}

$pathsToMeasure = getAllFiles([
    BASE_PATH.'/includes/themes/default/templates',
    BASE_PATH.'/includes/themes/default',
    BASE_PATH.'/includes/modules'
]);

$measuredFiles = [];
foreach($pathsToMeasure as $file){
    $measuredFiles[] = measureFile($file);
}

$pageEnd = microtime(true);
$totalDuration = ($pageEnd-$pageStart)*1000;

// Prüfen, ob lokale Datei existiert (für JS)
function checkFileExists($url){
    if(str_starts_with($url, 'http')) return true;
    $path = $_SERVER['DOCUMENT_ROOT'] . $url;
    return file_exists($path);
}
?>

<style>
#perf-dashboard{background:#111;color:#eee;font-family:monospace;padding:10px;font-size:12px;}
#perf-dashboard table{border-collapse:collapse;width:100%;margin-bottom:10px;}
#perf-dashboard th, #perf-dashboard td{border:1px solid #555;padding:3px;text-align:left;font-size:12px;}
#perf-dashboard .slow{background:#600;color:#fff;font-weight:bold;}
#perf-dashboard .medium{background:#660;color:#fff;font-weight:bold;}
#perf-dashboard .fast{background:#060;color:#000;font-weight:bold;}
#perf-dashboard tr:hover{opacity:0.8;cursor:pointer;}
#perf-overlay{position:fixed;bottom:10px;right:10px;background:rgba(0,0,0,0.85);color:#fff;font-family:monospace;font-size:12px;padding:8px 12px;border-radius:6px;box-shadow:0 0 6px rgba(0,0,0,0.5);z-index:99999;}
#perf-overlay p{margin:2px 0;}
</style>

<div id="perf-dashboard">

<h2>Widgets</h2>
<?php foreach($widgetsByPosition as $pos=>$widgets):
    if(!empty($widgets)): ?>
    <h3><?=htmlspecialchars($pos)?></h3>
    <table>
        <tr><th>Widget</th><th>Ladezeit (ms)</th><th>Inhalt (Zeichen)</th></tr>
        <?php foreach($widgets as $key=>$info):
            $len = strlen(strip_tags($info['output']));
            $cls = $info['time']>100?'slow':($info['time']>50?'medium':'fast');
        ?>
        <tr class="<?=$cls?>">
            <td><?=htmlspecialchars($key)?></td>
            <td><?=$info['time']?></td>
            <td><?=$len?></td>
        </tr>
        <?php endforeach;?>
    </table>
<?php endif; endforeach; ?>

<h2>DB-Abfragen</h2>
<table>
<tr><th>Query</th><th>Zeit (ms)</th></tr>
<tr class="<?=$exampleQuery['time']>100?'slow':($exampleQuery['time']>50?'medium':'fast')?>">
<td>SELECT settings_widgets</td>
<td><?=$exampleQuery['time']?></td>
</tr>
</table>

<h2>Gesamtladezeit PHP</h2>
<p><?=round($totalDuration,2)?> ms</p>

<h2>Externe Ressourcen</h2>
<table id="perf-resources">
<tr><th>Resource</th><th>Typ</th><th>Dauer (ms)</th><th>Status</th></tr>
</table>

<h2>Lokale PHP/HTML Dateien</h2>
<table>
<tr><th>Datei</th><th>Ladezeit (ms)</th><th>Status</th></tr>
<?php foreach($measuredFiles as $f):
    $cls = !$f['exists'] ? 'slow' : ($f['time']>100?'slow':($f['time']>50?'medium':'fast'));
?>
<tr class="<?=$cls?>">
    <td><?=htmlspecialchars(str_replace(BASE_PATH,'',$f['file']))?></td>
    <td><?=$f['time']?></td>
    <td><?=$f['exists'] ? '✔ vorhanden' : '❌ fehlt'?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>Paint & LCP</h2>
<p id="perf-lcp">LCP wird gemessen...</p>
<p id="perf-fcp">FCP wird gemessen...</p>
</div>

<div id="perf-overlay">
  <p id="overlay-fcp">FCP: …</p>
  <p id="overlay-lcp">LCP: …</p>
</div>

<script>
function setAmpel(el, ms, limits, failed=false){
    if(failed){ el.style.color='red'; el.title='Fehler beim Laden'; return; }
    if(ms <= limits[0]) el.style.color='limegreen';
    else if(ms <= limits[1]) el.style.color='orange';
    else el.style.color='red';
}

if ('PerformanceObserver' in window) {
    try {
        const po = new PerformanceObserver(list=>{
            list.getEntries().forEach(entry=>{
                if(entry.entryType==='largest-contentful-paint'){
                    const ms = Math.round(entry.startTime || entry.renderTime || entry.loadTime);
                    const text = `LCP: ${entry.element?.tagName || 'unknown'}, ${ms} ms`;
                    const perfLCP = document.getElementById('perf-lcp');
                    const overlayLCP = document.getElementById('overlay-lcp');
                    if(perfLCP) perfLCP.textContent=text;
                    if(overlayLCP) overlayLCP.textContent=text;
                    if(overlayLCP) setAmpel(overlayLCP, ms, [2500,4000]);
                }
                if(entry.entryType==='paint' && entry.name==='first-contentful-paint'){
                    const ms = Math.round(entry.startTime);
                    const text = `FCP: ${ms} ms`;
                    const perfFCP = document.getElementById('perf-fcp');
                    const overlayFCP = document.getElementById('overlay-fcp');
                    if(perfFCP) perfFCP.textContent=text;
                    if(overlayFCP) overlayFCP.textContent=text;
                    if(overlayFCP) setAmpel(overlayFCP, ms, [1800,3000]);
                }
            });
        });
        po.observe({type:'largest-contentful-paint', buffered:true});
        po.observe({type:'paint', buffered:true});
    } catch(e) { console.warn('PerformanceObserver type nicht unterstützt:', e); }
}

window.addEventListener('load', () => {
    const resTable = document.getElementById('perf-resources');

    performance.getEntriesByType('resource')
        .filter(r => ['script','link','img','css'].includes(r.initiatorType))
        .sort((a,b)=>b.duration-a.duration)
        .forEach(r => {
            const tr = document.createElement('tr');
            let cls = 'fast';
            let failed = false;
            const isLocal = !r.name.startsWith('http');
            if(isLocal){
                const xhr = new XMLHttpRequest();
                xhr.open('HEAD', r.name, false);
                xhr.send();
                if(xhr.status >= 400){ failed=true; cls='slow'; }
            }
            if(r.duration>200) cls='slow';
            else if(r.duration>100) cls='medium';
            tr.className=cls;
            tr.innerHTML = `<td>${r.name}</td><td>${r.initiatorType}</td><td>${Math.round(r.duration)}</td><td>${failed?'❌ fehlt':'✔ vorhanden'}</td>`;
            resTable.appendChild(tr);
            setAmpel(tr, r.duration, [50,100], failed);
        });

    const longTaskObserver = new PerformanceObserver(list=>{
        list.getEntries().forEach(entry=>{
            const ms=Math.round(entry.duration);
            const tr=document.createElement('tr');
            tr.className=ms>200?'slow':(ms>100?'medium':'fast');
            tr.innerHTML=`<td>⚡ Long Task</td><td>JS Execution</td><td>${ms}</td><td>✔</td>`;
            resTable.appendChild(tr);
        });
    });
    longTaskObserver.observe({entryTypes:['longtask']});
});

window.addEventListener('load', () => {
    setTimeout(()=>{
        const po = new PerformanceObserver(list => {
            list.getEntries().forEach(entry => {
                if(entry.entryType === 'largest-contentful-paint') {
                    const ms = Math.round(entry.startTime);
                    const text = `LCP: ${entry.element?.tagName || 'unknown'}, ${ms} ms`;
                    const perfLCP = document.getElementById('perf-lcp');
                    const overlayLCP = document.getElementById('overlay-lcp');
                    if(perfLCP) perfLCP.textContent = text;
                    if(overlayLCP){ overlayLCP.textContent = text; setAmpel(overlayLCP, ms, [2500,4000]); }
                }
            });
        });
        po.observe({type: 'largest-contentful-paint', buffered: true});

        const fcpEntries=performance.getEntriesByType('paint').filter(e=>e.name==='first-contentful-paint');
        if(fcpEntries.length>0){
            const ms=Math.round(fcpEntries[0].startTime);
            const text=`FCP: ${ms} ms`;
            const perfFCP=document.getElementById('perf-fcp');
            const overlayFCP=document.getElementById('overlay-fcp');
            if(perfFCP) perfFCP.textContent=text;
            if(overlayFCP){ overlayFCP.textContent=text; setAmpel(overlayFCP, ms, [1800,3000]); }
        }
    },2000);
});
</script>


