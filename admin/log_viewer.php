<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

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

// POST-Handler: IP block/unblock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blockedData = file_exists($blockedIPsFile) ? json_decode(file_get_contents($blockedIPsFile), true) : [];
    if (!is_array($blockedData)) $blockedData = [];

    $didBlock = false;
    $didUnblock = false;

    $returnUrl = (string)($_SERVER['REQUEST_URI'] ?? '');

    // Neue IP blockieren
    if (isset($_POST['block_ip'], $_POST['block_reason'])) {
        $ip = trim((string)$_POST['block_ip']);
        $reason = trim((string)$_POST['block_reason']);

        if ($ip !== '') { $blockedData[] = ['ip' => $ip, 'reason' => $reason, 'date' => date('Y-m-d H:i:s')]; $didBlock = true; }
        else nx_redirect($returnUrl, 'warning', 'alert_ip_required', false);
    }

    // IP freigeben
    if (isset($_POST['unblock_ip'])) {
        $unblockIP = (string)$_POST['unblock_ip'];
        $before = count($blockedData);
        $blockedData = array_filter($blockedData, fn($item) => ((string)($item['ip'] ?? '')) !== $unblockIP);
        $after = count($blockedData);
        $didUnblock = ($after < $before);
    }

    file_put_contents($blockedIPsFile, json_encode(array_values($blockedData), JSON_PRETTY_PRINT));

    if ($didBlock) { nx_audit_action('security_overview', 'audit_action_ip_blocked', $ip, null, $returnUrl, ['ip' => $ip, 'reason' => $reason]); nx_redirect($returnUrl, 'success', 'alert_ip_blocked', false); }
    if ($didUnblock) { nx_audit_action('security_overview', 'audit_action_ip_unblocked', $unblockIP, null, $returnUrl, ['ip' => $unblockIP]); nx_redirect($returnUrl, 'success', 'alert_ip_unblocked', false); }

    nx_redirect($returnUrl, 'success', 'alert_saved', false);
}


// Funktionen laden Logs
function loadLogLines(string $file, string $search, int $page, int $entriesPerPage): array {
    if (!file_exists($file)) return ['lines' => [], 'total' => 0, 'pages' => 0, 'page' => 0];

    $content = file_get_contents($file);
    if ($content === false) return ['lines' => [], 'total' => 0, 'pages' => 0, 'page' => 0];

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
    if ($totalEntries === 0) {
        return ['lines' => [], 'total' => 0, 'pages' => 0, 'page' => 0];
    }

    $totalPages = (int)ceil($totalEntries / $entriesPerPage);
    $page = max(1, min($page, $totalPages));

    $start = ($page - 1) * $entriesPerPage;
    $displayEntries = array_slice($entries, $start, $entriesPerPage);

    // Trennlinie wieder an jeden Eintrag anhängen
    $displayEntries = array_map(fn($entry) => $entry . "\n----------------------------------------", $displayEntries);

    return ['lines' => $displayEntries, 'total' => $totalEntries, 'pages' => $totalPages, 'page' => $page];
}

function loadBlockedIPs(string $file, string $search, int $page, int $linesPerPage): array {
    if (!file_exists($file)) return ['lines' => [], 'total' => 0, 'pages' => 0, 'page' => 0];
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) $data = [];

    if ($search !== '') {
        $data = array_filter($data, fn($item) => stripos($item['ip'] ?? '', $search) !== false || stripos($item['reason'] ?? '', $search) !== false);
    }

    $totalLines = count($data);
    if ($totalLines === 0) {
        return ['lines' => [], 'total' => 0, 'pages' => 0, 'page' => 0];
    }

    $totalPages = (int)ceil($totalLines / $linesPerPage);
    $page = max(1, min($page, $totalPages));

    $start = ($page - 1) * $linesPerPage;
    $displayLines = array_slice($data, $start, $linesPerPage);

    return ['lines' => $displayLines, 'total' => $totalLines, 'pages' => $totalPages, 'page' => $page];
}


// Logs laden
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
pre {
    background: #222;
    color: #eee;
    padding: 10px;
    max-height: 400px;

    overflow-x: auto;          /* horizontal scroll statt Layoutbruch */
    overflow-y: auto;

    white-space: pre-wrap;     /* Zeilen umbrechen */
    word-break: break-word;    /* 🔥 extrem wichtig */
    overflow-wrap: anywhere;   /* 🔥 bricht auch lange Tokens */
    max-width: 100%;           /* NIE breiter als Container */
    box-sizing: border-box;
}
</style>


<!-- Access Control Log -->
<div class="col-lg-12 mb-4">
    <div class="card shadow-sm border-0 mb-4 mt-4 h-100">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-shield-lock"></i>
                <span><?= $languageService->get('access_control_log'); ?></span>
                <small class="small-muted"><?= $languageService->get('info_status'); ?></small>
            </div>
        </div>
        <div class="card-body p-4">
            <form method="get" class="mb-3 row g-2">
                <div class="col">
                    <input type="text" name="search_access" class="form-control" placeholder="<?= $languageService->get('filter_placeholder_access'); ?>" value="<?= htmlspecialchars($searchAccess) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary"><?= $languageService->get('search'); ?></button>
                </div>

                <!-- Preserve other section state -->
                <input type="hidden" name="site" value="log_viewer">
                <input type="hidden" name="page_access" value="1">
                <input type="hidden" name="page_suspicious" value="<?= (int)$pageSuspicious ?>">
                <input type="hidden" name="search_suspicious" value="<?= htmlspecialchars($searchSuspicious) ?>">
                <input type="hidden" name="page_blocked" value="<?= (int)$pageBlocked ?>">
                <input type="hidden" name="search_blocked" value="<?= htmlspecialchars($searchBlocked) ?>">
            </form>

            <p class="mb-2"><?= sprintf($languageService->get('display_log_entries'), count($accessLog['lines']), $accessLog['total'], $accessLog['page'], $accessLog['pages']); ?></p>
            <pre class="mb-3"><?= htmlspecialchars(implode("\n", $accessLog['lines'])) ?></pre>

            <?php if ($accessLog['pages'] > 1): ?>
                <nav class="mt-5 mb-6">
                    <ul class="pagination justify-content-center mb-0">

                        <li class="page-item <?= ($accessLog['page'] <= 1 ? 'disabled' : '') ?>">
                            <a class="page-link" aria-label="Previous" href="admincenter.php?site=log_viewer&page_access=<?= max(1, $accessLog['page'] - 1) ?>&search_access=<?= urlencode($searchAccess) ?>&page_suspicious=<?= $pageSuspicious ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>&page_blocked=<?= $pageBlocked ?>&search_blocked=<?= urlencode($searchBlocked) ?>">
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $accessLog['pages']; $i++): ?>
                            <li class="page-item <?= ($i === $accessLog['page'] ? 'active' : '') ?>">
                                <a class="page-link" href="admincenter.php?site=log_viewer&page_access=<?= $i ?>&search_access=<?= urlencode($searchAccess) ?>&page_suspicious=<?= $pageSuspicious ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>&page_blocked=<?= $pageBlocked ?>&search_blocked=<?= urlencode($searchBlocked) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= ($accessLog['page'] >= $accessLog['pages'] ? 'disabled' : '') ?>">
                            <a class="page-link" aria-label="Next" href="admincenter.php?site=log_viewer&page_access=<?= min($accessLog['pages'], $accessLog['page'] + 1) ?>&search_access=<?= urlencode($searchAccess) ?>&page_suspicious=<?= $pageSuspicious ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>&page_blocked=<?= $pageBlocked ?>&search_blocked=<?= urlencode($searchBlocked) ?>">
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        </li>

                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Suspicious Access Log -->
<div class="col-lg-12 mb-4">
    <div class="card shadow-sm border-0 mb-4 mt-4 h-100">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-exclamation-triangle"></i>
                <span><?= $languageService->get('suspicious_access_log'); ?></span>
                <small class="small-muted"><?= $languageService->get('info_status'); ?></small>
            </div>
        </div>
        <div class="card-body p-4">
            <form method="get" class="mb-3 row g-2">
                <div class="col">
                    <input type="text" name="search_suspicious" class="form-control" placeholder="<?= $languageService->get('filter_placeholder_suspicious'); ?>" value="<?= htmlspecialchars($searchSuspicious) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary"><?= $languageService->get('search'); ?></button>
                </div>

                <!-- Preserve other section state -->
                <input type="hidden" name="site" value="log_viewer">
                <input type="hidden" name="page_suspicious" value="1">
                <input type="hidden" name="page_access" value="<?= (int)$pageAccess ?>">
                <input type="hidden" name="search_access" value="<?= htmlspecialchars($searchAccess) ?>">
                <input type="hidden" name="page_blocked" value="<?= (int)$pageBlocked ?>">
                <input type="hidden" name="search_blocked" value="<?= htmlspecialchars($searchBlocked) ?>">
            </form>

            <p class="mb-2"><?= sprintf($languageService->get('display_log_entries'), count($suspiciousLog['lines']), $suspiciousLog['total'], $suspiciousLog['page'], $suspiciousLog['pages']); ?></p>
            <pre class="mb-3"><?= htmlspecialchars(implode("\n", $suspiciousLog['lines'])) ?></pre>

            <?php if ($suspiciousLog['pages'] > 1): ?>
                <nav class="mt-5 mb-6">
                    <ul class="pagination justify-content-center mb-0">

                        <li class="page-item <?= ($suspiciousLog['page'] <= 1 ? 'disabled' : '') ?>">
                            <a class="page-link" aria-label="Previous" href="admincenter.php?site=log_viewer&page_suspicious=<?= max(1, $suspiciousLog['page'] - 1) ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>&page_access=<?= $pageAccess ?>&search_access=<?= urlencode($searchAccess) ?>&page_blocked=<?= $pageBlocked ?>&search_blocked=<?= urlencode($searchBlocked) ?>">
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $suspiciousLog['pages']; $i++): ?>
                            <li class="page-item <?= ($i === $suspiciousLog['page'] ? 'active' : '') ?>">
                                <a class="page-link" href="admincenter.php?site=log_viewer&page_suspicious=<?= $i ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>&page_access=<?= $pageAccess ?>&search_access=<?= urlencode($searchAccess) ?>&page_blocked=<?= $pageBlocked ?>&search_blocked=<?= urlencode($searchBlocked) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= ($suspiciousLog['page'] >= $suspiciousLog['pages'] ? 'disabled' : '') ?>">
                            <a class="page-link" aria-label="Next" href="admincenter.php?site=log_viewer&page_suspicious=<?= min($suspiciousLog['pages'], $suspiciousLog['page'] + 1) ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>&page_access=<?= $pageAccess ?>&search_access=<?= urlencode($searchAccess) ?>&page_blocked=<?= $pageBlocked ?>&search_blocked=<?= urlencode($searchBlocked) ?>">
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        </li>

                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Blocked IPs -->
<div class="col-12 col-lg-12 mb-4">
    <div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-ban"></i>
                <span><?= $languageService->get('blocked_ips_title'); ?></span>
                <small class="small-muted"><?= $languageService->get('info_status'); ?></small>
            </div>
        </div>

        <div class="card-body p-4">
            <form method="post" class="mb-3 row g-2">
                <div class="col-md-4">
                    <input type="text" name="block_ip" class="form-control" placeholder="<?= $languageService->get('ip_address_placeholder'); ?>" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="block_reason" class="form-control" placeholder="<?= $languageService->get('reason_placeholder'); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-danger"><?= $languageService->get('block_ip_button'); ?></button>
                </div>
            </form>

            <form method="get" class="mb-3 row g-2">
                <div class="col-md-9">
                    <input type="text" name="search_blocked" class="form-control" placeholder="<?= $languageService->get('filter_placeholder_blocked'); ?>" value="<?= htmlspecialchars($searchBlocked) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary"><?= $languageService->get('search'); ?></button>
                </div>

                <!-- Preserve other section state -->
                <input type="hidden" name="site" value="log_viewer">
                <input type="hidden" name="page_blocked" value="1">
                <input type="hidden" name="page_access" value="<?= (int)$pageAccess ?>">
                <input type="hidden" name="search_access" value="<?= htmlspecialchars($searchAccess) ?>">
                <input type="hidden" name="page_suspicious" value="<?= (int)$pageSuspicious ?>">
                <input type="hidden" name="search_suspicious" value="<?= htmlspecialchars($searchSuspicious) ?>">
            </form>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th><?= $languageService->get('ip'); ?></th>
                            <th><?= $languageService->get('reason'); ?></th>
                            <th><?= $languageService->get('date'); ?></th>
                            <th><?= $languageService->get('status'); ?></th>
                            <th class="text-end"><?= $languageService->get('action'); ?></th>
                        </tr>
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
                                <td class="text-end">
                                    <form method="post" style="margin:0;">
                                        <input type="hidden" name="unblock_ip" value="<?= htmlspecialchars($item['ip'] ?? '') ?>">
                                        <button type="submit" class="btn btn-success"><?= $languageService->get('unblock_button'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="mt-2 mb-2"><?= sprintf($languageService->get('display_log_entries'), count($blockedIPs['lines']), $blockedIPs['total'], $blockedIPs['page'], $blockedIPs['pages']); ?></p>

            <?php if ($blockedIPs['pages'] > 1): ?>
                <nav class="mt-5 mb-6">
                    <ul class="pagination justify-content-center mb-0">

                        <li class="page-item <?= ($blockedIPs['page'] <= 1 ? 'disabled' : '') ?>">
                            <a class="page-link" aria-label="Previous" href="admincenter.php?site=log_viewer&page_blocked=<?= max(1, $blockedIPs['page'] - 1) ?>&search_blocked=<?= urlencode($searchBlocked) ?>&page_access=<?= $pageAccess ?>&search_access=<?= urlencode($searchAccess) ?>&page_suspicious=<?= $pageSuspicious ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>">
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $blockedIPs['pages']; $i++): ?>
                            <li class="page-item <?= ($i === $blockedIPs['page'] ? 'active' : '') ?>">
                                <a class="page-link" href="admincenter.php?site=log_viewer&page_blocked=<?= $i ?>&search_blocked=<?= urlencode($searchBlocked) ?>&page_access=<?= $pageAccess ?>&search_access=<?= urlencode($searchAccess) ?>&page_suspicious=<?= $pageSuspicious ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= ($blockedIPs['page'] >= $blockedIPs['pages'] ? 'disabled' : '') ?>">
                            <a class="page-link" aria-label="Next" href="admincenter.php?site=log_viewer&page_blocked=<?= min($blockedIPs['pages'], $blockedIPs['page'] + 1) ?>&search_blocked=<?= urlencode($searchBlocked) ?>&page_access=<?= $pageAccess ?>&search_access=<?= urlencode($searchAccess) ?>&page_suspicious=<?= $pageSuspicious ?>&search_suspicious=<?= urlencode($searchSuspicious) ?>">
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        </li>

                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>