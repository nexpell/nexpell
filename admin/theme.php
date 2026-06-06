<?php

// Überprüfen, ob die Session bereits gestartet wurde
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;

// Admin-Zugriff überprüfen
AccessControl::checkAdminAccess('ac_theme');

?>
<iframe src="theme_preview.php?v=<?php echo time(); ?>" width="100%" height="2200" style="border: 1px solid #ccc;"></iframe>

