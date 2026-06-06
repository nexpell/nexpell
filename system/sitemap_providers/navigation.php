<?php
declare(strict_types=1);

/** Provider: Navigationseinträge aus navigation_website_sub (robust für index.php?site=...) */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];
    $DENYLIST   = $CTX['DENYLIST'];

    // Helper aus Hauptdatei
    if (!function_exists('sitemap_register_page') || !function_exists('sitemap_build_loc')) return;

    $sql = "SELECT url, COALESCE(DATE(last_modified), DATE(NOW())) AS last_modified
            FROM navigation_website_sub
            WHERE indropdown = 1";
    $res = $db->query($sql);
    if (!$res) return;

    while ($row = $res->fetch_assoc()) {
        $urlRaw = trim((string)($row['url'] ?? ''));

        // 1) Sprache vorne im Pfad entfernen (falls vorhanden)
        $pathOnly = $urlRaw;
        $queryStr = '';
        if (false !== ($qpos = strpos($urlRaw, '?'))) {
            $pathOnly = substr($urlRaw, 0, $qpos);
            $queryStr = substr($urlRaw, $qpos + 1);
        }
        $pathOnly = preg_replace('~^/?(de|en|it)(?:/|$)~i', '', ltrim($pathOnly, '/'));

        // 2) Query in Kleinbuchstaben-Schlüssel parsen
        $query = [];
        if ($queryStr !== '') {
            parse_str($queryStr, $tmp);
            foreach ($tmp as $k => $v) $query[strtolower((string)$k)] = $v;
        }

        // 3) contentKey bestimmen
        //    - Wenn site=... existiert → nimm das
        //    - Sonst nimm erstes Segment aus Pfad
        //    - IDs (staticid / page / articleid / downloadid) erkennen
        $contentKey = '';
        $queryBase  = [];

        if (!empty($query['site'])) {
            $site = strtolower(trim((string)$query['site']));
            $queryBase['site'] = $site;

            if (isset($query['staticid']))   { $contentKey = "{$site}/staticid/"   . (int)$query['staticid']; }
            elseif ($site === 'wiki' && isset($query['page'])) { $contentKey = "wiki/page/" . (int)$query['page']; }
            elseif ($site === 'articles' && isset($query['articleid'])) { $contentKey = "articles/articleid/" . (int)$query['articleid']; }
            elseif ($site === 'downloads' && isset($query['downloadid'])) {
    $contentKey = "downloads/downloadid/" . (int)$query['downloadid'];
} 
// NEU:
elseif ($site === 'downloads' && isset($query['action'], $query['id']) && strtolower((string)$query['action']) === 'detail') {
    $contentKey = "downloads/detail/" . (int)$query['id'];
}
            else { $contentKey = $site; }

            // Non-SEO-Fallback-IDs in queryBase mitgeben
            foreach (['staticid','page','articleid','downloadid'] as $k) {
                if (isset($query[$k])) $queryBase[$k] = $query[$k];
            }
        } else {
            $path = trim($pathOnly, '/');
            if ($path !== '') {
                // Muster wie "static/staticid/11"
                if (preg_match('~^([a-z0-9_-]+)/staticid/([0-9]+)$~i', $path, $m)) {
                    $contentKey = strtolower($m[1]) . '/staticid/' . $m[2];
                    $queryBase = ['site' => strtolower($m[1]), 'staticid' => $m[2]];
                } elseif (preg_match('~^wiki/(?:page/)?([0-9]+)$~i', $path, $m)) {
                    $contentKey = 'wiki/page/' . $m[1];
                    $queryBase = ['site' => 'wiki', 'page' => $m[1]];
                } else {
                    // einfacher Abschnitt: about, forum, downloads, ...
                    $contentKey = strtolower(explode('/', $path)[0]);
                    $queryBase = $contentKey !== '' ? ['site' => $contentKey] : [];
                }
            }
        }

        if ($contentKey === '') continue;
        $firstSeg = explode('/', $contentKey)[0];
        if (in_array($firstSeg, $DENYLIST, true)) continue;

        // 4) lastmod
        $d = (string)($row['last_modified'] ?? '');
        $lastmod = ($d !== '' && strtotime($d) !== false) ? date('Y-m-d', strtotime($d)) : date('Y-m-d');

        // 5) registrieren – sitemap_build_loc sorgt dafür, dass:
        //    - SEO: BASE/{lang}/{contentKey}
        //    - non-SEO: BASE/index.php?site=...&lang=...&id=...
        sitemap_register_page($pages, $contentKey, $lastmod, [], $languages, $BASE, $useSeoUrls, $SLUG_MAP, $queryBase);
    }

    $res->free();
};
