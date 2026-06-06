<?php

// Überprüfen, ob die Session bereits gestartet wurde
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;

// Admin-Zugriff überprüfen
AccessControl::checkAdminAccess('ac_theme');

?>
<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-palette"></i>
            <span><?= $languageService->get('title') ?></span>
            <small class="small-muted"><?= $languageService->get('preview') ?></small>
        </div>
    </div>
    <div class="card-body">
        <iframe src="theme_preview.php?v=<?php echo time(); ?>" width="100%" height="2200" scrolling="no"></iframe>
    </div>
</div>