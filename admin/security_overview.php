<?php

// Überprüfen, ob die Session bereits gestartet wurde
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\LoginSecurity;
use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_security_overview');

// Standardsprache setzen
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Sprachservice initialisieren
global $languageService,$_database;;
$languageService = new LanguageService($_database);
$languageService->readModule('security_overview', true);

echo '<div class="card">
<div class="card-header">
    <i class="bi bi-paragraph"></i> ' . $languageService->get('registration_login_activities') . '
</div>
<div class="card-body"><div class="container py-5">';
echo '<h4>' . $languageService->get('registration_attempts_title') . '</h4>';

// Pagination-Einstellungen
$limit = 10;
$page = isset($_GET['regpage']) ? (int)$_GET['regpage'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl der Versuche zählen
$countResult = safe_query("SELECT COUNT(*) AS total FROM user_register_attempts");
$countRow = mysqli_fetch_array($countResult);
$totalAttempts = (int)$countRow['total'];
$totalPages = ceil($totalAttempts / $limit);

// Versuche abrufen
$query = safe_query("SELECT * FROM user_register_attempts ORDER BY attempt_time DESC LIMIT $limit OFFSET $offset");

echo '<table class="table table-bordered table-striped bg-white shadow-sm">
    <thead class="table-light">
        <tr>
            <th scope="col">' . $languageService->get('id_short') . '</th>
            <th>' . $languageService->get('username') . '</th>
            <th>' . $languageService->get('email') . '</th>
            <th scope="col">' . $languageService->get('ip_address') . '</th>
            <th scope="col">' . $languageService->get('timestamp') . '</th>
            <th scope="col">' . $languageService->get('status') . '</th>
            <th scope="col">' . $languageService->get('reason') . '</th>
        </tr>
    </thead>
    <tbody>';

while ($row = mysqli_fetch_array($query)) {
    $status_badge = $row['status'] === 'success'
        ? '<span class="badge bg-success">' . $languageService->get('success') . '</span>'
        : '<span class="badge bg-danger">' . $languageService->get('failed') . '</span>';

    echo '<tr>
            <td>' . (int)$row['id'] . '</td>
            <td>' . htmlspecialchars($row['username']) . '</td>
            <td>' . htmlspecialchars($row['email']) . '</td>
            <td>' . htmlspecialchars($row['ip_address']) . '</td>
            <td>' . date("d.m.Y H:i", strtotime($row['attempt_time'])) . '</td>
            <td>' . $status_badge . '</td>
            <td>' . htmlspecialchars($row['reason'] ?? '-') . '</td>
        </tr>';
}

echo '</tbody></table>';

// Pagination Links
if ($totalPages > 1) {
    echo '<nav class="mt-3"><ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<li class="page-item ' . $activeClass . '">
                    <a class="page-link" href="?site=security_overview&regpage=' . $i . '">' . $i . '</a>
                </li>';
    }
    echo '</ul></nav>';
}


echo '';
echo '<h4>' . $languageService->get('users') . '</h4>';

// Pagination-Einstellungen
$limit = 10;
$page = isset($_GET['userpage']) ? (int)$_GET['userpage'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl Benutzer zählen
$countResult = $_database->query("SELECT COUNT(*) AS total FROM users");
$countRow = $countResult->fetch_assoc();
$totalUsers = (int)$countRow['total'];
$totalPages = ceil($totalUsers / $limit);

// Benutzer abrufen
$get = $_database->query("
    SELECT userID, username, email, is_active, registerdate
    FROM users
    ORDER BY registerdate DESC
    LIMIT $limit OFFSET $offset
");

echo '<table class="table table-bordered table-striped bg-white shadow-sm">
    <thead class="table-light">
        <tr>
            <th>' . $languageService->get('id') . '</th>
            <th>' . $languageService->get('username') . '</th>
            <th>' . $languageService->get('email') . '</th>
            <th>' . $languageService->get('activated') . '</th>
            <th>' . $languageService->get('registered') . '</th>
        </tr>
    </thead>';
echo '<tbody>';

while ($ds = $get->fetch_assoc()) {
    echo '<tr>
        <td>' . (int)$ds['userID'] . '</td>
        <td>' . htmlspecialchars($ds['username']) . '</td>
        <td>' . htmlspecialchars($ds['email']) . '</td>
        <td>' . ($ds['is_active'] ? '✔️' : '❌') . '</td>
        <td>' . date('d.m.Y H:i', strtotime($ds['registerdate'])) . '</td>
    </tr>';
}

echo '</tbody></table>';

// Pagination Links
if ($totalPages > 1) {
    echo '<nav class="mt-3"><ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<li class="page-item ' . $activeClass . '">
                    <a class="page-link" href="?site=security_overview&userpage=' . $i . '">' . $i . '</a>
                </li>';
    }
    echo '</ul></nav>';
}


// ------------------------------------

// Mehrfach-Löschung
if (isset($_POST['delete_selected']) && !empty($_POST['selected_sessions'])) {
    $ids = array_map('trim', $_POST['selected_sessions']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $_database->prepare("DELETE FROM user_sessions WHERE session_id IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
    $stmt->execute();
    $stmt->close();

    header("Location: admincenter.php?site=security_overview&deleted=1");
    exit;
}
// Erfolgsmeldung (wird nur bei vollem Seitenaufruf angezeigt)
if (isset($_GET['deleted'])) {
    echo '<div class="alert alert-success">' . $languageService->get('session_deleted_success') . '</div>';
}


// Pagination-Einstellungen
$limit = 10; // Maximal 10 Sessions pro Seite
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl der Sessions
$countResult = $_database->query("SELECT COUNT(*) AS total FROM user_sessions");
$countRow = $countResult->fetch_assoc();
$totalSessions = (int)$countRow['total'];
$totalPages = ceil($totalSessions / $limit);

// Sessions abrufen
$getSessions = $_database->query("
    SELECT s.session_id, s.userID, u.username, s.user_ip, s.session_data, s.browser, s.last_activity
    FROM user_sessions s
    LEFT JOIN users u ON s.userID = u.userID
    ORDER BY s.last_activity DESC
    LIMIT $limit OFFSET $offset
");


?>
<div class="container py-5">
    <h4><?= $languageService->get('active_sessions'); ?></h4>

    <form method="POST" action="" onsubmit="return confirm('<?= $languageService->get('confirm_delete_sessions'); ?>');">
        <div id="session-table-container">
            <table class="table table-bordered table-striped bg-white shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th><?= $languageService->get('session_id'); ?></th>
                        <th><?= $languageService->get('username'); ?></th>
                        <th><?= $languageService->get('ip'); ?></th>
                        <th><?= $languageService->get('last_activity'); ?></th>
                        <th><?= $languageService->get('browser'); ?></th>
                        <th>
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
                        <td>' . $sessionTime . '</td>
                        <td>' . htmlspecialchars(substr($ds['browser'], 0, 40)) . '...</td>
                        <td>
                            <input type="checkbox" name="selected_sessions[]" value="' . htmlspecialchars($ds['session_id']) . '">
                        </td>
                    </tr>';
                }
                ?>
                </tbody>
            </table>
        </div> <div class="text-end mt-2">
            <button type="submit" name="delete_selected" class="btn btn-danger">
                <?= $languageService->get('delete_selected'); ?>
            </button>
        </div>
    </form>

    <?php if ($totalPages > 1) : ?>
        <nav>
            <ul id="pagination-container" class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++) :
                    $activeClass = ($i == $page) ? 'active' : '';
                ?>
                    <li class="page-item <?= $activeClass ?>">
                        <a class="page-link" href="javascript:void(0);" onclick="loadPage(<?= $i ?>)"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
// "Alle auswählen" Checkbox
document.getElementById('select-all').addEventListener('click', function() {
    let checkboxes = document.querySelectorAll('input[name="selected_sessions[]"]');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>



<script>
function deleteSession(sessionId) {
    if (!confirm('<?= $languageService->get('confirm_delete_session'); ?>')) return;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/admin/admincenter.php?site=security_overview&ajax=1&delete_session=1', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    loadPage(<?= $page ?>); // Tabelle neu laden
                } else {
                    alert(res.error || 'Löschen fehlgeschlagen');
                }
            } catch (e) {
                console.error(e, xhr.responseText);
            }
        }
    };
    xhr.send('session_id=' + encodeURIComponent(sessionId));
}
</script>


<?php
// AJAX-Anfrage erkennen und nur reinen Inhalt liefern


if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_clean(); // Ausgabe-Puffer leeren für saubere Antwort

    $tableHTML = '<table class="table table-bordered table-striped bg-white shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th>' . $languageService->get('session_id') . '</th>
                            <th>' . $languageService->get('username') . '</th>
                            <th>' . $languageService->get('ip') . '</th>
                            <th>' . $languageService->get('last_activity') . '</th>
                            <th>' . $languageService->get('browser') . '</th>
                            <th>' . $languageService->get('action') . '</th>
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
            <td>' . $sessionTime . '</td>
            <td>' . htmlspecialchars(substr($ds['browser'], 0, 40)) . '...</td>
            <td>
                <button type="button" class="btn btn-danger" onclick="deleteSession('. $ds['session_id'] .')">
                    '. $languageService->get('delete') .'
                </button>
            </td>
        </tr>';
    }

    $tableHTML .= '</tbody></table>';

    // Pagination neu bauen
    $paginationHTML = '';
    if ($totalPages > 1) {
        $paginationHTML = '<ul class="pagination justify-content-center">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $activeClass = ($i == $page) ? 'active' : '';
            $paginationHTML .= '<li class="page-item ' . $activeClass . '">
                                    <a class="page-link" href="javascript:void(0);" onclick="loadPage(' . $i . ')">' . $i . '</a>
                                </li>';
        }
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

// Fehlgeschlagene Login-Versuche (letzte 15 Minuten) - Anzeige
echo '<h4>' . $languageService->get('failed_login_attempts_title') . '</h4>';

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
$totalPages = ceil($totalIps / $limit);

// IPs abrufen mit LIMIT und OFFSET
$get = $_database->query("
    SELECT ip, COUNT(*) AS attempts, MAX(UNIX_TIMESTAMP(attempt_time)) AS last_attempt
    FROM failed_login_attempts
    WHERE attempt_time > NOW() - INTERVAL 15 MINUTE
    GROUP BY ip
    ORDER BY attempts DESC
    LIMIT $limit OFFSET $offset
");

echo '<table class="table table-bordered table-striped bg-white shadow-sm">
    <thead class="table-light">
        <tr>
            <th>' . $languageService->get('ip_address') . '</th>
            <th>' . $languageService->get('attempts') . '</th>
            <th>' . $languageService->get('last_attempt') . '</th>
            <th>' . $languageService->get('action') . '</th>
        </tr>
    </thead>
    <tbody>';

while ($ds = $get->fetch_assoc()) {
    echo '<tr>
            <td>' . htmlspecialchars($ds['ip']) . '</td>
            <td>' . (int)$ds['attempts'] . '</td>
            <td>' . date("d.m.Y H:i:s", $ds['last_attempt']) . '</td>
            <td><button class="btn btn-danger ban-ip-btn" data-ip="' . htmlspecialchars($ds['ip']) . '">' . $languageService->get('ban') . '</button></td>
          </tr>';
}

echo '</tbody></table>';

// Pagination Links
if ($totalPages > 1) {
    echo '<nav class="mt-3"><ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<li class="page-item ' . $activeClass . '">
                    <a class="page-link" href="?site=security_overview&failpage=' . $i . '">' . $i . '</a>
                </li>';
    }
    echo '</ul></nav>';
}

// JavaScript für AJAX-Handling (Sperren-Button)
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const banButtons = document.querySelectorAll('.ban-ip-btn');
    banButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const ip = this.getAttribute('data-ip');
            if (confirm('<?= $languageService->get('confirm_ban_ip'); ?>'.replace('%s', ip))) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ban_ip=' + encodeURIComponent(ip)
                })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'OK') {
                        alert('<?= $languageService->get('ip_banned_success'); ?>');
                        location.reload();
                    } else {
                        alert('<?= $languageService->get('ip_ban_error'); ?>');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('<?= $languageService->get('network_error'); ?>');
                });
            }
        });
    });
});
</script>

<?php

echo '<h4>' . $languageService->get('banned_ips_title') . '</h4>';

// Pagination-Einstellungen für gebannte IPs
$limit = 10;
$page = isset($_GET['banpage']) ? (int)$_GET['banpage'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Gesamtanzahl gesperrter IPs holen
$countResult = $_database->query("SELECT COUNT(*) AS total FROM banned_ips");
$countRow = $countResult->fetch_assoc();
$totalIps = (int)$countRow['total'];
$totalPages = ceil($totalIps / $limit);

// IPs abrufen mit LIMIT und OFFSET
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
    $ipToDelete = $_database->real_escape_string($_POST['delete_ip']);

    // IP aus der Datenbank löschen
    $deleteQuery = "DELETE FROM banned_ips WHERE ip = '$ipToDelete' LIMIT 1";
    if ($_database->query($deleteQuery)) {
        echo '<div class="alert alert-success">' . $languageService->get('ip_deleted') . '</div>';
    } else {
        echo '<div class="alert alert-danger">' . $languageService->get('ip_delete_failed') . '</div>';
    }
}


$get = $_database->query($query);

echo '<table class="table table-bordered table-striped bg-white shadow-sm">
        <thead class="table-light">
            <tr>
        <th>' . $languageService->get('ip') . '</th>
        <th>' . $languageService->get('username') . '</th>
        <th>' . $languageService->get('email') . '</th>
        <th>' . $languageService->get('role') . '</th>
        <th>' . $languageService->get('unban_time') . '</th>
        <th>' . $languageService->get('reason') . '</th>
        <th>' . $languageService->get('action') . '</th>
    </tr></thead><tbody>';

while ($ds = $get->fetch_assoc()) {
    echo '<tr>
        <td>' . htmlspecialchars($ds['ip']) . '</td>
        <td>' . (!empty($ds['username']) ? htmlspecialchars($ds['username']) : '<em>' . $languageService->get('unknown') . '</em>') . '</td>
        <td>' . (!empty($ds['email']) ? htmlspecialchars($ds['email']) : '<em>' . $languageService->get('unknown') . '</em>') . '</td>
        <td>' . (isset($ds['role_name']) ? htmlspecialchars($ds['role_name']) : '<em>' . $languageService->get('none') . '</em>') . '</td>
        <td>' . date("d.m.Y H:i", strtotime($ds['deltime'])) . '</td>
        <td>' . htmlspecialchars($ds['reason']) . '</td>
        <td>
            <form method="post" onsubmit="return confirm(\'' . $languageService->get('confirm_delete_ip') . '\');" style="display:inline;">
                <input type="hidden" name="delete_ip" value="' . htmlspecialchars($ds['ip']) . '">
                <button type="submit" class="btn btn-danger">' . $languageService->get('delete') . '</button>
            </form>
        </td>
    </tr>';
}
echo '</tbody></table>';

// Pagination Links für gebannte IPs
if ($totalPages > 1) {
    echo '<nav class="mt-3"><ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<li class="page-item ' . $activeClass . '">
                    <a class="page-link" href="?site=security_overview&banpage=' . $i . '">' . $i . '</a>
                </li>';
    }
    echo '</ul></nav>';
}
echo '</div></div></div>';

?>