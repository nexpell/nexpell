<?php
// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;

// Admin-Zugriff pruefen
AccessControl::checkAdminAccess('ac_database');

// Captcha/Transaction
$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

// Helper: safe redirect on invalid transaction
$requireCaptcha = static function (string $returnUrl, $CAPCLASS): void {
    $provided = (string)($_REQUEST['captcha_hash'] ?? '');
    if (!$CAPCLASS->checkCaptcha(0, $provided)) {
        nx_redirect($returnUrl, 'danger', 'alert_transaction_invalid', false);
    }
};

// POST: Upload SQL Backup
if (isset($_POST['upload'])) {

    $returnUrl = 'admincenter.php?site=backup';
    $upload    = $_FILES['sql'] ?? null;

    $requireCaptcha($returnUrl, $CAPCLASS);

    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['name'])) {
        nx_redirect(
            $returnUrl,
            'warning',
            (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) ? 'alert_no_file_selected' : 'alert_upload_failed',
            false
        );
    }

    $backupDir = __DIR__ . '/myphp-backup-files/';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
        nx_redirect($returnUrl, 'danger', 'alert_upload_error', false);
    }

    $origName = (string)($upload['name'] ?? '');
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($origName));
    $backupFileName = time() . '_' . $safeName;
    $destFile = $backupDir . $backupFileName;

    if (!move_uploaded_file((string)($upload['tmp_name'] ?? ''), $destFile)) {
        nx_redirect($returnUrl, 'danger', 'alert_upload_error', false);
    }

    // Beschreibung
    $descRaw = (string)($_POST['description'] ?? '');
    $desc    = ($descRaw !== '') ? $descRaw : $safeName;
    $backupDescription = mysqli_real_escape_string($_database, $desc);

    $createdBy   = (int)$userID;
    $createdDate = date('Y-m-d H:i:s');

    $query = "INSERT INTO backups (filename, description, createdby, createdate)
              VALUES ('$backupFileName', '$backupDescription', '$createdBy', '$createdDate')";

    if (!mysqli_query($_database, $query)) {
        nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
    }

    nx_audit_action('backup', 'audit_action_backup_created', null, $backupFileName, $returnUrl, ['filename' => $backupFileName]);
    nx_redirect($returnUrl, 'success', 'alert_saved', false);
}

// GET-Parameter
$action   = (string)($_GET['action'] ?? '');
$returnto = (string)($_GET['back'] ?? 'database');

// GET: Delete Backup
if (isset($_GET['delete'])) {

    $returnUrl = 'admincenter.php?site=database';
    $requireCaptcha($returnUrl, $CAPCLASS);

    $id = (int)($_GET['id'] ?? 0);

    $filename = '';
    $res = mysqli_query($_database, "SELECT filename FROM backups WHERE id='$id' LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $filename = (string)($row['filename'] ?? '');
    }

    if (!mysqli_query($_database, "DELETE FROM backups WHERE id='$id'")) {
        nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
    }

    nx_audit_delete('backup', (string)$id, ($filename !== '' ? $filename : (string)$id), $returnUrl);
    nx_redirect($returnUrl, 'success', 'alert_deleted', false);
}

// GET: Optimize Database
elseif ($action === 'optimize') {

    $returnUrl = 'admincenter.php?site=' . $returnto;
    $requireCaptcha($returnUrl, $CAPCLASS);

    // Admin & Context check (ohne mbstring-Abhaengigkeit)
    $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if (!ispageadmin($userID) || substr(basename($reqUri), 0, 15) !== 'admincenter.php') {
        nx_redirect($returnUrl, 'danger', 'alert_access_denied', false);
    }

    $resDb = safe_query('SELECT DATABASE()');
    if (!$resDb) {
        nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
    }

    $dbRow = mysqli_fetch_row($resDb);
    $db    = (string)($dbRow[0] ?? '');
    if ($db === '') {
        nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
    }

    $result = mysqli_query($_database, "SHOW TABLES FROM `$db`");
    if ($result) {
        while ($table = mysqli_fetch_row($result)) {
            if (!empty($table[0])) {
                safe_query('OPTIMIZE TABLE `' . $table[0] . '`');
            }
        }
        nx_audit_action('database', 'audit_action_database_optimized', null, null, $returnUrl, ['db' => $db]);
        nx_redirect($returnUrl, 'success', 'alert_database_optimized', false);
    }

    nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
}

// GET: Backup erstellen (Export)
elseif ($action === 'write') {

    $returnUrl = 'admincenter.php?site=database';
    $requireCaptcha($returnUrl, $CAPCLASS);

    define('BACKUP_DIR', __DIR__ . '/myphp-backup-files');

    class Backup_Database {
        private mysqli $conn;
        private string $backupDir;
        private string $backupFile;
        private string $db;

        public function __construct(mysqli $conn, string $dbName, string $backupDir = BACKUP_DIR) {
            $this->conn = $conn;
            $this->db = $dbName;
            $this->backupDir = $backupDir;

            if (!is_dir($this->backupDir) && !mkdir($this->backupDir, 0777, true) && !is_dir($this->backupDir)) {
                throw new RuntimeException('Could not create backup dir');
            }

            $this->backupFile = rtrim($this->backupDir, '/\\') . '/' . $this->db . '-' . date('Y-m-d_H-i-s') . '.sql';
        }

        public function backupTables($tables = '*'): string {
            if ($tables === '*') {
                $result = mysqli_query($this->conn, 'SHOW TABLES');
                if (!$result) {
                    throw new RuntimeException('SHOW TABLES failed: ' . mysqli_error($this->conn));
                }
                $tables = [];
                while ($row = mysqli_fetch_row($result)) {
                    if (!empty($row[0])) {
                        $tables[] = $row[0];
                    }
                }
            }

            $sqlDump = "-- Backup von {$this->db} erstellt am " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                $createRes = mysqli_query($this->conn, "SHOW CREATE TABLE `$table`");
                if (!$createRes) {
                    throw new RuntimeException("SHOW CREATE TABLE failed for $table: " . mysqli_error($this->conn));
                }
                $row = mysqli_fetch_assoc($createRes);
                $createSql = (string)($row['Create Table'] ?? '');
                if ($createSql === '') {
                    continue;
                }

                $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n" . $createSql . ";\n\n";

                $dataResult = mysqli_query($this->conn, "SELECT * FROM `$table`");
                if (!$dataResult) {
                    throw new RuntimeException("SELECT * failed for $table: " . mysqli_error($this->conn));
                }

                while ($dataRow = mysqli_fetch_assoc($dataResult)) {
                    $vals = [];
                    foreach ($dataRow as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = "'" . mysqli_real_escape_string($this->conn, (string)$v) . "'";
                        }
                    }
                    $sqlDump .= "INSERT INTO `$table` VALUES(" . implode(',', $vals) . ");\n";
                }

                $sqlDump .= "\n";
            }

            if (file_put_contents($this->backupFile, $sqlDump) === false) {
                throw new RuntimeException('Could not write backup file');
            }

            return $this->backupFile;
        }
    }

    try {
        $resDb = safe_query('SELECT DATABASE()');
        if (!$resDb) {
            nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
        }
        $dbRow  = mysqli_fetch_row($resDb);
        $dbName = (string)($dbRow[0] ?? '');
        if ($dbName === '') {
            nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
        }

        $backup   = new Backup_Database($_database, $dbName);
        $filename = $backup->backupTables();

        $relativeFilename = basename($filename);
        $description      = mysqli_real_escape_string($_database, $relativeFilename);
        $createdBy        = (int)$userID;
        $createdDate      = date('Y-m-d H:i:s');

        $query = "INSERT INTO backups (filename, description, createdby, createdate)
                  VALUES ('$relativeFilename', '$description', '$createdBy', '$createdDate')";

        if (!mysqli_query($_database, $query)) {
            nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
        }

        nx_audit_action('database', 'audit_action_backup_created', null, null, $returnUrl, ['filename' => $relativeFilename]);
        nx_redirect($returnUrl, 'success', 'alert_saved', false);

    } catch (Throwable $e) {
        nx_redirect($returnUrl, 'danger', 'Fehler: ' . $e->getMessage(), true, true);
    }
}

// GET: Backup wiederherstellen
elseif ($action === 'back') {

    $returnUrl = 'admincenter.php?site=' . $returnto;
    $requireCaptcha($returnUrl, $CAPCLASS);

    $id = (int)($_GET['id'] ?? 0);

    $res = safe_query("SELECT * FROM backups WHERE id='$id' LIMIT 1");
    $ds  = $res ? mysqli_fetch_assoc($res) : null;
    $filename = is_array($ds) ? (string)($ds['filename'] ?? '') : '';
    if ($id <= 0 || $filename === '') {
        nx_redirect($returnUrl, 'danger', 'alert_db_error', false);
    }

    $backupFile = __DIR__ . '/myphp-backup-files/' . $filename;

    class Restore_Database {
        private mysqli $conn;
        private string $backupFile;
        private array $excludeTables = ['backups'];
        private $languageService;

        public function __construct(mysqli $conn, string $backupFile, $languageService) {
            $this->conn = $conn;
            $this->backupFile = $backupFile;
            $this->languageService = $languageService;
        }

        public function restoreDb(): int {
            if (!file_exists($this->backupFile)) {
                throw new RuntimeException($this->languageService->get('error_no_backup') . ' ' . htmlspecialchars($this->backupFile));
            }

            mysqli_query($this->conn, 'SET FOREIGN_KEY_CHECKS=0');

            $result = mysqli_query($this->conn, 'SHOW TABLES');
            if (!$result) {
                mysqli_query($this->conn, 'SET FOREIGN_KEY_CHECKS=1');
                throw new RuntimeException('SHOW TABLES failed: ' . mysqli_error($this->conn));
            }

            while ($row = mysqli_fetch_row($result)) {
                $table = (string)($row[0] ?? '');
                if ($table === '' || in_array($table, $this->excludeTables, true)) {
                    continue;
                }
                mysqli_query($this->conn, "DROP TABLE IF EXISTS `$table`");
            }

            $handle = fopen($this->backupFile, 'r');
            if (!$handle) {
                mysqli_query($this->conn, 'SET FOREIGN_KEY_CHECKS=1');
                throw new RuntimeException($this->languageService->get('error_backup_open') . ' ' . $this->backupFile);
            }

            $sql = '';
            $executed = 0;

            while (($line = fgets($handle)) !== false) {
                $trim = trim($line);

                if ($trim === '' || preg_match('/^(--|#)/', $trim) || preg_match('/^\/*\*/', $trim)) {
                    continue;
                }

                // backups-Tabelle nicht wiederherstellen
                if (preg_match('/\b`?backups`?\b/i', $trim)) {
                    continue;
                }

                $sql .= $line;

                if (substr(trim($line), -1) === ';') {
                    if (!mysqli_query($this->conn, $sql)) {
                        $err = mysqli_error($this->conn);
                        fclose($handle);
                        mysqli_query($this->conn, 'SET FOREIGN_KEY_CHECKS=1');
                        throw new RuntimeException('SQL error: ' . $err);
                    }
                    $sql = '';
                    $executed++;
                }
            }

            fclose($handle);
            mysqli_query($this->conn, 'SET FOREIGN_KEY_CHECKS=1');

            return $executed;
        }
    }

    try {
        $restore = new Restore_Database($_database, $backupFile, $languageService);
        $count   = (int)$restore->restoreDb();

        nx_audit_action('database', 'audit_action_backup_restored', null, null, $returnUrl, ['filename' => basename($backupFile), 'count' => $count]);
        nx_redirect($returnUrl, 'success', 'alert_backup_restored' . ' (' . $count . ')', false);

    } catch (Throwable $e) {
        nx_redirect($returnUrl, 'danger', 'Restore error: ' . $e->getMessage(), true, true);
    }
}

// Standardansicht
else {
    $resultBackups = safe_query('SELECT * FROM `backups` ORDER BY id DESC');

    // Card: Aktionen (Export/Optimize/Upload)
?>
    <div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-database"></i>
                <span><?= $languageService->get('database') ?></span>
                <small class="text-muted"><?= $languageService->get('export') ?> / <?= $languageService->get('optimize') ?></small>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row g-4">

                <!-- Export / Optimize -->
                <div class="col-12 col-lg-6">
                    <div class="d-grid gap-2">
                        <a href="admincenter.php?site=database&amp;action=write&amp;captcha_hash=<?= htmlspecialchars($hash) ?>" class="btn btn-primary">
                            <i class="bi bi-database-down me-2"></i> <?= $languageService->get('export') ?>
                        </a>
                        <small class="text-muted"><?= $languageService->get('export_info') ?></small>

                        <a href="admincenter.php?site=database&amp;action=optimize&amp;captcha_hash=<?= htmlspecialchars($hash) ?>" class="btn btn-warning mt-2">
                            <i class="bi bi-database-gear me-2"></i> <?= $languageService->get('optimize') ?>
                        </a>
                        <small class="text-muted"><?= $languageService->get('optimize_info') ?></small>
                    </div>
                </div>

                <!-- Upload Backup -->
                <div class="col-12 col-lg-6">
                    <form method="post" action="admincenter.php?site=database" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                        <label class="form-label mb-0"><?= $languageService->get('backup_file') ?></label>
                        <input class="form-control" type="file" name="sql" accept=".sql" required>

                        <input type="hidden" name="captcha_hash" value="<?= htmlspecialchars($hash) ?>">
                        <button type="submit" name="upload" class="btn btn-success mt-2">
                            <i class="bi bi-filetype-sql me-2"></i> <?= $languageService->get('upload') ?>
                        </button>
                        <div class="text-muted small"><?= $languageService->get('upload_info') ?></div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Backups -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-filetype-sql"></i>
                <span><?= $languageService->get('sql_query') ?></span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:80px;">ID</th>
                            <th><?= $languageService->get('file') ?></th>
                            <th style="width:320px;"><?= $languageService->get('date') ?></th>
                            <th style="width:320px;"><?= $languageService->get('created_by') ?></th>
                            <th style="width:520px;"><?= $languageService->get('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $download_url = 'admin/myphp-backup-files/';
                    if ($resultBackups) {
                        while ($ds = mysqli_fetch_array($resultBackups)):
                            $id = (int)($ds['id'] ?? 0);
                            $filename = (string)($ds['filename'] ?? '');
                            $description = (string)($ds['description'] ?? '');
                            $createdby = getusername($ds['createdby']);
                            $createdate = date('d/m/Y H:i', strtotime($ds['createdate']));
                    ?>
                        <tr>
                            <td><?= $id ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($description ?: $filename) ?></div>
                                <?php if (!empty($filename) && $description !== $filename): ?>
                                    <div class="text-muted small"><?= htmlspecialchars($filename) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($createdate) ?></td>
                            <td><?= htmlspecialchars((string)$createdby) ?></td>
                            <td>
                                <div class="d-inline-flex flex-wrap gap-2">
                                    <a href="<?= $download_url . rawurlencode($filename) ?>" class="btn btn-primary d-inline-flex align-items-center gap-1 flex-row w-auto">
                                        <i class="bi bi-download"></i>Download
                                    </a>
                                    <a href="admincenter.php?site=database&amp;action=back&amp;id=<?= $id ?>&amp;captcha_hash=<?= htmlspecialchars($hash) ?>" class="btn btn-success d-inline-flex align-items-center gap-1 flex-row w-auto">
                                        <i class="bi bi-database-up"></i><?= $languageService->get('upload') ?>
                                    </a>

                                    <button type="button" class="btn btn-danger d-inline-flex align-items-center gap-1 flex-row w-auto"
                                            data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                            data-delete-url="admincenter.php?site=database&amp;delete=true&amp;id=<?= $id ?>&amp;captcha_hash=<?= htmlspecialchars($hash) ?>">
                                        <i class="bi bi-trash3"></i><?= $languageService->get('delete') ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php
}
?>