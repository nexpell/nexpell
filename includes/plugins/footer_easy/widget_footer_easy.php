<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $languageService, $myclanname, $since;

$lang = $languageService->detectLanguage();
$languageService->readModule('footer_easy');


$tpl = Template::getInstance();

// Links aus DB holen
$res = safe_query("SELECT * FROM plugins_footer_easy ORDER BY link_number");
$data = [];
$translate = new multiLanguage($lang);

while ($r = mysqli_fetch_assoc($res)) {
    $translate->detectLanguages($r['copyright_link_name']);
    $name = $translate->getTextByLanguage($r['copyright_link_name']);

    $num = $r['link_number'];
    $url = htmlspecialchars($r['copyright_link']);
    $txt = htmlspecialchars($name);
    $tgt = $r['new_tab'] ? ' target="_blank"' : '';
    $data["copyright_link{$num}"] = $url
    ? '<a class="foot_link me-3" href="' . 
      htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url)) . 
      "\" {$tgt} rel=\"nofollow\">{$txt}</a>"
    : '';
}

// Template-Daten
$data_array = array_merge([
  'myclanname' => $myclanname,
  'date' => date("Y"),
  'since' => $since,
], $data);

// ausgeben
echo $tpl->loadTemplate("footer_easy", "content", $data_array, 'plugin');

#define('DEBUG', true);
$debugFile = __DIR__ . '/../../../system/performance_d1ebug.php';
if (file_exists($debugFile)) {
    #echo "<pre>DEBUG FILE FOUND: $debugFile</pre>";
    include $debugFile;
} else {
    #echo "<pre>DEBUG FILE NOT FOUND: $debugFile</pre>";
}