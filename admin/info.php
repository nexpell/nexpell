<?php
use nexpell\LanguageService;

// Sicherstellen, dass Session läuft
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprache initialisieren, Standard ist Deutsch
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Die globale Variable für die MySQLi-Verbindung wird angenommen
global $languageService, $_database;
$languageService = new LanguageService($_database);
$languageService->readModule('info', true);

// Angemeldeter Benutzer
$userID = (int)$_SESSION['userID'];
$adminName = $_SESSION['username'] ?? 'Admin';

// =======================================================
// DYNAMISCHE DATEN AUS DER DATENBANK MIT MYSQLI LADEN
// =======================================================

// 1. Aktive Nutzer (Nutzer mit Aktivität in den letzten 5 Minuten)
$stmt = $_database->prepare("SELECT COUNT(*) FROM users WHERE last_activity > NOW() - INTERVAL 5 MINUTE AND is_online = 1");
$stmt->execute();
$stmt->bind_result($onlineUsers);
$stmt->fetch();
$stmt->close();
$onlineUsers = $onlineUsers ?: 0;

// 2. Anzahl installierter Plugins
$stmt = $_database->prepare("SELECT COUNT(*) FROM settings_plugins_installed");
$stmt->execute();
$stmt->bind_result($installedPlugins);
$stmt->fetch();
$stmt->close();
$installedPlugins = $installedPlugins ?: 0;

// 3. Anzahl installierter Themes
$stmt = $_database->prepare("SELECT COUNT(*) FROM settings_themes_installed");
$stmt->execute();
$stmt->bind_result($installedThemes);
$stmt->fetch();
$stmt->close();
$installedThemes = $installedThemes ?: 0;

// 4. Gesamtbesucher und Seitenaufrufe
$stmt = $_database->prepare("SELECT COUNT(DISTINCT ip_address) AS total_visitors, SUM(pageviews) AS total_pageviews FROM visitor_statistics");
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$totalVisitors = number_format($stats['total_visitors'] ?? 0);
$totalPageviews = number_format($stats['total_pageviews'] ?? 0);
$stmt->close();

// 5. Letztes Backup
$stmt = $_database->prepare("SELECT createdate FROM backups ORDER BY createdate DESC LIMIT 1");
$stmt->execute();
$stmt->bind_result($lastBackupDate);
$stmt->fetch();
$stmt->close();
$lastBackups = $lastBackupDate ? date('d.m.Y H:i', strtotime($lastBackupDate)) : 'Kein Backup gefunden';

// 6. Neueste Benutzer
$stmt = $_database->prepare("
    SELECT 
        u.userID, 
        u.username, 
        u.registerdate AS registered_at,
        GROUP_CONCAT(ur.role_name ORDER BY ur.roleID SEPARATOR ', ') AS roles
    FROM users u
    LEFT JOIN user_role_assignments ura ON u.userID = ura.userID
    LEFT JOIN user_roles ur ON ura.roleID = ur.roleID
    GROUP BY u.userID
    ORDER BY u.registerdate DESC
    LIMIT 3
");
$stmt->execute();
$result = $stmt->get_result();
$latestUsers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Formatieren der 'when'-Spalte und Markieren von Admins
foreach ($latestUsers as &$user) {
    $registeredTimestamp = strtotime($user['registered_at']);
    $diff = time() - $registeredTimestamp;

    // Zeitangabe formatieren
    if ($diff < 60) $user['when'] = $diff . ' Sek.';
    elseif ($diff < 3600) $user['when'] = floor($diff/60) . ' Min.';
    elseif ($diff < 86400) $user['when'] = floor($diff/3600) . ' Std.';
    else $user['when'] = floor($diff/86400) . ' Tagen';

    // Rollen-Badges erzeugen
    $roleBadges = [];
    $roles = array_map('trim', explode(',', $user['roles'] ?? ''));

    foreach ($roles as $role) {
        $cleanRole = htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (stripos($role, 'admin') !== false) {
            $roleBadges[] = '<span class="badge bg-danger">' . $cleanRole . '</span>';
            $user['username'] = '' . htmlspecialchars($user['username']) . '';
        } elseif (stripos($role, 'moderator') !== false) {
            $roleBadges[] = '<span class="badge bg-warning text-dark">' . $cleanRole . '</span>';
        } elseif (stripos($role, 'redakteur') !== false || stripos($role, 'editor') !== false) {
            $roleBadges[] = '<span class="badge bg-info text-dark">' . $cleanRole . '</span>';
        } else {
            $roleBadges[] = '<span class="badge bg-secondary">' . $cleanRole . '</span>';
        }
    }

    $user['role_badges'] = implode(' ', $roleBadges);
    $user['username'] = htmlspecialchars($user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
unset($user);





// 7. Kürzliche System-Aktivitäten
// Diese Daten sind weiterhin statisch, da keine Log-Tabelle existiert.
$recentLogs = [
    '2025-08-22 10:15 - Login: user: Admin',
    ''.$lastBackups.' - Letztes Backup gespeichert',
    ''.$current_version.' - System-Update: v'.htmlspecialchars($current_version).' installiert',
];

// 8. Versionsprüfung für Updates

// === Version & Updateprüfung mit visible_for + release_date + local last_update.txt ===

// Aktuelle Version ermitteln
$current_version = file_exists(__DIR__ . '/../system/version.php')
    ? include __DIR__ . '/../system/version.php'
    : '1.0.0';

// Lokales Update-Datum laden
$last_update_file = __DIR__ . '/../system/last_update.txt';
$last_update_date = 'unbekannt';
if (file_exists($last_update_file)) {
    $content = trim(file_get_contents($last_update_file));
    if ($content !== '') {
        $last_update_date = htmlspecialchars($content);
    }
}

// Update-Manifest abrufen
$update_info_url = "https://update.nexpell.de/updates/update_info.json";
$update_info = json_decode(@file_get_contents($update_info_url), true);

// Aktueller Benutzer (sichtbar für bestimmte Updates)
$current_user_email = $_SESSION['user_email'] ?? 'info@nexpell.de'; // Fallback

$updateAvailable = false;
$updateVersion = '';
$updateDate = '';
$updateText = 'Aktuell';
$updateChangelog = '';
$requiresNewUpdater = false;

if (isset($update_info['updates']) && is_array($update_info['updates'])) {
    foreach ($update_info['updates'] as $update) {

        // --- Sichtbarkeitsprüfung ---
        $visible_for = [];
        if (!empty($update['visible_for'])) {
            if (is_string($update['visible_for'])) {
                $visible_for = array_map('trim', explode(',', $update['visible_for']));
            } elseif (is_array($update['visible_for'])) {
                foreach ($update['visible_for'] as $entry) {
                    $visible_for = array_merge($visible_for, array_map('trim', explode(',', $entry)));
                }
            }
        }

        $is_visible = empty($visible_for) || in_array($current_user_email, $visible_for, true);

        // --- Versionsvergleich ---
        if ($is_visible && version_compare($update['version'], $current_version, '>')) {
            $updateAvailable = true;
            $updateVersion   = $update['version'];
            $updateDate      = $update['release_date'] ?? 'unbekannt';
            $updateChangelog = $update['changelog'] ?? '';
            $requiresNewUpdater = $update['requires_new_updater'] ?? false;
            $updateText = "v{$updateVersion} verfügbar";
            break;
        }
    }
}



$news_updates = [];

// JSON von der zentralen URL abrufen
#$json = file_get_contents('https://www.nexpell.de/admin/support_admin_news.php');

// JSON von der zentralen URL abrufen
$news_updates = [];
$json = @file_get_contents('https://www.nexpell.de/admin/support_admin_news_json.php');

if ($json !== false) {
    $data = json_decode($json, true);
    if (is_array($data)) {
        $news_updates = $data;
    }
}

?>

<style>
    /* UI-Tweaks für ein besseres Layout */
    .card-shortcuts .btn { min-width: 140px; }
    .metric { font-size: 1.5rem; font-weight: 600;color: #fe821d; }
    .small-muted { color: #6c757d; font-size: .9rem; }
    .fixed-col { min-width: 160px; }
    .activity-pre { max-height: 220px; overflow: auto; background: #f8f9fa; padding: 10px; border-radius: .375rem; }

    /* Standardmäßig Höhe automatisch */
.card-pair-1,
.card-pair-2 {
    display: flex;
    flex-direction: column;
}

/* Gleiche Höhe für obere Cards */
.card-pair-1 {
    height: auto; /* wird durch JS gesetzt */
}

/* Gleiche Höhe für untere Cards */
.card-pair-2 {
    height: auto; /* wird durch JS gesetzt */
}
</style>


    
<!-- Hauptbereich -->
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">

<div>
  <h1 class="h3 mb-0">Hallo, <?= htmlspecialchars($adminName) ?> 👋</h1>
  <div class="small text-muted mb-3">Übersicht & Schnellzugriffe</div>

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <div class="d-flex align-items-start mb-2">
        <i class="bi bi-info-circle text-primary fs-3 me-2"></i>
        <h5 class="card-title mb-0">Willkommen im Nexpell Admincenter</h5>
      </div>
      <p class="mb-3 text-muted">
        Das <strong>Admincenter</strong> ist die zentrale Steuerzentrale deines Nexpell-Systems.
        Hier kannst du sämtliche Module, Plugins und Themes verwalten, neue Funktionen aktivieren
        und dein System aktuell halten. Über die übersichtliche Navigation erreichst du schnell
        alle wichtigen Bereiche – von der Benutzer- und Rechteverwaltung bis zu Widgets, Seiten
        und System-Updates.
      </p>
      <p class="mb-3 text-muted">
  Die <strong>Info-Seite</strong> bietet dir einen umfassenden Überblick über den aktuellen Zustand deiner Nexpell-Installation.
  Hier findest du nicht nur technische Daten wie <b>Server-Umgebung</b>, <b>PHP-Version</b> und <b>Datenbank-Verbindung</b>,
  sondern auch eine Zusammenfassung der aktivierten <b>Plugins</b>, <b>Themes</b> und zuletzt ausgeführten <b>Backups</b>.
</p>

<p class="mb-3 text-muted">
  Darüber hinaus zeigt dir die Info-Seite wichtige Betriebsinformationen wie die aktuelle Anzahl der
  <b>Online-Nutzer</b>, <b>Besucherstatistiken</b> und <b>Systemaktivitäten</b> — etwa letzte Logins,
  ausgeführte Updates oder gespeicherte Backups. Diese Daten helfen dir dabei, den Systemstatus
  stets im Blick zu behalten und frühzeitig auf mögliche Probleme zu reagieren.
</p>

<p class="mb-3 text-muted">
  Im Abschnitt <strong>Neuigkeiten vom Nexpell-Team</strong> erhältst du außerdem aktuelle Informationen
  rund um dein System: Ankündigungen zu neuen Versionen, Sicherheitshinweise, Funktionsupdates und
  Hinweise zu kritischen Bugs. Diese Nachrichten werden regelmäßig aktualisiert, um dich über alle
  relevanten Änderungen, geplante Features und verfügbare Updates auf dem Laufenden zu halten.
</p>

<p class="mb-0 text-muted">
  Nutze die Info-Seite regelmäßig, um sicherzustellen, dass dein System auf dem neuesten Stand ist.
  Besonders vor einem <b>Core-Update</b> oder größeren Plugin-Änderungen empfiehlt es sich, einen Blick
  in diese Übersicht zu werfen – so kannst du die Integrität deines Systems gewährleisten, Probleme
  frühzeitig erkennen und bei Bedarf direkt über die <b>Backup- oder Update-Verwaltung</b> handeln.
</p>

      <p class="mb-3 text-muted">
        Zudem bietet dir das Admincenter eine integrierte Update-Verwaltung, mit der du automatisch
        prüfen kannst, ob neue Versionen verfügbar sind. Alle Updates werden sicher über den
        <strong>Nexpell Update-Server</strong> geladen und installiert – inklusive Datenbank-Migration
        und Integritätsprüfung. Nach jedem erfolgreichen Update wird automatisch das Datum in
        <code>/system/last_update.txt</code> gespeichert.
      </p>

      
      <?php
if ($updateAvailable) {
    echo "
    <div class='alert alert-warning small mb-3'>
      <div><strong>💡 Aktuelle Systeminformationen:</strong></div>
      <ul class='mb-0 mt-1'>
        <li><b>🔔 Update {$updateVersion}</b> verfügbar</li>
        <li>Veröffentlicht: " . htmlspecialchars($updateDate) . "</li>
        <li>Installierte Version: <code>" . htmlspecialchars($current_version) . "</code></li>
        <li>Zuletzt aktualisiert am: <code>" . htmlspecialchars($last_update_date) . "</code></li>
      </ul>
      " . ($requiresNewUpdater
            ? "<div class='text-danger mt-2'><b>⚠️ Dieses Update erfordert einen neuen Updater!</b></div>"
            : "") . "
      <hr class='my-2'>
      <div class='small'>" . nl2br(htmlspecialchars($updateChangelog)) . "</div>
    </div>";
} else {
    echo "
    <div class='alert alert-success small mb-3'>
      <div><strong>💡 Aktuelle Systeminformationen:</strong></div>
      <ul class='mb-0 mt-1'>
        <li>✅ System ist aktuell</li>
        <li><span class='text-muted'>Version: <code>" . htmlspecialchars($current_version) . "</code></span></li>
        <li><span class='text-muted'>Zuletzt aktualisiert am: <code>" . htmlspecialchars($last_update_date) . "</code></span></li>
        <li><strong>Pfad:</strong> <code>" . htmlspecialchars(realpath(__DIR__ . '/..')) . "</code></li>
      </ul>
    </div>";
}

?>

      <hr class="my-3">

      <p class="small text-muted mb-0">
        <i class="bi bi-shield-check"></i>
        Bitte beachte, dass nur Benutzer mit ausreichenden Rechten Zugriff auf die administrativen
        Funktionen haben. Achte stets darauf, dass du regelmäßig Backups anlegst, bevor du große
        Änderungen oder System-Updates durchführst.
      </p>
    </div>
  </div>
</div>


        
    </div>

    <!-- Dashboard-Kacheln -->
    <div class="row">
        <div class="col-6 col-md-3 mb-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="small-muted"><i class="bi bi-people-fill me-1"></i> Online-Nutzer</div>
                    <div class="metric"><?= number_format($onlineUsers) ?></div>
                    <div class="small-muted">Jetzt online</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3 mb-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="small-muted"><i class="bi bi-plug me-1"></i> Installierte Plugins</div>
                    <div class="metric"><?= (int)$installedPlugins ?></div>
                    <a href="admincenter.php?site=plugin_manager" class="small-muted">Plugins verwalten</a>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3 mb-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="small-muted"><i class="bi bi-palette me-1"></i> Installierte Themes</div>
                    <div class="metric"><?= (int)$installedThemes ?></div>
                    <a href="admincenter.php?site=theme_installer" class="small-muted">Themes verwalten</a>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3 mb-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="small-muted"><i class="bi bi-hdd-stack-fill me-1"></i> Letztes Backup</div>
                    <div class="metric"><?= htmlspecialchars($lastBackups) ?></div>
                    <a href="admincenter.php?site=database" class="small-muted">Backup-Verwaltung</a>
                </div>
            </div>
        </div>

    </div>

    <!-- Hauptbereich -->
    <div class="row mt-2">
        <!-- Linke Seite -->
        <div class="col-lg-7 d-flex flex-column">
            <!-- Schnellzugriffe (obere Card) -->
            <div class="card card-shortcuts card-pair-1 flex-fill mb-2">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h5 class="card-title mb-1">Schnellzugriffe</h5>
                        <div class="small-muted mb-2">Aktionen, die Admins häufig nutzen</div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="admincenter.php?site=posts_new" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> <s>Neuer Beitrag</s></a>
                            <a href="admincenter.php?site=user_roles" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people"></i> Benutzer- und Rechteverwaltung</a>
                            <a href="admincenter.php?site=plugin_manager" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plug"></i> Plugin-Manager</a>
                            <a href="admincenter.php?site=settings" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Einstellungen</a>
                            <a href="admincenter.php?site=media" class="btn btn-outline-secondary btn-sm"><i class="bi bi-image"></i> <s>Medien</s></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System-Aktivitäten (untere Card) -->
            <div class="card shadow-sm card-pair-2 flex-fill">
                <div class="card-body">
                    <h5 class="card-title">Kürzliche System-Aktivitäten</h5>
                    <div class="small-muted mb-2">Wichtige Log-Einträge & Aktionen</div>
                    <div class="row h-100">
                        <div class="col-md-7">
                            <ul class="list-group list-group-flush">
                                <?php if (!empty($recentLogs)): ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <li class="list-group-item small-muted"><?= htmlspecialchars($log) ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-muted">Keine Log-Einträge gefunden.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="col-md-5">
                            <div class="small-muted mb-1">Schnellaktionen</div>
                            <div class="d-grid gap-2">
                                <a href="admincenter.php?site=site_lock" class="btn btn-outline-primary btn-sm">Wartungsmodus</a>
                                <a href="admincenter.php?site=database" class="btn btn-outline-success btn-sm">Backup jetzt starten</a>
                                <a href="admincenter.php?site=security_overview" class="btn btn-outline-warning btn-sm">Registrierungs- und Login-Aktivitäten</a>
                                <a href="admincenter.php?site=log_viewer" class="btn btn-outline-danger btn-sm">Zugriffprotokoll</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rechte Seite -->
        <div class="col-lg-5 d-flex flex-column">
            <!-- Webseiten-Statistiken (obere Card) -->
            <div class="card shadow-sm card-pair-1 flex-fill mb-2">
                <div class="card-body">
                    <h5 class="card-title">Webseiten-Statistiken</h5>
                    <div class="d-flex justify-content-between">
                        <div class="text-center">
                            <div class="metric"><?= $totalVisitors ?></div>
                            <div class="small-muted">Besucher</div>
                        </div>
                        <div class="text-center">
                            <div class="metric"><?= $totalPageviews ?></div>
                            <div class="small-muted">Seitenaufrufe</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Neueste Benutzer (untere Card) -->
            <div class="card shadow-sm card-pair-2 flex-fill">
                <div class="card-body">
                    <h5 class="card-title">Neueste Benutzer</h5>
                    <div class="small-muted mb-2">Letzte Registrierungen / Anmeldungen</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-start">Rolle</th>
                                    <th class="text-end">Vor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($latestUsers)): ?>
                                    <?php foreach ($latestUsers as $u): ?>
                                        <tr>
                                            <td><?= $u['username'] ?></td>
                                            <td class="text-start">
                                                <?= $u['role_badges'] ?? '<span class="badge bg-secondary">Keine Rollen</span>' ?>
                                            </td>
                                            <td class="text-end small text-muted"><?= htmlspecialchars($u['when']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Keine neuen Benutzer gefunden.</td>
                                    </tr>
                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 text-end"><a href="admincenter.php?site=user_roles" class="primary">Alle Benutzer</a></div>
                </div>
            </div>
        </div>
    </div>

    <!-- News -->
    <div class="card shadow-sm mt-2">
        <div class="card-body">
            <h5 class="mb-3">Neuigkeiten vom Nexpell-Team</h5>
            <div class="alert alert-info" role="alert">
              <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Zweck der Neuigkeiten-Sektion</h6>
              <p>
                Diese Sektion zeigt allen Administratoren wichtige Informationen an, die für den Betrieb, 
                die Sicherheit und die Weiterentwicklung von <strong>Nexpell</strong> relevant sind. Beispiele:
              </p>
              <ul class="mb-0">
                <li><i class="bi bi-shield-lock me-1"></i> Sicherheitsupdates oder Patches für den Core</li>
                <li><i class="bi bi-stars me-1"></i> Neue Features in kommenden Versionen</li>
                <li><i class="bi bi-gear me-1"></i> Wichtige Änderungen an Plugins oder API</li>
                <li><i class="bi bi-exclamation-triangle me-1"></i> Hinweise zu kritischen Bugs oder bekannten Problemen</li>
              </ul>
            </div>

            <div class="list-group shadow-sm mb-4">
                <?php if (!empty($news_updates)): ?>
                    <?php foreach ($news_updates as $news): ?>
                        <?php if (!empty($news['link'])): ?>
                            <a href="<?= htmlspecialchars($news['link']); ?>" class="list-group-item list-group-item-action" target="_blank">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($news['title']); ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($news['date']); ?></small>
                                </div>
                                <p class="mb-0 small"><?= $news['summary']; ?></p>
                            </a>
                        <?php else: ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($news['title']); ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($news['date']); ?></small>
                                </div>
                                <p class="mb-0 small"><?= $news['summary']; ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="list-group-item text-muted">Keine News verfügbar</div>
                <?php endif; ?>
            </div>

        </div>
    </div>


</div>

<!-- JS für gleiche Höhe gegenüberliegender Cards -->
<script>
function matchCardHeights() {
    const pair1 = document.querySelectorAll('.card-pair-1');
    let max1 = 0;
    pair1.forEach(c => { max1 = Math.max(max1, c.offsetHeight); });
    pair1.forEach(c => { c.style.height = max1 + 'px'; });

    const pair2 = document.querySelectorAll('.card-pair-2');
    let max2 = 0;
    pair2.forEach(c => { max2 = Math.max(max2, c.offsetHeight); });
    pair2.forEach(c => { c.style.height = max2 + 'px'; });
}

window.addEventListener('load', matchCardHeights);
window.addEventListener('resize', matchCardHeights);
</script>
