<?php
// === /includes/builder_core.php ===
// Gemeinsame Core-Funktionen für Live- und Preview-Builder
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===========================================================
   SECURITY / INIT
   =========================================================== */
if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__, 2)); // zwei Ebenen hoch: /system/core → /
}
require_once BASE_PATH . '/system/config.inc.php';

// --- DB Connection ---
global $_database;
/** @var mysqli|null $_database */
$_database = $_database ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_errno) {
  http_response_code(500);
  die('DB connection failed: ' . $_database->connect_error);
}
$_database->set_charset('utf8mb4');

// --- CSRF Token ---
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

/* ===========================================================
   KONSTANTEN / GRUNDWERTE
   =========================================================== */

// Definiert die gültigen Widget-Zonen
if (!defined('NX_ZONES')) {
  define('NX_ZONES', ['top','undertop','left','maintop','mainbottom','right','bottom']);
}

/* ===========================================================
   HELPER-FUNKTIONEN
   =========================================================== */

/**
 * Sicherheit: SQL-Escape Wrapper
 */
function nx_escape(string $value): string {
  global $_database;
  return $_database->real_escape_string($value);
}

/**
 * Lädt alle registrierten Widgets aus der Tabelle settings_widgets.
 * Gibt ein Array mit widget_key, title, plugin, allowed_zones usw. zurück.
 */
function nx_load_available_widgets(): array {
  global $_database;
  $out = [];
  if (!$_database) return $out;

  $res = $_database->query("
    SELECT widget_key, COALESCE(NULLIF(title,''), widget_key) AS title,
           plugin, allowed_zones
    FROM settings_widgets
    ORDER BY title ASC
  ");
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $out[] = [
        'widget_key'   => $row['widget_key'],
        'title'        => $row['title'],
        'plugin'       => $row['plugin'],
        'allowed_zones'=> $row['allowed_zones'] ?? '',
      ];
    }
    $res->free();
  }
  return $out;
}

/**
 * Lädt alle gespeicherten Widget-Instanzen einer Seite.
 * Gibt ein Array [position => [widgets...]] zurück.
 */

function nx_load_widgets_for_page(string $page): array {
    global $_database;

    $out = [];
    if (!$_database || $_database->connect_errno) {
        echo "<div class='text-danger'>❌ DB-Verbindung ungültig</div>";
        return $out;
    }

    $stmt = $_database->prepare("
        SELECT 
            p.position,
            p.widget_key,
            p.instance_id,
            COALESCE(p.settings, '{}') AS settings,
            COALESCE(w.title, p.widget_key) AS title,
            COALESCE(w.allowed_zones, '') AS allowed_zones
        FROM settings_widgets_positions AS p
        LEFT JOIN settings_widgets AS w ON w.widget_key = p.widget_key
        WHERE p.page = ?
        ORDER BY p.position ASC, p.sort_order ASC
    ");

    if (!$stmt) {
        echo "<div class='text-danger'>❌ Prepare fehlgeschlagen: " . htmlspecialchars($_database->error) . "</div>";
        return $out;
    }

    $stmt->bind_param('s', $page);
    if (!$stmt->execute()) {
        echo "<div class='text-danger'>❌ Execute fehlgeschlagen: " . htmlspecialchars($stmt->error) . "</div>";
        $stmt->close();
        return $out;
    }

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pos = $row['position'] ?: 'unknown';
        if (!isset($out[$pos])) $out[$pos] = [];

        // Robust gegen ungültiges JSON
        $settings = [];
        $json = trim($row['settings'] ?? '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $settings = $decoded;
            } else {
                // Fehlerhafte JSONs sicher reparieren
                $settings = [];
            }
        }

        $out[$pos][] = [
            'widget_key'    => (string)$row['widget_key'],
            'instance_id'   => (string)$row['instance_id'],
            'title'         => (string)$row['title'],
            'settings'      => $settings,
            'allowed_zones' => (string)($row['allowed_zones'] ?? '')
        ];
    }

    $stmt->close();

    // 🧪 Debug optional
    if (isset($_GET['debug_builder'])) {
        echo "<pre class='small text-muted bg-light border p-2'><b>🧩 Debug nx_load_widgets_for_page({$page}):</b>\n";
        echo htmlspecialchars(print_r($out, true));
        echo "</pre>";
    }

    return $out;
}



/**
 * Gibt ein Widget-HTML aus (optional für spätere Integration)
 */
function nx_render_widget(array $widget): string {
  $title = htmlspecialchars($widget['title'] ?? 'Untitled');
  return "<div class='nx-live-item border p-2 mb-1 rounded bg-light'>{$title}</div>";
}

/**
 * Einfacher Log-Helper (optional)
 */
function nx_log(string $msg): void {
  // file_put_contents(BASE_PATH.'/logs/builder.log', '['.date('c').'] '.$msg.PHP_EOL, FILE_APPEND);
}
