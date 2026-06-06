<?php
namespace nexpell;

class NavigationUpdater
{
    public static function updateFromAdminFile(string $admin_file): string
    {
        $modulname = null;

        $result = safe_query("SELECT modulname FROM settings_plugins WHERE admin_file = '" . escape($admin_file) . "' LIMIT 1");

        if (mysqli_num_rows($result)) {
            $row = mysqli_fetch_assoc($result);
            $modulname = $row['modulname'];

            $currentTime = date('Y-m-d H:i:s');
            safe_query("UPDATE navigation_website_sub SET last_modified = '" . $currentTime . "' WHERE modulname = '" . escape($modulname) . "'");

            return '<div class="alert alert-success">✅ Navigations-Datum für <strong>' . htmlspecialchars($modulname) . '</strong> aktualisiert.</div>';
        }

        return '<div class="alert alert-danger">❌ Modulname nicht gefunden (settings_plugins.admin_file = <strong>' . htmlspecialchars($admin_file) . '</strong>).</div>';
    }
}
