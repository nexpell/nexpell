<?php


namespace nexpell\logging;

class AdminLogger
{
    public static function log(
        int $adminID,
        string $action_text,
        string $module,
        int $affected_id = 0,
        ?string $old_value = null,
        ?string $new_value = null,
        ?string $ip_address = null,
        ?int $timestamp = null,
        string $affected_table = ''
    ): void
    {
        $action_codes = [
            'Erstellen' => 1,
            'Bearbeiten' => 2,
            'Löschen' => 3,
            'Login' => 4,
            'Logout' => 5,
        ];

        $action = $action_codes[$action_text] ?? 0;
        $ip = $ip_address ?? $_SERVER['REMOTE_ADDR'];
        $timestamp = time();

        // Escape Strings sicher
        $module = escape($module);
        $affected_table = escape($affected_table);
        $old_value = $old_value !== null ? "'" . escape($old_value) . "'" : 'NULL';
        $new_value = $new_value !== null ? "'" . escape($new_value) . "'" : 'NULL';
        $ip = "'" . escape($ip) . "'";
        // Zeitstempel in DATETIME konvertieren
        $datetime = date('Y-m-d H:i:s', $ts);

        // safe_query ohne prepared statement
        safe_query("
            INSERT INTO admin_audit_log (
                adminID, action, module, affected_table, affected_id,
                old_value, new_value, ip_address, timestamp
            ) VALUES (
                $adminID,
                $action,
                '$module',
                '$affected_table',
                $affected_id,
                $old_value,
                $new_value,
                $ip,
                $datetime
            )
        ");
    }





    public static function updateWithLog(
    string $table,
    string $id_column,
    int $affected_id,
    array $new_data,
    string $action_text,
    string $module,
    int $adminID
): void {
    // Sicherheitsmaßnahmen
    $table_escaped = escape($table);
    $id_column_escaped = escape($id_column);
    $affected_id = (int)$affected_id;

    // Alte Daten holen
    $old_result = safe_query("SELECT * FROM `$table_escaped` WHERE `$id_column_escaped` = $affected_id");
    $old_row = mysqli_fetch_assoc($old_result) ?? [];

    // JSON-Werte sicher kodieren und escapen
    $old_value = $old_row ? "'" . escape(json_encode($old_row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "'" : 'NULL';
    if ($old_value === false) {
        // Fehlerbehandlung bei json_encode
        $old_value = 'NULL';
    }
    $new_value = "'" . escape(json_encode($new_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "'";

    // Logging vorbereiten
    $action_codes = [
        'Erstellen' => 1,
        'Bearbeiten' => 2,
        'Löschen' => 3,
        'Login' => 4,
        'Logout' => 5,
    ];
    $action = (int)($action_codes[$action_text] ?? 0);
    $ip = "'" . escape($_SERVER['REMOTE_ADDR']) . "'";
    $timestamp = time();

    $adminID = (int)$adminID;
    $module_escaped = "'" . escape($module) . "'";
    $table_escaped_literal = "'" . $table_escaped . "'"; // Nur für das INSERT, nicht fürs SELECT!
    // Zeitstempel in DATETIME konvertieren
    $datetime = date('Y-m-d H:i:s', $timestamp);

    // Log speichern mit Parameterbindung
    $stmt = $_database->prepare("
        INSERT INTO admin_audit_log (
            adminID, action, module, affected_table,
            affected_id, old_value, new_value, ip_address, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iississss",
        $adminID,
        $action,
        $module_escaped,
        $table_escaped_literal,
        $affected_id,
        $old_value,
        $new_value,
        $ip,
        $datetime
    );
    $stmt->execute();
}







    public static function fetchOldData(string $table, string $primaryKey, int $id, array $fields): array
    {
        // Sicherheitsmaßnahmen: Escaping der Tabelle und des Primary Keys
        $escaped_table = escape($table);
        $escaped_primaryKey = escape($primaryKey);
        $id = (int)$id;

        // Alle Felder escapen
        $escaped_fields = array_map('escape', $fields);
        $columns = implode(", ", $escaped_fields);

        // Query ausführen
        $result = safe_query("SELECT $columns FROM `$escaped_table` WHERE `$escaped_primaryKey` = $id");

        // Rückgabe der Daten oder leeres Array, wenn keine gefunden wurden
        return (mysqli_num_rows($result)) ? mysqli_fetch_assoc($result) : [];
    }





}