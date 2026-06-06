<!-- Footer Consent -->
<?= $pluginManager->getFooterModule(); ?>
</div>


<!-- Cookie Consent -->
<div id="cookie-overlay" style="display:none;"></div>
<?php require_once BASE_PATH . '/components/cookie/cookie-consent.php'; ?>

<?php
    echo $components_js ?? '';
    echo $theme_js ?? '';
    echo '<!--Plugin & Widget js-->' . PHP_EOL;
    echo $plugin_js ?? '';
    echo '<!--Plugin & Widget js END-->' . PHP_EOL;
?>

<?php if (!empty($_SESSION['userID'])): ?>
<script>
window.nexpellPresence = {
    enabled: true,
    endpoint: "/system/user_presence.php",
    heartbeatMs: 60000
};
</script>
<script src="/components/js/user_presence.js"></script>
<?php endif; ?>

<!-- ... dein HTML-Header etc. ... -->

<script defer src="/components/js/nx_editor.js"></script>

<!-- reCAPTCHA Loader -->
<script src="https://www.google.com/recaptcha/api.js?hl=de" async defer></script>


<?php
if (defined('DEBUG_PERFORMANCE') && DEBUG_PERFORMANCE) {
    $userId = $_SESSION['userID'] ?? null; // Session-Variable prüfen

    if ($userId) {
        // mysqli Prepared Statement
        $stmt = $_database->prepare("
            SELECT 1
            FROM user_role_assignments ura
            JOIN user_roles ur ON ura.roleID = ur.roleID
            WHERE ura.userID = ? AND ur.role_name = 'admin'
            LIMIT 1
        ");

        // Parameter binden
        $stmt->bind_param('i', $userId);

        // Ausführen
        $stmt->execute();

        // Ergebnis holen
        $stmt->store_result();
        $isAdmin = $stmt->num_rows > 0;

        if ($isAdmin) {
            include BASE_PATH . '/system/performance_debug.php';
        }

        $stmt->close();
    }
}
?>



</body>
</html>
