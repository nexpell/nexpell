<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_footer_easy (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  link_number tinyint(1) NOT NULL COMMENT '1–5',
  copyright_link_name varchar(255) NOT NULL DEFAULT '',
  copyright_link varchar(255) NOT NULL DEFAULT '',
  new_tab tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY link_number (link_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

safe_query("INSERT IGNORE INTO plugins_footer_easy (id, link_number, copyright_link_name, copyright_link, new_tab) VALUES
(1, 1, '[[lang:de]]Impressum[[lang:en]]Imprint[[lang:it]]Impronta Editoriale', 'index.php?site=imprint', 0),
(2, 2, '[[lang:de]]Datenschutz-Bestimmungen[[lang:en]]Privacy Policy[[lang:it]]Informativa sulla Privacy', 'index.php?site=privacy_policy', 0),
(3, 3, '[[lang:de]]Kontakt[[lang:en]]Contact[[lang:it]]Contatti', 'index.php?site=contact', 0),
(4, 4, '[[lang:de]]Counter[[lang:en]]Counter[[lang:it]]Counter', 'index.php?site=counter', 0),
(5, 5, '', '', 0)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('Footer Easy', 'footer_easy', '[[lang:de]]Mit diesem Plugin könnt ihr einen neuen Footer Easy anzeigen lassen.[[lang:en]]With this plugin you can have a new Footer Easy displayed.[[lang:it]]Con questo plugin puoi visualizzare un nuovo piè di pagina.', 'admin_footer_easy', 1, 'T-Seven', 'https://webspell-rm.de', '', '', '0.1', 'includes/plugins/footer_easy/', 1, 1, 0, 0, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_footer_easy', 'Footer Easy', 'footer_easy', 'footer_easy')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 7, 'footer_easy', '[[lang:de]]Footer Easy[[lang:en]]Footer Easy[[lang:it]]PiÃ¨ di pagina Easy', 'admincenter.php?site=admin_footer_easy', 0)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'plugin_footer_easy', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'plugin_footer_easy' LIMIT 1
  ))
");
  
 ?>