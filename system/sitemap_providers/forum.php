<?php
declare(strict_types=1);

/**
 * Forum Sitemap Provider (FIXED – no slug column)
 *
 * ✔ nur kanonische Thread-URLs
 * ✔ keine Pagination
 * ✔ nur relevante Threads
 * ✔ lastmod = letzter sichtbarer Post
 */

return function (array &$pages, array $CTX): void {

    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    /* ---------------------------------------------
     * Helper
     * --------------------------------------------- */
    $dateFromUnix = static function (?int $ts): string {
        return ($ts && $ts > 0) ? date('Y-m-d', $ts) : date('Y-m-d');
    };

    /* ---------------------------------------------
     * 1) Letzter sichtbarer Post je Thread
     * --------------------------------------------- */
    $lastPostTs = [];

    $sql = "
        SELECT
            threadID,
            MAX(
                GREATEST(
                    IFNULL(created_at, 0),
                    IFNULL(edited_at, 0)
                )
            ) AS last_ts
        FROM plugins_forum_posts
        WHERE is_deleted = 0
        GROUP BY threadID
    ";

    if ($res = $db->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $lastPostTs[(int)$r['threadID']] = (int)$r['last_ts'];
        }
        $res->free();
    }

    /* ---------------------------------------------
     * 2) Relevante Threads laden (Top-Threads)
     * --------------------------------------------- */
    $sql = "
        SELECT
            t.threadID,
            t.catID,
            t.created_at,
            t.updated_at,
            COUNT(p.postID) AS post_count
        FROM plugins_forum_threads t
        JOIN plugins_forum_posts p
            ON p.threadID = t.threadID
            AND p.is_deleted = 0
        WHERE t.is_deleted = 0
        GROUP BY t.threadID
        HAVING post_count >= 2
        ORDER BY
            COALESCE(t.updated_at, t.created_at) DESC
        LIMIT 500
    ";

    if (!$res = $db->query($sql)) {
        error_log('[sitemap/forum] THREAD QUERY FAILED: ' . $db->error);
        return;
    }

    while ($row = $res->fetch_assoc()) {

        $threadID = (int)$row['threadID'];
        if ($threadID <= 0) continue;

        /* lastmod bestimmen */
        $tsCandidates = [];

        // letzter Post (bereits int)
        if (isset($lastPostTs[$threadID])) {
            $tsCandidates[] = (int)$lastPostTs[$threadID];
        }

        // updated_at (DATETIME → unix)
        if (!empty($row['updated_at'])) {
            $u = strtotime((string)$row['updated_at']);
            if ($u !== false) $tsCandidates[] = $u;
        }

        // created_at (DATETIME → unix)
        if (!empty($row['created_at'])) {
            $c = strtotime((string)$row['created_at']);
            if ($c !== false) $tsCandidates[] = $c;
        }

        $lastmod = $dateFromUnix(
            $tsCandidates ? max($tsCandidates) : null
        );


        /* -----------------------------------------
         * Canonical Thread-URL (ID-basiert)
         * ----------------------------------------- */
        $contentKey = "forum/thread/{$threadID}";
        $qBase = [
            'site'   => 'forum',
            'action' => 'thread',
            'id'     => $threadID
        ];

        foreach ($languages as $lang) {
            $loc = sitemap_build_loc(
                $contentKey,
                $lang,
                $BASE,
                $useSeoUrls,
                $SLUG_MAP,
                $qBase
            );

            if (!isset($pages[$contentKey])) {
                $pages[$contentKey] = ['langs'=>[], 'lastmods'=>[]];
            }

            $pages[$contentKey]['langs'][$lang]    = $loc;
            $pages[$contentKey]['lastmods'][$lang] = $lastmod;
        }
    }

    $res->free();

    /* ---------------------------------------------
     * 3) Forum-Startseite
     * --------------------------------------------- */
    $today = date('Y-m-d');
    $key   = 'forum';

    if (!isset($pages[$key])) {
        foreach ($languages as $lang) {
            $loc = sitemap_build_loc(
                $key,
                $lang,
                $BASE,
                $useSeoUrls,
                $SLUG_MAP
            );

            $pages[$key]['langs'][$lang]    = $loc;
            $pages[$key]['lastmods'][$lang] = $today;
        }
    }
};
