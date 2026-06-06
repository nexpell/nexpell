<?php
declare(strict_types=1);

namespace nexpell\user;

class UserPoints
{
    /* ==============================
       KONFIGURATION
    ============================== */

    private static array $tables = [
        ['table' => 'plugins_articles',       'col' => 'userID', 'type' => 'Artikel',     'weight' => 10],
        ['table' => 'comments',               'col' => 'userID', 'type' => 'Kommentare',  'weight' => 2],
        ['table' => 'plugins_rules',          'col' => 'userID', 'type' => 'Clan-Regeln', 'weight' => 5],
        ['table' => 'plugins_partners',       'col' => 'userID', 'type' => 'Partners',    'weight' => 5],
        ['table' => 'plugins_sponsors',       'col' => 'userID', 'type' => 'Sponsoren',   'weight' => 5],
        ['table' => 'plugins_links',          'col' => 'userID', 'type' => 'Links',       'weight' => 5],
        ['table' => 'plugins_forum_posts',    'col' => 'userID', 'type' => 'Forum',       'weight' => 2],
        ['table' => 'plugins_downloads_logs', 'col' => 'userID', 'type' => 'Downloads',   'weight' => 2],
    ];

    /* ==============================
       ÖFFENTLICHE API
    ============================== */

    public static function get(int $userID): int
    {
        return self::calculate($userID)['total_points'];
    }

    public static function getFull(int $userID): array
    {
        return self::calculate($userID);
    }

    /* ==============================
       KERNLOGIK
    ============================== */

    private static function calculate(int $userID): array
    {
        if ($userID <= 0) {
            return self::emptyResult();
        }

        global $_database;

        $details      = [];
        $totalPoints  = 0;

        // --- Plugin-/Content-Zählungen ---
        foreach (self::$tables as $cfg) {

            if (!self::tableExists($cfg['table'])) {
                continue;
            }

            $count = self::getUserCount($cfg['table'], $cfg['col'], $userID);
            $points = $count * $cfg['weight'];

            $details[] = [
                'type'   => $cfg['type'],
                'count'  => $count,
                'weight' => $cfg['weight'],
                'points' => $points
            ];

            $totalPoints += $points;
        }

        // --- Logins (immer!) ---
        $stmt = $_database->prepare(
            "SELECT COUNT(*) FROM user_sessions WHERE userID = ?"
        );
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $stmt->bind_result($logins);
        $stmt->fetch();
        $stmt->close();

        $loginPoints = $logins * 2;

        $details[] = [
            'type'   => 'Logins',
            'count'  => $logins,
            'weight' => 2,
            'points' => $loginPoints
        ];

        $totalPoints += $loginPoints;

        // --- Level ---
        $level         = (int) floor($totalPoints / 100);
        $levelPercent  = $totalPoints % 100;

        return [
            'total_points' => $totalPoints,
            'level'        => $level,
            'level_percent'=> $levelPercent,
            'details'      => $details
        ];
    }

    /* ==============================
       HELPER
    ============================== */

    private static function tableExists(string $table): bool
    {
        global $_database;
        $res = $_database->query(
            "SHOW TABLES LIKE '" . $_database->real_escape_string($table) . "'"
        );
        return $res && $res->num_rows > 0;
    }

    private static function getUserCount(string $table, string $col, int $userID): int
    {
        global $_database;

        $stmt = $_database->prepare(
            "SELECT COUNT(*) FROM `$table` WHERE `$col` = ?"
        );
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        return (int)$count;
    }

    private static function emptyResult(): array
    {
        return [
            'total_points'  => 0,
            'level'         => 0,
            'level_percent' => 0,
            'details'       => []
        ];
    }
}



