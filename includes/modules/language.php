<?php
$currentPath = $_SERVER['REQUEST_URI'];
$currentLang = $_SESSION['language'] ?? 'de';

// Sprachen aus DB laden
$langData = [];
$availableLangs = []; // <--- HIER initialisieren

$result = $_database->query("SELECT iso_639_1, name_native, name_en, flag 
                             FROM settings_languages 
                             WHERE active = 1 
                             ORDER BY name_en ASC");
while ($row = $result->fetch_assoc()) {
    $iso = $row['iso_639_1'];
    $availableLangs[] = $iso; // <--- Sammeln
    
    $langData[$iso] = [
        'flag' => $row['flag'] ?: "/admin/images/flags/{$iso}.png",
        'name' => $row['name_native'] ?: ($row['name_en'] ?: ucfirst($iso))
    ];
}


// --- Funktion: URL für Sprachlink erstellen ---
function buildLangUrl(string $iso, string $currentPath, array $availableLangs): string {
    $parsed = parse_url($currentPath);
    $segments = explode('/', trim($parsed['path'], '/'));

    if (in_array($segments[0], $availableLangs)) {
        $segments[0] = $iso;
    } else {
        array_unshift($segments, $iso);
    }

    $newPath = '/' . implode('/', $segments);
    parse_str($parsed['query'] ?? '', $query);
    unset($query['new_lang']);
    if (!empty($query)) $newPath .= '?' . http_build_query($query);

    return $newPath;
}

// --- Sprachlinks bauen ---
$language_links = '';
$flag_ok = '';
$lang_ok = '';

foreach ($langData as $iso => $data) {
    $url = buildLangUrl($iso, $currentPath, $availableLangs);

    if ($iso === $currentLang) {
        $lang_ok = '<a class="dropdown-item active" href="' . htmlspecialchars($url) . '">'
            . '<span class="flag" style="background-image: url(' . htmlspecialchars($data['flag']) . ')"></span> '
            . htmlspecialchars($data['name']) . '</a>';
        $flag_ok = '<span class="flag" style="background-image: url(' . htmlspecialchars($data['flag']) . ')"></span>';
    } else {
        $language_links .= '<a class="dropdown-item" href="' . htmlspecialchars($url) . '">'
            . '<span class="flag" style="background-image: url(' . htmlspecialchars($data['flag']) . ')"></span> '
            . htmlspecialchars($data['name']) . '</a>';
    }
}

// Template Daten
$data_array = [
    'flag_ok' => $flag_ok,
    'languages_ok' => $lang_ok,
    'languages' => $language_links,
];

echo $tpl->loadTemplate("navigation", "languages", $data_array, 'theme');
