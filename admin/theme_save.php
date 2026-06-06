<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/config.inc.php';

header('Content-Type: text/plain; charset=utf-8');

function nx_table_exists(mysqli $db, string $table): bool
{
    $t = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$t}'");
    return (bool)($res && $res->num_rows > 0);
}

function nx_column_exists(mysqli $db, string $table, string $column): bool
{
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return (bool)($res && $res->num_rows > 0);
}

function nx_upsert_nav_setting(mysqli $db, string $key, string $value): bool
{
    $stmt = $db->prepare("
        INSERT INTO navigation_website_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

try {
    $_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($_database->connect_error) {
        http_response_code(500);
        echo 'DB-Verbindungsfehler: ' . $_database->connect_error;
        exit;
    }
    $_database->set_charset('utf8mb4');

    $theme = trim((string)($_POST['theme'] ?? ''));
    $navbar = trim((string)($_POST['navbar'] ?? ''));

    if ($theme === '') {
        http_response_code(400);
        echo "Fehlerhafte Eingabe: 'theme' fehlt oder ist leer.";
        exit;
    }

    $navbarShadow = '';
    $navbarModus = 'light';
    if ($navbar !== '') {
        $parts = explode('|', $navbar, 2);
        if (count($parts) !== 2) {
            http_response_code(400);
            echo "Ungültiges Format für 'navbar'.";
            exit;
        }
        $navbarShadow = trim((string)$parts[0]);
        $navbarModus = trim((string)$parts[1]);
    }

    if (!nx_table_exists($_database, 'settings_themes')) {
        http_response_code(500);
        echo 'DB-Fehler: Tabelle settings_themes fehlt.';
        exit;
    }
    if (!nx_column_exists($_database, 'settings_themes', 'themename') || !nx_column_exists($_database, 'settings_themes', 'active')) {
        http_response_code(500);
        echo 'DB-Fehler: Spalten in settings_themes fehlen (themename/active).';
        exit;
    }

    $stmt = $_database->prepare('UPDATE settings_themes SET themename = ? WHERE active = 1');
    if (!$stmt) {
        http_response_code(500);
        echo 'DB-Fehler beim Speichern des Themes.';
        exit;
    }
    $stmt->bind_param('s', $theme);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        $stmt2 = $_database->prepare('UPDATE settings_themes SET active = 0');
        if ($stmt2) {
            $stmt2->execute();
            $stmt2->close();
        }

        $stmt3 = $_database->prepare('UPDATE settings_themes SET themename = ?, active = 1 ORDER BY themeID ASC LIMIT 1');
        if (!$stmt3) {
            http_response_code(500);
            echo 'DB-Fehler beim Speichern des Themes.';
            exit;
        }
        $stmt3->bind_param('s', $theme);
        $stmt3->execute();
        $stmt3->close();
    }

    if (nx_column_exists($_database, 'settings_themes', 'navbar_class') && nx_column_exists($_database, 'settings_themes', 'navbar_theme')) {
        $stmt4 = $_database->prepare('UPDATE settings_themes SET navbar_class = ?, navbar_theme = ? WHERE active = 1');
        if ($stmt4) {
            $stmt4->bind_param('ss', $navbarShadow, $navbarModus);
            $stmt4->execute();
            $stmt4->close();
        }
    }

    if (!nx_table_exists($_database, 'navigation_website_settings')) {
        http_response_code(500);
        echo 'DB-Fehler: Tabelle navigation_website_settings fehlt.';
        exit;
    }

    $settings = [
        'navbar_shadow' => $navbarShadow,
        'navbar_modus'  => $navbarModus,
        'navbar_class'  => $navbarShadow,
        'navbar_theme'  => $navbarModus,
    ];

    foreach ($settings as $key => $value) {
        if (!nx_upsert_nav_setting($_database, $key, $value)) {
            http_response_code(500);
            echo "DB-Fehler beim Speichern von {$key}.";
            exit;
        }
    }

    if (function_exists('nx_audit_action')) {
        nx_audit_action('theme_installer', 'audit_action_theme_activated', $theme, null, 'admincenter.php?site=theme_installer', ['theme' => $theme]);
        nx_audit_action('webside_navigation', 'audit_action_website_navigation_settings_saved', null, null, 'admincenter.php?site=webside_navigation', ['settings' => array_keys($settings)]);
    }

    echo 'OK';
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Save-Fehler: ' . $e->getMessage();
}
