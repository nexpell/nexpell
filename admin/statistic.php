<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standard setzen, wenn nicht vorhanden
// Standard setzen, wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('statistic', true);

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_statistic');


// Benutzerstatistiken vorbereiten
$res = safe_query("SELECT COUNT(*) AS total_users FROM users");
$total_users = mysqli_fetch_array($res)['total_users'];

$today = strtotime(date('Y-m-d 00:00:00'));
$weekly = strtotime('-7 days');
$monthly = strtotime('-30 days');

$today_users = mysqli_fetch_array(safe_query("SELECT COUNT(*) AS count FROM users WHERE registerdate >= $today"))['count'];
$week_users = mysqli_fetch_array(safe_query("SELECT COUNT(*) AS count FROM users WHERE registerdate >= $weekly"))['count'];
$month_users = mysqli_fetch_array(safe_query("SELECT COUNT(*) AS count FROM users WHERE registerdate >= $monthly"))['count'];

$last30 = strtotime('-30 days');
$active = mysqli_fetch_array(safe_query("SELECT COUNT(*) AS count FROM users WHERE lastlogin >= $last30"))['count'];
$inactive = $total_users - $active;

// Benutzer nach Rollen
$roles = safe_query("
    SELECT r.role_name, COUNT(*) AS count
    FROM user_role_assignments ura
    JOIN user_roles r ON ura.roleID = r.roleID
    GROUP BY r.roleID
");

// Letzte Logins
$logins = safe_query("SELECT username, FROM_UNIXTIME(lastlogin) AS login_time FROM users ORDER BY lastlogin DESC LIMIT 10");

// Klickstatistik vorbereiten
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');

$clicksPerDayRes = $_database->query("
    SELECT DATE(clicked_at) AS day, COUNT(*) AS clicks
    FROM link_clicks
    WHERE clicked_at BETWEEN '$startDate' AND '$endDate'
    GROUP BY day
    ORDER BY day DESC
");

$topUrlsRes = $_database->query("
    SELECT url, COUNT(*) AS clicks
    FROM link_clicks
    GROUP BY url
    ORDER BY clicks DESC
    LIMIT 10
");

$topIpsRes = $_database->query("
    SELECT ip_address, COUNT(*) AS clicks
    FROM link_clicks
    GROUP BY ip_address
    ORDER BY clicks DESC
    LIMIT 5
");

$totalClicks = $_database->query("SELECT COUNT(*) AS total FROM link_clicks")->fetch_assoc()['total'];

// Benutzer mit Avatar
$with_avatar_result = safe_query("
  SELECT COUNT(*) AS count
  FROM users u
  INNER JOIN user_profiles p ON u.userID = p.userID
  WHERE p.avatar IS NOT NULL AND p.avatar != ''
");
$row = mysqli_fetch_assoc($with_avatar_result);
$with_avatar = (int)$row['count'];

// Benutzer ohne Avatar
$without_avatar_result = safe_query("
  SELECT COUNT(*) AS count
  FROM users u
  LEFT JOIN user_profiles p ON u.userID = p.userID
  WHERE p.avatar IS NULL OR p.avatar = ''
");
$row2 = mysqli_fetch_assoc($without_avatar_result);
$without_avatar = (int)$row2['count'];

// Pagination (optional)
$limit = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Anzahl Klicks zählen
$countResult = $_database->query("SELECT COUNT(*) as count FROM link_clicks");
$countRow = $countResult->fetch_assoc();
$totalClicks = $countRow['count'];

// Klicks holen mit Limit & Offset
$sql = "SELECT * FROM link_clicks";
$result = $_database->query($sql);
?>

<?php echo '<div class="card">
    <div class="card-header">' . $languageService->get('user_statistics') . '</div>
    <div class="card-body">
        <div class="container py-4">';
?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">👤 <?= $languageService->get('user_statistics') ?></h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $languageService->get('total_users') ?>
                        <span class="badge bg-secondary"><?= $total_users ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $languageService->get('today_registered') ?>
                        <span class="badge bg-secondary"><?= $today_users ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $languageService->get('last_7_days') ?>
                        <span class="badge bg-secondary"><?= $week_users ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $languageService->get('last_30_days') ?>
                        <span class="badge bg-secondary"><?= $month_users ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $languageService->get('active_users') ?>
                        <span class="badge bg-success"><?= $active ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $languageService->get('inactive_users') ?>
                        <span class="badge bg-danger"><?= $inactive ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $languageService->get('with_profile_picture') ?>
                        <span class="badge bg-info"><?= $with_avatar ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $languageService->get('without_profile_picture') ?>
                        <span class="badge bg-info"><?= $without_avatar ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">👥 <?= $languageService->get('users_by_role') ?></h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php while ($row = mysqli_fetch_array($roles)): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($row['role_name']) ?>
                            <span class="badge bg-primary rounded-pill"><?= $row['count'] ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">⏱ <?= $languageService->get('last_logins') ?></h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    while ($row = mysqli_fetch_assoc($logins)):
                        $login_time = $row['login_time'] ?? '';
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= htmlspecialchars($row['username']) ?></span>
                            <small class="text-muted"><?= htmlspecialchars($login_time) ?></small>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<br>

<div class="card mb-4 shadow-sm mt-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> <?= $languageService->get('link_click_analysis') ?></h5>
    </div>
    <div class="card-body">
        <div class="row g-4">

            <!-- Klicks pro Tag -->
            <div class="col-md-4">
                <h6><i class="bi bi-calendar-week"></i> <?= $languageService->get('clicks_per_day') ?></h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= $languageService->get('date') ?></th>
                                <th><?= $languageService->get('clicks') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $clicksPerDayRes->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['day']) ?></td>
                                    <td><?= $row['clicks'] ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top 10 URLs -->
            <div class="col-md-4">
                <h6><i class="bi bi-link-45deg"></i> <?= $languageService->get('top_10_urls') ?></h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>URL</th>
                                <th><?= $languageService->get('clicks') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $topUrlsRes->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-truncate" style="max-width: 250px;">
                                        <a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" rel="nofollow">
                                            <?= htmlspecialchars($row['url']) ?>
                                        </a>
                                    </td>
                                    <td><?= $row['clicks'] ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top IPs -->
            <div class="col-md-4">
                <h6><i class="bi bi-pc-display"></i> <?= $languageService->get('top_ips') ?></h6>
                <ul class="list-group list-group-flush">
                    <?php while ($row = $topIpsRes->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($row['ip_address']) ?>
                            <span class="badge bg-secondary rounded-pill"><?= $row['clicks'] ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>

        </div>
    </div>
</div>


<div class="card mb-4 shadow-sm mt-4">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?= $languageService->get('click_management') ?></h5>
        <span class="badge bg-info"><?= $languageService->get('total') ?>: <?= $totalClicks ?></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th><?= $languageService->get('plugin') ?></th>
                        <th><?= $languageService->get('click_url') ?></th>
                        <th><?= $languageService->get('click_time') ?></th>
                        <th><?= $languageService->get('ip_address') ?></th>
                        <th>User-Agent</th>
                        <th><?= $languageService->get('action') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['plugin']) ?></td>
                                <td><a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" rel="nofollow" class="text-truncate" style="max-width: 250px; display: block;"><?= htmlspecialchars($row['url']) ?></a></td>
                                <td><?= htmlspecialchars($row['clicked_at']) ?></td>
                                <td><?= htmlspecialchars($row['ip_address']) ?></td>
                                <td><div class="text-truncate" style="max-width: 200px;"><?= htmlspecialchars($row['user_agent']) ?></div></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('<?= $languageService->get('confirm_delete') ?>');" class="m-0">
                                        <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><?= $languageService->get('delete') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted"><?= $languageService->get('no_clicks_found') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $totalPages = ceil($totalClicks / $limit);
        if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php echo '</div></div></div>'; ?>


<?php
// Löschfunktion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $_database->query("DELETE FROM link_clicks WHERE id = $deleteId");
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
?>