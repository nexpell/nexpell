<?php
namespace nexpell;

class PluginMigrationHelper
{
    public static function tableExists(string $table): bool
    {
        $res = safe_query("SHOW TABLES LIKE '{$table}'");
        return $res && mysqli_num_rows($res) > 0;
    }

    public static function columnExists(string $table, string $column): bool
    {
        $res = safe_query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $res && mysqli_num_rows($res) > 0;
    }

    public static function indexExists(string $table, string $index): bool
    {
        $res = safe_query("SHOW INDEX FROM `{$table}` WHERE Key_name='{$index}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}
