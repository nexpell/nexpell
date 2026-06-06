<?php

function nx_audit_sanitize_identifier(string $value): string
{
    $value = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $value);
    return $value !== '' ? $value : 'unknown';
}

function nx_audit_detect_page(): string
{
    if (!empty($_GET['site'])) {
        return nx_audit_sanitize_identifier((string)$_GET['site']);
    }
    return basename((string)($_SERVER['SCRIPT_NAME'] ?? 'admin'));
}

/**
 * Rolle aus DB lesen (user_role_assignments + user_roles), pro Request gecached.
 * Entspricht AccessControl::enforce().
 */
function nx_audit_get_actor_role(mysqli $db, int $userID): string
{
    static $cache = []; // [userID => role_name]
    if ($userID <= 0) return 'unknown';
    if (isset($cache[$userID])) return $cache[$userID];

    $role = 'unknown';

    $stmt = $db->prepare("
        SELECT r.role_name
        FROM user_role_assignments a
        JOIN user_roles r ON a.roleID = r.roleID
        WHERE a.userID = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userID);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $role = (string)($res->fetch_assoc()['role_name'] ?? 'unknown');
        }
        $stmt->close();
    }

    $role = $role !== '' ? $role : 'unknown';
    $cache[$userID] = $role;
    return $role;
}

function nx_audit_detect_plugin_name(): ?string
{
    $site = (string)($_GET['site'] ?? '');
    if ($site !== '') {
        if (preg_match('/^(plugins?|plugin)[_\-]?([a-zA-Z0-9]+)/', $site, $m)) {
            return $m[2];
        }
        if (preg_match('/^(plugins?|plugin)[_\-]?([a-zA-Z0-9]+)[_\-]/', $site, $m2)) {
            return $m2[2];
        }
    }

    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (preg_match('#/plugins/([^/]+)/#', $script, $m3)) {
        return $m3[1];
    }

    return null;
}

/**
 * CORE: schreibt in admin_audit_log
 */
function nx_admin_audit(
    mysqli $db,
    string $action,
    ?string $page = null,
    ?string $objectType = null,
    $objectId = null,
    ?string $message = null,
    array $meta = []
): void {

    /* ===========================
       PATCH: Trigger-Rekursion verhindern
       =========================== */
    static $auditRunning = false;
    static $auditTableAvailable = null;
    if ($auditRunning === true) {
        return;
    }
    $auditRunning = true;
    /* =========================== */

    try {
        if ($auditTableAvailable === null) {
            $auditTableAvailable = false;
            $chk = $db->query("SHOW TABLES LIKE 'admin_audit_log'");
            if ($chk instanceof mysqli_result && $chk->num_rows > 0) {
                $auditTableAvailable = true;
            }
        }

        if ($auditTableAvailable !== true) {
            return;
        }

    $action = strtoupper(trim($action));
    $page   = $page ? nx_audit_sanitize_identifier($page) : nx_audit_detect_page();

    $actorUserID   = (int)($_SESSION['userID'] ?? 0);
    $actorUsername = (string)($_SESSION['username'] ?? 'unknown');
    $actorRole     = nx_audit_get_actor_role($db, $actorUserID);

    if (empty($_SESSION['nx_request_id'])) {
        $_SESSION['nx_request_id'] = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
    $requestId = (string)$_SESSION['nx_request_id'];

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = $ip !== '' ? hash('sha256', $ip) : null;
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $objectTypeVar = $objectType !== null ? nx_audit_sanitize_identifier($objectType) : null;
    $objectIdStr   = ($objectId === null || $objectId === '') ? null : (string)$objectId;

    if (!empty($GLOBALS['__pageTitle']) && empty($meta['page_title'])) {
        $meta['page_title'] = (string)$GLOBALS['__pageTitle'];
    }
    if (!empty($GLOBALS['__pageCategoryUrl']) && empty($meta['page_category_url'])) {
        $meta['page_category_url'] = (string)$GLOBALS['__pageCategoryUrl'];
    }
    if (!empty($_SERVER['REQUEST_URI']) && empty($meta['page_url'])) {
        $meta['page_url'] = (string)$_SERVER['REQUEST_URI'];
    }

    $metaJson = !empty($meta)
        ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

        $stmt = $db->prepare("
            INSERT INTO admin_audit_log
                (actor_userID, actor_username, actor_role, page, action, object_type, object_id, message, meta_json, request_id, ip_hash, user_agent)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param(
                "isssssssssss",
                $actorUserID,
                $actorUsername,
                $actorRole,
                $page,
                $action,
                $objectTypeVar,
                $objectIdStr,
                $message,
                $metaJson,
                $requestId,
                $ipHash,
                $ua
            );
            @$stmt->execute();
            $stmt->close();
        }
    } catch (\Throwable $e) {
        // Audit must never break frontend/admin flow.
        return;
    } finally {
        /* ===========================
           PATCH: Guard freigeben
           =========================== */
        $auditRunning = false;
    }
}

/**
 * Wrapper: Aufruf in den Unterseiten
 */
function nx_audit(
    string $action,
    ?string $objectType = null,
    $objectId = null,
    ?string $message = null,
    array $meta = []
): void {
    if (!isset($GLOBALS['_database']) || !($GLOBALS['_database'] instanceof mysqli)) return;
    nx_admin_audit($GLOBALS['_database'], $action, null, $objectType, $objectId, $message, $meta);
}

/**
 * Audit::UPDATE
 */
function nx_audit_update(
    string $page,
    ?string $objectId,
    bool $hasChanged,
    ?string $name = null,
    ?string $pageUrl = null
): void {
    nx_audit(
        'UPDATE',
        $page,
        $objectId !== null ? (string)$objectId : 'singleton',
        $hasChanged ? 'audit_update_changed' : 'audit_update_nochange',
        array_filter([
            'name'     => $name,
            'page_url' => $pageUrl,
        ])
    );
}

/**
 * Audit::CREATE
 */
function nx_audit_create(
    string $page,
    string $objectId,
    ?string $name = null,
    ?string $pageUrl = null
): void {
    nx_audit(
        'CREATE',
        $page,
        $objectId,
        'audit_create_page',
        array_filter([
            'name'     => $name,
            'page_url' => $pageUrl,
        ])
    );
}

/**
 * Audit::DELETE
 */
function nx_audit_delete(
    string $page,
    string $objectId,
    ?string $name = null,
    ?string $pageUrl = null
): void {
    nx_audit(
        'DELETE',
        $page,
        $objectId,
        'audit_delete_page',
        array_filter([
            'name'     => $name,
            'page_url' => $pageUrl,
        ])
    );
}

/**
 * Audit::DELETE AUS ZUORDNUNG
 */
function nx_audit_remove(
    string $page,
    string $objectId,
    ?string $name = null,
    ?string $pageUrl = null
): void {
    nx_audit(
        'DELETE',
        $page,
        $objectId,
        'audit_delete_page_object',
        array_filter([
            'name'     => $name,
            'page_url' => $pageUrl,
        ])
    );
}

/**
 * Audit::UPDATE ACTIONS
 */
function nx_audit_action(
    string $page,
    string $actionLabel,
    ?string $objectId = null,
    ?string $name = null,
    ?string $pageUrl = null,
    array $meta = []
): void {

    $baseMeta = array_filter([
        'action'   => $actionLabel,
        'name'     => $name,
        'page_url' => $pageUrl,
    ]);

    $finalMeta = array_merge($baseMeta, $meta);

    if (strpos($actionLabel, 'audit_action_') === 0) {
        unset($finalMeta['action']);

        nx_audit(
            'UPDATE',
            $page,
            $objectId !== null ? (string)$objectId : 'singleton',
            $actionLabel,
            $finalMeta
        );
        return;
    }
}

/**
 * Plugin-Wrapper
 */
function nx_audit_plugin(
    string $action,
    $objectId = null,
    ?string $message = null,
    array $meta = [],
    ?string $pluginName = null,
    ?string $pluginTable = null
): void {
    if (!isset($GLOBALS['_database']) || !($GLOBALS['_database'] instanceof mysqli)) return;

    $detected = $pluginName ?: (nx_audit_detect_plugin_name() ?? 'unknown');
    $plugin   = nx_audit_sanitize_identifier($detected);

    $objectType = 'plugin:' . $plugin;

    if ($pluginTable === null || $pluginTable === '') {
        $pluginTable = 'plugins_' . $plugin;
    }
    $pluginTable = nx_audit_sanitize_identifier($pluginTable);

    $meta['plugin'] = $plugin;
    $meta['table']  = $pluginTable;

    nx_admin_audit($GLOBALS['_database'], $action, null, $objectType, $objectId, $message, $meta);
}
?>
