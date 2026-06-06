<?php

$language_array = array(

    'accept'                => 'Akzeptieren',
    'activate_template'     => 'Template aktivieren',
    'admincenter'           => 'Dashboard',
    'edit_profile'          => 'Profil bearbeiten',
    'forum'                 => 'Neues im Forum',
    'info'                  => 'Die angefragte Seite konnte nicht gefunden werden. <i><b>Aktiviere ein Template im Dashbord!</b></i>',
    'log_off'               => 'Abmelden',
    'login'                 => 'LOGIN',
    'messaging_system'      => 'Nachrichtensystem',
    'more_new_forum_post'   => 'Neue Beiträge im Forum',
    'more_new_message'      => 'Neue Nachrichten',
    'my_account'            => 'Mein Konto',
    'no_forum_post'         => 'Keine neuen Beiträge im Forum',
    'no_new_messages'       => 'Keine Nachricht',
    'one_new_forum_post'    => 'Neuer Beitrag im Forum',
    'one_new_message'       => 'Neue Nachricht',
    'overview'              => 'ÜBERSICHT',
    'select_lang'           => 'Auswahl',
    'to_profil'             => 'Mein Profil',

);


/*$lang = isset($_GET['lang']) ? $_GET['lang'] : 'de';
$site = isset($_GET['site']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['site']) : 'index';

// 1. Sprachdatei im globalen Sprachverzeichnis
$langfile = __DIR__ . "/languages/{$lang}/{$site}.php";

// 2. Wenn nicht vorhanden, in Plugin-Sprachverzeichnis suchen
if (!file_exists($langfile)) {
    $pluginLangFile = __DIR__ . "/includes/plugins/{$site}/languages/{$lang}/{$site}.php";
    if (file_exists($pluginLangFile)) {
        $langfile = $pluginLangFile;
    } else {
        die("Sprachdatei {$lang}/{$site}.php fehlt!xxx");
    }
}

include $langfile;*/
