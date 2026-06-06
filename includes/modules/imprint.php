<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $_database, $languageService, $hp_title;

$lang = $languageService->detectLanguage();

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

$data_array = [
    'class' => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Imprint'
];

echo $tpl->loadTemplate("imprint", "head", $data_array, 'theme');

// Impressum-Daten auslesen
$stmt = $_database->prepare("SELECT * FROM settings_imprint LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$imprint_data = $result->fetch_assoc();

$type_labels = [
    'private' => $languageService->get('private_option') ?? 'Privat',
    'association' => $languageService->get('association_option') ?? 'Verein',
    'small_business' => $languageService->get('small_business_option') ?? 'Kleinunternehmer',
    'company' => $languageService->get('company_option') ?? 'Unternehmen',
    'unknown' => 'Unbekannt'
];

$translate = new multiLanguage($lang);
$translate->detectLanguages($imprint_data['disclaimer']);

$type = $imprint_data['type'] ?? 'unknown';

// Dynamisches Name-Label vorab definieren
if ($type === 'association') {
    $name_label = $languageService->get('association_label') ?? 'Vereinsname';
} elseif ($type === 'company' || $type === 'small_business') {
    $name_label = $languageService->get('company_name_label') ?? 'Firmenname';
} else {
    $name_label = $languageService->get('name_label') ?? 'Name';
}

$core_version_file = __DIR__ . '/system/version.php';

if (!file_exists($core_version_file)) {
    // Alternative: absolute Pfadangabe vom Projektstamm
    $core_version_file = $_SERVER['DOCUMENT_ROOT'] . '/system/version.php';
}

$core_version = file_exists($core_version_file) ? include $core_version_file : null;
$core_version_text = $core_version ?? 'Bitte Core-Version bei Supportanfragen angeben';

// Language-Text mit Platzhalter laden und ersetzen
$imprint_info_template = $languageService->get('imprint_info') ?? '';
$imprint_info_filled = str_replace('{core_version}', $core_version_text, $imprint_info_template);

$data_array = [
    'impressum_type_label' => $languageService->get('impressum_type_label') ?? 'Typ',
    'represented_by_label' => $languageService->get('represented_by_company_label') ?? $languageService->get('represented_by_label') ?? 'Vertreten durch',
    'tax_id_label' => $languageService->get('tax_id_company_label') ?? $languageService->get('tax_id_label') ?? 'Steuernummer',
    'vat_id_label' => $languageService->get('vat_id_label') ?? 'USt-ID',
    'register_office_label' => $languageService->get('register_office_label') ?? 'Registergericht',
    'register_number_label' => $languageService->get('register_number_label') ?? 'Handelsregister-Nr.',
    'supervisory_authority_label' => $languageService->get('supervisory_authority_label') ?? 'Aufsichtsbehörde',
    'address_label' => $languageService->get('address_label') ?? 'Adresse',
    'postal_code_label' => $languageService->get('postal_code_label') ?? 'PLZ',
    'city_label' => $languageService->get('city_label') ?? 'Ort',
    'email_label' => $languageService->get('email_label') ?? 'E-Mail',
    'website_label' => $languageService->get('website_label') ?? 'Webseite',
    'phone_label' => $languageService->get('phone_label') ?? 'Telefon',
    'disclaimer_label' => $languageService->get('disclaimer_label') ?? 'Haftungsausschluss',
    'association_label' => $languageService->get('association_label') ?? 'Vereinsname',

    // Hier kommt der **fertig ersetzte** Text
    'imprint_info' => $imprint_info_filled,

    'name_label' => $name_label,

    // Werte
    'impressum_hp_name' => $hp_title,
    'impressum_type' => $type_labels[$type] ?? $type_labels['unknown'],
    'company_name' => $imprint_data['company_name'] ?? '',
    'represented_by' => $imprint_data['represented_by'] ?? '',
    'tax_id' => $imprint_data['tax_id'] ?? '',
    'vat_id' => $imprint_data['vat_id'] ?? '',
    'register_office' => $imprint_data['register_office'] ?? '',
    'register_number' => $imprint_data['register_number'] ?? '',
    'supervisory_authority' => $imprint_data['supervisory_authority'] ?? '',
    'address' => $imprint_data['address'] ?? '',
    'postal_code' => $imprint_data['postal_code'] ?? '',
    'city' => $imprint_data['city'] ?? '',
    'email' => $imprint_data['email'] ?? '',
    'website' => $imprint_data['website'] ?? '',
    'phone' => $imprint_data['phone'] ?? '',

    // Core-Version nur den Wert
    'core_version' => $core_version_text,
    'disclaimer' => $translate->getTextByLanguage($imprint_data['disclaimer'] ?? '')
];

// Template rendern
echo $tpl->loadTemplate("imprint", "content", $data_array, 'theme');
