<?php

namespace nexpell;

class PluginPackageService
{
    public static function downloadPluginFiles(array $plugin, string $target, $languageService): bool
    {
        $modul = $plugin['modulname'] ?? null;
        if (!$modul) {
            error_log($languageService->get('installer_error_missing_modulname'));
            return false;
        }

        $downloadUrls = self::buildPluginPackageDownloadUrls(self::buildPluginPackageCandidateNames($plugin));
        $tmp = sys_get_temp_dir() . '/' . uniqid((string)$modul . '_', true) . '.zip';

        $data = false;
        foreach ($downloadUrls as $url) {
            $candidateData = @file_get_contents($url);
            if ($candidateData === false || strlen($candidateData) < 100) {
                continue;
            }

            $data = $candidateData;
            break;
        }

        if ($data === false) {
            error_log(sprintf($languageService->get('installer_error_download_failed_url'), implode(' | ', $downloadUrls)));
            return false;
        }

        file_put_contents($tmp, $data);

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            error_log(sprintf($languageService->get('installer_error_zip_open_failed'), $tmp));
            @unlink($tmp);
            return false;
        }

        $zip->close();
        $ok = self::installPluginFilesFromZip($tmp, $target, (string)$modul, $languageService);
        @unlink($tmp);

        return $ok;
    }

    public static function findLocalPluginPackagePath(array $plugin, string $pluginPath): ?string
    {
        $modul = (string)($plugin['modulname'] ?? '');

        foreach (self::buildPluginPackageCandidateNames($plugin) as $candidateName) {
            $candidatePath = rtrim($pluginPath, '/\\') . '/' . $candidateName;
            if (file_exists($candidatePath)) {
                return $candidatePath;
            }
        }

        if ($modul === '') {
            return null;
        }

        $fallbackMatches = glob(rtrim($pluginPath, '/\\') . '/' . $modul . '*.zip');
        if (!is_array($fallbackMatches) || $fallbackMatches === []) {
            return null;
        }

        usort($fallbackMatches, static function (string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        });

        return $fallbackMatches[0] ?? null;
    }

    public static function installPluginFilesFromZip(string $zipPath, string $target, ?string $modul, $languageService): bool
    {
        if (!file_exists($zipPath)) {
            return false;
        }

        $extractDir = sys_get_temp_dir() . '/' . uniqid((string)$modul . '_extract_', true);
        self::ensureDirectory($extractDir);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            error_log(sprintf($languageService->get('installer_error_zip_open_failed'), $zipPath));
            self::deleteFolder($extractDir);
            return false;
        }

        $zip->extractTo($extractDir);
        $zip->close();

        $sourceDir = self::resolveExtractedPluginRoot($extractDir, $modul);
        if ($sourceDir === null) {
            self::deleteFolder($extractDir);
            return false;
        }

        self::syncPluginDirectoryFromSource($sourceDir, $target, $modul);

        foreach (['install.php', 'update.php'] as $scriptFile) {
            $scriptPath = $target . '/' . $scriptFile;
            if (file_exists($scriptPath)) {
                self::normalizeLegacyLangColumnsInScript($scriptPath);
            }
        }

        $hasInstallerScript = file_exists($target . '/install.php') || file_exists($target . '/update.php');
        if (!$hasInstallerScript) {
            error_log(sprintf($languageService->get('installer_error_plugin_missing_files'), (string)$modul));
            self::deleteFolder($extractDir);
            return false;
        }

        self::deleteFolder($extractDir);
        return true;
    }

    public static function syncPluginDirectoryFromSource(string $sourceDir, string $targetDir, ?string $modul): void
    {
        $preserved = array_values(array_unique(array_merge(
            self::pluginPersistentRelativePaths($modul),
            self::detectPluginPersistentRelativePaths($targetDir)
        )));

        self::ensureDirectory($targetDir);
        self::deleteNonPreservedPluginEntries($targetDir, $preserved);
        self::copyDirectoryRecursive($sourceDir, $targetDir);
    }

    public static function normalizeLegacyLangColumnsInScript(string $scriptPath): void
    {
        $content = @file_get_contents($scriptPath);
        if ($content === false || $content === '') {
            return;
        }

        $patched = str_replace(
            [
                "INSERT INTO `plugins_achievements` (`id`,",
                "INSERT INTO plugins_achievements (id,",
                "INSERT INTO `plugins_achievements_categories` (`id`,",
                "INSERT INTO plugins_achievements_categories (id,",
            ],
            [
                "INSERT IGNORE INTO `plugins_achievements` (`id`,",
                "INSERT IGNORE INTO plugins_achievements (id,",
                "INSERT IGNORE INTO `plugins_achievements_categories` (`id`,",
                "INSERT IGNORE INTO plugins_achievements_categories (id,",
            ],
            $content
        );

        $patched = preg_replace_callback(
            '/(INSERT\s+(?:IGNORE\s+)?INTO\s+`?[a-z0-9_]+_lang`?\s*)\((.*?)\)(\s*VALUES)/is',
            static function (array $match): string {
                $cols = array_map(
                    static function (string $column): string {
                        return strtolower(trim(str_replace('`', '', $column)));
                    },
                    explode(',', $match[2])
                );

                $map = [
                    'name' => 'content_key',
                    'lang' => 'language',
                    'translation' => 'content',
                ];

                $changed = false;
                foreach ($cols as $index => $column) {
                    if (isset($map[$column])) {
                        $cols[$index] = $map[$column];
                        $changed = true;
                    }
                }

                if (!$changed) {
                    return $match[0];
                }

                return $match[1] . '(`' . implode('`, `', $cols) . '`)' . $match[3];
            },
            $patched
        );

        if (!is_string($patched) || $patched === '' || $patched === $content) {
            return;
        }

        @file_put_contents($scriptPath, $patched);
    }

    private static function buildPluginPackageCandidateNames(array $plugin): array
    {
        $modul = (string)($plugin['modulname'] ?? '');
        $version = (string)($plugin['version'] ?? '');
        $download = basename((string)($plugin['download'] ?? ''));

        $candidateNames = [];
        if ($download !== '' && strtoupper($download) !== 'DISABLED') {
            $candidateNames[] = $download;
        }
        if ($modul !== '' && $version !== '') {
            $candidateNames[] = $modul . '_' . $version . '.zip';
            $candidateNames[] = $modul . '-' . $version . '.zip';
        }
        if ($modul !== '') {
            $candidateNames[] = $modul . '.zip';
        }

        return array_values(array_unique($candidateNames));
    }

    private static function buildPluginPackageDownloadUrls(array $candidateNames): array
    {
        $urls = [];
        $serverName = rawurlencode((string)($_SERVER['SERVER_NAME'] ?? ''));

        foreach ($candidateNames as $candidateName) {
            $encodedName = rawurlencode($candidateName);
            $urls[] = "https://www.update.nexpell.de/system/download.php?type=plugin&file={$encodedName}&site={$serverName}";
            $urls[] = "https://www.update.nexpell.de/plugins/{$encodedName}";
        }

        return array_values(array_unique($urls));
    }

    private static function pluginPersistentRelativePaths(?string $modul): array
    {
        $common = ['uploads', 'uploads/forum_images', 'images', 'files', 'img'];
        $map = [
            'forum' => ['uploads', 'uploads/forum_images'],
            'gallery' => ['images/upload'],
        ];

        $modul = strtolower((string)$modul);
        $paths = $common;
        if ($modul !== '' && isset($map[$modul])) {
            $paths = array_merge($paths, $map[$modul]);
        }

        return array_values(array_unique($paths));
    }

    private static function detectPluginPersistentRelativePaths(string $pluginDir): array
    {
        $detected = [];
        $directoryNames = ['images', 'uploads', 'files', 'img'];

        if (!is_dir($pluginDir)) {
            return $detected;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pluginDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isDir()) {
                continue;
            }

            $basename = strtolower($item->getBasename());
            if (!in_array($basename, $directoryNames, true)) {
                continue;
            }

            $fullPath = str_replace('\\', '/', $item->getPathname());
            $pluginBase = rtrim(str_replace('\\', '/', $pluginDir), '/');
            if (strpos($fullPath, $pluginBase . '/') !== 0) {
                continue;
            }

            $relative = ltrim(substr($fullPath, strlen($pluginBase)), '/');
            if ($relative !== '') {
                $detected[] = $relative;
            }
        }

        usort($detected, static function (string $a, string $b): int {
            return substr_count($a, '/') <=> substr_count($b, '/');
        });

        $normalized = [];
        foreach ($detected as $path) {
            $skip = false;
            foreach ($normalized as $kept) {
                if ($path === $kept || strpos($path, $kept . '/') === 0) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip) {
                $normalized[] = $path;
            }
        }

        return $normalized;
    }

    private static function resolveExtractedPluginRoot(string $extractDir, ?string $modul): ?string
    {
        $candidates = [$extractDir];
        $modul = strtolower((string)$modul);

        if (is_dir($extractDir . '/' . $modul)) {
            array_unshift($candidates, $extractDir . '/' . $modul);
        }

        foreach ($candidates as $candidate) {
            if (file_exists($candidate . '/install.php') || file_exists($candidate . '/update.php')) {
                return $candidate;
            }
        }

        $entries = @scandir($extractDir);
        if (!is_array($entries)) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $extractDir . '/' . $entry;
            if (!is_dir($candidate)) {
                continue;
            }

            if (file_exists($candidate . '/install.php') || file_exists($candidate . '/update.php')) {
                return $candidate;
            }
        }

        return null;
    }

    private static function deleteNonPreservedPluginEntries(string $targetDir, array $preservedRelativePaths): void
    {
        if (!is_dir($targetDir)) {
            return;
        }

        $entries = @scandir($targetDir);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $targetDir . '/' . $entry;
            if (self::shouldPreservePluginPath($entry, $preservedRelativePaths)) {
                continue;
            }

            if (is_dir($fullPath)) {
                self::deleteFolder($fullPath);
            } elseif (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private static function shouldPreservePluginPath(string $relativePath, array $preservedRelativePaths): bool
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return false;
        }

        foreach ($preservedRelativePaths as $preservedPath) {
            $preservedPath = trim(str_replace('\\', '/', $preservedPath), '/');
            if ($preservedPath === '') {
                continue;
            }

            if (
                $relativePath === $preservedPath ||
                strpos($relativePath, $preservedPath . '/') === 0 ||
                strpos($preservedPath, $relativePath . '/') === 0
            ) {
                return true;
            }
        }

        return false;
    }

    private static function copyDirectoryRecursive(string $sourceDir, string $targetDir): void
    {
        self::ensureDirectory($targetDir);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', $iterator->getSubPathName());
            $targetPath = $targetDir . '/' . $relative;

            if ($item->isDir()) {
                self::ensureDirectory($targetPath);
                continue;
            }

            self::ensureDirectory(dirname($targetPath));
            @copy($item->getPathname(), $targetPath);
        }
    }

    private static function ensureDirectory(string $dir): void
    {
        if ($dir === '' || $dir === '.' || is_dir($dir)) {
            return;
        }

        @mkdir($dir, 0755, true);
    }

    private static function deleteFolder(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::deleteFolder($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
