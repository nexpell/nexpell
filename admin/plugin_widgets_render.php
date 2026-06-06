<?php
// /admin/widget_render.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require_once BASE_PATH . '/system/config.inc.php';

// Debug
if (!defined('NXB_DEBUG')) define('NXB_DEBUG', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// === DB-Verbindung ===
global $_database;
$_database = $_database ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_errno) {
    http_response_code(500);
    echo 'DB error: ' . $_database->connect_error;
    exit;
}
$_database->set_charset('utf8mb4');

// === CSRF ===
$hdr  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sess = $_SESSION['csrf_token'] ?? '';
if (!$hdr || !$sess || !hash_equals($sess, $hdr)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'CSRF invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

// === Request format ===
$acceptFormat = $_GET['format'] ?? 'json';
if ($acceptFormat === 'html') {
    header('Content-Type: text/html; charset=utf-8');
} else {
    header('Content-Type: application/json; charset=utf-8');
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// === Input ===
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Bad JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$widget_key  = isset($in['widget_key'])  ? trim((string)$in['widget_key'])  : '';
$instance_id = isset($in['instance_id']) ? trim((string)$in['instance_id']) : '';
$title       = isset($in['title'])       ? trim((string)$in['title'])       : $widget_key;
$settings    = $in['settings'] ?? [];
$page        = isset($in['page']) ? trim((string)$in['page']) : 'index';
$langCode    = isset($in['lang']) ? strtolower(trim((string)$in['lang'])) : ($_SESSION['language'] ?? 'de');
$builder     = !empty($in['builder']);

/* === Zonen-Restriktions-Logik START (nicht-invasiv) =================== */
/* Optionales Feld "position" aus dem Request lesen. Wenn vorhanden,
   und allowed_zones gesetzt sind, prüfen wir die Erlaubnis. */
$position    = isset($in['position']) ? trim((string)$in['position']) : '';

function nxb_is_zone_allowed_for_widget(mysqli $_database, string $widgetKey, string $zone): bool {
    if ($widgetKey === '' || $zone === '') return true; // ohne Daten keine Einschränkung
    $allowed = null;
    if ($stmt = $_database->prepare("SELECT allowed_zones FROM settings_widgets WHERE widget_key = ? LIMIT 1")) {
        $stmt->bind_param('s', $widgetKey);
        if ($stmt->execute()) {
            $stmt->bind_result($allowedZones);
            if ($stmt->fetch()) {
                $allowed = $allowedZones;
            }
        }
        $stmt->close();
    }
    if ($allowed === null || $allowed === '') return true; // leer/NULL = überall erlaubt
    $zones = array_filter(array_map('trim', explode(',', (string)$allowed)));
    if (empty($zones)) return true; // ebenfalls überall erlaubt
    return in_array($zone, $zones, true);
}
/* === Zonen-Restriktions-Logik ENDE ==================================== */

if ($widget_key === '' || $instance_id === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Missing widget parameters'], JSON_UNESCAPED_UNICODE);
    exit;
}

// === Utils ===
function nxb_safe_html(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function nxb_to_studly(string $key): string {
    $key = preg_replace('/[^a-z0-9]+/i',' ', $key);
    $key = ucwords(strtolower(trim($key)));
    return str_replace(' ', '', $key);
}

// === Widget-Suchpfade ===
if (!defined('NXB_WIDGET_PATHS')) {
    define('NXB_WIDGET_PATHS', [
        BASE_PATH . '/includes/plugins',
        BASE_PATH . '/includes/widgets',
        BASE_PATH . '/includes/components'
    ]);
}

// === Fehlerkarte ===
function nxb_render_error_card(string $title, string $key, string $msg): string {
    return '
    <div class="card border-danger-subtle mb-3">
      <div class="card-header py-2">
        <strong>'.nxb_safe_html($title).'</strong>
        <span class="text-muted small">('.nxb_safe_html($key).')</span>
      </div>
      <div class="card-body py-3">
        <div class="text-danger small mb-0">'.nxb_safe_html($msg).'</div>
      </div>
    </div>';
}

// === Init Mini-Umgebung ===
require_once BASE_PATH . '/system/core/init_widget.php';

/* === Zonen-Restriktions-Logik START (Blocking vor Render) ============= */
if ($position !== '') { // nur prüfen, wenn eine Zone übergeben wurde
    $allowed = nxb_is_zone_allowed_for_widget($_database, $widget_key, $position);
    if (!$allowed) {
        // Für HTML-Ausgabe im Builder eine saubere Karte anzeigen
        $msg = 'Dieses Widget ist für die Zone "' . $position . '" nicht erlaubt.';
        if ($acceptFormat === 'html') {
            echo nxb_render_error_card($title ?: $widget_key, $widget_key, $msg);
        } else {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
/* === Zonen-Restriktions-Logik ENDE ==================================== */

// === Renderer ===
function nxb_render_widget_content_ajax(
    string $widget_key,
    string $instance_id,
    array $settings,
    string $title,
    bool $builder,
    string $page,
    string $langCode
): string {
    global $_database;

    $ctx = [
        'builder'     => $builder,
        'widget_key'  => $widget_key,
        'instance_id' => $instance_id,
        'title'       => $title,
        'page'        => $page,
        'lang'        => $langCode
    ];

    // --- 1) Funktion render_{key}
    $func = 'render_' . preg_replace('/[^a-z0-9_]/i','_', $widget_key);
    if (function_exists($func)) {
        try {
            $html = $func($instance_id, $settings, $title, $ctx);
            if (is_string($html)) return $html;
        } catch (Throwable $e) {
            return nxb_render_error_card($title, $widget_key, 'Function renderer exception: '.$e->getMessage());
        }
    }

    // --- 2) Klasse Widget_{StudlyCase}
    $class = 'Widget_' . nxb_to_studly($widget_key);
    if (class_exists($class)) {
        try {
            if (is_callable([$class,'render'])) {
                $html = $class::render($instance_id, $settings, $title, $ctx);
                if (is_string($html)) return $html;
            }
            $obj = new $class();
            if (method_exists($obj, 'render')) {
                $html = $obj->render($instance_id, $settings, $title, $ctx);
                if (is_string($html)) return $html;
            }
        } catch (Throwable $e) {
            return nxb_render_error_card($title, $widget_key, 'Class renderer exception: '.$e->getMessage());
        }
    }

    // --- 3) Plugin-Datei
    $pluginDir = '';
    $stmt = $_database->prepare("SELECT plugin FROM settings_widgets WHERE widget_key = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $widget_key);
        $stmt->execute();
        $stmt->bind_result($pluginDir);
        $stmt->fetch();
        $stmt->close();
    }

    if ($pluginDir !== '') {
        $pluginPath = BASE_PATH . '/includes/plugins/' . $pluginDir . '/' . $widget_key . '.php';
        if (is_file($pluginPath)) {
            try {
                ob_start();
                include $pluginPath;
                return (string)ob_get_clean();
            } catch (Throwable $e) {
                ob_end_clean();
                return nxb_render_error_card($title, $widget_key, 'Template include error: '.$e->getMessage());
            }
        }
    }

    // --- 4) Generische Template-Datei
    foreach (NXB_WIDGET_PATHS as $base) {
        $file = rtrim($base, '/\\') . '/' . $widget_key . '.php';
        if (is_file($file)) {
            try {
                ob_start();
                include $file;
                return (string)ob_get_clean();
            } catch (Throwable $e) {
                ob_end_clean();
                return nxb_render_error_card($title, $widget_key, 'Template include error: '.$e->getMessage());
            }
        }
    }

    return nxb_render_error_card($title, $widget_key, 'Kein Renderer gefunden (Function/Class/Template).');
}

// === Rendern ===
try {
    $html = nxb_render_widget_content_ajax(
        $widget_key, $instance_id, $settings, $title, $builder, $page, $langCode
    );

    if ($acceptFormat === 'html') {
        echo $html; // ✅ Direktes HTML für Builder
    } else {
        echo json_encode(['ok'=>true,'html'=>$html], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    http_response_code(500);
    if ($acceptFormat === 'html') {
        echo '<div class="alert alert-danger small">Render error: '.nxb_safe_html($e->getMessage()).'</div>';
    } else {
        echo json_encode(['ok'=>false,'error'=>'render error: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
