<?php
declare(strict_types=1);

/** Provider: Startseite / Home je Sprache (+ x-default) */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];        // z.B. ['de','en','it']
    $BASE       = rtrim($CTX['BASE'], '/'); // hpurl aus settings, evtl. mit Subpfad
    $useSeoUrls = $CTX['useSeoUrls'];       // bool
    $lastmod    = date('Y-m-d');            // oder aus settings.updated_at, falls vorhanden

    // Content-Key für Home bündeln (ein <url>-Block mit hreflang-Alternates)
    $key = '@home';
    if (!isset($pages[$key])) {
        $pages[$key] = ['langs' => [], 'lastmods' => []];
    }

    foreach ($languages as $lang) {
        // SEO: https://domain.tld/de/
        // non-SEO: https://domain.tld/index.php?lang=de
        if ($useSeoUrls) {
            $href = $BASE . '/' . $lang . '/';
        } else {
            $href = $BASE . '/index.php?lang=' . rawurlencode($lang);
        }
        $pages[$key]['langs'][$lang]    = $href;
        $pages[$key]['lastmods'][$lang] = $lastmod;
    }

    // Wichtig: Die Haupt-Startseite (x-default) ist bereits in sitemap.php so verdrahtet,
    // dass sie auf joinUrl($BASE) zeigt. D.h. wir müssen hier KEINEN extra Eintrag anlegen.
};
