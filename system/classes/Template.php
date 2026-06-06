<?php

class Template
{
    public string $themes_path;
    public string $template_path;
    public string $plugins_path;
    public string $admin_path;
    public string $theme;

    public function __construct(
        string $themes_path = 'includes/themes/',
        string $template_path = 'templates/',
        string $plugins_path = 'includes/plugins/',
        string $admin_path = 'templates/',
        string $theme = '' // Default Theme-Name
    ) {
        // Korrekte Pfad-Kombination, ohne doppeltes Theme-Verzeichnis
        $this->themes_path = rtrim($themes_path, '/') . '/';
        $this->template_path = rtrim($template_path, '/') . '/';
        $this->plugins_path = rtrim($plugins_path, '/') . '/';
        $this->admin_path = rtrim($admin_path, '/') . '/';
        $this->theme = $theme; // Nur der Name des Themes
    }


    // Pfad zum Template je nach Quelle bestimmen
    private function getTemplatePath(string $file, string $block, string $source, string $plugin_name): string
    {
        switch ($source) {
            case "plugin":
    if (empty($plugin_name)) {
        // Plugin-Name dynamisch ermitteln (z. B. aus Stacktrace)
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && preg_match("#includes/plugins/([^/]+)/#", $trace['file'], $matches)) {
                $plugin_name = $matches[1];
                break;
            }
        }
    }

    if (!$plugin_name) {
        throw new Exception("Plugin-Name konnte nicht automatisch ermittelt werden (file=$file, block=$block).");
    }

    // === Reihenfolge der Kandidaten-Dateien ===
    $candidates = [
        BASE_PATH . "/includes/plugins/{$plugin_name}/templates/{$file}.html",
        BASE_PATH . "/includes/plugins/{$plugin_name}/templates/{$block}.html",
        BASE_PATH . "/includes/plugins/{$plugin_name}/templates/default.html"
    ];

    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    throw new Exception(
        "Template-Datei nicht gefunden: " . implode(' ', $candidates)
    );


            case 'admin':
                $path = $this->admin_path . $file . '.html';
                if (!file_exists($path)) {
                    if (defined('NXB_DEBUG') && NXB_DEBUG) {
                        throw new \Exception("Admin-Template-Datei nicht gefunden: $path");
                    }
                    return "<!-- Missing admin template: $path -->";
                }
                return $path;

            case 'theme':
                $path = $this->themes_path . rtrim($this->theme, '/') . '/' . $this->template_path . $file . '.html';
                if (!file_exists($path)) {
                    if (defined('NXB_DEBUG') && NXB_DEBUG) {
                        throw new \Exception("Theme-Template nicht gefunden: $path");
                    }
                    return "<!-- Missing theme template: $path -->";
                }
                return $path;

            default:
                throw new \Exception("Unbekannte Template-Quelle: $source");
        }
    }


    private function parseConditions(string $template, array $data): string
    {
        while (strpos($template, '{if ') !== false) {
            $template = $this->processConditionBlock($template, $data);
        }
        return $template;
    }

    private function parseConditionals($content, $data) {
        // if ... else ... endif
        $pattern = '/{\s*if\s+(.*?)\s*}}(.*?)({\s*else\s*}}(.*?))?{\s*endif\s*}}/s';
        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $condition = trim($matches[1]);
            $if_true = $matches[2];
            $if_false = $matches[4] ?? '';

            // Unterstützt einfache Variable ohne Logik
            $value = $data[$condition] ?? false;
            return $value ? $if_true : $if_false;
        }, $content);
    }

    private function processConditionBlock(string $template, array $data): string
    {
        $start = strpos($template, '{if ');
        if ($start === false) return $template;

        $end = $this->findMatchingEndif($template, $start);
        if ($end === false) return $template;

        $block = substr($template, $start, $end - $start + strlen('{endif}'));

        // Extrahiere Bedingung
        #if (!preg_match('/{\s*if\s+(\w+)\s*}/', $block, $m)) return $template;
        if (!preg_match('/{\s*if\s+(\w+)\s*}/', $block, $m)) return $template;
        $key = $m[1];

        // Zerlege in Inhalt
        $inner = preg_replace('/^{\s*if\s+\w+\s*}|{\s*endif\s*}$/', '', $block);
        $parts = explode('{else}', $inner, 2);
        $trueBlock = $parts[0];
        $falseBlock = $parts[1] ?? '';

        $value = $data[$key] ?? false;
        $result = $value ? $trueBlock : $falseBlock;

        // Rekursiv auf geschachtelte Bedingungen anwenden
        $result = $this->parseConditions($result, $data);

        return substr($template, 0, $start) . $result . substr($template, $end + strlen('{endif}'));
    }

    private function findMatchingEndif(string $template, int $offset): int|false {
        $level = 1;
        $pos = $offset;

        while ($level > 0) {
            $ifPos = strpos($template, '{if ', $pos);
            $endifPos = strpos($template, '{endif}', $pos);

            if ($endifPos === false) return false;

            if ($ifPos !== false && $ifPos < $endifPos) {
                $level++;
                $pos = $ifPos + 4;
            } else {
                $level--;
                if ($level === 0) {
                    return $endifPos;
                }
                $pos = $endifPos + 7;
            }
        }

        return false;
    }

    private function evaluateCondition(string $condition, array $vars): bool {
        $condition = trim($condition);

        if (preg_match('/^(.+?)\s*(==|!=|>=|<=|>|<)\s*(.+)$/', $condition, $matches)) {
            $leftKey = trim($matches[1]);
            $operator = $matches[2];
            $rightRaw = trim($matches[3]);

            $leftVal = $this->getNestedValue($vars, $leftKey);
            $rightVal = $this->normalizeValue($rightRaw);

            error_log("Vergleich: '$leftVal' $operator '$rightVal'");

            switch ($operator) {
                case '==': return $leftVal == $rightVal;
                case '!=': return $leftVal != $rightVal;
                case '>':  return $leftVal > $rightVal;
                case '<':  return $leftVal < $rightVal;
                case '>=': return $leftVal >= $rightVal;
                case '<=': return $leftVal <= $rightVal;
            }
        } else {
            $val = $this->getNestedValue($vars, $condition);

            error_log("Wert für '$condition': " . var_export($val, true));

            if (is_string($val)) {
                $valLower = strtolower($val);
                if (in_array($valLower, ['1', 'true', 'yes'], true)) {
                    return true;
                }
                if (in_array($valLower, ['0', 'false', 'no', ''], true)) {
                    return false;
                }
            }

            return (bool)$val;
        }
    }

    private function normalizeValue(string $raw) {
        // Entferne Anführungszeichen
        if ((str_starts_with($raw, '"') && str_ends_with($raw, '"')) ||
            (str_starts_with($raw, "'") && str_ends_with($raw, "'"))) {
            $raw = substr($raw, 1, -1);
        }

        // Spezialfälle für boolsche Strings
        $lower = strtolower($raw);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if (is_numeric($raw)) return $raw + 0;

        return $raw;
    }

    // Prüft, ob ein Wert als "true" gelten soll
    private function isTruthy($val): bool {
        if (is_string($val)) {
            $val = trim($val);
            return $val !== '' && $val !== '0' && strtolower($val) !== 'false';
        }
        return !empty($val);
    }

    // Hilfsfunktion zum Zugriff auf verschachtelte Werte (wie schon in deiner Klasse)
    private function getNestedValue(array $array, string $path) {
        $parts = explode('.', $path);
        foreach ($parts as $part) {
            if (is_array($array) && array_key_exists($part, $array)) {
                $array = $array[$part];
            } else {
                error_log("Key '$part' nicht gefunden im Array für Pfad '$path'");
                return null;
            }
        }
        error_log("getNestedValue('$path') = " . var_export($array, true));
        return $array;
    }

    private function parseIfBlocks(string $template, array $vars): string {
        while (preg_match('/{if\s+([^}]+)}/i', $template, $ifMatch, PREG_OFFSET_CAPTURE)) {
            $startPos = $ifMatch[0][1];
            $condition = $ifMatch[1][0];

            $offset = $startPos + strlen($ifMatch[0][0]);
            $endPos = $this->findMatchingEndif($template, $offset);
            if ($endPos === false) break;

            $innerContent = substr($template, $offset, $endPos - $offset);
            $elsePosInBlock = $this->findElseInIfBlock($innerContent);

            if ($elsePosInBlock !== false) {
                $truePart = substr($innerContent, 0, $elsePosInBlock);
                $falsePart = substr($innerContent, $elsePosInBlock + 6);
            } else {
                $truePart = $innerContent;
                $falsePart = '';
            }

            $fullBlock = substr($template, $startPos, $endPos - $startPos + 7);
            $condEval = $this->evaluateCondition($condition, $vars);
            $replaceWith = $condEval ? $truePart : $falsePart;

            // Rekursiv auf Inhalt anwenden (wichtig für verschachtelte IFs)
            $replaceWith = $this->parseIfBlocks($replaceWith, $vars);
            $template = substr_replace($template, $replaceWith, $startPos, strlen($fullBlock));
        }

        return $template;
    }

    public function loadTemplate(string $file, string $block, array $replaces = [], string $source = 'theme', string $plugin_name = ''): string
    {
        // Template-Pfad bestimmen
        $path = $this->getTemplatePath($file, $block, $source, $plugin_name);

        if (!file_exists($path)) {
            throw new \Exception("Template-Datei nicht gefunden: $path");
        }

        $content = file_get_contents($path);

        $start_marker = "<!-- " . $file . "_" . $block . " -->";
        $parts = explode($start_marker, $content);

        if (!isset($parts[1])) {
            throw new \Exception("Blockmarker '$start_marker' nicht gefunden!");
        }

        $block_parts = explode("<!-- END -->", $parts[1]);
        $template_block = isset($block_parts[0]) ? $block_parts[0] : '';

        if (trim($template_block) === '') {
            throw new \Exception("Der Template-Block '$file -> $content' ist leer oder fehlt.");
        }

        // 1. Zuerst foreach-Schleifen verarbeiten (kann neue if-Blöcke enthalten)
        $template_block = $this->parseForeach($template_block, $replaces);

        // 2. Dann if-Blöcke verarbeiten (nachdem foreach-Inhalte eingesetzt wurden)
        $template_block = $this->parseIfBlocks($template_block, $replaces);

        // 3. Zuletzt einfache Platzhalter ersetzen (z. B. {title}, {link.title})
        if (!empty($replaces)) {
            $converted = [];
            foreach ($replaces as $key => $value) {
                // Nur skalare Werte direkt ersetzen
                if (is_scalar($value)) {
                    $converted["{" . $key . "}"] = (string)$value;
                }
            }
            $template_block = strtr($template_block, $converted);
        }

        return $template_block;
    }

    private function parseForeachBlock(string $template, string $varName, array $items): string {
        if (preg_match('/{foreach\s+' . preg_quote($varName, '/') . '\s+as\s+(\w+)}/', $template, $match, PREG_OFFSET_CAPTURE)) {
            $blockVar = $match[1][0];
            $startPos = $match[0][1] + strlen($match[0][0]);
            $endPos = strpos($template, '{/foreach}', $startPos);
            if ($endPos === false) return $template;

            $innerBlock = substr($template, $startPos, $endPos - $startPos);
            $rendered = '';

            foreach ($items as $item) {
                $scopedVars = [$blockVar => $item];
                $content = $this->parseIfBlocks($innerBlock, $scopedVars);
                $rendered .= $this->replaceVars($content, $scopedVars);
            }

            $fullBlock = substr($template, $match[0][1], ($endPos + 10) - $match[0][1]);
            return str_replace($fullBlock, $rendered, $template);
        }

        return $template;
    }

    private function parseIfStatements($tpl, $data) {
        return preg_replace_callback('/{if\s+([^}]+)}(.*?)(?:{else}(.*?))?{endif}/is', function($matches) use ($data) {
            $condition = trim($matches[1]);
            $ifBlock = $matches[2];
            $elseBlock = isset($matches[3]) ? $matches[3] : '';

            // Ersetze Variablen im Condition-String
            $conditionEvaluated = preg_replace_callback('/\b([\w.]+)\b/', function($varMatch) use ($data) {
                $varPath = explode('.', $varMatch[1]);
                $value = $data;
                foreach ($varPath as $key) {
                    if (is_array($value) && isset($value[$key])) {
                        $value = $value[$key];
                    } else {
                        return 'null';
                    }
                }

                // Booleans und Zahlen korrekt darstellen
                if (is_bool($value)) return $value ? 'true' : 'false';
                if (is_numeric($value)) return $value;
                return '"' . addslashes($value) . '"';
            }, $condition);

            // Sicher auswerten
            $result = false;
            try {
                $evalCode = 'return ' . $conditionEvaluated . ';';
                $result = eval($evalCode);
            } catch (\Throwable $e) {
                $result = false;
            }

            return $result ? $ifBlock : $elseBlock;
        }, $tpl);
    }

    private function parseForeach(string $template, array $vars): string {
        while (preg_match('/{foreach\s+(\S+)\s+as\s+(\S+)}/', $template, $match, PREG_OFFSET_CAPTURE)) {
            $fullMatch = $match[0][0];
            $startPos = $match[0][1];
            $arrayName = $match[1][0];
            $itemName = $match[2][0];

            $endTag = '{/foreach}';
            $endPos = strpos($template, $endTag, $startPos);
            if ($endPos === false) break;

            $innerStart = $startPos + strlen($fullMatch);
            $innerLength = $endPos - $innerStart;
            $innerContent = substr($template, $innerStart, $innerLength);

            $replacement = '';
            if (isset($vars[$arrayName]) && is_array($vars[$arrayName])) {
                foreach ($vars[$arrayName] as $item) {
                    $processed = $innerContent;

                    // Variablenersetzung im aktuellen foreach-Block
                    foreach ($item as $key => $value) {
                        if (is_scalar($value)) {
                            $processed = str_replace("{" . $itemName . "." . $key . "}", (string)$value, $processed);
                        }
                    }

                    // Rekursive Verarbeitung von verschachtelten Bedingungen/Loops innerhalb des Blocks
                    $processed = $this->parseIfBlocks($processed, [$itemName => $item]);
                    $processed = $this->parseForeach($processed, [$itemName => $item]);

                    $replacement .= $processed;
                }
            }

            $fullLength = $endPos + strlen($endTag) - $startPos;
            $template = substr_replace($template, $replacement, $startPos, $fullLength);
        }

        return $template;
    }

    private function replaceVariables(string $template, array $vars, ?string $prefix = null): string {
        return preg_replace_callback('/\{'.($prefix ? preg_quote($prefix) : '').'(?:\.([^\}]+))?\}/', function($matches) use ($vars, $prefix) {
            if (isset($matches[1])) {
                $keys = explode('.', $matches[1]);
                $value = $vars;
                foreach ($keys as $key) {
                    if (is_array($value) && array_key_exists($key, $value)) {
                        $value = $value[$key];
                    } else {
                        return $matches[0]; // Variable nicht gefunden, Original lassen
                    }
                }
                return is_scalar($value) ? (string)$value : $matches[0];
            } else {
                if ($prefix !== null) {
                    // Nur der Prefix vorhanden, z.B. {item}
                    if (array_key_exists($prefix, $vars) && is_scalar($vars[$prefix])) {
                        return (string)$vars[$prefix];
                    }
                    return $matches[0];
                } else {
                    // Variable ohne Punkt, z.B. {key}
                    if (array_key_exists($matches[0], $vars)) {
                        $val = $vars[$matches[0]];
                        return is_scalar($val) ? (string)$val : $matches[0];
                    }
                    return $matches[0];
                }
            }
        }, $template);
    }

    private function findElseInIfBlock(string $block): int|false {
        $level = 0;
        $offset = 0;

        while (true) {
            $ifPos = strpos($block, '{if ', $offset);
            $elsePos = strpos($block, '{else}', $offset);
            $endifPos = strpos($block, '{endif}', $offset);

            if ($elsePos === false && $endifPos === false) break;

            if ($ifPos !== false && $ifPos < $elsePos && $ifPos < $endifPos) {
                $level++;
                $offset = $ifPos + 4;
            } elseif ($elsePos !== false && ($endifPos === false || $elsePos < $endifPos)) {
                if ($level === 0) {
                    return $elsePos;
                }
                $offset = $elsePos + 6;
            } elseif ($endifPos !== false) {
                if ($level > 0) {
                    $level--;
                }
                $offset = $endifPos + 7;
            } else {
                break;
            }
        }

        return false;
    }

    private static ?Template $instance = null;

    public static function setInstance(Template $tpl): void
    {
        self::$instance = $tpl;
    }

    public static function getInstance(): Template
    {
        if (!self::$instance) {
            throw new \Exception("Template-Instanz wurde noch nicht gesetzt.");
        }
        return self::$instance;
    }


    public function renderPagination(string $baseUrl, int $currentPage, int $totalPages, string $pageParam = 'page'): string
    {
        if ($totalPages <= 1) {
            return ''; // Keine Pagination nötig
        }

        $html = '<nav><ul class="pagination pagination-sm justify-content-center mt-4">';

        // Zurück-Button
        $prevDisabled = ($currentPage <= 1) ? ' disabled' : '';
        $prevPage = max(1, $currentPage - 1);
        $html .= '<li class="page-item' . $prevDisabled . '">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($baseUrl) . '&' . $pageParam . '=' . $prevPage . '" aria-label="Previous">&laquo;</a>';
        $html .= '</li>';

        // Seiten-Buttons
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i === $currentPage) ? ' active' : '';
            $html .= '<li class="page-item' . $active . '">';
            $html .= '<a class="page-link" href="' . htmlspecialchars($baseUrl) . '&' . $pageParam . '=' . $i . '">' . $i . '</a>';
            $html .= '</li>';
        }

        // Weiter-Button
        $nextDisabled = ($currentPage >= $totalPages) ? ' disabled' : '';
        $nextPage = min($totalPages, $currentPage + 1);
        $html .= '<li class="page-item' . $nextDisabled . '">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($baseUrl) . '&' . $pageParam . '=' . $nextPage . '" aria-label="Next">&raquo;</a>';
        $html .= '</li>';

        $html .= '</ul></nav>';

        return $html;
    }

}
