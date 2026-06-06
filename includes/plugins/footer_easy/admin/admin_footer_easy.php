<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

$_SESSION['language'] = $_SESSION['language'] ?? 'de';

global $languageService;
$languageService = new LanguageService($_database);
$languageService->readPluginModule('footer_easy');


use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('footer_easy');

#$pm = new plugin_manager();
#$plugin_language = $pm->plugin_language("footer_easy", $plugin_path);
$tpl = new Template();

// speichern
if (isset($_POST['save'])) {

    // alle alten Daten löschen und neu einfügen
    safe_query("DELETE FROM plugins_footer_easy");

    for ($i = 1; $i <= 5; $i++) {
        $link  = htmlspecialchars($_POST["copyright_link$i"], ENT_QUOTES);
        $name  = htmlspecialchars($_POST["copyright_link_name$i"], ENT_QUOTES);
        $newtab = isset($_POST["new_tab$i"]) ? 1 : 0;

        safe_query("
          INSERT INTO plugins_footer_easy
            (link_number, copyright_link, copyright_link_name, new_tab)
          VALUES
            ($i, '$link', '$name', $newtab)
        ");
    }

    echo '<div class="alert alert-success">'
         . $languageService->get('success') .
         '</div>';
    redirect("admincenter.php?site=admin_footer_easy", "", 1);
    exit;
}

// Daten aus DB laden
$result = safe_query("SELECT * FROM plugins_footer_easy ORDER BY link_number");
$footer_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $footer_data[$row['link_number']] = $row;
}

// Ausgabe
echo '<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> ' . $languageService->get('title') . '
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_footer_easy">' . $languageService->get('title') . '</a></li>
            <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('breadcrumb_edit') . '</li>
        </ol>
    </nav>  

    <div class="card-body p-0">

        <div class="container py-5">

        <form method="post">
        <table class="table table-bordered table-striped bg-white shadow-sm">
            <thead class="table-light">
          <tr>
            <th>'.$languageService->get('name').'</th>
            <th>'.$languageService->get('link').'</th>            
            <th>'.$languageService->get('new_tab').'</th>
          </tr>
        </thead>
        <tbody>';

for ($i = 1; $i <= 5; $i++) {
    $row = $footer_data[$i] ?? ['copyright_link'=>'','copyright_link_name'=>'','new_tab'=>1];
    $linkVal = htmlspecialchars($row['copyright_link']);
    $nameVal = htmlspecialchars($row['copyright_link_name']);
    $chk     = $row['new_tab'] ? 'checked' : '';

    echo "<tr>      
      <td><input name=\"copyright_link_name{$i}\" type=\"text\" class=\"form-control\" value=\"{$nameVal}\"></td>
      <td><input name=\"copyright_link{$i}\" type=\"text\" class=\"form-control\" value=\"{$linkVal}\"></td>
      <td class=\"text-center\">
        <input name=\"new_tab{$i}\" type=\"checkbox\" {$chk}>
      </td>
    </tr>";
}

echo '    </tbody>
      </table>
      <button name="save" class="btn btn-primary btn-sm">'
        . $languageService->get('save') .
      '</button>
    </form>
  </div>
</div></div>';
?>