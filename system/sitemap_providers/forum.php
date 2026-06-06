<?php
declare(strict_types=1);

/**
 * Provider: Forum – exakt für dein Schema (Nexpell)
 *
 * Tabellen:
 *  - plugins_forum_threads:
 *      threadIDPrimärschlüssel (PK, int)
 *      slug (varchar)
 *      catIDIndex (int)
 *      created_at (int, unix)
 *      updated_at (int, unix)
 *      is_locked (tinyint, optional)
 *  - plugins_forum_posts:
 *      threadID (int, FK)
 *      created_at (int, unix)
 *      edited_at (int, unix, nullable)
 *      is_deleted (tinyint)
 *
 * URLs:
 *  SEO:    /<lang>/forum/thread/<slug|id>/page/<n>
 *          /<lang>/forum/overview/<catId>
 *          /<lang>/forum
 *  nonSEO: /index.php?site=forum&action=thread&id=<id>&page=<n>&lang=<lang>
 *          /index.php?site=forum&action=overview&id=<catId>&lang=<lang>
 */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    $dateFromUnix = static function (?int $ts): string {
        return ($ts && $ts > 0) ? date('Y-m-d', $ts) : date('Y-m-d');
    };

    // Posts pro Seite (optional aus settings_forum)
    $POSTS_PER_PAGE = 20;
    if ($rs = $db->query("SHOW TABLES LIKE 'settings_forum'")) {
        if ($rs->num_rows > 0) {
            $rs->free();
            if ($r = $db->query("SELECT posts_per_page, threads_per_page FROM settings_forum LIMIT 1")) {
                if ($row = $r->fetch_assoc()) {
                    $pp = (int)($row['posts_per_page'] ?? $row['threads_per_page'] ?? 20);
                    if ($pp > 0 && $pp < 500) $POSTS_PER_PAGE = $pp;
                }
                $r->free();
            }
        } else { $rs->free(); }
    }

    // --- Posts: Anzahl & letzter Zeitstempel pro Thread (nur nicht gelöschte)
    $postCount  = []; // threadId => total
    $lastPostTs = []; // threadId => unix

    $whereDel   = "WHERE (`is_deleted` IN (0,'0') OR `is_deleted` IS NULL)";

    // Anzahl Posts je Thread
    $sql = "SELECT `threadID` AS tid, COUNT(*) AS c
            FROM `plugins_forum_posts` {$whereDel}
            GROUP BY `threadID`";
    if ($rs = $db->query($sql)) {
        while ($r = $rs->fetch_assoc()) $postCount[(string)$r['tid']] = (int)$r['c'];
        $rs->free();
    }

    // letzter Zeitstempel je Thread (MAX(GREATEST(edited_at, created_at)))
    $sql = "SELECT `threadID` AS tid,
                   MAX(GREATEST(IFNULL(`edited_at`,0), IFNULL(`created_at`,0))) AS last_ts
            FROM `plugins_forum_posts` {$whereDel}
            GROUP BY `threadID`";
    if ($rs = $db->query($sql)) {
        while ($r = $rs->fetch_assoc()) $lastPostTs[(string)$r['tid']] = (int)$r['last_ts'];
        $rs->free();
    }

    // --- Threads holen
    $threads = []; // ['id'=>..., 'slug'=>..., 'cat'=>int|null, 'upd'=>int, 'cre'=>int]
    $batch=1000; $offset=0;

    while (true) {
        $sql = "SELECT
                    `threadID` AS tid,
                    `slug`,
                    `catID` AS cat_id,
                    `updated_at` AS t_updated,
                    `created_at` AS t_created
                FROM `plugins_forum_threads`
                ORDER BY `updated_at` DESC, `threadID` DESC
                LIMIT {$batch} OFFSET {$offset}";
        $res = $db->query($sql);
        if (!$res) { break; }

        $count=0;
        while ($row = $res->fetch_assoc()) {
            $count++;
            $threads[] = [
                'id'   => (string)$row['tid'],
                'slug' => trim((string)$row['slug']),
                'cat'  => isset($row['cat_id']) ? (int)$row['cat_id'] : null,
                'upd'  => (int)$row['t_updated'],
                'cre'  => (int)$row['t_created'],
            ];
        }
        $res->free();
        if ($count < $batch) break;
        $offset += $batch;
    }

    // Fallback: Wenn keine Threads vorhanden, aus Posts ableiten
    if (!$threads) {
        $sql = "SELECT DISTINCT `threadID` AS tid
                FROM `plugins_forum_posts`
                ORDER BY `threadID` DESC
                LIMIT 5000";
        if ($rs = $db->query($sql)) {
            while ($r = $rs->fetch_assoc()) {
                $tid = (string)$r['tid'];
                $threads[] = [
                    'id'   => $tid,
                    'slug' => '',
                    'cat'  => null,
                    'upd'  => $lastPostTs[$tid] ?? 0,
                    'cre'  => 0,
                ];
            }
            $rs->free();
        }
    }

    // --- URLs bauen
    $catsSeen = []; // catId => lastmod max

    foreach ($threads as $t) {
        $tid  = $t['id']; if ($tid === '') continue;
        $slug = $t['slug'];
        $cat  = $t['cat'];

        // lastmod: letzter Post → thread.updated_at → thread.created_at
        $tsCandidates = [];
        if (isset($lastPostTs[$tid])) $tsCandidates[] = (int)$lastPostTs[$tid];
        if ($t['upd'] > 0)            $tsCandidates[] = (int)$t['upd'];
        if ($t['cre'] > 0)            $tsCandidates[] = (int)$t['cre'];
        $lastmod = $dateFromUnix($tsCandidates ? max($tsCandidates) : null);

        // Pagination: mind. 1 Seite
        $totalPosts = max(1, (int)($postCount[$tid] ?? 1));
        $perPage    = max(1, (int)$POSTS_PER_PAGE);
        $totalPages = (int)ceil($totalPosts / $perPage);

        for ($page = 1; $page <= $totalPages; $page++) {
            if ($slug !== '') {
                $contentKey = "forum/thread/{$slug}/page/{$page}";
                $qBase = ['site'=>'forum','action'=>'thread','slug'=>$slug,'page'=>$page];
            } else {
                $contentKey = "forum/thread/{$tid}/page/{$page}";
                $qBase = ['site'=>'forum','action'=>'thread','id'=>$tid,'page'=>$page];
            }

            foreach ($languages as $lang) {
                $loc = sitemap_build_loc($contentKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP, $qBase);
                if (!isset($pages[$contentKey])) $pages[$contentKey] = ['lastmods'=>[],'langs'=>[]];
                $pages[$contentKey]['langs'][$lang]    = $loc;
                $pages[$contentKey]['lastmods'][$lang] = $lastmod;
            }
        }

        if ($cat !== null) {
            if (!isset($catsSeen[$cat]) || $lastmod > $catsSeen[$cat]) {
                $catsSeen[$cat] = $lastmod;
            }
        }
    }

    // Category-Overviews
    foreach ($catsSeen as $catId => $catLastmod) {
        $contentKey = "forum/overview/{$catId}";
        $qBase = ['site'=>'forum','action'=>'overview','id'=>$catId];
        foreach ($languages as $lang) {
            $loc = sitemap_build_loc($contentKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP, $qBase);
            if (!isset($pages[$contentKey])) $pages[$contentKey] = ['lastmods'=>[],'langs'=>[]];
            $pages[$contentKey]['langs'][$lang]    = $loc;
            $pages[$contentKey]['lastmods'][$lang] = $catLastmod;
        }
    }

    // /forum Listing
    $listKey = 'forum';
    $today   = date('Y-m-d');
    if (!isset($pages[$listKey])) {
        foreach ($languages as $lang) {
            $loc = sitemap_build_loc($listKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP);
            $pages[$listKey]['langs'][$lang]    = $loc;
            $pages[$listKey]['lastmods'][$lang] = $today;
        }
    }
};
