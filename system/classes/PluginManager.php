<?php

namespace nexpell;

use mysqli;

class PluginManager
{
    private $_database;
    private $_loadedAssets = [
        'plugins' => [],
        'widgets' => []
    ];

    public $cssOutput = "";
    public $jsOutput = "";

    public function __construct(mysqli $database)
    {
        $this->_database = $database;
    }

    public function plugin_data($var, $id = 0, $admin = false)
    {
        if ($id > 0) {
            $query = safe_query("SELECT * FROM settings_plugins WHERE `activate`='1' AND `pluginID`=" . intval($id));
            return mysqli_fetch_array($query);
        }

        $field = $admin ? 'admin_file' : 'index_link';
        $result = safe_query("SELECT * FROM settings_plugins WHERE `activate`='1'");
        while ($row = mysqli_fetch_array($result)) {
            $files = explode(",", $row[$field]);
            if (in_array($var, $files)) return $row;
        }
        return false;
    }

    public function plugin_updatetitle($site)
    {
        $arr = $this->plugin_data($site);
        return $arr['name'] ?? null;
    }

    public function pluginID_by_name($name)
    {
        $request = safe_query("SELECT * FROM `settings_plugins` WHERE `activate`='1' AND `name` LIKE '%" . $name . "%'");
        if (mysqli_num_rows($request)) {
            $tmp = mysqli_fetch_array($request);
            return $tmp['pluginID'];
        }
        return 0;
    }

    public function plugin_hf($id, $name)
    {
        $row = $this->plugin_data("", intval($id));
        if (!$row) return;
        $hfiles = explode(",", $row['hiddenfiles']);
        if (in_array($name, $hfiles)) {
            $file = rtrim($row['path'], '/') . '/' . $name . ".php";
            if (file_exists($file)) require_once($file);
        }
    }

    /* =========================
       WIDGET-FUNKTIONEN
       ========================= */
    /**
     * Rendert ein Widget und stellt dem Include diese Variablen bereit:
     * - $instanceId (string)
     * - $settings   (array)
     * - $title      (string)
     * - $ctx        (array)  // z.B. ['builder'=>true, 'page'=>'index', ...]
     */
    public function renderWidget(string $widgetKey, array $context = []): string
    {
        $widgetData = $this->getWidgetData($widgetKey);
        if (!$widgetData) {
            error_log("renderWidget: unknown widget_key: {$widgetKey}");
            return "";
        }

        $plugin = $widgetData['plugin'];
        if (!$this->isPluginActive($plugin)) {
            error_log("renderWidget: plugin inactive: {$plugin}");
            return "";
        }

        // Assets (CSS/JS) des Widgets laden (einmalig)
        $this->loadWidgetAssets($widgetKey);

        // Widget-Datei
        $widgetFile = $_SERVER['DOCUMENT_ROOT'] . "/includes/plugins/{$plugin}/{$widgetKey}.php";
        if (!file_exists($widgetFile)) {
            error_log("Widget-Datei nicht gefunden: {$widgetFile}");
            return "";
        }

        // Kontext-Variablen für das Include
        $instanceId = (string)($context['instanceId'] ?? '');
        $settings   = (array) ($context['settings']   ?? []);
        $title      = (string)($context['title']      ?? ($widgetData['widget_key'] ?? $widgetKey));
        $ctx        = (array) ($context['ctx']        ?? $context); // alles Weitere

        ob_start();
        include $widgetFile;  // Include erhält $instanceId, $settings, $title, $ctx
        return (string)ob_get_clean();
    }

    private function getWidgetData(string $widgetKey)
    {
        $stmt = $this->_database->prepare("SELECT widget_key, plugin FROM settings_widgets WHERE widget_key = ? LIMIT 1");
        $stmt->bind_param("s", $widgetKey);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    private function loadWidgetAssets(string $widgetKey): void
    {
        $widgetData = $this->getWidgetData($widgetKey);
        if (!$widgetData) return;

        $plugin = $widgetData['plugin'];
        $basePath = "/includes/plugins/{$plugin}";

        if (!in_array($widgetKey, $this->_loadedAssets['widgets'], true)) {
            $this->loadAsset('css', $basePath, $widgetKey);
            $this->loadAsset('js',  $basePath, $widgetKey);
            $this->_loadedAssets['widgets'][] = $widgetKey;
        }
    }

    /* =========================
       PLUGIN-FUNKTIONEN
       ========================= */
    public function loadPluginPage(string $site): ?string
    {
        $stmt = $this->_database->prepare("SELECT modulname FROM settings_plugins WHERE modulname = ? LIMIT 1");
        $stmt->bind_param("s", $site);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) return null;

        $plugin = $row['modulname'];
        $pluginFile = $_SERVER['DOCUMENT_ROOT'] . "/includes/plugins/{$plugin}/{$plugin}.php";
        return file_exists($pluginFile) ? $pluginFile : null;
    }

    private function isPluginActive(string $plugin): bool
    {
        $stmt = $this->_database->prepare("SELECT activate FROM settings_plugins WHERE modulname = ? LIMIT 1");
        $stmt->bind_param("s", $plugin);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        return !empty($row['activate']);
    }

    public function loadPluginAssets(string $plugin): void
    {
        $basePath = "/includes/plugins/{$plugin}";
        if (!in_array($plugin, $this->_loadedAssets['plugins'], true)) {
            $this->loadAsset('css', $basePath, $plugin);
            $this->loadAsset('js',  $basePath, $plugin);
            $this->_loadedAssets['plugins'][] = $plugin;
        }
    }

    /* =========================
       ASSET-HILFSFUNKTION
       ========================= */
    private function loadAsset(string $type, string $basePath, string $name): void
    {
        $file = $_SERVER['DOCUMENT_ROOT'] . "{$basePath}/{$type}/{$name}.{$type}";
        if (!file_exists($file)) return;

        if ($type === 'css') {
            $this->cssOutput .= "<link rel=\"stylesheet\" href=\"{$basePath}/{$type}/{$name}.css\">\n";
        } elseif ($type === 'js') {
            $this->jsOutput .= "<script defer src=\"{$basePath}/{$type}/{$name}.js\"></script>\n";
        }
    }

    /* =========================
       NAVIGATION & FOOTER MODULE
       ========================= */

    public function getNavigationModule(): void
    {
        global $_database;

        $stmt = $_database->prepare("SELECT modulname FROM settings_plugins WHERE modulname = ? LIMIT 1");
        $key = "navigation";
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if (!$row) {
            echo "Widget 'navigation' nicht gefunden.";
            return;
        }

        $plugin = $row['modulname'];
        $widget_path = $_SERVER['DOCUMENT_ROOT'] . "/includes/plugins/{$plugin}/widget_navigation.php";

        if (file_exists($widget_path)) {
            include $widget_path;
        } else {
            echo "Widget-Datei widget_navigation.php im Plugin {$plugin} nicht gefunden!";
        }
    }

    public function getFooterModule(): void
    {
        global $_database;

        $stmt = $_database->prepare("SELECT modulname FROM settings_plugins WHERE modulname = ? LIMIT 1");
        $key = "footer_easy";
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if (!$row) {
            echo "Widget 'footer_easy' nicht gefunden.";
            return;
        }

        $plugin = $row['modulname'];
        $widget_path = $_SERVER['DOCUMENT_ROOT'] . "/includes/plugins/{$plugin}/widget_footer_easy.php";

        if (file_exists($widget_path)) {
            include $widget_path;
        } else {
            echo "Widget-Datei widget_footer_easy.php im Plugin {$plugin} nicht gefunden!";
        }
    }
}
