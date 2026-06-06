<?php
namespace nexpell;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class CMSUpdater
{
    private array $log = [];

    public function runUpdates(): string
    {
        $this->logMsg("🚀 CMS-Update gestartet...");

        $lockfile = __DIR__ . '/../../system/update_lock.txt';
        $versionFile = __DIR__ . '/../../system/version.php';
        $currentVersion = file_exists($versionFile) ? include $versionFile : '1.0.0';

        // 1️⃣ Stop bei 1.0.1 (alter Updater)
        if (($currentVersion === '1.0.1') || (file_exists($lockfile) && version_compare($currentVersion, '1.0.1', '<='))) {
            $this->logMsg("⛔ Update gestoppt – Lockdatei erkannt oder Version {$currentVersion} blockiert weitere Updates.");
            return $this->renderLog();
        }

        // 2️⃣ Lock entfernen wenn neuer Updater aktiv
        if (file_exists($lockfile) && version_compare($currentVersion, '1.0.1', '>')) {
            @unlink($lockfile);
            $this->logMsg("🔓 Lockdatei entfernt – neuer Updater erkannt (Version {$currentVersion}).");
        }

        // 🔄 Migrationen
        $this->runMigrations();

        // 📦 Systemdateien
        $this->updateCoreFiles();

        // 🔄 Statistiken loggen
        $this->sendUpdateStats($currentVersion);

        // 🧹 TMP löschen
        $this->cleanupTmp();

        $this->logMsg("✅ Update abgeschlossen.");
        return $this->renderLog();
    }

    /**
     * 📡 Statistiken an update.nexpell.de senden
     */
    private function sendUpdateStats(string $oldVersion): void
    {
        $site = $_SERVER['SERVER_NAME'] ?? 'unknown';
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Versionsdatei lesen nach Update
        $versionFile = __DIR__ . '/../../system/version.php';
        $newVersion = file_exists($versionFile) ? include $versionFile : $oldVersion;

        $url = "https://update.nexpell.de/system/download.php"
             . "?type=update"
             . "&file=" . rawurlencode("core_update_{$newVersion}.zip")
             . "&version_old=" . rawurlencode($oldVersion)
             . "&version_new=" . rawurlencode($newVersion)
             . "&site=" . rawurlencode($site)
             . "&ip=" . rawurlencode($ip);

        $this->logMsg("🌐 Sende Update-Statistik an update.nexpell.de ...");

        $res = @file_get_contents($url);

        if ($res !== false) {
            $this->logMsg("📊 Update-Statistik erfolgreich übermittelt.");
        } else {
            $this->logMsg("⚠️ Update-Statistik konnte nicht gesendet werden.");
        }
    }

    /**
     * Systemdateien aktualisieren
     */
    private function updateCoreFiles(): void
    {
        $this->logMsg("🧩 Systemdateien aktualisieren...");

        $source = __DIR__ . '/../../admin/tmp/system/classes/';
        $target = __DIR__ . '/';

        $files = [
            'CMSUpdater.php',
            'DatabaseMigrationHelper.php'
        ];

        foreach ($files as $file) {
            $src = $source . $file;
            $dst = $target . $file;

            if (file_exists($src)) {
                if (@copy($src, $dst)) {
                    $this->logMsg("✅ $file → erfolgreich nach /system/classes/ kopiert.");
                } else {
                    $this->logMsg("❌ Fehler: $file konnte nicht kopiert werden!");
                }
            } else {
                $this->logMsg("ℹ️ $file nicht im Updatepaket gefunden – übersprungen.");
            }
        }
    }

    /**
     * Migrationen ausführen
     */
    private function runMigrations(): void
    {
        $this->logMsg("🔄 Migrationen ausführen...");

        $migrationDir = __DIR__ . '/../../admin/tmp/migrations/';
        if (!is_dir($migrationDir)) {
            $this->logMsg("⚠️ Kein Migrationsordner gefunden ($migrationDir).");
            return;
        }

        $migrations = glob($migrationDir . '*.php');
        if (!$migrations) {
            $this->logMsg("ℹ️ Keine Migrationsdateien gefunden.");
            return;
        }

        sort($migrations, SORT_NATURAL);
        $latestFile = end($migrations);
        $latestVersion = basename($latestFile, '.php');

        $this->logMsg("📦 Es wird nur die neueste Migration ausgeführt: Version $latestVersion");

        foreach ($migrations as $migrationFile) {
            $version = basename($migrationFile, '.php');

            if ($version !== $latestVersion) {
                $this->logMsg("⏩ Überspringe ältere Migration $version.");
                continue;
            }

            try {
                $this->logMsg("▶️ Starte Migration für Version $version...");
                include $migrationFile;
                $this->logMsg("✅ Migration $version erfolgreich abgeschlossen.");
            } catch (\Throwable $e) {
                $this->logMsg("❌ Fehler in Migration $version: " . $e->getMessage());
            }
        }
    }

    /**
     * TMP löschen
     */
    private function cleanupTmp(): void
    {
        $tmpDir = __DIR__ . '/../../admin/tmp/';
        if (!is_dir($tmpDir)) {
            $this->logMsg("ℹ️ Kein temporäres Verzeichnis vorhanden.");
            return;
        }

        $this->logMsg("🧹 Bereinige temporäres Verzeichnis...");

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getRealPath());
        }

        @rmdir($tmpDir);
        $this->logMsg("✅ Temporäres Verzeichnis gelöscht (/admin/tmp/).");
    }

    private function logMsg(string $message): void
    {
        $this->log[] = date('[Y-m-d H:i:s] ') . $message;
    }

    private function renderLog(): string
    {
        $html = "<div class='p-3 bg-light border rounded'>";
        foreach ($this->log as $entry) {
            if (str_contains($entry, '❌')) {
                $html .= "<div class='alert alert-danger py-1 my-1'><i class='bi bi-x-circle-fill me-2'></i>" . htmlspecialchars($entry) . "</div>";
            } elseif (str_contains($entry, '⚠️') || str_contains($entry, '⛔')) {
                $html .= "<div class='alert alert-warning py-1 my-1'><i class='bi bi-exclamation-triangle-fill me-2'></i>" . htmlspecialchars($entry) . "</div>";
            } elseif (str_contains($entry, '✅')) {
                $html .= "<div class='alert alert-success py-1 my-1 small'><i class='bi bi-check-circle-fill me-2'></i>" . htmlspecialchars($entry) . "</div>";
            } elseif (str_contains($entry, '🌐')) {
                $html .= "<div class='alert alert-info py-1 my-1 small'><i class='bi bi-cloud-arrow-up me-2'></i>" . htmlspecialchars($entry) . "</div>";
            } else {
                $html .= "<div class='text-muted small'>" . htmlspecialchars($entry) . "</div>";
            }
        }
        $html .= "</div>";
        return $html;
    }
}
