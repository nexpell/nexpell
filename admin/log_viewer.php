<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

// Standardsprache setzen
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Sprachservice initialisieren
global $languageService,$_database;;
$languageService = new LanguageService($_database);
$languageService->readModule('log_viewer', true);

use nexpell\AccessControl;
AccessControl::checkAdminAccess('ac_log_viewer');

// Log-Dateien
$logFileAccess = __DIR__ . '/logs/access_control.log';
$logFileSuspicious = __DIR__ . '/logs/suspicious_access.log';
$blockedIPsFile = __DIR__ . '/logs/blocked_ips.json';

// Parameter Access Control Log
$pageAccess = isset($_GET['page_access']) ? max(1, (int)$_GET['page_access']) : 1;
$searchAccess = isset($_GET['search_access']) ? trim($_GET['search_access']) : '';

// Parameter Suspicious Access Log
$pageSuspicious = isset($_GET['page_suspicious']) ? max(1, (int)$_GET['page_suspicious']) : 1;
$searchSuspicious = isset($_GET['search_suspicious']) ? trim($_GET['search_suspicious']) : '';

// Parameter Blocked IPs
$pageBlocked = isset($_GET['page_blocked']) ? max(1, (int)$_GET['page_blocked']) : 1;
$searchBlocked = isset($_GET['search_blocked']) ? trim($_GET['search_blocked']) : '';

$linesPerPage = 50;

/* =========================
    POST-Handler: IP block/unblock
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blockedData = file_exists($blockedIPsFile) ? json_decode(file_get_contents($blockedIPsFile), true) : [];
    if (!is_array($blockedData)) $blockedData = [];

    // Neue IP blockieren
    if (isset($_POST['block_ip'], $_POST['block_reason'])) {
        $ip = trim($_POST['block_ip']);
        $reason = trim($_POST['block_reason']);
        if ($ip !== '') {
            $blockedData[] = ['ip' => $ip, 'reason' => $reason, 'date' => date('Y-m-d H:i:s')];
        }
    }

    // IP freigeben
    if (isset($_POST['unblock_ip'])) {
        $unblockIP = $_POST['unblock_ip'];
        $blockedData = array_filter($blockedData, fn($item) => ($item['ip'] ?? '') !== $unblockIP);
    }

    file_put_contents($blockedIPsFile, json_encode(array_values($blockedData), JSON_PRETTY_PRINT));
    // Redirect nach POST
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/* =========================
    Funktionen laden Logs
========================= */
function loadLogLines(string $file, string $search, int $page, int $entriesPerPage): array {
    if (!file_exists($file)) return ['lines' => [], 'total' => 0, 'pages' => 1, 'page' => 1];

    $content = file_get_contents($file);
    if ($content === false) return ['lines' => [], 'total' => 0, 'pages' => 1, 'page' => 1];

    // Einträge anhand der Trennlinie splitten
    $entries = preg_split('/[-]{40,}/', $content);
    $entries = array_map('trim', $entries);
    $entries = array_filter($entries); // leere entfernen

    // Filter nach Suchbegriff
    if ($search !== '') {
        $entries = array_values(array_filter($entries, fn($entry) => stripos($entry, $search) !== false));
    }

    // Neueste Einträge zuerst
    $entries = array_reverse($entries);

    $totalEntries = count($entries);
    $totalPages = max(1, (int)ceil($totalEntries / $entriesPerPage));
    $page = min($page, $totalPages);

    $start = ($page - 1) * $entriesPerPage;
    $displayEntries = array_slice($entries, $start, $entriesPerPage);

    // Trennlinie wieder an jeden Eintrag anhängen
    $displayEntries = array_map(fn($entry) => $entry . "\n----------------------------------------", $displayEntries);

    return ['lines' => $displayEntries, 'total' => $totalEntries, 'pages' => $totalPages, 'page' => $page];
}




function loadBlockedIPs(string $file, string $search, int $page, int $linesPerPage): array {
    if (!file_exists($file)) return ['lines' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) $data = [];

    if ($search !== '') {
        $data = array_filter($data, fn($item) => stripos($item['ip'] ?? '', $search) !== false || stripos($item['reason'] ?? '', $search) !== false);
    }

    $totalLines = count($data);
    $totalPages = max(1, (int)ceil($totalLines / $linesPerPage));
    $page = min($page, $totalPages);

    $start = ($page - 1) * $linesPerPage;
    $displayLines = array_slice($data, $start, $linesPerPage);

    return ['lines' => $displayLines, 'total' => $totalLines, 'pages' => $totalPages, 'page' => $page];
}





/* =========================
    Logs laden
========================= */
$accessLog = loadLogLines($logFileAccess, $searchAccess, $pageAccess, $linesPerPage);
$suspiciousLog = loadLogLines($logFileSuspicious, $searchSuspicious, $pageSuspicious, $linesPerPage);
$blockedIPs = loadBlockedIPs($blockedIPsFile, $searchBlocked, $pageBlocked, $linesPerPage);



// Level automatisch bestimmen
foreach ($blockedIPs['lines'] as &$item) {
    if (stripos($item['reason'], $languageService->get('reason_failed_logins')) !== false) {
        $item['level'] = 'critical';
    } elseif (stripos($item['reason'], $languageService->get('reason_test')) !== false) {
        $item['level'] = 'warning';
    } else {
        $item['level'] = 'info';
    }
}
unset($item);
?>

<style>
    pre { background: #222; color: #eee; padding: 10px; max-height: 400px; overflow-x: auto; white-space: pre-wrap; }
    .container { margin-bottom: 3rem; }
</style>

<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> <?= $languageService->get('log_viewer'); ?>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item active" aria-current="page"><?= $languageService->get('log_viewer_overview'); ?></li>
        </ol>
    </nav>   

    <div class="card-body">

<div class="container">
    <h5><?= $languageService->get('access_control_log'); ?></h5>

    <form method="get" class="mb-3 row g-2">
        <div class="col-auto">
            <input type="text" name="search_access" class="form-control" placeholder="<?= $languageService->get('filter_placeholder_access'); ?>" value="<?= htmlspecialchars($searchAccess) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><?= $languageService->get('search'); ?></button>
        </div>
    </form>

    <p><?= sprintf($languageService->get('display_log_entries'), count($accessLog['lines']), $accessLog['total'], $accessLog['page'], $accessLog['pages']); ?></p>
    <pre><?= htmlspecialchars(implode("\n", $accessLog['lines'])) ?></pre>

    <nav>
        <ul class="pagination">
            <?php for ($i = 1; $i <= $accessLog['pages']; $i++): ?>
                <li class="page-item <?= $i === $accessLog['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="admincenter.php?site=log_viewer&page_access=<?= $i ?>&search_access=<?= urlencode($searchAccess) ?>&page_suspicious=<?= $pageSuspicious ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>&page_blocked=<?= $pageBlocked ?>&search_blocked=<?= urlencode($searchBlocked) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<div class="container">
    <h5><?= $languageService->get('suspicious_access_log'); ?></h5>

    <form method="get" class="mb-3 row g-2">
        <div class="col-auto">
            <input type="text" name="search_suspicious" class="form-control" placeholder="<?= $languageService->get('filter_placeholder_suspicious'); ?>" value="<?= htmlspecialchars($searchSuspicious) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-danger"><?= $languageService->get('search'); ?></button>
        </div>
    </form>

    <p><?= sprintf($languageService->get('display_log_entries'), count($suspiciousLog['lines']), $suspiciousLog['total'], $suspiciousLog['page'], $suspiciousLog['pages']); ?></p>
    <pre><?= htmlspecialchars(implode("\n", $suspiciousLog['lines'])) ?></pre>

    <nav>
        <ul class="pagination">
            <?php for ($i = 1; $i <= $suspiciousLog['pages']; $i++): ?>
                <li class="page-item <?= $i === $suspiciousLog['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="admincenter.php?site=log_viewer&page_suspicious=<?= $i ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>&page_access=<?= $pageAccess ?>&search_access=<?= urlencode($searchAccess) ?>&page_blocked=<?= $pageBlocked ?>&search_blocked=<?= urlencode($searchBlocked) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<div class="container">
    <h5><?= $languageService->get('blocked_ips_title'); ?></h5>

    <form method="post" class="mb-3 row g-2">
        <div class="col-auto">
            <input type="text" name="block_ip" class="form-control" placeholder="<?= $languageService->get('ip_address_placeholder'); ?>" required>
        </div>
        <div class="col-auto">
            <input type="text" name="block_reason" class="form-control" placeholder="<?= $languageService->get('reason_placeholder'); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-warning"><?= $languageService->get('block_ip_button'); ?></button>
        </div>
    </form>

    <form method="get" class="mb-3 row g-2">
        <div class="col-auto">
            <input type="text" name="search_blocked" class="form-control" placeholder="<?= $languageService->get('filter_placeholder_blocked'); ?>" value="<?= htmlspecialchars($searchBlocked) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary"><?= $languageService->get('search'); ?></button>
        </div>
    </form>

    <table class="table table-striped">
        <thead>
            <tr><th><?= $languageService->get('ip'); ?></th><th><?= $languageService->get('reason'); ?></th><th><?= $languageService->get('date'); ?></th><th><?= $languageService->get('status'); ?></th><th><?= $languageService->get('action'); ?></th></tr>
        </thead>
        <tbody>
            <?php foreach ($blockedIPs['lines'] as $item): ?>
                <?php
                    // Zeilenfarbe basierend auf 'level'
                    $rowClass = '';
                    $statusBadge = '';
                    if (!empty($item['level'])) {
                        switch ($item['level']) {
                            case 'critical':
                                $rowClass = 'table-danger'; // rot
                                $statusBadge = '<span class="badge bg-danger">' . $languageService->get('critical_status') . '</span>';
                                break;
                            case 'warning':
                                $rowClass = 'table-warning'; // gelb
                                $statusBadge = '<span class="badge bg-warning text-dark">' . $languageService->get('warning_status') . '</span>';
                                break;
                            case 'info':
                                $rowClass = 'table-info'; // blau
                                $statusBadge = '<span class="badge bg-info text-dark">' . $languageService->get('info_status') . '</span>';
                                break;
                        }
                    }
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= htmlspecialchars($item['ip'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['reason'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['date'] ?? '') ?></td>
                    <td><?= $statusBadge ?></td>
                    <td>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="unblock_ip" value="<?= htmlspecialchars($item['ip'] ?? '') ?>">
                            <button type="submit" class="btn btn-success btn-sm"><?= $languageService->get('unblock_button'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p><?= sprintf($languageService->get('display_log_entries'), count($blockedIPs['lines']), $blockedIPs['total'], $blockedIPs['page'], $blockedIPs['pages']); ?></p>

    <nav>
        <ul class="pagination">
            <?php for ($i = 1; $i <= $blockedIPs['pages']; $i++): ?>
                <li class="page-item <?= $i === $blockedIPs['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="admincenter.php?site=log_viewer&page_blocked=<?= $i ?>&search_blocked=<?= urlencode($searchBlocked) ?>&page_access=<?= $pageAccess ?>&search_access=<?= urlencode($searchAccess) ?>&page_suspicious=<?= $pageSuspicious ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>


</div></div>