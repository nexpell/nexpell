<?php
$name = isset($_GET['modulname']) ? $_GET['modulname'] : '';
// Safety: wenn kein Modulname kommt, abbrechen
if ($name === '') {
  return;
}
// ----------------------------------------------------------------------------
// System-Einträge entfernen
// ----------------------------------------------------------------------------
DeleteData("settings_plugins", "modulname", $name);
DeleteData("navigation_dashboard_links", "modulname", $name);
DeleteData("navigation_website_sub", "modulname", $name);
DeleteData("settings_module", "modulname", $name);
DeleteData("settings_widgets", "modulname", $name);
// ----------------------------------------------------------------------------
// Tabellen entfernen
// ----------------------------------------------------------------------------
safe_query("DROP TABLE IF EXISTS `plugins_footer`");
$sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
if ($sanitized !== '') {
  safe_query("DROP TABLE IF EXISTS `plugins_" . $sanitized . "`");
}
?>