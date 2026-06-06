<?php
declare(strict_types=1);

/**
 * Provider: Partners – NUR Übersichtsseite
 *
 * URLs:
 *  SEO:
 *   /<lang>/partners
 *
 *  non-SEO:
 *   /index.php?site=partners
 */

return function (array &$pages, array $CTX): void {

    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    $listKey = 'partners';
    $today   = date('Y-m-d');

    if (!isset($pages[$listKey])) {
        foreach ($languages as $lang) {
            $loc = sitemap_build_loc(
                $listKey,
                $lang,
                $BASE,
                $useSeoUrls,
                $SLUG_MAP
            );

            $pages[$listKey]['langs'][$lang]    = $loc;
            $pages[$listKey]['lastmods'][$lang] = $today;
        }
    }

    error_log('[sitemap] partners: nur Übersichtsseite hinzugefügt');
};
