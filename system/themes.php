<?php

class Theme
{
    /**
     * Gibt den Pfad zum aktiven Theme zurück.
     * Fallback ist "includes/themes/default/".
     */
    public function get_active_theme(): string
    {
        // Aktives Theme abrufen
        $result = safe_query("SELECT `pfad` FROM `settings_themes` WHERE `active` = 1 LIMIT 1");

        // Überprüfen, ob ein Ergebnis vorliegt
        if (!$result || mysqli_num_rows($result) === 0) {
            return "includes/themes/default/";
        }

        // Theme-Pfad auslesen und escapen
        $row = mysqli_fetch_assoc($result);
        $path = trim($row['pfad']);

        // Leerer Pfad? Fallback nutzen
        if ($path === '') {
            return "includes/themes/default/";
        }

        return "includes/themes/" . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . "/";
    }
}
