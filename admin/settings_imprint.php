<?php
use nexpell\LanguageService;
use nexpell\AccessControl;

if (session_status() === PHP_SESSION_NONE) session_start();

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('ac_imprint');

$CAPCLASS = new \nexpell\Captcha;
$errors = [];
$success = false;

// 1. Sprachen laden
$languages = [];
$res = mysqli_query($_database, "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $languages[$row['iso_639_1']] = $row['name_de'];
}

// 2. Aktive Sprache bestimmen
$currentLang = null;

// 2️⃣ Aktive Sprache bestimmen

if (!empty($_SESSION['imprint_active_lang'])) {
    $currentLang = $_SESSION['imprint_active_lang'];
    unset($_SESSION['imprint_active_lang']);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['active_lang'])) {
    $currentLang = $_POST['active_lang'];
}
elseif (!empty($_SESSION['language'])) {
    $currentLang = $_SESSION['language'];
}
else {
    $currentLang = $languageService->detectLanguage();
}

// 4️⃣ Sicherheit: nur erlaubte Sprachen
if (!isset($languages[$currentLang])) {
    $currentLang = array_key_first($languages); // meist 'de'
}

// 2. Stammdaten aus DB holen
$res = $_database->query("SELECT * FROM settings_imprint LIMIT 1");
$data = ($res && $row = $res->fetch_assoc()) ? $row : [];

// 3. Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_redirect('admincenter.php?site=settings_imprint','danger','alert_transaction_invalid',false);
    }

    // Alle Felder einlesen
    $type    = trim($_POST['type'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $register_office = trim($_POST['register_office'] ?? '');
    $register_number = trim($_POST['register_number'] ?? '');
    $vat_id  = trim($_POST['vat_id'] ?? '');
    $supervisory_authority = trim($_POST['supervisory_authority'] ?? '');

    $company_name = '';
    $represented_by = '';
    $tax_id = '';

    // Logik für Typen (Wiederhergestellt)
    if ($type === 'private') {
        $company_name = trim($_POST['company_name_private'] ?? '');
    } elseif ($type === 'association') {
        $company_name   = trim($_POST['company_name_association'] ?? '');
        $represented_by = trim($_POST['represented_by_association'] ?? '');
    } elseif ($type === 'small_business') {
        $company_name = trim($_POST['company_name_small_business'] ?? '');
        $tax_id       = trim($_POST['tax_id_small_business'] ?? '');
    } elseif ($type === 'company') {
        $company_name   = trim($_POST['company_name_company'] ?? '');
        $represented_by = trim($_POST['represented_by_company'] ?? '');
        $tax_id         = trim($_POST['tax_id_company'] ?? '');
    }

    // Validierung (Wiederhergestellt)
    if (empty($type)) $errors[] = $languageService->get('give_type');
    if (in_array($type, ['private','association','small_business','company']) && empty($company_name)) {
        $errors[] = $languageService->get('need_name');
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $languageService->get('need_email');
    }
    if (empty($address))     $errors[] = $languageService->get('need_address');
    if (empty($postal_code)) $errors[] = $languageService->get('need_plz');
    if (empty($city))        $errors[] = $languageService->get('need_location');

    if (empty($errors)) {
        // Escapen für SQL
        $type_e = $_database->real_escape_string($type);
        $company_name_e = $_database->real_escape_string($company_name);
        $represented_by_e = $_database->real_escape_string($represented_by);
        $tax_id_e = $_database->real_escape_string($tax_id);
        $email_e = $_database->real_escape_string($email);
        $website_e = $_database->real_escape_string($website);
        $phone_e = $_database->real_escape_string($phone);
        $address_e = $_database->real_escape_string($address);
        $postal_code_e = $_database->real_escape_string($postal_code);
        $city_e = $_database->real_escape_string($city);
        $register_office_e = $_database->real_escape_string($register_office);
        $register_number_e = $_database->real_escape_string($register_number);
        $vat_id_e = $_database->real_escape_string($vat_id);
        $supervisory_authority_e = $_database->real_escape_string($supervisory_authority);

        // Update settings_imprint
        $_database->query("UPDATE settings_imprint SET 
            type='$type_e', company_name='$company_name_e', represented_by='$represented_by_e', 
            tax_id='$tax_id_e', email='$email_e', website='$website_e', phone='$phone_e', 
            address='$address_e', postal_code='$postal_code_e', city='$city_e', 
            register_office='$register_office_e', register_number='$register_number_e', 
            vat_id='$vat_id_e', supervisory_authority='$supervisory_authority_e' LIMIT 1");

        // MEHRSPRACHIGER CONTENT (settings_content_lang)
        $activeLang = $_POST['active_lang'] ?? $currentLang;

        if (
            isset($_POST['content'][$activeLang]) &&
            is_string($_POST['content'][$activeLang])
        ) {
            $lang_e = $_database->real_escape_string($activeLang);
            $html_e = $_database->real_escape_string(trim($_POST['content'][$activeLang]));

            $_database->query("
                INSERT INTO settings_content_lang (content_key, language, content, updated_at)
                VALUES ('imprint', '$lang_e', '$html_e', NOW())
                ON DUPLICATE KEY UPDATE
                    content = VALUES(content),
                    updated_at = NOW()
            ");
        }


        $activeLang = $_POST['active_lang'] ?? $currentLang;
        $_SESSION['imprint_active_lang'] = $activeLang;

        nx_audit_update(
            'settings_imprint',
            null,
            true,
            null,
            'admincenter.php?site=settings_imprint'
        );

        nx_redirect(
            'admincenter.php?site=settings_imprint&active_lang=' . $activeLang,
            'success',
            'alert_saved',
            false
        );
    } else {
        foreach ($errors as $e) nx_alert('danger', (string)$e, false, true, true);
    }
}

// 4. Content aus neuer Tabelle laden
$content    = [];
$lastUpdate = [];

$res_lang = $_database->query("
    SELECT language, content, updated_at
    FROM settings_content_lang
    WHERE content_key = 'imprint'
");

while ($row = $res_lang->fetch_assoc()) {
    $lang = $row['language'];
    $content[$lang]    = $row['content'];
    $lastUpdate[$lang] = $row['updated_at'];
}

$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<script>
    const lastUpdateByLang = <?= json_encode($lastUpdate, JSON_UNESCAPED_UNICODE) ?>;
</script>
<form method="post" id="privacy_policyForm">
<div class="nx-lang-editor">
    <input type="hidden" name="captcha_hash" value="<?= h($hash) ?>">
    <input type="hidden" name="active_lang" id="active_lang" value="<?= h($currentLang) ?>">

    <div class="row g-4">
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm border-0 mb-4 mt-3">
                <div class="card-header">
                    <div class="card-title mb-0"><i class="bi bi-paragraph"></i> <?= $languageService->get('imprint') ?></div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="type" class="form-label"><?= $languageService->get('type_label') ?></label>
                        <select name="type" id="type" class="form-select" required>
                            <option value="">- <?= $languageService->get('select_type') ?> -</option>
                            <option value="private" <?= ($data['type'] ?? '') === 'private' ? 'selected' : '' ?>><?= $languageService->get('private_person') ?></option>
                            <option value="association" <?= ($data['type'] ?? '') === 'association' ? 'selected' : '' ?>><?= $languageService->get('association') ?></option>
                            <option value="small_business" <?= ($data['type'] ?? '') === 'small_business' ? 'selected' : '' ?>><?= $languageService->get('small_business_owner') ?></option>
                            <option value="company" <?= ($data['type'] ?? '') === 'company' ? 'selected' : '' ?>><?= $languageService->get('company') ?></option>
                        </select>
                    </div>

                    <div id="private_fields" class="type_block mb-3" style="display:none;">
                        <label class="form-label"><?= $languageService->get('name_private') ?></label>
                        <input type="text" name="company_name_private" value="<?= h($data['company_name'] ?? '') ?>" class="form-control">
                    </div>
                    
                    <div id="association_fields" class="type_block mb-3" style="display:none;">
                        <label class="form-label"><?= $languageService->get('name_association') ?></label>
                        <input type="text" name="company_name_association" value="<?= h($data['company_name'] ?? '') ?>" class="form-control mb-2">
                        <label class="form-label"><?= $languageService->get('represented_by_association') ?></label>
                        <input type="text" name="represented_by_association" value="<?= h($data['represented_by'] ?? '') ?>" class="form-control">
                    </div>

                    <div id="small_business_fields" class="type_block mb-3" style="display:none;">
                        <label class="form-label"><?= $languageService->get('name_small_business') ?></label>
                        <input type="text" name="company_name_small_business" value="<?= h($data['company_name'] ?? '') ?>" class="form-control mb-2">
                        <label class="form-label"><?= $languageService->get('tax_id_small_business') ?></label>
                        <input type="text" name="tax_id_small_business" value="<?= h($data['tax_id'] ?? '') ?>" class="form-control">
                    </div>

                    <div id="company_fields" class="type_block mb-3" style="display:none;">
                        <label class="form-label"><?= $languageService->get('name_company') ?></label>
                        <input type="text" name="company_name_company" value="<?= h($data['company_name'] ?? '') ?>" class="form-control mb-2">
                        <label class="form-label"><?= $languageService->get('represented_by_company') ?></label>
                        <input type="text" name="represented_by_company" value="<?= h($data['represented_by'] ?? '') ?>" class="form-control mb-2">
                        <label class="form-label"><?= $languageService->get('tax_id_company') ?></label>
                        <input type="text" name="tax_id_company" value="<?= h($data['tax_id'] ?? '') ?>" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= $languageService->get('email') ?></label>
                        <input type="email" class="form-control" name="email" value="<?= h($data['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $languageService->get('address') ?></label>
                        <input type="text" class="form-control" name="address" value="<?= h($data['address'] ?? '') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><?= $languageService->get('postal_code') ?> / <?= $languageService->get('city') ?></label>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control w-25" name="postal_code" value="<?= h($data['postal_code'] ?? '') ?>" required>
                            <input type="text" class="form-control w-75" name="city" value="<?= h($data['city'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div id="register_fields" style="display:none;">
                        <div class="mb-2">
                            <label class="form-label"><?= $languageService->get('register_office') ?></label>
                            <input type="text" class="form-control" name="register_office" value="<?= h($data['register_office'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label"><?= $languageService->get('register_number') ?></label>
                            <input type="text" class="form-control" name="register_number" value="<?= h($data['register_number'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label"><?= $languageService->get('vat_id') ?></label>
                            <input type="text" class="form-control" name="vat_id" value="<?= h($data['vat_id'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-9">
            <div class="card shadow-sm border-0 mb-4 mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="card-title mb-0">
                        <i class="bi bi-shield-exclamation"></i>
                        <?= $languageService->get('disclaimer') ?>
                    </div>

                    <div class="d-flex align-items-center gap-3 ms-auto">

                        <!-- LANGUAGE SWITCH -->
                        <div class="btn-group" id="lang-switch">
                            <?php foreach ($languages as $iso => $label): ?>
                                <button type="button"
                                        class="btn <?= $iso === $currentLang ? 'btn-primary' : 'btn-secondary' ?>"
                                        data-lang="<?= $iso ?>">
                                    <?= strtoupper($iso) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <!-- LAST UPDATE -->
                        <div class="text-end ps-3">
                            <div class="text-muted small" id="last-update-box">
                                <?php if (!empty($lastUpdate[$currentLang])): ?>
                                    <i class="bi bi-clock-history me-1"></i>
                                    <span id="last-update-text">
                                        <?= date('d.m.Y H:i', strtotime($lastUpdate[$iso])) ?>
                                    </span>
                                <?php else: ?>
                                    <span id="last-update-text">–</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <textarea
                        id="nx-editor-main"
                        class="form-control"
                        data-editor="nx_editor"
                        rows="20"><?= htmlspecialchars($content[$currentLang] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    
                    <?php foreach ($languages as $iso => $label): ?>
                        <input type="hidden" name="content[<?= $iso ?>]" id="content_<?= $iso ?>" value="<?= h($content[$iso] ?? '') ?>">
                    <?php endforeach; ?>

                    <div class="mt-3">
                        <button type="submit" name="submit" class="btn btn-primary"><?= $languageService->get('save') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<script>
function toggleFields(type) {
    document.querySelectorAll('.type_block').forEach(b => b.style.display = 'none');
    const regFields = document.getElementById('register_fields');
    regFields.style.display = (type === 'small_business' || type === 'company') ? 'block' : 'none';
    if(type) {
        const target = document.getElementById(type + '_fields');
        if(target) target.style.display = 'block';
    }
}


</script>