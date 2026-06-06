<?php
namespace nexpell;

class DatabaseMigrationHelper
{
    private array $log = [];

    public function log(string $message): void
    {
        $this->log[] = date('[Y-m-d H:i:s] ') . $message;
    }

    public function getLog(): string
    {
        $html = '';
        foreach ($this->log as $entry) {
            if (stripos($entry, '⚠️') !== false) {
                $html .= "<div class='alert alert-warning py-2 my-1'><i class='bi bi-exclamation-triangle-fill me-2'></i>" . htmlspecialchars($entry) . "</div>";
            } elseif (stripos($entry, '❌') !== false || stripos($entry, 'SQL-Fehler') !== false) {
                $html .= "<div class='alert alert-danger py-2 my-1'><i class='bi bi-x-octagon-fill me-2'></i>" . htmlspecialchars($entry) . "</div>";
            } else {
                $html .= "<div class='alert alert-secondary py-1 my-1 small'>" . htmlspecialchars($entry) . "</div>";
            }
        }
        return $html;
    }

    public function getLogHtml(): string
    {
        $out = '';
        foreach ($this->log as $entry) {
            if (stripos($entry, '✅') !== false) {
                $out .= "<div class='text-success small py-1'>{$entry}</div>";
            } elseif (stripos($entry, '⚠️') !== false) {
                $out .= "<div class='text-warning small py-1'>{$entry}</div>";
            } elseif (stripos($entry, '❌') !== false) {
                $out .= "<div class='text-danger small py-1'>{$entry}</div>";
            } else {
                $out .= "<div class='text-muted small py-1'>{$entry}</div>";
            }
        }
        return $out;
    }

    // ======= Utilitys =======

    public function columnExists(string $table, string $column): bool
    {
        global $_database;
        $table = $_database->real_escape_string($table);
        $column = $_database->real_escape_string($column);
        $res = $_database->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }

    public function addColumnIfNotExists(string $table, string $columnDefinition): void
    {
        preg_match('/^`?(\w+)`?\s/i', $columnDefinition, $m);
        $column = $m[1] ?? null;
        if ($column && !$this->columnExists($table, $column)) {
            $this->runQuery("ALTER TABLE `$table` ADD COLUMN $columnDefinition");
            $this->log("Spalte '$column' zu '$table' hinzugefügt.");
        } else {
            $this->log("Spalte '$column' in '$table' bereits vorhanden.");
        }
    }

    public function dropColumnIfExists(string $table, string $column): void
    {
        if ($this->columnExists($table, $column)) {
            $this->runQuery("ALTER TABLE `$table` DROP COLUMN `$column`");
            $this->log("Spalte '$column' von '$table' entfernt.");
        } else {
            $this->log("Spalte '$column' in '$table' nicht vorhanden – wird übersprungen.");
        }
    }

    public function tableExists(string $table): bool
    {
        global $_database;
        $table = $_database->real_escape_string($table);
        $res = $_database->query("SHOW TABLES LIKE '$table'");
        return $res && $res->num_rows > 0;
    }

    public function createTableIfNotExists(string $tableSQL): void
    {
        $this->runQuery($tableSQL);
        preg_match('/CREATE TABLE IF NOT EXISTS `?(\w+)`?/i', $tableSQL, $m);
        $table = $m[1] ?? 'unbekannt';
        $this->log("Tabelle '$table' überprüft bzw. erstellt.");
    }

    public function escapeIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }

    public function dropTableIfExists(string $table): void
    {
        $table = $this->escapeIdentifier($table);
        $this->runQuery("DROP TABLE IF EXISTS `$table`;");
        $this->log("🗑️ Tabelle '$table' gelöscht (falls vorhanden).");
    }

    // =====================================================
    // Hauptfunktion: SQL ausführen mit intelligentem Auto-Fix
    // =====================================================
    public function runQuery(string $query)
    {
        global $_database;

        $query = trim($query);
        if ($query === '') return false;

        // 🧩 Operation & Tabellenname extrahieren
        // 🧩 Operation & Tabellenname extrahieren
        $operation = strtoupper(strtok($query, ' '));
        // 🧠 Befehle ohne Tabellenbezug (z. B. PREPARE / EXECUTE / DEALLOCATE)
        $nonTableOps = ['PREPARE', 'EXECUTE', 'DEALLOCATE', 'SET', 'USE', 'SHOW'];
        if (in_array($operation, $nonTableOps, true)) {
            try {
                $result = $_database->query($query);
                $this->log("✅ $operation-Anweisung erfolgreich ausgeführt.");
                return $result;
            } catch (\Throwable $e) {
                $this->log("❌ Fehler bei $operation: " . $e->getMessage());
                return false;
            }
        }
        $table = 'unbekannt';

        // 🔍 erweitert: berücksichtigt "CREATE TABLE IF NOT EXISTS"
        if (preg_match('/\b(?:INTO|TABLE|UPDATE|FROM)\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([\w\d_]+)`?/i', $query, $m)) {
            $table = $m[1];
        }

        // 🧩 Meta-Info für CREATE TABLE
        $metaInfo = '';
        if ($operation === 'CREATE' && preg_match('/CREATE\s+TABLE/i', $query)) {
            $cols = substr_count($query, "\n  `");
            $engine = '';
            if (preg_match('/ENGINE\s*=\s*([a-zA-Z0-9_]+)/i', $query, $em)) {
                $engine = $em[1];
            }
            if (preg_match('/CHARSET\s*=\s*([a-zA-Z0-9_]+)/i', $query, $cm)) {
                $charset = $cm[1];
                $metaInfo = "– $cols Spalten, ENGINE=$engine, CHARSET=$charset";
            } else {
                $metaInfo = "– $cols Spalten";
            }
        }

        // 🧩 Meta-Info für ALTER TABLE
        elseif ($operation === 'ALTER') {
            if (preg_match('/ADD COLUMN\s+`?(\w+)`?/i', $query, $am)) {
                $metaInfo = "– Spalte {$am[1]} hinzugefügt";
            } elseif (preg_match('/MODIFY\s+`?(\w+)`?/i', $query, $mm)) {
                $metaInfo = "– Spalte {$mm[1]} geändert";
            } elseif (preg_match('/DROP COLUMN\s+`?(\w+)`?/i', $query, $dm)) {
                $metaInfo = "– Spalte {$dm[1]} gelöscht";
            }
        }

        // 🧩 Meta-Info für INSERT
        elseif ($operation === 'INSERT' || $operation === 'REPLACE') {
            $count = substr_count($query, '),(') + 1;
            $metaInfo = "– $count Datensatz" . ($count > 1 ? 'e' : '') . " eingefügt";
        }

        // --- Ausführung ---
        try {
            $result = $_database->query($query);

            if ($result === false) {
                $error = $_database->error;
                if (stripos($error, 'Duplicate entry') !== false) {
                    $this->log("⚠️ Duplikat in '$table' übersprungen ($error)");
                    return false;
                }
                $this->log("❌ SQL-Fehler in '$table': $error | Query: $query");
                return false;
            }

            // ✅ Erfolgreiches Logging mit Detailinfo
            $this->log("✅ $operation → '$table' erfolgreich ausgeführt. $metaInfo");
            return $result;
        } catch (\Throwable $e) {
            $this->log("❌ Ausnahme in '$table': " . $e->getMessage());
            return false;
        }
    }



    /** Kompatibilität */
    public function run(string $query)
    {
        return $this->runQuery($query);
    }

    public function escape(string $value): string
    {
        global $_database;
        return $_database->real_escape_string($value);
    }

    public function query(string $sql)
    {
        return $this->runQuery($sql);
    }

    
}
