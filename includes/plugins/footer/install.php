<?php
global $_database;
safe_query("CREATE TABLE IF NOT EXISTS plugins_footer (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  row_type enum('category','link','footer_text','footer_template') NOT NULL DEFAULT 'link',
  category_key varchar(64) NOT NULL DEFAULT '',
  section_title varchar(255) NOT NULL DEFAULT 'Navigation',
  section_sort int(10) UNSIGNED NOT NULL DEFAULT 1,
  link_sort int(10) UNSIGNED NOT NULL DEFAULT 1,
  footer_link_name varchar(255) NOT NULL DEFAULT '',
  footer_link_url varchar(255) NOT NULL DEFAULT '',
  new_tab tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_section (section_sort, section_title),
  KEY idx_section_links (section_sort, section_title, link_sort),
  KEY idx_footer_cat (row_type, category_key),
  KEY idx_footer_cat_title (row_type, section_title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

safe_query("CREATE TABLE IF NOT EXISTS plugins_footer_lang (
  id int(11) NOT NULL AUTO_INCREMENT,
  content_key varchar(80) NOT NULL,
  language char(2) NOT NULL,
  content mediumtext NOT NULL,
  updated_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uniq_content_lang (content_key, language),
  KEY idx_content_key (content_key),
  KEY idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

if (!function_exists('footer_install_extract_lang')) {
  function footer_install_extract_lang(string $text, string $lang): string {
    if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
      return trim((string)$m[1]);
    }
    if ($lang === 'gb' && preg_match('/\[\[lang:en\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
      return trim((string)$m[1]);
    }
    if ($lang === 'en' && preg_match('/\[\[lang:gb\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
      return trim((string)$m[1]);
    }
    if (preg_match('/\[\[lang:[a-z]{2}\]\]/i', $text)) {
      return '';
    }
    return trim($text);
  }
}

// ------------------------------------------------------------------------------------
// DEFAULT DATA
// ------------------------------------------------------------------------------------
$cat_legal = '97cef6e0a43f670b6b06577a5530d1a4';
$cat_nav   = '846495f9ceed11accf8879f555936a7d';

safe_query("
  INSERT IGNORE INTO `plugins_footer`
    (`id`, `row_type`, `category_key`, `section_title`, `section_sort`, `link_sort`, `footer_link_name`, `footer_link_url`, `new_tab`)
  VALUES
    (1, 'category', '97cef6e0a43f670b6b06577a5530d1a4', 'Legal', 2, 1, '', '', 0),
    (2, 'category', '846495f9ceed11accf8879f555936a7d', 'Navigation', 1, 1, '', '', 0),
    (3, 'link', '97cef6e0a43f670b6b06577a5530d1a4', 'Rechtliches', 2, 1, '', 'index.php?site=privacy_policy', 0),
    (4, 'link', '97cef6e0a43f670b6b06577a5530d1a4', 'Rechtliches', 2, 2, '', 'index.php?site=imprint', 0),
    (5, 'link', '97cef6e0a43f670b6b06577a5530d1a4', 'Rechtliches', 2, 3, '', 'index.php?site=terms_of_service', 0),
    (6, 'link', '846495f9ceed11accf8879f555936a7d', 'Navigation', 1, 1, '', 'index.php?site=contact', 0),
    (7, 'footer_text', '', 'footer_description', 0, 0, '', '', 0),
    (8, 'footer_template', '', 'footer_template', 1, 1, 'standard', '', 0)
");

// --------------------------------------------------------------------
// MIGRATION/SEED: link names + footer text into plugins_footer_lang
// --------------------------------------------------------------------
$langs = ['de','en','it'];
$lr = safe_query("SELECT id, row_type, section_title, footer_link_name FROM plugins_footer");
if ($lr) {
  while ($row = mysqli_fetch_assoc($lr)) {
    $id = (int)($row['id'] ?? 0);
    $rowType = (string)($row['row_type'] ?? '');
    $section = (string)($row['section_title'] ?? '');
    $nameRaw = (string)($row['footer_link_name'] ?? '');

    if ($id > 0 && $rowType === 'link') {
      $ck = 'link_name_' . $id;
      foreach ($langs as $iso) {
        $txt = mysqli_real_escape_string($_database, footer_install_extract_lang($nameRaw, $iso));
        safe_query("INSERT INTO plugins_footer_lang (content_key, language, content, updated_at)
                    VALUES ('{$ck}','{$iso}','{$txt}',NOW())
                    ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()");
      }
    }

    if ($rowType === 'footer_text' && $section === 'footer_description') {
      foreach ($langs as $iso) {
        $txt = mysqli_real_escape_string($_database, footer_install_extract_lang($nameRaw, $iso));
        safe_query("INSERT INTO plugins_footer_lang (content_key, language, content, updated_at)
                    VALUES ('footer_text','{$iso}','{$txt}',NOW())
                    ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()");
      }
    }
  }
}

// Ensure multilingual defaults for seeded links / titles.
$upsertFooterLang = static function (string $contentKey, string $lang, string $content) use ($_database): void {
  $ck = mysqli_real_escape_string($_database, $contentKey);
  $lg = mysqli_real_escape_string($_database, $lang);
  $ct = mysqli_real_escape_string($_database, $content);
  safe_query("INSERT INTO plugins_footer_lang (content_key, language, content, updated_at)
              VALUES ('{$ck}','{$lg}','{$ct}',NOW())
              ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()");
};

$upsertFooterLang('cat_title_' . $cat_legal, 'de', 'Rechtliches');
$upsertFooterLang('cat_title_' . $cat_legal, 'en', 'Legal');
$upsertFooterLang('cat_title_' . $cat_legal, 'it', 'Legale');
$upsertFooterLang('cat_title_' . $cat_nav, 'de', 'Navigation');
$upsertFooterLang('cat_title_' . $cat_nav, 'en', 'Navigation');
$upsertFooterLang('cat_title_' . $cat_nav, 'it', 'Navigazione');

$upsertFooterLang('link_name_3', 'de', 'Datenschutz');
$upsertFooterLang('link_name_3', 'en', 'Privacy Policy');
$upsertFooterLang('link_name_3', 'it', 'Informativa sulla Privacy');
$upsertFooterLang('link_name_4', 'de', 'Impressum');
$upsertFooterLang('link_name_4', 'en', 'Imprint');
$upsertFooterLang('link_name_4', 'it', 'Impronta Editoriale');
$upsertFooterLang('link_name_5', 'de', 'Nutzungsbedingungen');
$upsertFooterLang('link_name_5', 'en', 'Terms and Conditions');
$upsertFooterLang('link_name_5', 'it', 'Termini e condizioni');
$upsertFooterLang('link_name_6', 'de', 'Kontakt');
$upsertFooterLang('link_name_6', 'en', 'Contact');
$upsertFooterLang('link_name_6', 'it', 'Contatti');

// ------------------------------------------------------------------------------------
// SYSTEM: settings_plugins
// ------------------------------------------------------------------------------------
safe_query("
    INSERT IGNORE INTO settings_plugins
        (pluginID, modulname, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar)
    VALUES
        ('', 'footer', 'admin_footer', 1, 'Fjolnd', 'https://webspell-rm.de', '', '', '0.1', 'includes/plugins/footer/', 1, 1, 0, 0, 'deactivated');
");

safe_query("
    DELETE FROM settings_plugins_lang
    WHERE content_key IN ('plugin_name_footer_easy', 'plugin_info_footer_easy')
");

safe_query("
    INSERT IGNORE INTO settings_plugins_lang 
        (content_key, language, content, modulname, updated_at)
    VALUES
        ('plugin_name_footer', 'de', 'Footer', 'footer_easy', NOW()),
        ('plugin_info_footer', 'de', 'Mit diesem Plugin könnt ihr einen neuen Footer anzeigen lassen.', 'footer', NOW()),
        ('plugin_info_footer', 'en', 'With this plugin you can have a new Footer displayed.', 'footer', NOW()),
        ('plugin_info_footer', 'it', 'Con questo plugin puoi visualizzare un nuovo piè di pagina.', 'footer', NOW())
");

// ------------------------------------------------------------------------------------
// WIDGET: settings_widgets
// ------------------------------------------------------------------------------------
safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
  ('widget_footer', 'Footer', 'footer', 'footer')
");

// ------------------------------------------------------------------------------------
// NAVIGATION: Admin Dashboard Link
// Wichtig: konsistentes modulname -> footer
// ------------------------------------------------------------------------------------
safe_query("
    INSERT IGNORE INTO navigation_dashboard_links
        (catID, modulname, url, sort)
    VALUES
        (7, 'footer', 'admincenter.php?site=admin_footer', 0)
");
$linkID = 0;
$linkRes = safe_query("
  SELECT linkID
  FROM navigation_dashboard_links
  WHERE modulname='footer' AND url='admincenter.php?site=admin_footer'
  ORDER BY linkID DESC
  LIMIT 1
");
if ($linkRes && ($linkRow = mysqli_fetch_assoc($linkRes))) {
  $linkID = (int)($linkRow['linkID'] ?? 0);
}

if ($linkID > 0) {
  safe_query("
      INSERT IGNORE INTO navigation_dashboard_lang
          (content_key, language, content, updated_at)
      VALUES
          ('nav_link_{$linkID}', 'de', 'footer', NOW()),
          ('nav_link_{$linkID}', 'en', 'footer', NOW()),
          ('nav_link_{$linkID}', 'it', 'footer', NOW())
  ");
}

// ------------------------------------------------------------------------------------
// RIGHTS: Admin-Navi Rechte setzen
// ------------------------------------------------------------------------------------
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'footer', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'footer' LIMIT 1
  ))
");
?>


