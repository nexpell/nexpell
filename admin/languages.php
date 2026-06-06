<?php

use nexpell\LanguageManager;
use nexpell\LanguageService;
use nexpell\AccessControl;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
session_start();
}

// Standardsprache setzen
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $_database, $languageService;
$languageService = new LanguageService($_database);
$languageService->readModule('languages', true);

// Adminrechte prüfen
AccessControl::checkAdminAccess('ac_languages');

// Manager initialisieren
$langManager = new LanguageManager($_database);

// Initialwerte
$action = $_GET['action'] ?? '';
$editid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editLanguage = null;
$message = '';
$messageClass = '';


if ($action === 'delete' && $editid > 0) {
$lang = $langManager->getLanguage($editid);
if ($lang) {
$langManager->deleteLanguage($editid);
$message = $languageService->get('language_deleted_success');
$messageClass = 'alert-success';
} else {
$message = $languageService->get('language_not_found');
$messageClass = 'alert-danger';
}
$action = '';
}

// Sprache zur Bearbeitung laden
if ($action === 'edit' && $editid > 0) {
$editLanguage = $langManager->getLanguage($editid);
if (!$editLanguage) {
$message = $languageService->get('language_not_found');
$messageClass = 'alert-danger';
$action = '';
$editid = 0;
}
}

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$iso1 = trim($_POST['iso_639_1'] ?? '');
$nameEn = trim($_POST['name_en'] ?? '');

if (strlen($iso1) !== 2) {
$message = $languageService->get('iso_code_length_error');
$messageClass = 'alert-danger';
} elseif ($nameEn === '') {
$message = $languageService->get('english_name_required');
$messageClass = 'alert-danger';
} else {
$data = [
'iso_639_1' => $iso1,
'iso_639_2' => trim($_POST['iso_639_2'] ?? ''),
'name_en' => $nameEn,
'name_native' => trim($_POST['name_native'] ?? ''),
'name_de' => trim($_POST['name_de'] ?? ''),
'flag'=> trim($_POST['flag'] ?? ''),
'active'=> isset($_POST['active']) ? 1 : 0,
];

if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
$success = $langManager->updateLanguage((int)$_POST['id'], $data);
if ($success) {
$message = $languageService->get('language_updated_success');
$messageClass = 'alert-success';
$editLanguage = $langManager->getLanguage((int)$_POST['id']);
$action = 'edit';
$editid = (int)$_POST['id'];
} else {
$message = $languageService->get('language_updated_error');
$messageClass = 'alert-danger';
}
} else {
$success = $langManager->insertLanguage($data);
if ($success) {
header("Location: admincenter.php?site=languages&success=add");
exit;
} else {
$message = $languageService->get('language_add_error');
$messageClass = 'alert-danger';
$action = 'add';
}
}
}
}

// Alle Sprachen laden (immer für Tabelle notwendig)
$languages = $langManager->getAllLanguages();

?>

<?php if ($message): ?>
<div class="alert <?php echo $messageClass; ?>" role="alert">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="card">
<div class="card-header">
<i class="bi bi-paragraph"></i> <?= $languageService->get('manage_languages') ?>
</div>

<nav aria-label="breadcrumb">
<ol class="breadcrumb t-5 p-2 bg-light">
<li class="breadcrumb-item active" aria-current="page"><?= $languageService->get('manage_languages') ?></li>
</ol>
</nav>

<div class="card-body">
<div class="container py-5">
<h2 class="mb-4"><?= $languageService->get('manage_languages') ?></h2>

<?php if ($action === 'add' || $action === 'edit'): ?>
<h2><?php echo $action === 'add' ? $languageService->get('add_new_language') : $languageService->get('edit_language'); ?></h2>

<form method="post" action="" class="mb-5">
<?php if ($action === 'edit'): ?>
<input type="hidden" name="id" value="<?php echo (int)$editid; ?>">
<?php endif; ?>

<div class="mb-3">
<label for="iso_639_1" class="form-label"><?= $languageService->get('iso_code_1_label') ?></label>
<input type="text" class="form-control" id="iso_639_1" name="iso_639_1" maxlength="2" required
 value="<?php echo htmlspecialchars($_POST['iso_639_1'] ?? $editLanguage['iso_639_1'] ?? ''); ?>" />
</div>

<div class="mb-3">
<label for="iso_639_2" class="form-label"><?= $languageService->get('iso_code_2_label') ?></label>
<input type="text" class="form-control" id="iso_639_2" name="iso_639_2" maxlength="3"
 value="<?php echo htmlspecialchars($_POST['iso_639_2'] ?? $editLanguage['iso_639_2'] ?? ''); ?>" />
</div>

<div class="mb-3">
<label for="name_en" class="form-label"><?= $languageService->get('english_name_label') ?></label>
<input type="text" class="form-control" id="name_en" name="name_en" required
 value="<?php echo htmlspecialchars($_POST['name_en'] ?? $editLanguage['name_en'] ?? ''); ?>" />
</div>

<div class="mb-3">
<label for="name_native" class="form-label"><?= $languageService->get('native_name_label') ?></label>
<input type="text" class="form-control" id="name_native" name="name_native"
 value="<?php echo htmlspecialchars($_POST['name_native'] ?? $editLanguage['name_native'] ?? ''); ?>" />
</div>

<div class="mb-3">
<label for="name_de" class="form-label"><?= $languageService->get('german_name_label') ?></label>
<input type="text" class="form-control" id="name_de" name="name_de"
 value="<?php echo htmlspecialchars($_POST['name_de'] ?? $editLanguage['name_de'] ?? ''); ?>" />
</div>

<div class="mb-3">
<label for="flag" class="form-label"><?= $languageService->get('flag_path_label') ?></label>
<input type="text" class="form-control" id="flag" name="flag" placeholder="<?= $languageService->get('flag_path_placeholder') ?>"
 value="<?php echo htmlspecialchars($_POST['flag'] ?? $editLanguage['flag'] ?? ''); ?>" />
</div>

<div class="form-check mb-3">
<input type="checkbox" class="form-check-input" id="active" name="active"
 <?php
 $checked = ($_SERVER['REQUEST_METHOD'] === 'POST') ? isset($_POST['active']) : ($editLanguage['active'] ?? 1);
 echo $checked ? 'checked' : '';
 ?> />
<label class="form-check-label" for="active"><?= $languageService->get('active_label') ?></label>
</div>

<button type="submit" class="btn btn-primary">
<?php echo $action === 'edit' ? $languageService->get('save_language_button') : $languageService->get('add_language_button'); ?>
</button>
<a href="admincenter.php?site=languages" class="btn btn-secondary"><?= $languageService->get('back_to_overview_button') ?></a>
</form>

<?php else: ?>

<a href="admincenter.php?site=languages&action=add" class="btn btn-success mb-3"><?= $languageService->get('add_new_language_button') ?></a>

<table class="table table-bordered table-striped align-middle">
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
<th><?= $languageService->get('table_header_actions') ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($languages as $lang): ?>
<tr>
<td><?php echo (int)$lang['id']; ?></td>
<td>
<?php if (!empty($lang['flag'])): ?>
<img src="<?php echo htmlspecialchars($lang['flag']); ?>" alt="Flagge" style="max-height:24px;">
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
<td>
<a href="admincenter.php?site=languages&action=edit&id=<?php echo (int)$lang['id']; ?>" class="btn btn-primary"><?= $languageService->get('edit_button') ?></a>
<a href="admincenter.php?site=languages&action=delete&id=<?php echo (int)$lang['id']; ?>" class="btn btn-danger ms-1" onclick="return confirm('<?= $languageService->get('confirm_delete') ?>');"><?= $languageService->get('delete_button') ?></a>
</td>
</tr>
<?php endforeach; ?>
<?php if (count($languages) === 0): ?>
<tr><td colspan="9" class="text-center"><?= $languageService->get('no_languages_found') ?></td></tr>
<?php endif; ?>
</tbody>
</table>

<?php endif; ?>
</div>
</div>
</div>