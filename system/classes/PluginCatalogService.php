<?php

namespace nexpell;

class PluginCatalogService
{
    public static function matchesCore(array $plugin, string $coreVersion): bool
    {
        $min = $plugin['core']['min'] ?? null;
        $max = $plugin['core']['max'] ?? null;

        if ($min && version_compare($coreVersion, (string)$min, '<')) {
            return false;
        }
        if ($max && version_compare($coreVersion, (string)$max, '>')) {
            return false;
        }

        return true;
    }

    public static function isInstallable(array $plugin, string $adminEmail, string $coreVersion, array &$debug = []): bool
    {
        $adminEmail = strtolower(trim($adminEmail));
        $version = (string)($plugin['version'] ?? 'unknown');
        $modulname = (string)($plugin['modulname'] ?? '');

        if (!empty($plugin['core']['min']) && version_compare($coreVersion, (string)$plugin['core']['min'], '<')) {
            $debug[] = "{$modulname} {$version}: core {$coreVersion} < min {$plugin['core']['min']}";
            return false;
        }

        if (!empty($plugin['core']['max']) && version_compare($coreVersion, (string)$plugin['core']['max'], '>')) {
            $debug[] = "{$modulname} {$version}: core {$coreVersion} > max {$plugin['core']['max']}";
            return false;
        }

        $visibleFor = strtoupper((string)($plugin['visible_for'] ?? 'ALL'));

        if ($visibleFor === 'ALL') {
            $debug[] = "{$modulname} {$version}: visible_for ALL";
            return true;
        }

        if ($visibleFor === 'CUSTOM') {
            $emails = array_map('strtolower', $plugin['visible_emails'] ?? []);
            if (in_array($adminEmail, $emails, true)) {
                $debug[] = "{$modulname} {$version}: CUSTOM match {$adminEmail}";
                return true;
            }

            $debug[] = "{$modulname} {$version}: CUSTOM no match ({$adminEmail})";
            return false;
        }

        $debug[] = "{$modulname} {$version}: unknown visible_for";
        return false;
    }

    public static function resolveLocalizedText($value, string $lang): string
    {
        $lang = strtolower(trim($lang));
        if ($lang === '') {
            $lang = 'de';
        }

        if (is_array($value)) {
            foreach ([$lang, 'en', 'gb', 'de', 'it'] as $key) {
                if (isset($value[$key]) && trim((string)$value[$key]) !== '') {
                    return (string)$value[$key];
                }
            }
            foreach ($value as $entry) {
                if (trim((string)$entry) !== '') {
                    return (string)$entry;
                }
            }
            return '';
        }

        $text = (string)$value;
        if ($text === '') {
            return '';
        }

        if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/si', $text, $match)) {
            return trim((string)$match[1]);
        }

        foreach (['en', 'gb', 'de', 'it'] as $fallback) {
            if (preg_match('/\[\[lang:' . preg_quote($fallback, '/') . '\]\](.*?)(?=\[\[lang:|$)/si', $text, $match)) {
                return trim((string)$match[1]);
            }
        }

        return trim($text);
    }

    public static function loadPluginsRegistry(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Nexpell Plugin Installer',
        ]);

        $json = curl_exec($ch);
        if ($json === false) {
            throw new \RuntimeException(curl_error($ch));
        }

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            throw new \RuntimeException('Registry HTTP error');
        }

        $data = json_decode($json, true);
        if (!isset($data['plugins']) || !is_array($data['plugins'])) {
            throw new \RuntimeException('Invalid plugins_v2.json');
        }

        return $data['plugins'];
    }
}
