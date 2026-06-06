<?php
// Session starten
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;

// Global
global $_database, $languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('database', true);

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('ac_database');

// Captcha erstellen
$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

// POST: Backup hochladen
if (isset($_POST['upload'])) {
    $upload = $_FILES['sql'];
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        if (!empty($upload['name'])) {

            $backupDir = __DIR__ . "/myphp-backup-files/";
            if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

            $backupFileName = time() . '_' . basename($upload['name']);
            $destFile = $backupDir . $backupFileName;

            if (move_uploaded_file($upload['tmp_name'], $destFile)) {
                $backupDescription = !empty($_POST['description']) 
                    ? mysqli_real_escape_string($_database, $_POST['description']) 
                    : mysqli_real_escape_string($_database, basename($upload['name']));

                $createdBy = $userID;
                $createdDate = date("Y-m-d H:i:s");

                $query = "INSERT INTO backups (filename, description, createdby, createdate)
                          VALUES ('$backupFileName', '$backupDescription', '$createdBy', '$createdDate')";

                if (mysqli_query($_database, $query)) {
                    echo '<div class="alert alert-success">Backup erfolgreich hochgeladen und in der Datenbank gespeichert.</div>';
                } else {
                    echo '<div class="alert alert-danger">Fehler beim Speichern in der Datenbank: ' . mysqli_error($_database) . '</div>';
                }
            } else {
                echo '<div class="alert alert-danger">Fehler beim Verschieben der Datei.</div>';
            }

        } else {
            echo '<div class="alert alert-warning">Keine Datei ausgewählt.</div>';
        }
    } else {
        echo '<div class="alert alert-danger">' . $languageService->get('transaction_invalid') . '</div>';
    }
}

// GET-Parameter
$action = $_GET['action'] ?? '';
$returnto = $_GET['back'] ?? 'database';

// Backup löschen (nur DB-Eintrag, keine Datei!)
if (isset($_GET['delete'])) {
    if ($CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'])) {
        $id = intval($_GET['id']);

        $query = "DELETE FROM backups WHERE id='$id'";
        if (!mysqli_query($_database, $query)) {
            echo '<div class="alert alert-danger">Fehler beim Löschen des Eintrags: ' . mysqli_error($_database) . '</div>';
        } else {
            echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Hinweis:</strong> Backup-Eintrag wurde gelöscht, die Datei bleibt bestehen.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
            echo '<script>setTimeout(()=>{window.location.href="admincenter.php?site=database";},2000);</script>';
        }
    } else {
        echo '<div class="alert alert-danger">' . $languageService->get('transaction_invalid') . '</div>';
    }
}

// Datenbank optimieren
elseif ($action == "optimize") {
    if (!ispageadmin($userID) || mb_substr(basename($_SERVER['REQUEST_URI']), 0, 15) != "admincenter.php") {
        die($languageService->get('access_denied'));
    }
    $db = mysqli_fetch_array(safe_query("SELECT DATABASE()"))[0];
    $result = mysqli_query($_database, "SHOW TABLES FROM `$db`");
    while ($table = mysqli_fetch_array($result)) {
        safe_query("OPTIMIZE TABLE `" . $table[0] . "`");
    }
    redirect('admincenter.php?site=' . $returnto, '', 0);
}

// Backup erstellen
elseif ($action == "write") {
    define('BACKUP_DIR', __DIR__ . '/myphp-backup-files');

    class Backup_Database {
        private $conn, $backupDir, $backupFile, $db;
        public function __construct($conn, $dbName, $backupDir = BACKUP_DIR) {
            $this->conn = $conn;
            $this->db = $dbName;
            $this->backupDir = $backupDir;
            if (!is_dir($this->backupDir)) mkdir($this->backupDir, 0777, true);
            $this->backupFile = $this->backupDir . '/' . $this->db . '-' . date('Y-m-d_H-i-s') . '.sql';
        }
        public function backupTables($tables = '*') {
            if ($tables === '*') {
                $result = mysqli_query($this->conn, "SHOW TABLES");
                $tables = [];
                while ($row = mysqli_fetch_array($result)) $tables[] = $row[0];
            }

            $sqlDump = "-- Backup von {$this->db} erstellt am " . date('Y-m-d H:i:s') . "\n\n";
            foreach ($tables as $table) {
                $row = mysqli_fetch_assoc(mysqli_query($this->conn, "SHOW CREATE TABLE `$table`"));
                $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n" . $row['Create Table'] . ";\n\n";
                $dataResult = mysqli_query($this->conn, "SELECT * FROM `$table`");
                while ($dataRow = mysqli_fetch_assoc($dataResult)) {
                    $vals = array_map(fn($v)=>is_null($v)?"NULL":"'".addslashes($v)."'", array_values($dataRow));
                    $sqlDump .= "INSERT INTO `$table` VALUES(" . implode(',', $vals) . ");\n";
                }
                $sqlDump .= "\n";
            }
            file_put_contents($this->backupFile, $sqlDump);
            return $this->backupFile;
        }
    }

    $dbName = mysqli_fetch_array(safe_query("SELECT DATABASE()"))[0];

    try {
        $backup = new Backup_Database($_database, $dbName);
        $filename = $backup->backupTables();

        $relativeFilename = basename($filename);
        $description = mysqli_real_escape_string($_database, $relativeFilename);
        $createdBy = $userID;
        $createdDate = date("Y-m-d H:i:s");

        $query = "INSERT INTO backups (filename, description, createdby, createdate)
                  VALUES ('$relativeFilename', '$description', '$createdBy', '$createdDate')";          

        if (!mysqli_query($_database, $query)) {
            echo '<div class="alert alert-danger">Fehler beim Speichern in der DB: ' . mysqli_error($_database) . '</div>';
        } else {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Erfolg!</strong> Backup erstellt und in der Datenbank gespeichert: <code>' . htmlspecialchars($relativeFilename) . '</code>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }

        echo '<a href="admincenter.php?site=database" class="btn btn-secondary mt-2">Zurück zur Datenbankverwaltung</a>';
        echo '<script>setTimeout(() => { window.location.href = "admincenter.php?site=database"; }, 3000);</script>';

    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Backup wiederherstellen
// Backup wiederherstellen
// Backup wiederherstellen
// Backup wiederherstellen
elseif ($action == "back") {
    $id = intval($_GET['id']);
    $ds = mysqli_fetch_array(safe_query("SELECT * FROM backups WHERE id='$id'"));
    $backupFile = __DIR__ . "/myphp-backup-files/" . $ds['filename'];

    class Restore_Database {
        private $conn;
        private $backupFile;
        private $excludeTables = ['backups'];

        public function __construct($conn, $backupFile) {
            $this->conn = $conn;
            $this->backupFile = $backupFile;
        }

        public function restoreDb() {
            if (!file_exists($this->backupFile)) {
                throw new Exception("Backup-Datei nicht gefunden: " . htmlspecialchars($this->backupFile));
            }

            mysqli_query($this->conn, "SET FOREIGN_KEY_CHECKS=0");

            // Alle Tabellen löschen außer excludeTables
            $result = mysqli_query($this->conn, "SHOW TABLES");
            while ($row = mysqli_fetch_array($result)) {
                $table = $row[0];
                if (!in_array($table, $this->excludeTables, true)) {
                    mysqli_query($this->conn, "DROP TABLE IF EXISTS `$table`");
                }
            }

            $handle = fopen($this->backupFile, 'r');
            if (!$handle) throw new Exception("Backup-Datei konnte nicht geöffnet werden.");

            $sql = '';
            $executed = 0;
            $skipStatement = false;
            $insideCreateTable = false;
            $primaryKeySeen = [];
            $currentTable = '';

            while (($line = fgets($handle)) !== false) {
                $trim = trim($line);

                // Leerzeilen oder Kommentare überspringen
                if ($trim === '' || preg_match('/^(--|#)/', $trim) || preg_match('/^\/\*/', $trim)) continue;

                // Skip Statement falls nötig
                if ($skipStatement) {
                    if (strpos($trim, ';') !== false) $skipStatement = false;
                    continue;
                }

                // BACKUPS-Tabelle ignorieren
                if (preg_match('/^(DROP\s+TABLE\s+IF\s+EXISTS\s+`?backups`?)/i', $trim)
                    || preg_match('/^(CREATE\s+TABLE\s+`?backups`?)/i', $trim)
                    || preg_match('/^(INSERT\s+INTO\s+`?backups`?)/i', $trim)
                    || preg_match('/^(ALTER\s+TABLE\s+`?backups`?)/i', $trim)) {
                    if (strpos($trim, ';') === false) $skipStatement = true;
                    continue;
                }

                // CREATE TABLE erkennen
                if (preg_match('/^CREATE\s+TABLE\s+`?(\w+)`?/i', $trim, $matches)) {
                    $insideCreateTable = true;
                    $currentTable = $matches[1];
                    $primaryKeySeen[$currentTable] = false;
                }

                // PRIMARY KEY Duplikate ignorieren
                if ($insideCreateTable && preg_match('/PRIMARY\s+KEY/i', $trim)) {
                    if ($primaryKeySeen[$currentTable]) continue;
                    $primaryKeySeen[$currentTable] = true;
                }

                // ALTER TABLE PRIMARY KEY Duplikate ignorieren
                if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?.*ADD\s+PRIMARY\s+KEY/i', $trim, $matches)) {
                    $table = $matches[1];
                    if (in_array($table, $this->excludeTables, true)) continue;
                    if (!empty($primaryKeySeen[$table])) continue;
                    $primaryKeySeen[$table] = true;
                    // Zeile trotzdem ausführen, wenn noch kein PK gesetzt
                }

                $sql .= $line . "\n";

                // Statement Ende
                if (substr(trim($line), -1) === ';') {
                    if (!mysqli_query($this->conn, $sql)) {
                        $err = mysqli_error($this->conn);
                        fclose($handle);
                        mysqli_query($this->conn, "SET FOREIGN_KEY_CHECKS=1");
                        throw new Exception("Fehler beim Ausführen von SQL:\n$sql\nMySQL-Fehler: $err");
                    }
                    $sql = '';
                    $executed++;
                    $insideCreateTable = false;
                    $currentTable = '';
                }
            }

            fclose($handle);
            mysqli_query($this->conn, "SET FOREIGN_KEY_CHECKS=1");
            return $executed;
        }
    }

    try {
        $restore = new Restore_Database($_database, $backupFile);
        $count = $restore->restoreDb();

        echo '<div class="container my-4">';
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Erfolg!</strong> Backup erfolgreich wiederhergestellt.<br>
                Tabelle <code>backups</code> blieb unverändert.<br>
                Ausgeführte SQL-Befehle: ' . $count . '<br>
                Datei: <code>' . htmlspecialchars(basename($backupFile)) . '</code>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        echo '<a href="admincenter.php?site=database" class="btn btn-secondary mt-2">Zurück zur Datenbankverwaltung</a>';
        echo '<script>setTimeout(()=>{window.location.href="admincenter.php?site=database";},3000);</script>';
        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Fehler: ' . nl2br(htmlspecialchars($e->getMessage())) . '</div>';
    }
}







// Standardansicht
else {
?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header"><i class="bi bi-database me-2"></i> <?= $languageService->get('database') ?></div>
        <div class="card-body">
            <div class="container my-4">
            <div class="row g-4">

                <!-- Export / Optimize -->
                <div class="col-md-6">
                    <div class="d-grid gap-3">
                        <a href="admincenter.php?site=database&amp;action=write&amp;captcha_hash=<?= $hash ?>" class="btn btn-primary">
                            <i class="bi bi-database-down me-2"></i> <?= $languageService->get('export') ?>
                        </a>
                        <small class="text-muted"><?= $languageService->get('export_info') ?></small>

                        <a href="admincenter.php?site=database&amp;action=optimize" class="btn btn-warning mt-3">
                            <i class="bi bi-database-gear me-2"></i> <?= $languageService->get('optimize') ?>
                        </a>
                        <small class="text-muted"><?= $languageService->get('optimize_info') ?></small>
                    </div>
                </div>

                <!-- Upload Backup -->
                <div class="col-md-6">
                    <form method="post" action="admincenter.php?site=database" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                        <label for="sqlFile" class="form-label"><?= $languageService->get('backup_file') ?></label>
                        <input type="file" name="sql" id="sqlFile" class="form-control">
                        <input type="hidden" name="captcha_hash" value="<?= $hash ?>" />
                        <button type="submit" name="upload" class="btn btn-success mt-2">
                            <i class="bi bi-filetype-sql me-2"></i> <?= $languageService->get('upload') ?>
                        </button>
                        <small class="text-muted"><?= $languageService->get('upload_info') ?></small>
                    </form>
                </div>
            </div>    
            </div>
        </div>
    </div>

    <!-- Backup Table -->
    <div class="card shadow-sm">
        <div class="card-header"><i class="bi bi-braces me-2"></i> <?= $languageService->get('sql_query') ?></div>
        <div class="card-body p-0">
            <div class="container my-4">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th><?= $languageService->get('file') ?></th>
                        <th><?= $languageService->get('date') ?></th>
                        <th><?= $languageService->get('created_by') ?></th>
                        <th><?= $languageService->get('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = safe_query("SELECT * FROM `backups` ORDER BY id DESC");
                    while ($ds = mysqli_fetch_array($result)) :
                        $id = $ds['id'];
                        $filename = $ds['filename'];
                        $description = $ds['description'];
                        $createdby = getusername($ds['createdby']);
                        $createdate = date("d/m/Y H:i", strtotime($ds['createdate']));
                        $download_url = "admin/myphp-backup-files/";
                    ?>
                        <tr>
                            <td><?= $id ?></td>
                            <td><?= htmlspecialchars($description) ?></td>
                            <td><?= $createdate ?></td>
                            <td><?= htmlspecialchars($createdby) ?></td>
                            <td class="d-flex flex-column gap-1">
                                <a href="<?= $download_url . $filename ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <a href="admincenter.php?site=database&amp;action=back&amp;id=<?= $id ?>" class="btn btn-outline-warning btn-sm">
                                    <i class="bi bi-database-up"></i> <?= $languageService->get('upload') ?>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $id ?>">
                                    <i class="bi bi-trash3"></i> <?= $languageService->get('delete') ?>
                                </button>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?= $id ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><?= $languageService->get('really_delete') ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $languageService->get('close') ?></button>
                                                <a href="admincenter.php?site=database&amp;delete=true&amp;id=<?= $id ?>&amp;captcha_hash=<?= $hash ?>" class="btn btn-danger"><?= $languageService->get('delete') ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

<?php
} // Ende else Standardansicht
?>
