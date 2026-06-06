<?php

namespace nexpell;

class AccessControl
{
    /* =====================================================
       ADMIN ACCESS
    ===================================================== */

    public static function hasAdminAccess($modulname)
    {
        global $userID;

        if (!$userID) {
            return false;
        }

        $query = "
            SELECT COUNT(*) AS access_count
            FROM user_role_admin_navi_rights ar
            JOIN user_role_assignments ur ON ar.roleID = ur.roleID
            WHERE ur.userID = " . (int)$userID . "
              AND ar.modulname = '" . escape($modulname) . "'
        ";

        $result = safe_query($query);
        $row = mysqli_fetch_assoc($result);
        return $row['access_count'] > 0;
    }

public static function checkAdminAccess($modulname, bool $apiMode = false)
{
    global $languageService;

    $userID = $_SESSION['userID'] ?? 0;
    $logFile = __DIR__ . '/../../admin/logs/access_control.log';

    // 🔒 Zugriff verweigern
    if (!$userID || !self::hasAnyRoleAccess($modulname, (int)$userID)) {
        http_response_code(403);

        if ($apiMode) {
            echo json_encode(['error' => 'Zugriff verweigert']);
            exit;
        }

        $modulnameDisplay = htmlspecialchars($modulname);

        /* ===============================
           ROLLEN DES USERS (IMMER!)
        =============================== */
        $roleNames = [];

        $roleQuery = "
            SELECT r.role_name
            FROM user_role_assignments ur
            JOIN user_roles r ON ur.roleID = r.roleID
            WHERE ur.userID = " . (int)$userID . "
        ";
        $roleResult = safe_query($roleQuery);

        while ($row = mysqli_fetch_assoc($roleResult)) {
            $roleNames[] = htmlspecialchars($row['role_name']);
        }

        $roleName = !empty($roleNames)
            ? implode(', ', $roleNames)
            : 'Keine Rolle';

            

        /* ===============================
           LINKNAME
        =============================== */
        $linkName = 'Unbekannter Link';
        $linkQuery = "
            SELECT *
            FROM navigation_dashboard_links
            WHERE modulname = '" . self::escape($modulname) . "'
            LIMIT 1
        ";
        $linkResult = safe_query($linkQuery);
        if ($linkRow = mysqli_fetch_assoc($linkResult)) {
            $lang = method_exists($languageService, 'detectLanguage')
                ? $languageService->detectLanguage()
                : ($_SESSION['language'] ?? 'de');

            if (!empty($linkRow['name'])) {
                $linkName = self::extractTextByLanguage((string)$linkRow['name'], $lang);
            } elseif (!empty($linkRow['content_key'])) {
                $contentKey = self::escape((string)$linkRow['content_key']);
                $langEsc = self::escape((string)$lang);
                $langResult = safe_query("
                    SELECT content
                    FROM navigation_dashboard_lang
                    WHERE content_key = '{$contentKey}'
                      AND language = '{$langEsc}'
                    LIMIT 1
                ");
                if ($langRow = mysqli_fetch_assoc($langResult)) {
                    if (!empty($langRow['content'])) {
                        $linkName = $langRow['content'];
                    }
                }
            } elseif (!empty($linkRow['modulname'])) {
                $linkName = (string)$linkRow['modulname'];
            }
        }

        /* ===============================
           AUSGABE
        =============================== */
        $errorMessage = "
        <div class='alert alert-danger text-center mt-5'>
                <i class='bi bi-shield-lock-fill fs-4'></i><br>
                <strong>Zugriff verweigert</strong><br>
                Du hast keine Berechtigung, diesen Bereich (Modul '<i>$modulnameDisplay</i>') zu bearbeiten.<br>
                <b>Linkname:</b> " . htmlspecialchars($linkName) . "<br><br>

                <div class='alert alert-secondary text-start mx-auto mt-3' style='max-width: 600px;'>
                    <i class='bi bi-info-circle-fill me-2 text-primary'></i>
                    <strong>Hinweis:</strong>
                    Dieser Bereich ist nur für Benutzer mit der entsprechenden <b>Admin-Rolle</b> 
                    oder speziellen <b>Zugriffsrechten</b> freigegeben.<br>
                    Falls du Zugriff benötigst, bitte einen Administrator, 
                    dir das Recht <code><i>$modulnameDisplay</i></code> 
                    unter <em>Benutzerrollen & Rechte</em> zuzuweisen.<hr>
                    <small class='text-muted'>
                        Falls du glaubst, dass es sich um einen Fehler handelt, 
                        wende dich bitte an einen Administrator mit der entsprechenden Rolle 
                        oder prüfe deine Rechte in der Benutzerverwaltung.
                    </small>
                </div>    
            <b>Ihre Rolle(n):</b> " . $roleName . "
        </div>";

        // Logging
        $logEntry = sprintf(
            "[%s] Zugriff verweigert: Modul='%s', UserID=%s, Rollen='%s', IP=%s\n",
            date('Y-m-d H:i:s'),
            $modulname,
            $userID,
            $roleName,
            $_SERVER['REMOTE_ADDR'] ?? 'Unbekannt'
        );

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        error_log($logEntry);

        echo $errorMessage;
        exit;
    }
}

    private static function hasAnyRoleAccess(string $modulname, int $userID): bool
    {
        $query = "
            SELECT 1
            FROM user_role_admin_navi_rights ar
            JOIN user_role_assignments ur ON ar.roleID = ur.roleID
            WHERE ur.userID = {$userID}
              AND ar.modulname = '" . self::escape($modulname) . "'
            LIMIT 1
        ";
        $result = safe_query($query);
        return mysqli_num_rows($result) > 0;
    }

    /* =====================================================
       FRONTEND / CONTENT ROLLEN
    ===================================================== */

public static function hasAnyRole(array $roleNames): bool
{
    if (empty($roleNames)) {
        return false;
    }

    // Gast
    if (!isset($_SESSION['userID']) || $_SESSION['userID'] <= 0) {
        return in_array('ac_guest', array_map('strtolower', $roleNames), true);
    }

    global $_database;
    $userID = (int)$_SESSION['userID'];

    // Platzhalter für Rollen
    $placeholders = implode(',', array_fill(0, count($roleNames), '?'));

    // 🔧 WICHTIG: Rollen doppelt binden (wegen OR … IN)
    $allRoles = array_merge($roleNames, $roleNames);

    // Typen: i + s * (Rollen * 2)
    $types = 'i' . str_repeat('s', count($allRoles));
    $params = array_merge([$types, $userID], $allRoles);

    $stmt = $_database->prepare("
        SELECT 1
        FROM user_role_assignments a
        JOIN user_roles r ON a.roleID = r.roleID
        WHERE a.userID = ?
          AND (
                r.role_name IN ($placeholders)
             OR CONCAT('ac_', LOWER(r.role_name)) IN ($placeholders)
          )
        LIMIT 1
    ");

    if (!$stmt) {
        error_log('hasAnyRole prepare failed: ' . $_database->error);
        return false;
    }

    $stmt->bind_param(...self::refValues($params));
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}



public static function hasRoleAccess(?string $accessRolesJson): bool
{
    // Öffentlich
    if (empty($accessRolesJson) || $accessRolesJson === '[]') {
        return true;
    }

    $roles = json_decode($accessRolesJson, true);
    if (!is_array($roles) || empty($roles)) {
        return true;
    }

    // Gast explizit erlaubt
    if (in_array('ac_guest', $roles, true)) {
        return true;
    }

    // Nicht eingeloggt → kein Zugriff
    if (empty($_SESSION['userID'])) {
        return false;
    }

    // ✅ REGISTRIERTER USER = eingeloggt
    if (in_array('ac_registered', $roles, true)) {
        return true;
    }

    // Rollenprüfung (Admin, Techniker, etc.)
    return self::hasAnyRole($roles);
}


    public static function canViewStatic(array $staticRow): bool
    {
        return self::hasRoleAccess($staticRow['access_roles'] ?? null);
    }

    /* =====================================================
       HELPERS
    ===================================================== */

    private static function refValues(array $arr)
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            return $arr;
        }

        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    private static function extractTextByLanguage(string $multiLangString, string $lang): string
    {
        preg_match_all(
            '/\[\[lang:(\w{2})\]\](.*?)(?=(\[\[lang:\w{2}\]\])|$)/s',
            $multiLangString,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            if ($match[1] === $lang) {
                return trim($match[2]);
            }
        }

        return $multiLangString;
    }

    private static function escape(string $str): string
    {
        global $_database;

        if ($_database instanceof \mysqli) {
            return mysqli_real_escape_string($_database, $str);
        }

        return addslashes($str);
    }

    /* =====================================================
       LEGACY / PATCH WRAPPER
    ===================================================== */

    public static function userHasRoleID(int $userID, int $roleID): bool
    {
        global $_database;

        $stmt = $_database->prepare("
            SELECT 1
            FROM user_role_assignments
            WHERE userID = ?
              AND roleID = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $userID, $roleID);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

/* --- FINALER FIX --- */
    public static function canAccessAdmin($db, int $userID): bool
    {
        if (!($db instanceof \mysqli) || $userID <= 0) {
            return false;
        }
        return self::canAccessAdminInternal($db, $userID);
    }

    private static function canAccessAdminInternal(\mysqli $db, int $userID): bool
    {
        $stmt = $db->prepare("
            SELECT 1
            FROM user_role_assignments ura
            JOIN user_role_admin_navi_rights arn ON arn.roleID = ura.roleID
            WHERE ura.userID = ?
            LIMIT 1
        ");
        if (!$stmt) return false;
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }
    
}
