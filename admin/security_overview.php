<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\LoginSecurity;
use nexpell\AccessControl;

// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_security_overview');

// Mehrfach-Löschung (Sessions)
if (isset($_POST['delete_selected']) && !empty($_POST['selected_sessions'])) {
    $ids = array_map('trim', (array)$_POST['selected_sessions']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $_database->prepare("DELETE FROM user_sessions WHERE session_id IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
    $stmt->execute();
    $stmt->close();

    nx_audit_action('security_overview', 'audit_action_named', 'sessions_bulk_delete', null, 'admincenter.php?site=security_overview', ['action' => nx_translate('alert_deleted'), 'count' => count($ids)]);
    nx_redirect('admincenter.php?site=security_overview', 'success', 'alert_deleted', false);
}

// Head / Seitencontainer
echo '<div class="row g-4 align-items-stretch">';

// Registrierungsversuche
echo '<div class="col-12 col-xl-8 col-lg-7">';
echo '  <div class="card shadow-sm border-0 mb-4 mt-4">';
echo '      <div class="card-header">';
echo '          <div class="card-title mb-0">';
echo '              <i class="bi bi-person-plus me-1"></i>';
echo '              <span>' . $languageService->get('registration_attempts_title') . '</span>';
echo '          </div>';
echo '      </div>';
echo '      <div class="card-body p-4">';

// Pagination-Einstellungen
$limit = 10;
$page = isset($_GET['regpage']) ? (int)$_GET['regpage'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl der Versuche zählen
$countResult = safe_query("SELECT COUNT(*) AS total FROM user_register_attempts");
$countRow = mysqli_fetch_array($countResult);
$totalAttempts = (int)$countRow['total'];
$totalPages = (int)ceil($totalAttempts / $limit);

// Versuche abrufen
$query = safe_query("SELECT * FROM user_register_attempts ORDER BY attempt_time DESC LIMIT $limit OFFSET $offset");

echo '<div class="table-responsive">';
echo '<table class="table">';
echo '  <thead>';
echo '      <tr>';
echo '          <th scope="col">' . $languageService->get('id_short') . '</th>';
echo '          <th>' . $languageService->get('username') . '</th>';
echo '          <th>' . $languageService->get('email') . '</th>';
echo '          <th scope="col">' . $languageService->get('ip_address') . '</th>';
echo '          <th scope="col">' . $languageService->get('timestamp') . '</th>';
echo '          <th scope="col">' . $languageService->get('status') . '</th>';
echo '          <th scope="col">' . $languageService->get('reason') . '</th>';
echo '      </tr>';
echo '  </thead>';
echo '  <tbody>';

while ($row = mysqli_fetch_array($query)) {
    $status_badge = $row['status'] === 'success'
        ? '<span class="badge bg-success">' . $languageService->get('success') . '</span>'
        : '<span class="badge bg-danger">' . $languageService->get('failed') . '</span>';

    echo '<tr>
            <td>' . (int)$row['id'] . '</td>
            <td>' . htmlspecialchars($row['username']) . '</td>
            <td>' . htmlspecialchars($row['email']) . '</td>
            <td>' . htmlspecialchars($row['ip_address']) . '</td>
            <td class="text-nowrap">' . date("d.m.Y H:i", strtotime($row['attempt_time'])) . '</td>
            <td>' . $status_badge . '</td>
            <td>' . htmlspecialchars($row['reason'] ?? '-') . '</td>
        </tr>';
}

echo '  </tbody>';
echo '</table>';
echo '</div>';

// Pagination Links
if ($totalPages > 1) {
    echo '<nav class="mt-3" aria-label="Pagination"><ul class="pagination justify-content-center mb-0">';

    // Zurück
    $prevDisabled = ($page <= 1) ? 'disabled' : '';
    $prevPage = max(1, $page - 1);
    echo '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="?site=security_overview&regpage=' . $prevPage . '" tabindex="-1"><i class="bi bi-chevron-left" aria-hidden="true"></i></a></li>';

    // Seitenzahlen
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<li class="page-item ' . $activeClass . '">
                <a class="page-link" href="?site=security_overview&regpage=' . $i . '">' . $i . '</a>
              </li>';
    }

    // Weiter
    $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
    $nextPage = min($totalPages, $page + 1);
    echo '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="?site=security_overview&regpage=' . $nextPage . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>';

    echo '</ul></nav>';
}

echo '      </div>';
echo '  </div>';
// Fehlgeschlagene Login-Versuche
echo '  <div class="card shadow-sm border-0 mb-4">';
echo '      <div class="card-header">';
echo '          <div class="card-title mb-0">';
echo '              <i class="bi bi-shield-lock me-1"></i>';
echo '              <span>' . $languageService->get('failed_login_attempts_title') . '</span>';
echo '          </div>';
echo '      </div>';
echo '      <div class="card-body p-4">';

// Pagination-Einstellungen
$limit = 10;
$page = isset($_GET['failpage']) ? (int)$_GET['failpage'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl an gruppierten IPs holen
$countResult = $_database->query("
    SELECT COUNT(*) AS total
    FROM (
        SELECT ip
        FROM failed_login_attempts
        WHERE attempt_time > NOW() - INTERVAL 15 MINUTE
        GROUP BY ip
    ) AS grouped
");
$countRow = $countResult->fetch_assoc();
$totalIps = (int)$countRow['total'];
$totalPages = (int)ceil($totalIps / $limit);

// IPs abrufen
$get = $_database->query("
    SELECT ip, COUNT(*) AS attempts, MAX(UNIX_TIMESTAMP(attempt_time)) AS last_attempt
    FROM failed_login_attempts
    WHERE attempt_time > NOW() - INTERVAL 15 MINUTE
    GROUP BY ip
    ORDER BY attempts DESC
    LIMIT $limit OFFSET $offset
");

echo '<div class="table-responsive">';
echo '<table class="table">
    <thead>
        <tr>
            <th>' . $languageService->get('ip_address') . '</th>
            <th>' . $languageService->get('attempts') . '</th>
            <th>' . $languageService->get('last_attempt') . '</th>
            <th class="text-end">' . $languageService->get('action') . '</th>
        </tr>
    </thead>
    <tbody>';

while ($ds = $get->fetch_assoc()) {
    echo '<tr>
            <td class="text-nowrap">' . htmlspecialchars($ds['ip']) . '</td>
            <td>' . (int)$ds['attempts'] . '</td>
            <td class="text-nowrap">' . date("d.m.Y H:i:s", $ds['last_attempt']) . '</td>
            <td class="text-end">
                <button class="btn btn-danger ban-ip-btn" data-ip="' . htmlspecialchars($ds['ip']) . '">' . $languageService->get('ban') . '</button>
            </td>
          </tr>';
}

echo '</tbody></table>';
echo '</div>';

// Pagination Links
if ($totalPages > 1) {
    echo '<nav class="mt-3" aria-label="Pagination"><ul class="pagination justify-content-center mb-0">';

    // Zurück
    $prevDisabled = ($page <= 1) ? 'disabled' : '';
    $prevPage = max(1, $page - 1);
    echo '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="?site=security_overview&failpage=' . $prevPage . '" tabindex="-1"><i class="bi bi-chevron-left" aria-hidden="true"></i></a></li>';

    // Seitenzahlen
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<li class="page-item ' . $activeClass . '">
                <a class="page-link" href="?site=security_overview&failpage=' . $i . '">' . $i . '</a>
              </li>';
    }

    // Weiter
    $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
    $nextPage = min($totalPages, $page + 1);
    echo '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="?site=security_overview&failpage=' . $nextPage . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>';

    echo '</ul></nav>';
}

echo '      </div>';
echo '  </div>';

// Gesperrte IPs
echo '  <div class="card shadow-sm border-0 mb-4">';
echo '      <div class="card-header">';
echo '          <div class="card-title mb-0">';
echo '              <i class="bi bi-ban me-1"></i>';
echo '              <span>' . $languageService->get('banned_ips_title') . '</span>';
echo '          </div>';
echo '      </div>';
echo '      <div class="card-body p-4">';

// Pagination-Einstellungen für gebannte IPs
$limit = 10;
$page = isset($_GET['banpage']) ? (int)$_GET['banpage'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl gesperrter IPs holen
$countResult = $_database->query("SELECT COUNT(*) AS total FROM banned_ips");
$countRow = $countResult->fetch_assoc();
$totalIps = (int)$countRow['total'];
$totalPages = (int)ceil($totalIps / $limit);

// IPs abrufen
$query = "
    SELECT
        b.ip,
        b.deltime,
        b.reason,
        b.email,
        u.username,
        r.role_name AS role_name
    FROM banned_ips b
    LEFT JOIN users u ON b.userID = u.userID
    LEFT JOIN user_role_assignments ura ON u.userID = ura.userID
    LEFT JOIN user_roles r ON ura.roleID = r.roleID
    ORDER BY b.deltime DESC
    LIMIT $limit OFFSET $offset
";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_ip'])) {
    $ipToDelete = $_database->real_escape_string((string)$_POST['delete_ip']);

    if ($_database->query("DELETE FROM banned_ips WHERE ip = '$ipToDelete' LIMIT 1")) {
        nx_audit_delete('security_overview', $ipToDelete, $ipToDelete, 'admincenter.php?site=security_overview');
        nx_alert('success', 'alert_deleted', false);
    } else {
        nx_alert('danger', 'alert_save_failed', false);
    }
}

$get = $_database->query($query);

echo '<div class="table-responsive">';
echo '<table class="table">
        <thead>
            <tr>
                <th>' . $languageService->get('ip') . '</th>
                <th>' . $languageService->get('username') . '</th>
                <th>' . $languageService->get('email') . '</th>
                <th>' . $languageService->get('role') . '</th>
                <th>' . $languageService->get('unban_time') . '</th>
                <th>' . $languageService->get('reason') . '</th>
                <th class="text-end">' . $languageService->get('action') . '</th>
            </tr>
        </thead>
        <tbody>';

while ($ds = $get->fetch_assoc()) {
    echo '<tr>
        <td class="text-nowrap">' . htmlspecialchars($ds['ip']) . '</td>
        <td>' . (!empty($ds['username']) ? htmlspecialchars($ds['username']) : '<em>' . $languageService->get('unknown') . '</em>') . '</td>
        <td>' . (!empty($ds['email']) ? htmlspecialchars($ds['email']) : '<em>' . $languageService->get('unknown') . '</em>') . '</td>
        <td>' . (isset($ds['role_name']) ? htmlspecialchars($ds['role_name']) : '<em>' . $languageService->get('none') . '</em>') . '</td>
        <td class="text-nowrap">' . date("d.m.Y H:i", strtotime($ds['deltime'])) . '</td>
        <td>' . htmlspecialchars($ds['reason']) . '</td>
	        <td class="text-end">
	            <form method="post" onsubmit="return confirm(&quot;' . htmlspecialchars($languageService->get('confirm_delete_ip')) . '&quot;);" style="display:inline;">
                <input type="hidden" name="delete_ip" value="' . htmlspecialchars($ds['ip']) . '">
                <button type="submit" class="btn btn-danger">' . $languageService->get('delete') . '</button>
            </form>
        </td>
    </tr>';
}

echo '</tbody></table>';
echo '</div>';

// Pagination Links für gebannte IPs
if ($totalPages > 1) {
    echo '<nav class="mt-3" aria-label="Pagination"><ul class="pagination justify-content-center mb-0">';

    // Zurück
    $prevDisabled = ($page <= 1) ? 'disabled' : '';
    $prevPage = max(1, $page - 1);
    echo '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="?site=security_overview&banpage=' . $prevPage . '" tabindex="-1"><i class="bi bi-chevron-left" aria-hidden="true"></i></a></li>';

    // Seitenzahlen
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<li class="page-item ' . $activeClass . '">
                <a class="page-link" href="?site=security_overview&banpage=' . $i . '">' . $i . '</a>
              </li>';
    }

    // Weiter
    $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
    $nextPage = min($totalPages, $page + 1);
    echo '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="?site=security_overview&banpage=' . $nextPage . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>';

    echo '</ul></nav>';
}

echo '      </div>';
echo '  </div>';
echo '</div>';

// Benutzerübersicht
echo '<div class="col-12 col-xl-4 col-lg-5 d-flex">';
echo '  <div class="card shadow-sm border-0 mb-4 mt-4 w-100 h-100">';
echo '      <div class="card-header">';
echo '          <div class="card-title mb-0">';
echo '              <i class="bi bi-people me-1"></i>';
echo '              <span>' . $languageService->get('users') . '</span>';
echo '          </div>';
echo '      </div>';
echo '      <div class="card-body p-4">';

// Pagination-Einstellungen
$limit = 10;
$page = isset($_GET['userpage']) ? (int)$_GET['userpage'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl Benutzer zählen
$countResult = $_database->query("SELECT COUNT(*) AS total FROM users");
$countRow = $countResult->fetch_assoc();
$totalUsers = (int)$countRow['total'];
$totalPages = (int)ceil($totalUsers / $limit);

// Benutzer abrufen
$get = $_database->query("
    SELECT userID, username, email, is_active, registerdate
    FROM users
    ORDER BY registerdate DESC
    LIMIT $limit OFFSET $offset
");

echo '<div class="table-responsive">';
echo '<table class="table">
    <thead>
        <tr>
            <th>' . $languageService->get('id') . '</th>
            <th>' . $languageService->get('username') . '</th>
            <th>' . $languageService->get('email') . '</th>
            <th class="text-center">' . $languageService->get('activated') . '</th>
            <th>' . $languageService->get('registered') . '</th>
        </tr>
    </thead>
    <tbody>';

while ($ds = $get->fetch_assoc()) {
    $statusBadge = $ds['is_active']
        ? '<span class="badge bg-success">' . $languageService->get('active') . '</span>'
        : '<span class="badge bg-warning">' . $languageService->get('inactive') . '</span>';

    echo '<tr>
        <td>' . (int)$ds['userID'] . '</td>
        <td>' . htmlspecialchars($ds['username']) . '</td>
        <td>' . htmlspecialchars($ds['email']) . '</td>
        <td class="text-center">' . $statusBadge . '</td>
        <td class="text-nowrap">' . date('d.m.Y H:i', strtotime($ds['registerdate'])) . '</td>
    </tr>';
}

echo '</tbody></table>';
echo '</div>';

// Pagination Links
if ($totalPages > 1) {
    echo '<nav class="mt-3" aria-label="Pagination"><ul class="pagination justify-content-center mb-0">';

    // Zurück
    $prevDisabled = ($page <= 1) ? 'disabled' : '';
    $prevPage = max(1, $page - 1);
    echo '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="?site=security_overview&userpage=' . $prevPage . '" tabindex="-1"><i class="bi bi-chevron-left" aria-hidden="true"></i></a></li>';

    // Seitenzahlen
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<li class="page-item ' . $activeClass . '">
                <a class="page-link" href="?site=security_overview&userpage=' . $i . '">' . $i . '</a>
              </li>';
    }

    // Weiter
    $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
    $nextPage = min($totalPages, $page + 1);
    echo '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="?site=security_overview&userpage=' . $nextPage . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>';

    echo '</ul></nav>';
}

echo '      </div>';
echo '  </div>';
echo '</div>';

// Sessions Pagination / Query

$limit = 10; // Maximal 10 Sessions pro Seite
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl der Sessions
$countResult = $_database->query("SELECT COUNT(*) AS total FROM user_sessions");
$countRow = $countResult->fetch_assoc();
$totalSessions = (int)$countRow['total'];
$totalPages = (int)ceil($totalSessions / $limit);

// Sessions abrufen
$getSessions = $_database->query("
    SELECT s.session_id, s.userID, u.username, s.user_ip, s.session_data, s.browser, s.last_activity
    FROM user_sessions s
    LEFT JOIN users u ON s.userID = u.userID
    ORDER BY s.last_activity DESC
    LIMIT $limit OFFSET $offset
");

// Aktive Sessions
?>
<div class="col-12">
    <div class="card shadow-sm border-0 mb-4 mt-4 h-100">
        <div class="card-header">
            <div class="card-title mb-0">
                <i class="bi bi-activity me-1"></i>
                <span><?= $languageService->get('active_sessions'); ?></span>
            </div>
        </div>
        <div class="card-body p-4">

            <form method="POST" action="" id="deleteSelectedSessionsForm">
                <input type="hidden" name="delete_selected" value="1">
                <div id="session-table-container" class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= $languageService->get('session_id'); ?></th>
                                <th><?= $languageService->get('username'); ?></th>
                                <th><?= $languageService->get('ip'); ?></th>
                                <th><?= $languageService->get('last_activity'); ?></th>
                                <th><?= $languageService->get('browser'); ?></th>
                                <th class="text-center">
                                    <input type="checkbox" id="select-all">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        while ($ds = $getSessions->fetch_assoc()) {
                            $username = isset($ds['username']) && !empty($ds['username']) ? $ds['username'] : $languageService->get('unknown');
                            $lastActivityTimestamp = (int)$ds['last_activity'];
                            if ($lastActivityTimestamp == 0) {
                                $lastActivityTimestamp = time();
                            }
                            $sessionTime = date("d.m.Y H:i", $lastActivityTimestamp);

                            echo '<tr>
                                <td>' . htmlspecialchars($ds['session_id']) . '</td>
                                <td>' . htmlspecialchars($username) . '</td>
                                <td>' . htmlspecialchars($ds['user_ip']) . '</td>
                                <td class="text-nowrap">' . $sessionTime . '</td>
                                <td>' . htmlspecialchars(substr($ds['browser'], 0, 40)) . '...</td>
                                <td class="text-center">
                                    <input type="checkbox" name="selected_sessions[]" value="' . htmlspecialchars($ds['session_id']) . '">
                                </td>
                            </tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-end mt-3">
                    <button type="button" class="btn btn-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#confirmDeleteModal"
                            data-delete-form="deleteSelectedSessionsForm">
                        <?= $languageService->get('delete_selected'); ?>
                    </button>
                </div>
            </form>

            <?php if ($totalPages > 1) : ?>
                <nav class="mt-3">
                    <ul id="pagination-container" class="pagination justify-content-center mb-0">
                        <?php
                        $prevDisabled = ($page <= 1) ? 'disabled' : '';
                        $prevPage = max(1, $page - 1);
                        ?>
                        <li class="page-item <?= $prevDisabled ?>">
                            <a class="page-link" href="javascript:void(0)" onclick="if (<?= $page ?> > 1) loadPage(<?= $prevPage ?>)"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPages; $i++) :
                            $activeClass = ($i == $page) ? 'active' : '';
                        ?>
                            <li class="page-item <?= $activeClass ?>">
                                <a class="page-link" href="javascript:void(0)" onclick="loadPage(<?= $i ?>)"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php
                        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
                        $nextPage = min($totalPages, $page + 1);
                        ?>
                        <li class="page-item <?= $nextDisabled ?>">
                            <a class="page-link" href="javascript:void(0)" onclick="if (<?= $page ?> < <?= $totalPages ?>) loadPage(<?= $nextPage ?>)"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        </div>
    </div>
</div>
</div>

<?php
// AJAX-Anfrage erkennen und nur reinen Inhalt liefern
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_clean();

    $tableHTML = '<table class="table">
                    <thead>
                        <tr>
                            <th>' . $languageService->get('session_id') . '</th>
                            <th>' . $languageService->get('username') . '</th>
                            <th>' . $languageService->get('ip') . '</th>
                            <th>' . $languageService->get('last_activity') . '</th>
                            <th>' . $languageService->get('browser') . '</th>
                            <th class="text-center">' . $languageService->get('action') . '</th>
                        </tr>
                    </thead>
                    <tbody>';

    $getSessions = $_database->query("
        SELECT s.session_id, s.userID, u.username, s.user_ip, s.session_data, s.browser, s.last_activity
        FROM user_sessions s
        LEFT JOIN users u ON s.userID = u.userID
        ORDER BY s.last_activity DESC
        LIMIT $limit OFFSET $offset
    ");

    while ($ds = $getSessions->fetch_assoc()) {
        $username = isset($ds['username']) && !empty($ds['username']) ? $ds['username'] : $languageService->get('unknown');
        $lastActivityTimestamp = (int)$ds['last_activity'];
        if ($lastActivityTimestamp == 0) {
            $lastActivityTimestamp = time();
        }
        $sessionTime = date("d.m.Y H:i", $lastActivityTimestamp);

        $tableHTML .= '<tr>
            <td>' . htmlspecialchars($ds['session_id']) . '</td>
            <td>' . htmlspecialchars($username) . '</td>
            <td>' . htmlspecialchars($ds['user_ip']) . '</td>
            <td class="text-nowrap">' . $sessionTime . '</td>
            <td>' . htmlspecialchars(substr($ds['browser'], 0, 40)) . '...</td>
            <td class="text-center">
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-delete-action="deleteSession" data-session-id="' . $ds['session_id'] . '">
                    ' . $languageService->get('delete') . '
                </button>
            </td>
        </tr>';
    }

    $tableHTML .= '</tbody></table>';

    // Pagination neu bauen
$paginationHTML = '';
if ($totalPages > 1) {
    $paginationHTML = '<ul class="pagination justify-content-center mb-0">';

    // Zurück
    $prevDisabled = ($page <= 1) ? 'disabled' : '';
    $prevPage = max(1, $page - 1);
    $paginationHTML .= '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="javascript:void(0);" onclick="if (' . $page . ' > 1) loadPage(' . $prevPage . ')"><i class="bi bi-chevron-left" aria-hidden="true"></i></a></li>';

    // Seitenzahlen
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        $paginationHTML .= '<li class="page-item ' . $activeClass . '">
                                <a class="page-link" href="javascript:void(0);" onclick="loadPage(' . $i . ')">' . $i . '</a>
                            </li>';
    }

    // Weiter
    $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
    $nextPage = min($totalPages, $page + 1);
    $paginationHTML .= '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="javascript:void(0);" onclick="if (' . $page . ' < ' . $totalPages . ') loadPage(' . $nextPage . ')"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>';

    $paginationHTML .= '</ul>';
}

echo json_encode([
        'table' => $tableHTML,
        'pagination' => $paginationHTML
    ]);
    exit;
}

if (!empty($_POST['delete_session']) || !empty($_POST['session_id'])) {
    $sessionId = (int)$_POST['session_id'];
    $deleteQuery = "DELETE FROM user_sessions WHERE session_id = $sessionId LIMIT 1";

    if ($_database->query($deleteQuery)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Konnte Session nicht löschen']);
    }
    exit;
}

// AJAX-Handler für IP-Sperren
if (isset($_POST['ban_ip']) && filter_var($_POST['ban_ip'], FILTER_VALIDATE_IP)) {
    $ipToBan = $_POST['ban_ip'];

    // prüfen, ob IP bereits gesperrt ist
    $check = $_database->prepare("SELECT banID FROM banned_ips WHERE ip = ?");
    $check->bind_param('s', $ipToBan);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows == 0) {
        // IP eintragen
        $stmt = $_database->prepare("
            INSERT INTO banned_ips (ip, deltime, reason, userID, email)
            VALUES (?, NOW() + INTERVAL 7 DAY, ?, 0, '')
        ");
        $reason = $languageService->get('auto_ban_reason');
        $stmt->bind_param('ss', $ipToBan, $reason);
        $stmt->execute();
    }

    echo 'OK';
    exit;
}

?>
<script>
document.addEventListener('DOMContentLoaded', () => {

    /* IP bannen */
    document.querySelectorAll('.ban-ip-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const ip = btn.dataset.ip;
            const confirmText = '<?= $languageService->get('confirm_ban_ip'); ?>'.replace('%s', ip);

            if (!confirm(confirmText)) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ban_ip=' + encodeURIComponent(ip)
            })
            .then(r => r.text())
            .then(t => {
                if (t.trim() === 'OK') {
                    alert('<?= $languageService->get('ip_banned_success'); ?>');
                    location.reload();
                } else {
                    alert('<?= $languageService->get('ip_ban_error'); ?>');
                }
            })
            .catch(err => {
                console.error(err);
                alert('<?= $languageService->get('network_error'); ?>');
            });
        });
    });

    const deleteModalEl = document.getElementById('confirmDeleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    if (deleteModalEl && confirmDeleteBtn) {
        deleteModalEl.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            // Reset
            confirmDeleteBtn.removeAttribute('data-delete-form');
            confirmDeleteBtn.removeAttribute('data-delete-action');
            confirmDeleteBtn.removeAttribute('data-session-id');

            // Form-Submit (Bulk Delete)
            const formId = trigger.getAttribute('data-delete-form');
            if (formId) {
                confirmDeleteBtn.setAttribute('href', '#');
                confirmDeleteBtn.setAttribute('data-delete-form', formId);
                return;
            }

            // Action (AJAX deleteSession)
            const action = trigger.getAttribute('data-delete-action');
            if (action === 'deleteSession') {
                confirmDeleteBtn.setAttribute('href', '#');
                confirmDeleteBtn.setAttribute('data-delete-action', 'deleteSession');
                confirmDeleteBtn.setAttribute('data-session-id', trigger.getAttribute('data-session-id') || '');
            }
        });

        confirmDeleteBtn.addEventListener('click', (e) => {
            const formId = confirmDeleteBtn.getAttribute('data-delete-form');
            const action = confirmDeleteBtn.getAttribute('data-delete-action');

            if (!formId && !action) return;

            e.preventDefault();

            if (formId) {
                const form = document.getElementById(formId);
                if (form) form.submit();
                return;
            }

            if (action === 'deleteSession') {
                const sessionId = confirmDeleteBtn.getAttribute('data-session-id');
                if (sessionId) deleteSession(sessionId);
            }
        });
    }


    /* Alle Sessions auswählen */
    document.getElementById('select-all')?.addEventListener('click', e => {
        document
            .querySelectorAll('input[name="selected_sessions[]"]')
            .forEach(cb => cb.checked = e.target.checked);
    });

});

/* Session löschen */
function deleteSession(sessionId) {
    if (!confirm('<?= $languageService->get('confirm_delete_session'); ?>')) return;

    fetch('/admin/admincenter.php?site=security_overview&ajax=1&delete_session=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'session_id=' + encodeURIComponent(sessionId)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            loadPage(<?= (int)$page ?>);
        } else {
           alert(res.error || <?= json_encode($languageService->get('delete_failed')) ?>);
        }
    })
    .catch(err => console.error(err));
}

/* Pagination */
function loadPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}
</script>