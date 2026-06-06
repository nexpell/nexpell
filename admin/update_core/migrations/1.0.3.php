<?php
declare(strict_types=1);

use nexpell\CMSDatabaseMigration;

return function (CMSDatabaseMigration $m): void {

    /* ============================================================
       1) navigation_website_settings
    ============================================================ */

    $m->log("🧩 Migration: navigation_website_settings");

    if (!$m->tableExists('navigation_website_settings')) {
        $m->runQuery("
            CREATE TABLE navigation_website_settings (
                setting_key VARCHAR(64) NOT NULL,
                setting_value VARCHAR(255) NOT NULL,
                last_modified TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $m->log("✅ Tabelle erstellt");
    } else {
        $m->log("ℹ️ Tabelle existiert bereits");
    }

    $defaults = [
        'dropdown_animation'   => 'fade',
        'logo_center'          => '0',
        'logo_dark'            => 'logo_dark.png',
        'logo_light'           => 'logo_light.png',
        'mobile_breakpoint'    => 'sm',
        'nav_height'           => '80px',
        'navbar_shadow'        => 'shadow-sm',
        'navbar_theme'         => 'auto',
        'theme_engine_enabled' => '0',
        'navbar_class'         => 'bg-primary'
    ];

    foreach ($defaults as $key => $value) {
        $m->runQuery("
            INSERT IGNORE INTO navigation_website_settings (setting_key, setting_value)
            VALUES ('{$m->escape($key)}', '{$m->escape($value)}')
        ");
    }

    /* ============================================================
       2) settings.forum_acl_debug
    ============================================================ */

    if (!$m->columnExists('settings', 'forum_acl_debug')) {
        $m->runQuery("
            ALTER TABLE settings
            ADD COLUMN forum_acl_debug TINYINT(1) NOT NULL DEFAULT 0
        ");
        $m->log("✅ forum_acl_debug hinzugefügt");
    } else {
        $m->log("ℹ️ forum_acl_debug existiert bereits");
    }

    /* ============================================================
       3) settings_seo_meta – Struktur & Defaults
    ============================================================ */

    $m->log("🌐 Migration: settings_seo_meta");

if (!$m->tableExists('settings_seo_meta')) {

    $m->runQuery("
        CREATE TABLE settings_seo_meta (
            site VARCHAR(64) NOT NULL,
            language VARCHAR(8) NOT NULL DEFAULT 'de',
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            PRIMARY KEY (site, language)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $m->log("✅ settings_seo_meta neu erstellt");

} elseif ($m->columnExists('settings_seo_meta', 'seoID')) {

    $m->log("🪛 Alte Struktur erkannt – migriere");

    $m->runQuery("RENAME TABLE settings_seo_meta TO settings_seo_meta_old");

    $m->runQuery("
        CREATE TABLE settings_seo_meta (
            site VARCHAR(64) NOT NULL,
            language VARCHAR(8) NOT NULL DEFAULT 'de',
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            PRIMARY KEY (site, language)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $m->runQuery("
        INSERT IGNORE INTO settings_seo_meta (site, language, title, description)
        SELECT site, language, title, description
        FROM settings_seo_meta_old
    ");

    $m->runQuery("DROP TABLE settings_seo_meta_old");

    $m->log("✅ settings_seo_meta migriert");
}


    /* ============================================================
       4) SEO DEFAULTS (DE / EN / IT)
    ============================================================ */

    $m->log("📄 SEO Defaults einfügen");

    $m->runQuery(<<<SQL
INSERT INTO `settings_seo_meta` (`site`, `language`, `title`, `description`) VALUES
('about','de','Über uns – Das Team hinter Nexpell','Lerne das Team und die Geschichte von Nexpell kennen. Ein modernes Open-Source-CMS für Gamer.'),
('about','en','About Us – The Team Behind Nexpell','Get to know the team and story behind Nexpell. A modern open-source CMS for gamers.'),
('about','it','Chi siamo – Il team dietro Nexpell','Scopri il team e la storia di Nexpell. Un CMS moderno e open-source per gamer.'),

('articles','de','Artikel – Aktuelle Beiträge und News','Entdecke spannende Artikel, Neuigkeiten und Hintergrundberichte rund um Nexpell und seine Community.'),
('articles','en','Articles – Latest Posts and News','Discover articles, news and in-depth reports about Nexpell and its community.'),
('articles','it','Articoli – Ultimi post e notizie','Scopri articoli, notizie e approfondimenti sulla community di Nexpell.'),

('contact','de','Kontakt – Nimm Kontakt mit dem Nexpell-Team auf','Du hast Fragen oder Feedback? Nutze unser Kontaktformular – wir freuen uns auf deine Nachricht.'),
('contact','en','Contact – Get in Touch with the Nexpell Team','Have questions or feedback? Use our contact form to reach the Nexpell team.'),
('contact','it','Contatto – Mettiti in contatto con il team Nexpell','Hai domande o suggerimenti? Usa il modulo di contatto per scriverci.'),

('discord','de','Nexpell Discord – Community & Support','Tritt dem offiziellen Nexpell-Discord bei und erhalte direkten Support vom Team.'),
('discord','en','Nexpell Discord – Community and Support','Join the official Nexpell Discord to connect with the community and get support.'),
('discord','it','Nexpell Discord – Community e Supporto','Unisciti al Discord ufficiale di Nexpell per parlare con la community e ricevere supporto.'),

('downloads','de','Downloads – Erweiterungen für dein Nexpell CMS','Lade Module, Themes und Erweiterungen für dein Nexpell CMS herunter.'),
('downloads','en','Downloads – Extensions for Your Nexpell CMS','Download modules, themes and extensions for your Nexpell CMS.'),
('downloads','it','Download – Estensioni per il tuo CMS Nexpell','Scarica moduli, temi ed estensioni per il tuo CMS Nexpell.'),

('forum','de','Community Forum – Fragen, Hilfe & Austausch','Stelle Fragen und tausche dich mit anderen Nexpell-Nutzern im Forum aus.'),
('forum','en','Community Forum – Questions, Help & Exchange','Ask questions and connect with other Nexpell users in the forum.'),
('forum','it','Forum della community – Domande, aiuto e confronto','Fai domande e confrontati con altri utenti della community.'),

('gametracker','de','Game Server Übersicht – Echtzeit-Serverstatus','Überwache deine Gameserver in Echtzeit: Spieler, Karten, Status und mehr.'),
('gametracker','en','Game Server Overview – Real-Time Server Info','Monitor your game servers in real time: players, maps, versions and server status.'),
('gametracker','it','Panoramica server di gioco – Stato in tempo reale','Monitora i tuoi server di gioco in tempo reale: giocatori, mappe e stato del server.'),

('imprint','de','Impressum – Rechtliche Angaben zu Nexpell','Rechtliche Informationen und Verantwortliche gemäß §5 TMG.'),
('imprint','en','Legal Notice – Company and Legal Information about Nexpell','Legal information and responsible parties in accordance with §5 TMG.'),
('imprint','it','Note legali – Informazioni legali su Nexpell','Informazioni legali e responsabili secondo il §5 TMG.'),

('privacy_policy','de','Datenschutz – Umgang mit deinen Daten','Erfahre, wie wir deine Daten schützen. DSGVO-konform und transparent.'),
('privacy_policy','en','Privacy Policy – How We Handle Your Data','Learn how we protect your data. GDPR-compliant and transparent.'),
('privacy_policy','it','Privacy – Come trattiamo i tuoi dati','Scopri come proteggiamo i tuoi dati in conformità al GDPR.'),

('shoutbox','de','Shoutbox – Kurznachrichten deiner Community','Poste schnelle Nachrichten und bleibe mit deiner Community verbunden.'),
('shoutbox','en','Shoutbox – Quick Messages for Your Community','Post short messages and stay connected with your community.'),
('shoutbox','it','Shoutbox – Messaggi rapidi per la tua community','Invia messaggi brevi e rimani in contatto con la tua community.'),

('todo','de','TODO – Offene Aufgaben und wichtige To-Dos','Behalte einen Überblick über offene Aufgaben und Projektfortschritte.'),
('todo','en','TODO – Open Tasks and Important To-Dos','Keep track of open tasks and ongoing project steps.'),
('todo','it','TODO – Compiti aperti e cose da fare importanti','Tieni traccia dei compiti aperti e dei passaggi pianificati.'),

('userlist','de','Mitgliederliste – Alle registrierten Nutzer im Überblick','Hier findest du alle Mitglieder der Nexpell-Community mit Profilinformationen.'),
('userlist','en','Member List – All Registered Users at a Glance','See all registered members of the Nexpell community with profile info.'),
('userlist','it','Lista membri – Tutti gli utenti registrati','Visualizza tutti i membri registrati della community Nexpell.'),

('default','de','Nexpell CMS – Das modulare CMS für Communities und Clans','Modernes Open-Source-CMS, modular, flexibel und kostenlos.'),
('default','en','Nexpell CMS – The Modular CMS for Communities and Clans','A modern modular open-source CMS for communities and clans.'),
('default','it','Nexpell CMS – Il CMS modulare per community e clan','Un CMS open-source moderno, modulare e completamente gratuito.'),

('achievements','de','Erfolge – Errungenschaften deiner Community','Zeige freigeschaltete Achievements und Fortschritte deiner Nutzer an und motiviere die Community.'),
('achievements','en','Achievements – Community Rewards and Progress','Display unlocked achievements and user progress to motivate your community.'),
('achievements','it','Obiettivi – Traguardi della tua community','Mostra gli obiettivi sbloccati e i progressi degli utenti per motivare la community.'),

('blog','de','Blog – Beiträge und Artikel auf deiner Website','Erstelle persönliche oder themenbezogene Blogbeiträge und teile Neuigkeiten mit deiner Community.'),
('blog','en','Blog – Posts and Articles for Your Website','Create personal or thematic blog posts and share updates with your community.'),
('blog','it','Blog – Articoli e post per il tuo sito','Crea articoli personali o a tema e condividi aggiornamenti con la tua community.'),

('carousel','de','Carousel – Slider für deine Startseite','Füge deiner Website einen modernen Bild- und Text-Slider hinzu.'),
('carousel','en','Carousel – Slider for Your Homepage','Add a modern image and text slider to your homepage.'),
('carousel','it','Carousel – Slider per la tua homepage','Aggiungi uno slider moderno alla tua homepage.'),

('counter','de','Besucherzähler – Statistische Auswertung','Zeigt Besucherzahlen und Statistikdaten im Adminbereich aus Datenschutzgründen nur intern an.'),
('counter','en','Visitor Counter – Internal Statistics','Shows visitor counts and statistics internally in the admin area.'),
('counter','it','Contatore visitatori – Statistiche interne','Mostra conteggi dei visitatori e statistiche solo internamente.'),

('entwicklungshistorie','de','Entwicklungshistorie – Versionsübersicht','Alle Änderungen, Versionen und Fortschritte in der Entwicklung von Nexpell.'),
('entwicklungshistorie','en','Development History – Version Overview','See all updates and versions of Nexpell in one place.'),
('entwicklungshistorie','it','Cronologia sviluppo – Panoramica versioni','Visualizza aggiornamenti e versioni di Nexpell.'),

('gallery','de','Galerie – Bilder und Alben anzeigen','Erstelle Bildergalerien und Alben für Events, Projekte oder Community-Beiträge.'),
('gallery','en','Gallery – Display Images and Albums','Create image galleries and albums.'),
('gallery','it','Galleria – Mostra immagini e album','Crea gallerie e album di immagini.'),

('lastlogin','de','Letzter Login – Aktivität deiner Nutzer','Zeigt an, wann Nutzer zuletzt online waren.'),
('lastlogin','en','Last Login – User Activity Overview','Shows last online times.'),
('lastlogin','it','Ultimo accesso – Attività utenti','Mostra l’ultimo accesso degli utenti.'),

('linklist','de','Link- & Empfehlungslisten – Nützliche Ressourcen','Sammlung hilfreicher Links.'),
('linklist','en','Link & Recommendation List – Useful Resources','List of useful links.'),
('linklist','it','Lista link e raccomandazioni – Risorse utili','Lista di link utili.'),

('masterlist','de','Call of Duty Masterlist – Serverübersicht','Zeigt verfügbare CoD-Server.'),
('masterlist','en','Call of Duty Masterlist – Server Overview','Displays available CoD servers.'),
('masterlist','it','Masterlist Call of Duty – Panoramica server','Mostra server CoD.'),

('partners','de','Partner – Unterstützer und Kooperationen','Stelle Partner übersichtlich dar.'),
('partners','en','Partners – Supporters and Cooperations','Show partners clearly.'),
('partners','it','Partner – Sponsor e collaborazioni','Mostra gli sponsor chiaramente.'),

('pricing','de','Preistabellen – Kosten & Leistungen','Erstelle Preislisten.'),
('pricing','en','Pricing – Plans and Features Overview','Create pricing tables.'),
('pricing','it','Prezzi – Panoramica piani e funzionalità','Crea listini prezzi.'),

('rules','de','Regeln – Community- & Serverregeln','Verwalte Regeln klar strukturiert.'),
('rules','en','Rules – Community & Server Guidelines','Manage rules clearly.'),
('rules','it','Regole – Linee guida','Gestisci regole chiaramente.'),

('sponsors','de','Sponsoren – Unterstützer deiner Community','Zeige Sponsoren übersichtlich.'),
('sponsors','en','Sponsors – Supporters of Your Community','Show sponsors clearly.'),
('sponsors','it','Sponsor – Sostenitori della community','Mostra sponsor chiaramente.'),

('seo','de','SEO Manager – Suchmaschinenoptimierung','Verwalte Meta-Daten deiner Website.'),
('seo','en','SEO Manager – Search Engine Optimization','Manage meta-data.'),
('seo','it','SEO Manager – Ottimizzazione motori di ricerca','Gestisci meta-dati.'),

('search','de','Suche – Inhalte schnell finden','Durchsuche Seiten schnell und effizient.'),
('search','en','Search – Find Content Quickly','Search content efficiently.'),
('search','it','Ricerca – Trova contenuti facilmente','Cerca contenuti facilmente.'),

('twitch','de','Twitch – Livestream auf deiner Website','Binde Twitch ein.'),
('twitch','en','Twitch – Livestream on Your Website','Embed Twitch stream.'),
('twitch','it','Twitch – Livestream sul tuo sito','Incorpora stream Twitch.'),

('whoisonline','de','Who is Online – Live-Aktivität anzeigen','Zeigt aktive Benutzer.'),
('whoisonline','en','Who is Online – Live User Activity','Shows live users.'),
('whoisonline','it','Chi è online – Attività utenti','Mostra utenti attivi.'),

('messenger','de','Messenger – Private Nachrichten','Private Nachrichten senden.'),
('messenger','en','Messenger – Private Messages','Send private messages.'),
('messenger','it','Messenger – Messaggi privati','Invia messaggi privati.'),

('livevisitor','de','Live Besucher – Echtzeit-Statistiken','Überwache Live-Besucher.'),
('livevisitor','en','Live Visitor – Real-Time Analytics','Monitor real-time visitors.'),
('livevisitor','it','Visitatori live – Statistiche in tempo reale','Monitora visitatori live.')
ON DUPLICATE KEY UPDATE
title = VALUES(title),
description = VALUES(description)
SQL);


/* ============================================================
   Navigation Dashboard Link + Adminrechte (RESET + NEU)
============================================================ */

$m->log("🧭 Migration: Navigation Dashboard-Link & Adminrechte");

/* -------------------------------------------
   1) Alte / doppelte Einträge entfernen
-------------------------------------------- */

// Navigation-Dashboard-Link säubern
$m->runQuery("
    DELETE FROM navigation_dashboard_links
    WHERE modulname = 'navigation'
");
$m->log("🧹 Alte navigation_dashboard_links entfernt");

// Adminrechte für Navigation säubern
$m->runQuery("
    DELETE FROM user_role_admin_navi_rights
    WHERE modulname = 'navigation'
      AND roleID = 1
      AND type = 'link'
");
$m->log("🧹 Alte Admin-Navi-Rechte entfernt");

/* -------------------------------------------
   2) Neue Einträge sauber einfügen
-------------------------------------------- */

$m->runQuery("
    INSERT INTO navigation_dashboard_links
        (catID, modulname, name, url, sort)
    VALUES (
        6,
        'navigation',
        '[[lang:de]]Theme Grundeinstellungen[[lang:en]]Theme Global Settings[[lang:it]]Impostazioni globali del tema',
        'admincenter.php?site=admin_navigation_settings',
        1
    )
");
$m->log("➕ Neuer Dashboard-Link für Navigation eingefügt");

$m->runQuery("
    INSERT INTO user_role_admin_navi_rights
        (roleID, type, modulname)
    VALUES
        (1, 'link', 'navigation')
");
$m->log("➕ Adminrecht für Navigation gesetzt");


/* ============================================================
   settings_plugins – UNIQUE KEY für modulname
============================================================ */

$m->log("🔐 Migration: settings_plugins UNIQUE KEY (modulname)");

global $_database;

$res = $_database->query("
    SELECT COUNT(*) AS cnt
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings_plugins'
      AND INDEX_NAME = 'uniq_modulname'
");

$row = $res->fetch_assoc();
$indexExists = (int)$row['cnt'];

if ($indexExists === 0) {

    $m->runQuery("
        ALTER TABLE settings_plugins
        ADD UNIQUE KEY uniq_modulname (modulname)
    ");

    $m->log("✅ UNIQUE KEY uniq_modulname hinzugefügt");

} else {

    $m->log("ℹ️ UNIQUE KEY uniq_modulname existiert bereits");
}




/* ============================================================
   settings_plugins – Plugin "Navigation" eintragen / aktualisieren
============================================================ */

$m->log("🧩 Migration: settings_plugins – Plugin 'navigation'");

$m->runQuery(<<<SQL
INSERT INTO settings_plugins
(
    name,
    modulname,
    info,
    admin_file,
    activate,
    author,
    website,
    index_link,
    hiddenfiles,
    version,
    path,
    status_display,
    plugin_display,
    widget_display,
    delete_display,
    sidebar
)
VALUES
(
    'Navigation',
    'navigation',
    '[[lang:de]]Mit diesem Plugin könnt ihr euch die Navigation anzeigen lassen.[[lang:en]]With this plugin you can display navigation.[[lang:it]]Con questo plugin puoi visualizzare la Barra di navigazione predefinita.',
    'admin_navigation_settings',
    1,
    'T-Seven',
    'https://www.nexpell.de',
    '',
    '',
    '0.3',
    'includes/plugins/navigation/',
    1,
    1,
    0,
    0,
    'deactivated'
)
ON DUPLICATE KEY UPDATE
    name            = VALUES(name),
    info            = VALUES(info),
    admin_file      = VALUES(admin_file),
    activate        = VALUES(activate),
    author          = VALUES(author),
    website         = VALUES(website),
    version         = VALUES(version),
    path            = VALUES(path),
    status_display  = VALUES(status_display),
    plugin_display  = VALUES(plugin_display),
    widget_display  = VALUES(widget_display),
    delete_display  = VALUES(delete_display),
    sidebar         = VALUES(sidebar)
SQL);

$m->log("✅ Plugin 'navigation' wurde eingetragen / aktualisiert");

/* ============================================================
   Migration – navigation_website_main (Menünamen aktualisieren)
============================================================ */

$m->log("🧭 Migration: navigation_website_main – Hauptmenü Namen");

$updates = [
    'nav_home'      => ['id' => 1, 'name' => '[[lang:de]]Aktuelles[[lang:en]]News[[lang:it]]Notizie'],
    'nav_about'     => ['id' => 2, 'name' => '[[lang:de]]Über uns[[lang:en]]About us[[lang:it]]Chi siamo'],
    'nav_community' => ['id' => 3, 'name' => '[[lang:de]]Community[[lang:en]]Community[[lang:it]]Community'],
    'nav_media'     => ['id' => 4, 'name' => '[[lang:de]]Medien[[lang:en]]Media[[lang:it]]Media'],
    'nav_service'   => ['id' => 5, 'name' => '[[lang:de]]Service[[lang:en]]Service[[lang:it]]Servizio'],
    'nav_network'   => ['id' => 6, 'name' => '[[lang:de]]Netzwerk[[lang:en]]Network[[lang:it]]Rete'],
];

foreach ($updates as $modulname => $data) {

    $name = $m->escape($data['name']);
    $id   = (int)$data['id'];

    $m->runQuery("
        UPDATE navigation_website_main
        SET name = '{$name}'
        WHERE TRIM(modulname) = '{$m->escape($modulname)}'
           OR mnavID = {$id}
    ");

    $m->log("✔ {$modulname} geprüft / aktualisiert");
}



    /* ============================================================
       DONE
    ============================================================ */

    $m->log("🎉 Migration 1.0.3 erfolgreich abgeschlossen");
};
