<?php

namespace nexpell;

class ThemeUninstaller
{
    private array $log = [];

    public function uninstall(string $theme_folder): void
    {
        global $_database;

        $theme_dir = '../includes/themes/default/css/dist/' . $theme_folder;

        if (is_dir($theme_dir)) {
            if (deleteFolder($theme_dir)) {
                $this->log[] = ['type' => 'success', 'message' => "Ordner $theme_folder wurde gelöscht."];
            } else {
                $this->log[] = ['type' => 'danger', 'message' => "Fehler beim Löschen des Ordners $theme_folder."];
            }
        } else {
            $this->log[] = ['type' => 'warning', 'message' => "Ordner $theme_folder nicht gefunden."];
        }

        $theme_folder = $_database->real_escape_string($theme_folder);

        // Theme-Eintrag in DB löschen
        safe_query("DELETE FROM settings_themes_installed WHERE modulname = '" . $theme_folder . "'");
        $this->log[] = ['type' => 'info', 'message' => "Eintrag in der Datenbank gelöscht."];
    }

    public function getLog(): array
    {
        return $this->log;
    }
}
