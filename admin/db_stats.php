<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('ac_db_stats');

global $_database;
$count_array = array();

// Tabellenliste (ohne Präfixe)
$tables_array = array (
    "plugins_about_us",
    "plugins_articles",
    "plugins_awards",
    "plugins_bannerrotation",
    "plugins_fight_us_challenge",
    "plugins_clanwars",
    "plugins_clan_rules",
    "contact",
    "plugins_faq",
    "plugins_faq_categories",
    "plugins_files",
    "plugins_files_categories",
    "plugins_forum_announcements",
    "plugins_forum_boards",
    "plugins_forum_categories",
    "plugins_forum_groups",
    "plugins_forum_moderators",
    "plugins_forum_posts",
    "plugins_forum_ranks",
    "plugins_forum_topics",
    "plugins_gallery",
    "plugins_gallery_categorys",
    "settings_games",
    "plugins_guestbook",
    "plugins_links",
    "plugins_links_categorys",
    "plugins_linkus",
    "plugins_messenger",
    "plugins_news",
    "plugins_news_rubrics",
    "plugins_news_comments",
    "plugins_partners",
    "plugins_poll",
    "plugins_servers",
    "plugins_shoutbox",
    "plugins_sponsors",
    "squads",
    "static",
    "users",
    "plugins_videos",
    "plugins_videos_categories",
    "plugins_videos_comments",
    "plugins_todo",
    "plugins_streams",
    "plugins_pic_update",
);

$db_size = 0;
$db_size_op = 0;

// Aktuellen Datenbanknamen ermitteln
if (!isset($db)) {
    $get = safe_query("SELECT DATABASE()");
    $ret = mysqli_fetch_array($get);
    $db = $ret[0];
}

// Gesamtanzahl Tabellen
$query = safe_query("SHOW TABLES");
$count_tables = mysqli_num_rows($query);

// Durchlaufe alle Tabellen
foreach ($tables_array as $table) {
    if (mysqli_num_rows(safe_query("SHOW TABLE STATUS FROM `$db` LIKE '$table'"))) {
        $check = mysqli_query($_database, "SELECT * FROM `$table`");
        if ($check) {
            $sql = safe_query("SHOW TABLE STATUS FROM `$db` LIKE '$table'");
            $data = mysqli_fetch_array($sql);
            $db_size += ($data['Data_length'] + $data['Index_length']);
            if (strtolower($data['Engine']) == "myisam") {
                $db_size_op += $data['Data_free'];
            }

            $lang_value = $languageService->get($table);
            $table_name = !empty($lang_value) ? $lang_value : ucfirst(str_replace("_", " ", $table));
            $count_array[] = array($table_name, $data['Rows']);
        }
    }
}
?>
<?php
$table_names = [];
$table_sizes = [];

// Durchlaufe alle Tabellen und berechne deren Größen
foreach ($tables_array as $table) {
    $sql_status = safe_query("SHOW TABLE STATUS FROM `$db` LIKE '$table'");
    if (mysqli_num_rows($sql_status)) {
        $data = mysqli_fetch_array($sql_status);
        
        // Berechnung der Datenbankgrößen
        $db_size += ($data['Data_length'] + $data['Index_length']);
        if (strtolower($data['Engine']) == "myisam") {
            $db_size_op += $data['Data_free'];
        }

        // Tabellenname und Größe speichern
        $lang_value = $languageService->get($table);
        $table_names[] = !empty($lang_value) ? $lang_value : ucfirst(str_replace("_", " ", $table));
        $table_sizes[] = (int)($data['Data_length'] + $data['Index_length']); // Umwandlung in int
    }
}

/**
 * Formatierte Ausgabe der Größe
 */
function format_size($size) {
    if ($size < 1024) {
        return $size . ' B';
    } elseif ($size < 1048576) {
        return round($size / 1024, 2) . ' KB';
    } elseif ($size < 1073741824) {
        return round($size / 1048576, 2) . ' MB';
    } else {
        return round($size / 1073741824, 2) . ' GB';
    }
}
?>

<!-- Datenbankinformationen -->
    <div class="row g-4">

        <!-- Datenbank -->
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm border-0 mb-4 mt-3 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-database"></i>
                        <span><?php echo $languageService->get('database'); ?></span>
                    </div>
                </div>
                <div class="card-body">


            <!-- Erste Reihe für MySQL Version, Größe und Tabellen -->
            <div class="row">
                <!-- Erste Tabelle: MySQL Version, Größe und Tabellen -->
                <div class="col-md-12">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo $languageService->get('property'); ?></th>
                                <th><?php echo $languageService->get('value'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo $languageService->get('mysql_version'); ?>:</td>
                                <td>
                                    <span class="pull-right text-muted small">
                                        <em><?php echo mysqli_get_server_info($_database); ?></em>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><?php echo $languageService->get('size'); ?>:</td>
                                <td>
                                    <span class="pull-right text-muted small">
                                        <em><?php echo $db_size; ?> Bytes (<?php echo round($db_size / 1024 / 1024, 2); ?> MB)</em>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><?php echo $languageService->get('overhead'); ?>:</td>
                                <td>
                                    <span class="pull-right text-muted small">
                                        <em><?php echo $db_size_op; ?> Bytes</em>
                                        <?php
                                        if ($db_size_op != 0) {
                                            echo '<a href="admincenter.php?site=database&amp;action=optimize&amp;back=page_statistic">
                                                    <font color="red"><b>' . $languageService->get('optimize') . '</b></font>
                                                  </a>';
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><?php echo $languageService->get('tables'); ?>:</td>
                                <td>
                                    <span class="pull-right text-muted small">
                                        <em><?php echo $count_tables; ?></em>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
    </div>

        <!-- Tabellenzeilen / Page Stats -->
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm border-0 mb-4 mt-4 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-table"></i>
                        <span><?php echo $languageService->get('page_stats'); ?></span>
                    </div>
                </div>
                <div class="card-body">

            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo $languageService->get('property'); ?></th>
                        <th><?php echo $languageService->get('value'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        for ($i = 0; $i < count($count_array); $i += 2) {
                            ?>
                            <td class="col-md-6">
                                <div class="d-flex justify-content-between">
                                    <div class="fw-semibold"><?php echo $count_array[$i][0]; ?>:</div>
                                    <div class="text-muted small"><em><?php echo $count_array[$i][1]; ?></em></div>
                                </div>
                            </td>
                            <?php if (isset($count_array[$i + 1])) { ?>
                                <td class="col-md-6">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-semibold"><?php echo $count_array[$i + 1][0]; ?>:</span>
                                        <span class="text-muted small"><em><?php echo $count_array[$i + 1][1]; ?></em></span>
                                    </div>
                                </td>
                            <?php } ?>
                            <?php
                            if (($i + 2) % 2 == 0) { // Neue Zeile nach 2 Spalten
                                echo '</tr><tr>';
                            }
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
                </div>
            </div>
        </div>

        <!-- Diagramm: Tabellen-Größe -->
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 mb-4 mt-4 h-100">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-bar-chart-line"></i>
                        <span><?php echo $languageService->get('table_size_chart'); ?></span>
                    </div>
                </div>
                <div class="card-body">

            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo $languageService->get('table_size_chart'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <!-- Canvas für das Diagramm -->
                            <div id="tableSizeChart" style="height:320px;"></div>
                        </td>
                    </tr>
                </tbody>
            </table>

                </div>
            </div>
        </div>

    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Daten aus PHP
    const tableNames = <?php echo json_encode($table_names); ?>;
    const tableSizesBytes = <?php echo json_encode($table_sizes); ?>;

    // In MB umrechnen (2 Nachkommastellen, als Number)
    const tableSizesMB = (Array.isArray(tableSizesBytes) ? tableSizesBytes : []).map(v => {
        const mb = (Number(v) || 0) / 1024 / 1024;
        return Math.round(mb * 100) / 100;
    });

    // Primärfarbe (optional; wird sonst aus CSS / Fallback gezogen)
    const UI_PRIMARY = '';

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

    const chartEl = document.querySelector('#tableSizeChart');
    if (chartEl) {
        new ApexCharts(
            chartEl,
            buildBarOptions(tableNames, tableSizesMB, 'MB', false)
        ).render();
    }
});
</script>