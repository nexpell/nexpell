<?php
namespace nexpell;

class MigrationDiffHelper
{
    private \mysqli $db;
    private DatabaseMigrationHelper $migrator;
    private array $steps_log = [];

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->migrator = new DatabaseMigrationHelper($db);
    }

    public function tableExists(string $table): bool
    {
        $res = $this->db->query("SHOW TABLES LIKE '{$table}'");
        return ($res && $res->num_rows > 0);
    }

    public function applyChanges(array $diff): void
    {
        foreach ($diff['add'] as $table => $cols) {
            if (!$this->tableExists($table)) {
                $columns = [];
                foreach ($cols as $col => $def) $columns[] = "`$col` $def";
                $sql = "CREATE TABLE `$table` (\n  " . implode(",\n  ", $columns) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                $this->migrator->run($sql);
                $this->steps_log[] = "<div class='alert alert-success py-1 my-1 small'>🧱 Tabelle <b>$table</b> neu erstellt.</div>";
            } else {
                $this->steps_log[] = "<div class='text-muted small'>ℹ️ Tabelle <b>$table</b> existiert bereits – übersprungen.</div>";
            }
        }

        foreach ($diff['change'] as $table => $chg) {
            if (!$this->tableExists($table)) {
                $this->steps_log[] = "<div class='alert alert-warning small'>⚠️ Tabelle <b>$table</b> nicht gefunden – übersprungen.</div>";
                continue;
            }

            $alterParts = [];

            foreach (($chg['add'] ?? []) as $c => $def) {
                if (!$this->migrator->columnExists($table, $c)) {
                    $alterParts[] = "ADD COLUMN `$c` $def";
                    $this->steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>$c</code> in <b>$table</b> hinzugefügt.</div>";
                }
            }

            foreach (($chg['mod'] ?? []) as $c => $def) {
                if ($this->migrator->columnExists($table, $c)) {
                    $alterParts[] = "MODIFY COLUMN `$c` $def";
                    $this->steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>$c</code> in <b>$table</b> geändert.</div>";
                }
            }

            if ($alterParts) {
                $sql = "ALTER TABLE `$table`\n  " . implode(",\n  ", $alterParts) . ";";
                $this->migrator->run($sql);
            }
        }
    }

    public function getLog(): string
    {
        return implode("\n", $this->steps_log);
    }
}
