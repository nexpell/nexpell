<?php

use nexpell\LanguageManager;
use nexpell\LanguageService;
use nexpell\AccessControl;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
session_start();
}

// Adminrechte prüfen
AccessControl::checkAdminAccess('ac_languages');

// Manager initialisieren
$langManager = new LanguageManager($_database);

$persistLanguageFlag = function (mysqli $_db, int $id, ?string $flagPath): void {
	if ($id <= 0) {
		return;
	}

	$flagPath = trim((string)$flagPath);

	$stmt = $_db->prepare("UPDATE settings_languages SET flag = ? WHERE id = ?");
	if (!$stmt) {
		return;
	}

	$val = ($flagPath !== '') ? $flagPath : null;
	$stmt->bind_param('si', $val, $id);
	$stmt->execute();
	$stmt->close();
};

// Initialwerte
$action = $_GET['action'] ?? '';
$editid = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $editid > 0) {

    $lang = $langManager->getLanguage($editid);
    if ($lang) {
        $auditName = trim((string)($lang['name_de'] ?? $lang['name'] ?? $lang['iso_639_1'] ?? $editid));
        $langManager->deleteLanguage($editid);
        nx_audit_delete('languages', (string)$editid, $auditName, 'admincenter.php?site=languages');
        nx_redirect('admincenter.php?site=languages', 'success', 'alert_deleted', false);
    }

    nx_redirect('admincenter.php?site=languages', 'danger', 'alert_not_found', false);
}

// Sprache zur Bearbeitung laden
if ($action === 'edit' && $editid > 0) {
    $editLanguage = $langManager->getLanguage($editid);

    if (!$editLanguage) { nx_alert('danger', 'alert_not_found', false); $action = ''; $editid = 0; }
}

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $iso1   = trim($_POST['iso_639_1'] ?? '');
    $nameEn = trim($_POST['name_en'] ?? '');

    $hasError = false;

    if (strlen($iso1) !== 2) { nx_alert('danger', 'alert_iso_code_invalid', false); $hasError = true; }
    elseif ($nameEn === '') { nx_alert('danger', 'alert_name_required', false); $hasError = true; }

    $flagPath = trim($_POST['existing_flag'] ?? ($_POST['flag'] ?? ''));

    if (
        !$hasError &&
        isset($_FILES['flag_upload']) &&
        is_array($_FILES['flag_upload']) &&
        !empty($_FILES['flag_upload']['tmp_name'])
    ) {
        $uploadError = (int)($_FILES['flag_upload']['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError !== UPLOAD_ERR_OK) { nx_alert('danger', 'alert_upload_error', false); $hasError = true; }
        else {
            $maxBytes = 3 * 1024 * 1024;
            if ((int)($_FILES['flag_upload']['size'] ?? 0) > $maxBytes) { nx_alert('danger', 'alert_upload_too_large', false); $hasError = true; }
            else {
                $originalName = (string)($_FILES['flag_upload']['name'] ?? '');
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];

                if (!in_array($ext, $allowedExt, true)) { nx_alert('danger', 'alert_upload_invalid_type', false); $hasError = true; }
                else {
                    $uploadDirFs = rtrim(__DIR__, '/\\') . '/images/flags/';
                    if (!is_dir($uploadDirFs)) { @mkdir($uploadDirFs, 0755, true); }

                    $base = preg_replace('/[^a-z0-9_-]/i', '', strtolower($iso1));
                    if ($base === '') $base = 'flag';

                    $filename = $base . '.' . $ext;

                    foreach (glob($uploadDirFs . $base . '.*') as $oldFile) { @unlink($oldFile); }

                    $destFs = $uploadDirFs . $filename;

                    if (!move_uploaded_file((string)$_FILES['flag_upload']['tmp_name'], $destFs)) { nx_alert('danger', 'alert_upload_error', false); $hasError = true; }
                    else { $flagPath = '/admin/images/flags/' . $filename; }
                }
            }
        }
    }

    if ($hasError) {
        $action = (isset($_POST['id']) && (int)$_POST['id'] > 0) ? 'edit' : 'add';
    } else {

        $data = [
            'iso_639_1'   => $iso1,
            'iso_639_2'   => trim($_POST['iso_639_2'] ?? ''),
            'name_en'     => $nameEn,
            'name_native' => trim($_POST['name_native'] ?? ''),
            'name_de'     => trim($_POST['name_de'] ?? ''),
            'flag'        => $flagPath,
            'active'      => isset($_POST['active']) ? 1 : 0,
        ];

        // UPDATE
        if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
            $id = (int)$_POST['id'];
            $success = $langManager->updateLanguage($id, $data);

            if ($success) {
                $persistLanguageFlag($_database, $id, $flagPath);
                $auditName = trim((string)($data['name_de'] ?? $data['name'] ?? $data['iso_639_1'] ?? $id));
                nx_audit_update('languages', (string)$id, true, $auditName, 'admincenter.php?site=languages&action=edit&id=' . $id);
                nx_redirect('admincenter.php?site=languages&action=edit&id=' . $id, 'success', 'alert_saved', false);
            }

            nx_alert('danger', 'alert_save_failed', false);
            $action = 'edit';
            $editid = $id;
            $editLanguage = $langManager->getLanguage($id);

        // INSERT
        } else {
            $success = $langManager->insertLanguage($data);

            if ($success) {
                $newId = (int)($_database->insert_id ?? 0);
                $auditName = trim((string)($data['name_de'] ?? $data['name'] ?? $data['iso_639_1'] ?? ($newId > 0 ? $newId : '—')));

                if ($newId > 0) {
                    $persistLanguageFlag($_database, $newId, $flagPath);
                    nx_audit_create('languages', (string)$newId, $auditName, 'admincenter.php?site=languages&action=edit&id=' . $newId);
                    nx_redirect('admincenter.php?site=languages&action=edit&id=' . $newId, 'success', 'alert_saved', false);
                }

                nx_audit_create('languages', null, $auditName, 'admincenter.php?site=languages');
                nx_redirect('admincenter.php?site=languages', 'success', 'alert_saved', false);
            }

            nx_alert('danger', 'alert_save_failed', false);
            $action = 'add';
        }
    }
}

// Alle Sprachen laden (immer für Tabelle notwendig)
$languages = $langManager->getAllLanguages();
?>

  <!-- Page Header -->
  <div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
      <div class="card-title">
        <i class="bi bi-translate"></i>
        <span><?= $languageService->get('manage_languages') ?></span>
        <small class="small-muted">
          <?php if ($action === 'add'): ?>
            <?= $languageService->get('add') ?>
          <?php elseif ($action === 'edit'): ?>
            <?= $languageService->get('edit') ?>
          <?php else: ?>
            <?= $languageService->get('settings') ?>
          <?php endif; ?>
        </small>
      </div>
    </div>
    <div class="card-body p-4">
          <?php if ($action === 'add' || $action === 'edit'): ?>
          <?php else: ?>
          <div class="row align-items-center mb-4">
            <div class="col-md-8">
              <a class="btn btn-secondary" href="admincenter.php?site=languages&action=add">
                  <?= $languageService->get('add') ?>
              </a>
            </div>
          </div>
          <?php endif; ?>

      <?php if ($action === 'add' || $action === 'edit'): ?>
		<form method="post" action="" class="form-horizontal" enctype="multipart/form-data">

          <?php if ($action === 'edit'): ?>
            <input type="hidden" name="id" value="<?php echo (int)$editid; ?>">
          <?php endif; ?>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iso_639_1" class="form-label">
                <?= $languageService->get('iso_code_1_label') ?>
              </label>
              <input
                type="text"
                class="form-control"
                id="iso_639_1"
                name="iso_639_1"
                placeholder="<?= $languageService->get('iso_code_1_example') ?>"
                maxlength="2"
                required
                value="<?= htmlspecialchars($_POST['iso_639_1'] ?? $editLanguage['iso_639_1'] ?? '') ?>"
              />
            </div>

            <div class="col-md-6 mb-3">
              <label for="iso_639_2" class="form-label">
                <?= $languageService->get('iso_code_2_label') ?>
              </label>
              <input
                type="text"
                class="form-control"
                id="iso_639_2"
                name="iso_639_2"
                placeholder="<?= $languageService->get('iso_code_2_example') ?>"
                maxlength="3"
                value="<?= htmlspecialchars($_POST['iso_639_2'] ?? $editLanguage['iso_639_2'] ?? '') ?>"
              />
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="name_en" class="form-label">
                <?= $languageService->get('english_name_label') ?>
              </label>
              <input
                type="text"
                class="form-control"
                id="name_en"
                name="name_en"
                required
                value="<?= htmlspecialchars($_POST['name_en'] ?? $editLanguage['name_en'] ?? '') ?>"
              />
            </div>

            <div class="col-md-6 mb-3">
              <label for="name_native" class="form-label">
                <?= $languageService->get('native_name_label') ?>
              </label>
              <input
                type="text"
                class="form-control"
                id="name_native"
                name="name_native"
                value="<?= htmlspecialchars($_POST['name_native'] ?? $editLanguage['name_native'] ?? '') ?>"
              />
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="name_de" class="form-label">
                <?= $languageService->get('german_name_label') ?>
              </label>
              <input
                type="text"
                class="form-control"
                id="name_de"
                name="name_de"
                value="<?= htmlspecialchars($_POST['name_de'] ?? $editLanguage['name_de'] ?? '') ?>"
              />
            </div>

			    <div class="col-md-6 mb-3">
			      <label for="flag_upload" class="form-label">
				      <?= $languageService->get('flag_path_label') ?>
			      </label>

			  <?php
				$currentFlag = $_POST['existing_flag'] ?? ($editLanguage['flag'] ?? '');
				if (!empty($currentFlag)):
			  ?>
				<div class="d-flex align-items-center gap-3 mb-2">
				  <img src="<?= htmlspecialchars($currentFlag) ?>" alt="Flag" style="max-height:32px;" />
				  <small class="text-muted"><?= htmlspecialchars($currentFlag) ?></small>
				</div>
			  <?php endif; ?>

			  <input type="hidden" name="existing_flag" value="<?= htmlspecialchars($currentFlag) ?>">
			  <input
				type="file"
				class="form-control"
				id="flag_upload"
				name="flag_upload"
				accept="image/*"
			  />
			  <small class="text-muted">
				<?= $languageService->get('flag_upload_help') ?>
			  </small>
			</div>
          </div>

          <div class="mb-4">
            <div class="form-check">
              <input
                class="form-check-input"
                type="checkbox"
                id="active"
                name="active"
                value="1"
                <?php
                  $checked = $_POST['active'] ?? ($editLanguage['active'] ?? 1);
                  echo ((int)$checked === 1) ? 'checked' : '';
                ?>
              >
              <label class="form-check-label" for="active">
                <?= $languageService->get('active_label') ?>
              </label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <?= $action === 'add'
                ? $languageService->get('save')
                : $languageService->get('save') ?>
            </button>
          </div>

      <?php else: ?>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th><?= $languageService->get('table_header_id') ?></th>
                <th><?= $languageService->get('table_header_flag') ?></th>
                <th><?= $languageService->get('table_header_iso1') ?></th>
                <th><?= $languageService->get('table_header_iso2') ?></th>
                <th><?= $languageService->get('table_header_name_en') ?></th>
                <th><?= $languageService->get('table_header_name_native') ?></th>
                <th><?= $languageService->get('table_header_name_de') ?></th>
                <th><?= $languageService->get('table_header_active') ?></th>
                <th class="text-end"><?= $languageService->get('table_header_actions') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($languages as $lang): ?>
                <tr>
                  <td><?php echo (int)$lang['id']; ?></td>
                  <td>
                    <?php if (!empty($lang['flag'])): ?>
                      <img class="rounded" src="<?php echo htmlspecialchars($lang['flag']); ?>" alt="Flagge" style="max-height:32px;">
                    <?php else: ?>
                      <span class="text-muted">–</span>
                    <?php endif; ?>
                  </td>
                    <td><?php echo htmlspecialchars($lang['iso_639_1']); ?></td>
                    <td><?php echo htmlspecialchars($lang['iso_639_2']); ?></td>
                    <td><?php echo htmlspecialchars($lang['name_en']); ?></td>
                    <td><?php echo htmlspecialchars($lang['name_native']); ?></td>
                    <td><?php echo htmlspecialchars($lang['name_de']); ?></td>
                  <td>
                    <?php if ((int)$lang['active'] === 1): ?>
                      <span class="badge bg-success"><?= $languageService->get('yes') ?></span>
                    <?php else: ?>
                      <span class="badge bg-secondary"><?= $languageService->get('no') ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex flex-wrap gap-2">
                      <a class="btn btn-primary d-inline-flex align-items-center gap-1 w-auto"
                         href="admincenter.php?site=languages&action=edit&id=<?php echo (int)$lang['id']; ?>">
                        <i class="bi bi-pencil-square"></i><?= $languageService->get('edit') ?>
                      </a>
                      <button type="button" class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto"
                              data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                              data-delete-url="admincenter.php?site=languages&action=delete&id=<?php echo (int)$lang['id']; ?>">
                        <i class="bi bi-trash3"></i><?= $languageService->get('delete') ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php if (count($languages) === 0): ?>
                <tr>
                  <td colspan="9" class="text-center"><?= $languageService->get('no_languages_found') ?></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <?php endif; ?>

    </div>
  </div>