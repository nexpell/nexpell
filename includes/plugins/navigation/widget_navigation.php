<?php

GLOBAL $_database, $logo,$theme_name,$themes,$tpl,$loggedin,$index_language,$modRewrite,$action,$modulname;

use nexpell\LanguageService;

$_language = new LanguageService($_database);
$_language->readModule('navigation');

$qs_arr = array();
parse_str($_SERVER['QUERY_STRING'], $qs_arr);
        
$getsite = 'startpage'; #Wird auf der Startseite angezeigt index.php
if(isset($qs_arr['site'])) {
  $getsite = $qs_arr['site'];
}

$ergebnis=safe_query("SELECT * FROM settings_themes WHERE active = '1'");
$ds=mysqli_fetch_array($ergebnis);

echo'<nav id="mainNavbar" class="sticky-top navbar navbar-expand-lg ' . $ds['navbar_class'] . '" data-bs-theme="' . $ds['navbar_theme'] . '">
    <div class="container">
        <!-- Klickbares Logo -->
    <a href="#" class="logo-link">
      <img src="../includes/themes/' . htmlspecialchars($theme_name, ENT_QUOTES, 'UTF-8') . '/images/' . $ds['logo_pic'] . '" 
           alt="Logo">
    </a>  
        <a class="navbar-brand invisible" href="#">Logo</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
          aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Navigation umschalten">
          <span class="navbar-toggler-icon"></span>
        </button>    
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav ms-auto">';
                require_once("./includes/modules/navigation.php");
                require_once("./includes/modules/language.php");                      
            echo'</ul>        
        </div>
  </div>
</nav>';


// Messenger Plugin prüfen
$messenger_active = false;
$result = safe_query("SELECT 1 FROM settings_plugins WHERE modulname='messenger' AND activate=1");
if (mysqli_num_rows($result)) {
    $messenger_active = true;
}
?>
<script>
const messengerActive = <?= $messenger_active ? 'true' : 'false' ?>;
</script>