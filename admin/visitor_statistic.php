<?php

use nexpell\LanguageService;
use nexpell\AccessControl;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../system/visitor_log_statistic.php';

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('ac_visitor_statistic');

// Zeitraum (week oder month)
// Range aus GET oder default

$range = $_GET['range'] ?? 'week';
if (!in_array($range, ['week', 'month', '6months', '12months'])) {
    $range = 'week';
}

$labels = [];
$visitors = [];
$maxonline_values = [];

switch ($range) {
    case 'week':
        // Letzte 7 Tage inkl. heute
        $since_date = date('Y-m-d', strtotime("-6 days"));
        $end_date   = date('Y-m-d');

        $day_pointer = strtotime($since_date);
        $days_array = [];
        while ($day_pointer <= strtotime($end_date)) {
            $key = date('Y-m-d', $day_pointer);
            $days_array[$key] = ['hits' => 0, 'maxonline' => 0];
            $day_pointer += 86400;
        }

        $result = $_database->query("
            SELECT DATE(date) as day, SUM(hits) as count, MAX(maxonline) as maxpeak
            FROM visitor_daily_counter
            WHERE date BETWEEN '$since_date' AND '$end_date'
            GROUP BY day
            ORDER BY day ASC
        ");
        while ($row = $result->fetch_assoc()) {
            $days_array[$row['day']] = ['hits' => (int)$row['count'], 'maxonline' => (int)$row['maxpeak']];
        }

        foreach ($days_array as $day => $values) {
            $labels[] = date('D', strtotime($day));
            $visitors[] = $values['hits'];
            $maxonline_values[] = $values['maxonline'];
        }
        break;

    case 'month':
        // Letzte 30 Tage inkl. heute
        $since_date = date('Y-m-d', strtotime("-29 days"));
        $end_date   = date('Y-m-d');

        $day_pointer = strtotime($since_date);
        $days_array = [];
        while ($day_pointer <= strtotime($end_date)) {
            $key = date('Y-m-d', $day_pointer);
            $days_array[$key] = ['hits' => 0, 'maxonline' => 0];
            $day_pointer += 86400;
        }

        $result = $_database->query("
            SELECT DATE(date) as day, SUM(hits) as count, MAX(maxonline) as maxpeak
            FROM visitor_daily_counter
            WHERE date BETWEEN '$since_date' AND '$end_date'
            GROUP BY day
            ORDER BY day ASC
        ");
        while ($row = $result->fetch_assoc()) {
            $days_array[$row['day']] = ['hits' => (int)$row['count'], 'maxonline' => (int)$row['maxpeak']];
        }

        foreach ($days_array as $day => $values) {
            $labels[] = date('d.m.', strtotime($day));
            $visitors[] = $values['hits'];
            $maxonline_values[] = $values['maxonline'];
        }
        break;

    case '6months':
        // Letzte 6 Monate inkl. aktueller
        $since_date = date('Y-m-01', strtotime("-5 months"));
        $end_date   = date('Y-m-01', strtotime("+1 month"));

        $months_array = [];
        $month_pointer = strtotime($since_date);
        while ($month_pointer < strtotime($end_date)) {
            $key = date('Y-m-01', $month_pointer);
            $months_array[$key] = ['hits' => 0, 'maxonline' => 0];
            $month_pointer = strtotime('+1 month', $month_pointer);
        }

        $result = $_database->query("
            SELECT DATE_FORMAT(date, '%Y-%m-01') as month_start, SUM(hits) as count, MAX(maxonline) as maxpeak
            FROM visitor_daily_counter
            WHERE date >= '$since_date'
            GROUP BY month_start
            ORDER BY month_start ASC
        ");
        while ($row = $result->fetch_assoc()) {
            $months_array[$row['month_start']] = ['hits' => (int)$row['count'], 'maxonline' => (int)$row['maxpeak']];
        }

        foreach ($months_array as $month => $values) {
            $labels[] = date('M Y', strtotime($month));
            $visitors[] = $values['hits'];
            $maxonline_values[] = $values['maxonline'];
        }
        break;

    case '12months':
        // Letzte 12 Monate inkl. aktueller
        $since_date = date('Y-m-01', strtotime("-11 months"));
        $end_date   = date('Y-m-01', strtotime("+1 month"));

        $months_array = [];
        $month_pointer = strtotime($since_date);
        while ($month_pointer < strtotime($end_date)) {
            $key = date('Y-m-01', $month_pointer);
            $months_array[$key] = ['hits' => 0, 'maxonline' => 0];
            $month_pointer = strtotime('+1 month', $month_pointer);
        }

        $result = $_database->query("
            SELECT DATE_FORMAT(date, '%Y-%m-01') as month_start, SUM(hits) as count, MAX(maxonline) as maxpeak
            FROM visitor_daily_counter
            WHERE date >= '$since_date'
            GROUP BY month_start
            ORDER BY month_start ASC
        ");
        while ($row = $result->fetch_assoc()) {
            $months_array[$row['month_start']] = ['hits' => (int)$row['count'], 'maxonline' => (int)$row['maxpeak']];
        }

        foreach ($months_array as $month => $values) {
            $labels[] = date('M Y', strtotime($month));
            $visitors[] = $values['hits'];
            $maxonline_values[] = $values['maxonline'];
        }
        break;
}

###############################

$time_limit = time() - 300; // 5 Minuten
$result = $_database->query("
    SELECT COUNT(DISTINCT ip_address) AS online_users
    FROM visitor_statistics
    WHERE UNIX_TIMESTAMP(created_at) > $time_limit
");
$online_users = (int) $result->fetch_assoc()['online_users'];

// --- Besucherstatistiken berechnen ---

function getVisitorCounter(mysqli $_database): array {
    $bot_condition    = getBotCondition();
    $today_date       = date('Y-m-d');
    $yesterday        = date('Y-m-d', strtotime('-1 day'));
    $month_start      = date('Y-m-01');
    $five_minutes_ago = time() - 300;

    // Heute (Hits aus daily_counter)
    $today_hits = (int)$_database->query("
        SELECT SUM(hits) AS hits
        FROM visitor_daily_counter
        WHERE DATE(date) = '$today_date'
    ")->fetch_assoc()['hits'];

    // Gestern
    $yesterday_hits = (int)$_database->query("
        SELECT SUM(hits) AS hits
        FROM visitor_daily_counter
        WHERE DATE(date) = '$yesterday'
    ")->fetch_assoc()['hits'];

    // Monat
    $month_hits = (int)$_database->query("
        SELECT SUM(hits) AS hits
        FROM visitor_daily_counter
        WHERE date >= '$month_start'
    ")->fetch_assoc()['hits'];

    // Gesamt
    $total_hits = (int)$_database->query("
        SELECT SUM(hits) AS hits
        FROM visitor_daily_counter
    ")->fetch_assoc()['hits'];

    // Online (letzte 5 Minuten, Bots raus)
    $online_visitors = (int)$_database->query("
        SELECT COUNT(DISTINCT ip_hash) AS cnt
        FROM visitor_statistics
        WHERE last_seen >= FROM_UNIXTIME($five_minutes_ago) $bot_condition
    ")->fetch_assoc()['cnt'];

    // MaxOnline (aus daily_counter)
    $max_online = (int)$_database->query("
        SELECT MAX(maxonline) AS maxcnt
        FROM visitor_daily_counter
    ")->fetch_assoc()['maxcnt'];

    // Seit wann die Webseite läuft (erstes Statistikdatum)
    // Erstes und letztes Statistikdatum holen
    $result_range = $_database->query("
        SELECT MIN(date) AS first_visit, MAX(date) AS last_visit
        FROM visitor_daily_counter
    ");
    $range = $result_range->fetch_assoc();

    $first_visit = $range['first_visit'];
    $last_visit  = $range['last_visit'];

    $first_visit_date = $first_visit ? date('d.m.Y', strtotime($first_visit)) : $languageService->get('unknown');

    // Anzahl Tage seit Start berechnen (inkl. Starttag)
    $days = ($first_visit && $last_visit)
        ? (floor((strtotime($last_visit) - strtotime($first_visit)) / 86400) + 1)
        : 0;

    // Durchschnitt pro Tag berechnen
    $avg_per_day = $days > 0 ? round($total_hits / $days, 1) : 0;

    // Durchschnitt pro Tag
    $result_days = $_database->query("
        SELECT DATEDIFF(MAX(date), MIN(date)) + 1 AS days
        FROM visitor_daily_counter
    ");
    $days = (int)$result_days->fetch_assoc()['days'];
    $avg_per_day = round($total_hits / max($days, 1), 1);

    return [
        'today'           => $today_hits,
        'yesterday'       => $yesterday_hits,
        'month'           => $month_hits,
        'total'           => $total_hits,
        'online'          => $online_visitors,
        'maxonline'       => $max_online,
        'first_visit'     => $first_visit_date,
        'average_per_day' => $avg_per_day,
        'days'            => $days
    ];
}

$counter = getVisitorCounter($_database);

// Eindeutige Besucher gesamt (Bots herausfiltern)
$bot_condition = getBotCondition();
$unique_total = (int)$_database->query(
    "SELECT COUNT(DISTINCT ip_hash) AS cnt FROM visitor_statistics WHERE 1=1 $bot_condition"
)->fetch_assoc()['cnt'];

// Geräte-Auswertung
$res_devices = safe_query(
    "SELECT device_type, COUNT(*) AS total FROM visitor_statistics WHERE created_at >= '$since_date' GROUP BY device_type"
);
$device_data = [];
while ($row = mysqli_fetch_assoc($res_devices)) {
    $device_data[$row['device_type']] = (int)$row['total'];
}

// OS-Auswertung
$res_os = safe_query(
    "SELECT os, COUNT(*) AS total FROM visitor_statistics WHERE created_at >= '$since_date' GROUP BY os"
);
$os_data = [];
while ($row = mysqli_fetch_assoc($res_os)) {
    $os_data[$row['os']] = (int)$row['total'];
}

// Browser-Auswertung
$res_browser = safe_query(
    "SELECT browser, COUNT(*) AS total FROM visitor_statistics WHERE created_at >= '$since_date' GROUP BY browser"
);
$browser_data = [];
while ($row = mysqli_fetch_assoc($res_browser)) {
    $browser_data[$row['browser']] = (int)$row['total'];
}

// Top 10 Seiten nach Klicks
$res_clicks = safe_query(
    "SELECT page, COUNT(*) AS total FROM visitor_statistics GROUP BY page ORDER BY total DESC LIMIT 10"
);
$top_pages = [];
while ($row = mysqli_fetch_assoc($res_clicks)) {
    $top_pages[] = $row;
}

// Top 10 Seiten
$top_pages = [];
$result = $_database->query("
    SELECT page, COUNT(*) AS visits
    FROM visitor_statistics
    GROUP BY page
    ORDER BY visits DESC
    LIMIT 10
");
while ($row = $result->fetch_assoc()) {
    $top_pages[] = $row;
}

// Top 10 Länder
$top_countries = [];
$top_countries_grouped = [];
$result = $_database->query("
    SELECT country_code, COUNT(DISTINCT ip_hash) AS visitors
    FROM visitor_statistics
    WHERE created_at >= '$since_date'
      AND country_code IS NOT NULL AND country_code <> ''
      $bot_condition
    GROUP BY country_code
");
while ($row = $result->fetch_assoc()) {
    $country_code = normalize_visitor_country_code($row['country_code'] ?? 'unknown');
    if ($country_code === 'unknown') {
        continue;
    }

    if (!isset($top_countries_grouped[$country_code])) {
        $top_countries_grouped[$country_code] = 0;
    }

    $top_countries_grouped[$country_code] += (int)$row['visitors'];
}
arsort($top_countries_grouped);
foreach (array_slice($top_countries_grouped, 0, 6, true) as $country_code => $visitors) {
    $top_countries[] = [
        'country_code' => $country_code,
        'visitors' => $visitors,
    ];
}

// Top-Referer
$res_referer = safe_query(
    "SELECT referer, COUNT(*) AS hits FROM visitor_statistics WHERE created_at >= '$since_date' GROUP BY referer ORDER BY hits DESC LIMIT 5"
);
$top_referers = [];
while ($row = mysqli_fetch_assoc($res_referer)) {
    $top_referers[] = $row;
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visits_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, [$languageService->get('date'), $languageService->get('ip_hash'), $languageService->get('device'), $languageService->get('os'), $languageService->get('browser'), $languageService->get('referer')]);

    $res_export = safe_query("SELECT * FROM visitor_statistics WHERE created_at >= '$since_date' ORDER BY created_at ASC");
    while ($row = mysqli_fetch_assoc($res_export)) {
        fputcsv($output, [
            $row['timestamp'],
            $row['ip_hash'],
            $row['device_type'],
            $row['os'],
            $row['browser'],
            $row['referer']
        ]);
    }
    fclose($output);
    nx_audit_action('visitor_statistics', 'audit_action_csv_exported', 'export_csv', null, 'admincenter.php?site=statistics');
    exit;
}

// Sprachlabels
$visitsLabel   = $languageService->get('visits');
$visitorsLabel = $languageService->get('visits');
?>
    <!-- KPI Cards -->
    <div class="row g-4">

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body">
                    <div class="mb-2 fw-semibold"><?= $languageService->get('online_users'); ?></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 p-2" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-person-check fs-4"></i>
                        </div>
                        <div class="fs-2 fw-semibold"><?= $online_users ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body">
                    <div class="mb-2 fw-semibold"><?= $languageService->get('visitors_today'); ?></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 p-2" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-calendar2-check fs-4"></i>
                        </div>
                        <div class="fs-2 fw-semibold"><?= $counter['today'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body">
                    <div class="mb-2 fw-semibold"><?= $languageService->get('visitors_yesterday'); ?></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 p-2" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-calendar2 fs-4"></i>
                        </div>
                        <div class="fs-2 fw-semibold"><?= $counter['yesterday'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body">
                    <div class="mb-2 fw-semibold"><?= $languageService->get('visitors_this_month'); ?></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 p-2" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-calendar3 fs-4"></i>
                        </div>
                        <div class="fs-2 fw-semibold"><?= $counter['month'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-body">
                    <div class="mb-2 fw-semibold"><?= $languageService->get('total_visits'); ?></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 p-2" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-people fs-4"></i>
                        </div>
                        <div class="fs-2 fw-semibold"><?= $unique_total ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-body">
                    <div class="mb-2 fw-semibold"><?= $languageService->get('avg_visits_per_day'); ?></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 p-2" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-bar-chart-line fs-4"></i>
                        </div>
                        <div class="fs-2 fw-semibold"><?= $counter['average_per_day'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-body">
                    <div class="mb-2 fw-semibold"><?= $languageService->get('online_visitors'); ?></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 p-2" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-clock fs-4"></i>
                        </div>
                        <div class="fs-2 fw-semibold"><?= $counter['online'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-body">
                    <div class="mb-2 fw-semibold d-flex justify-content-between align-items-center">
                        <span>
                            <?= $languageService->get('website_online_since'); ?>
                        </span>
                        <span class="text-muted">
                            <?= $counter['days'] ?> <?= $languageService->get('days_online'); ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 p-2" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-clock-history fs-4"></i>
                        </div>
                        <div class="fs-2 fw-semibold"><?= $counter['first_visit'] ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Charts -->
    <div class="row g-4 mt-1">

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-graph-up-arrow"></i>
                        <span><?= $languageService->get('daily_page_views'); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="get" action="admincenter.php" class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <input type="hidden" name="site" value="visitor_statistic">
                        <div class="d-flex align-items-center gap-2" style="max-width: 520px; width: 100%;">
                            <select name="range" class="form-select" onchange="this.form.submit()">
                                <option value="week" <?= ($range === 'week' ? 'selected' : '') ?>><?= $languageService->get('last_7_days'); ?></option>
                                <option value="month" <?= ($range === 'month' ? 'selected' : '') ?>><?= $languageService->get('last_30_days'); ?></option>
                                <option value="6months" <?= ($range === '6months' ? 'selected' : '') ?>><?= $languageService->get('last_6_months'); ?></option>
                                <option value="12months" <?= ($range === '12months' ? 'selected' : '') ?>><?= $languageService->get('last_12_months'); ?></option>
                            </select>

                            <a href="?site=visitor_statistic&range=<?= htmlspecialchars($range) ?>&export=csv"
                               class="btn btn-secondary"
                               title="<?= $languageService->get('csv_export_title'); ?>"
                               style="min-width: 180px;">
                                <i class="bi bi-file-earmark-arrow-down"></i> <?= $languageService->get('csv_export'); ?>
                            </a>
                        </div>
                    </form>

                    <div id="visitorsChart" style="height:320px;"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-diagram-2"></i>
                        <span><?= $languageService->get('top_pages'); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="topPagesChart" style="height:420px;"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-geo-alt"></i>
                        <span><?= $languageService->get('top_countries'); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <style>
                        #topCountriesMap { width: 100%; height: 260px; }
                        .jvm-tooltip {
                            position: absolute;
                            display: none;
                            border-radius: 6px;
                            padding: 6px 10px;
                            background: rgba(17, 24, 39, 0.92);
                            color: #fff;
                            font-size: 12px;
                            z-index: 9999;
                            white-space: nowrap;
                        }
                    </style>

                    <div id="topCountriesMap"></div>

                    <div class="mt-3" id="topCountriesList"></div>
                </div>
            </div>
        </div>

        <!-- Referer -->
        <div class="col-12 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-link-45deg"></i>
                        <span><?= $languageService->get('top_5_referers'); ?></span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($top_referers as $referer): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($referer['referer']) ?>
                                <span class="badge bg-secondary"><?= $referer['hits']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

    </div>

    <!-- Device / OS / Browser -->
    <div class="row g-4 mt-1">

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-phone"></i>
                        <span><?= $languageService->get('device_types'); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height:320px;">
                        <div id="deviceChart" style="height:180px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-pc-display"></i>
                        <span><?= $languageService->get('operating_systems'); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height:320px;">
                        <div id="osChart" style="height:180px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-browser-chrome"></i>
                        <span><?= $languageService->get('browsers'); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height:320px;">
                        <div id="browserChart" style="height:180px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>
<script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- Daten aus PHP ---
    const rangeLabels = <?= json_encode($labels) ?>;
    const visitsLabel = <?= json_encode($visitsLabel) ?>;

    const visitorsData = <?= json_encode($visitors) ?>;
    const maxOnlineData = <?= json_encode($maxonline_values) ?>;

    const topPagesLabels = <?= json_encode(array_column($top_pages, 'page')) ?>;
    const topPagesData = <?= json_encode(array_column($top_pages, 'visits')) ?>;

    const topCountriesLabels = <?= json_encode(array_column($top_countries, 'country_code')) ?>;
    const topCountriesData = <?= json_encode(array_column($top_countries, 'visitors')) ?>;

    const deviceLabels = <?= json_encode(array_keys($device_data)) ?>;
    const deviceSeries = <?= json_encode(array_values($device_data)) ?>;

    const osLabels = <?= json_encode(array_keys($os_data)) ?>;
    const osSeries = <?= json_encode(array_values($os_data)) ?>;

    const browserLabels = <?= json_encode(array_keys($browser_data)) ?>;
    const browserSeries = <?= json_encode(array_values($browser_data)) ?>;

    const UI_PRIMARY = <?= json_encode($uiPrimaryColor ?? '') ?>;
    function cssVar(name, fallback = '') {
        const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    if (UI_PRIMARY) {
        document.documentElement.style.setProperty('--ac-primary', UI_PRIMARY);
    }

    const AC_PRIMARY   = cssVar('--ac-primary', UI_PRIMARY || '#fe821d');
    const AC_SECONDARY = cssVar('--ac-secondary', '#6B7280');
    const AC_SUCCESS   = cssVar('--ac-success', '#25B88B');
    const AC_DANGER    = cssVar('--ac-danger',  '#DC434C');
    const AC_INFO      = cssVar('--ac-info',    '#3A7CA5');
    const AC_WARNING   = cssVar('--ac-warning', '#E69E53');

    function hexToRgb(hex) {
        const h = (hex || '').replace('#','').trim();
        const full = h.length === 3 ? h.split('').map(c=>c+c).join('') : h;
        if (full.length !== 6) return null;
        const n = parseInt(full, 16);
        return { r: (n>>16)&255, g: (n>>8)&255, b: n&255 };
    }
    function rgbToHex(r,g,b){
        const to = (x)=>Math.max(0,Math.min(255,Math.round(x))).toString(16).padStart(2,'0');
        return `#${to(r)}${to(g)}${to(b)}`;
    }
    function mix(hexA, hexB, t){
        const a = hexToRgb(hexA), b = hexToRgb(hexB);
        if (!a || !b) return hexA || hexB || '#999999';
        return rgbToHex(a.r + (b.r-a.r)*t, a.g + (b.g-a.g)*t, a.b + (b.b-a.b)*t);
    }
    function makeMonochromePalette(baseHex, count){
        const out = [];
        for (let i=0;i<count;i++){
            const t = (i+1)/(count+2);
            out.push(mix(baseHex, '#ffffff', Math.min(0.75, 0.18 + t*0.55)));
        }
        return out;
    }

    function donutColorsMaxPrimary(series){
        const base = [AC_PRIMARY, AC_SECONDARY, AC_SUCCESS, AC_DANGER, AC_INFO, AC_WARNING].filter(Boolean);
        const n = series.length;
        if (n === 0) return base;

        let maxIdx = 0;
        for (let i=1;i<n;i++){
            if ((series[i] ?? 0) > (series[maxIdx] ?? 0)) maxIdx = i;
        }

        const colors = new Array(n);
        colors[maxIdx] = AC_PRIMARY;

        const pool = [AC_SECONDARY, AC_SUCCESS, AC_DANGER, AC_INFO, AC_WARNING].filter(Boolean);
        let poolIdx = 0;

        const overflow = makeMonochromePalette(AC_PRIMARY, Math.max(0, n - 1 - pool.length));
        let overflowIdx = 0;

        for (let i=0;i<n;i++){
            if (i === maxIdx) continue;
            if (poolIdx < pool.length) {
                colors[i] = pool[poolIdx++];
            } else {
                colors[i] = overflow[overflowIdx++] || AC_PRIMARY;
            }
        }
        return colors;
    }

    // --- Helper: Balken-Chart ---
    function buildBarOptions(categories, data, seriesName, showDataLabels) {
        return {
            chart: {
                type: 'bar',
                height: '100%',
                toolbar: { show: false }
            },
            colors: [AC_PRIMARY],
            series: [{
                name: seriesName,
                data: data
            }],
            xaxis: {
                categories: categories,
                labels: {
                    rotate: -35,
                    trim: true,
                    hideOverlappingLabels: true
                }
            },
            yaxis: {
                min: 0,
                title: { text: seriesName }
            },
            plotOptions: {
                bar: {
                    columnWidth: '55%',
                    borderRadius: 3
                }
            },
            dataLabels: {
                enabled: false,
                offsetY: -10
            },
            tooltip: {
                y: { formatter: (val) => val }
            },
            grid: {
                show: false
            }};
    }

    // --- Helper: Donut-Chart ---
    function buildDonutOptions(labels, series) {
        return {
            chart: {
                type: 'donut',
                height: '100%'
            },
            colors: donutColorsMaxPrimary(series),
            states: { hover: { filter: { type: 'none' } }, active: { filter: { type: 'none' } } },
            labels: labels,
            series: series,
            legend: {
                position: 'top'
            },
            dataLabels: {
                enabled: false,
                formatter: function (val, opts) {
                    const i = opts.seriesIndex;
                    return opts.w.globals.series[i];
                }
            },
            tooltip: {
                y: { formatter: (val) => val }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '55%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            }
        };
    }

    // --- Daily Page Views (Besuche + MaxOnline) ---
    const visitorsEl = document.querySelector('#visitorsChart');
    if (visitorsEl) {
        
        const visitorsOptions = {
            chart: {
                type: 'area',
                height: 320,
                toolbar: { show: false }
            },
            colors: [AC_PRIMARY, AC_PRIMARY],
            series: [
                { name: visitsLabel, data: visitorsData },
                { name: 'MaxOnline', data: maxOnlineData }
            ],

            xaxis: {
                categories: rangeLabels,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: '#6B7280' } }
            },

            yaxis: [
                {
                    min: 0,
                    title: { text: 'Hits' },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                {
                    min: 0,
                    opposite: true,
                    title: { text: 'MaxOnline' },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                }
            ],

            stroke: { curve: 'smooth', width: 2, dashArray: [0, 6] }, 
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, gradientToColors: ['#93C5FD', '#FCA5A5'], inverseColors: false, opacityFrom: 0.35, opacityTo: 0.03, stops: [0, 90, 100] } },

            markers: { size: 0, hover: { sizeOffset: 2 } },

            dataLabels: { enabled: false },

            tooltip: { shared: true, intersect: false },

            legend: { show: false },

            grid: {
                show: true,
                borderColor: 'rgba(0,0,0,0.08)',
                strokeDashArray: 4
            }
        };
        new ApexCharts(visitorsEl, visitorsOptions).render();
    }

    // --- Top Pages ---
    const topPagesEl = document.querySelector('#topPagesChart');
    if (topPagesEl) {
        new ApexCharts(
            topPagesEl,
            buildBarOptions(topPagesLabels, topPagesData, visitsLabel, true)
        ).render();
    }

    // --- Top Countries (Weltkarte) ---
const topCountriesMapEl = document.getElementById('topCountriesMap');
if (topCountriesMapEl && typeof jsVectorMap !== 'undefined') {

    const TOP_COUNTRIES_LIMIT = 6;
    const topLabels = Array.isArray(topCountriesLabels) ? topCountriesLabels.slice(0, TOP_COUNTRIES_LIMIT) : [];
    const topData = Array.isArray(topCountriesData) ? topCountriesData.slice(0, TOP_COUNTRIES_LIMIT) : [];
    const countryCenters = {
        DE: [51.1657, 10.4515],
        US: [37.0902, -95.7129],
        GB: [55.3781, -3.4360],
        IE: [53.1424, -7.6921],
        NL: [52.1326, 5.2913],
        SG: [1.3521, 103.8198],
        BE: [50.5039, 4.4699],
        IN: [20.5937, 78.9629],
        CH: [46.8182, 8.2275],
        AU: [-25.2744, 133.7751],
        CA: [56.1304, -106.3468],
        CN: [35.8617, 104.1954],
        RU: [61.5240, 105.3188],
        BR: [-14.2350, -51.9253]
    };

    const valuesByCode = {};
    const markers = [];
    const markerPalette = ['#dd7f7f','#f06595','#845ef7','#339af0','#22b8cf','#51cf66','#fcc419','#ff922b','#adb5bd'];

    const _displayNames = (typeof Intl !== 'undefined' && Intl.DisplayNames)
        ? new Intl.DisplayNames([document.documentElement.lang || navigator.language || 'en'], { type: 'region' })
        : null;

    const getCountryName = (iso2) => {
        const code = String(iso2 || '').trim().toUpperCase();
        if (!code) return '';
        try {
            return _displayNames ? _displayNames.of(code) : code;
        } catch (e) {
            return code;
        }
    };

    const colorByCode = {};
    topLabels.forEach((code, idx) => {
        const iso2 = String(code || '').trim().toUpperCase();
        const val  = Number(topData[idx] || 0);

        if (!iso2) return;

        valuesByCode[iso2] = val;

        const c = markerPalette[idx % markerPalette.length];
        colorByCode[iso2] = c;

        if (countryCenters[iso2]) {
            markers.push({ name: iso2, coords: countryCenters[iso2], style: { fill: c, stroke: c } });
        }
    });

    const topCountriesMap = new jsVectorMap({
        selector: '#topCountriesMap',
        map: 'world',
        backgroundColor: 'transparent',
        zoomButtons: false,
        draggable: true,
        regionStyle: {
            initial: {
                fill: '#e9ecef',
                stroke: '#ffffff',
                strokeWidth: 1
            },
            hover: {
                fill: '#dee2e6'
            }
        },

        markers: markers,
        markerStyle: {
            initial: {
                r: 6,
                strokeWidth: 6,
                strokeOpacity: 0.25
            },
            hover: { strokeOpacity: 0.45 }
        },

        onRegionTooltipShow: function (tooltip, code) {
            const iso2 = String(code || '').toUpperCase();
            if (valuesByCode[iso2] != null) {
                tooltip.text(`${getCountryName(iso2)} • ${visitsLabel}: ${valuesByCode[iso2]}`);
            }
        },
        onMarkerTooltipShow: function (tooltip, index) {
            const m = markers[index];
            const iso2 = (m && m.name) ? m.name : '';
            if (iso2 && valuesByCode[iso2] != null) {
                tooltip.text(`${getCountryName(iso2)} • ${visitsLabel}: ${valuesByCode[iso2]}`);
            }
        }
    });
    try {
        const svgMarkers = topCountriesMapEl.querySelectorAll('.jvm-marker');
        svgMarkers.forEach((el, i) => {
            const iso2 = (markers[i] && markers[i].name) ? String(markers[i].name).toUpperCase() : '';
            const c = iso2 && colorByCode[iso2] ? colorByCode[iso2] : null;
            if (!c) return;
            el.setAttribute('fill', c);
            el.setAttribute('stroke', c);
            el.style.fill = c;
            el.style.stroke = c;
        });
    } catch (e) { }

    try {
        if (getComputedStyle(topCountriesMapEl).position === 'static') {
            topCountriesMapEl.style.position = 'relative';
        }

        let customTt = document.getElementById('topCountriesMapTooltip');
        if (!customTt) {
            customTt = document.createElement('div');
            customTt.id = 'topCountriesMapTooltip';
            customTt.style.position = 'absolute';
            customTt.style.zIndex = '10';
            customTt.style.display = 'none';
            customTt.style.pointerEvents = 'none';
            customTt.style.background = '#ffffff';
            customTt.style.border = '1px solid rgba(0,0,0,0.1)';
            customTt.style.borderRadius = '8px';
            customTt.style.padding = '6px 8px';
            customTt.style.fontSize = '12px';
            customTt.style.boxShadow = '0 6px 18px rgba(0,0,0,0.08)';
            customTt.style.color = '#111827';
            topCountriesMapEl.appendChild(customTt);
        }

        const svgMarkers = topCountriesMapEl.querySelectorAll('.jvm-marker');
        svgMarkers.forEach((el, i) => {
            el.addEventListener('mouseenter', (ev) => {
                const iso2 = (markers[i] && markers[i].name) ? String(markers[i].name).toUpperCase() : '';
                if (!iso2 || valuesByCode[iso2] == null) return;
                customTt.textContent = `${getCountryName(iso2)} • ${visitsLabel}: ${valuesByCode[iso2]}`;
                customTt.style.display = 'block';
            });

            el.addEventListener('mousemove', (ev) => {
                if (customTt.style.display !== 'block') return;
                const rect = topCountriesMapEl.getBoundingClientRect();
                const x = ev.clientX - rect.left + 10;
                const y = ev.clientY - rect.top + 10;
                customTt.style.left = `${x}px`;
                customTt.style.top = `${y}px`;
            });

            el.addEventListener('mouseleave', () => {
                customTt.style.display = 'none';
            });
        });
    } catch (e) { }


    const listEl = document.getElementById('topCountriesList');
    if (listEl) {
        listEl.innerHTML = '';

        topLabels.forEach((code, idx) => {
            const iso2 = String(code || '').trim().toUpperCase();
            const val  = Number(topData[idx] || 0);

            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';

            const dot = colorByCode[iso2] || '#adb5bd';
            const name = getCountryName(iso2);

            row.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle" style="width:10px;height:10px;background:${dot};display:inline-block;"></span>
                    <span class="fw-semibold">${name}</span>
                </div>
                <span class="badge bg-secondary">${val}</span>
            `;
            listEl.appendChild(row);
        });
    }
}

// --- Device / OS / Browser (Donuts) ---
    const deviceEl = document.querySelector('#deviceChart');
    if (deviceEl) new ApexCharts(deviceEl, buildDonutOptions(deviceLabels, deviceSeries)).render();

    const osEl = document.querySelector('#osChart');
    if (osEl) new ApexCharts(osEl, buildDonutOptions(osLabels, osSeries)).render();

    const browserEl = document.querySelector('#browserChart');
    if (browserEl) new ApexCharts(browserEl, buildDonutOptions(browserLabels, browserSeries)).render();

});
</script>
