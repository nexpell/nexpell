<?php

/**
 * Führt eine Weiterleitung zu einer angegebenen URL durch.
 *
 * @param string $url Die Ziel-URL für die Weiterleitung. 
 *                    Wenn 'back', wird die Seite zurückgeladen.
 * @param string $info Die Information, die angezeigt wird, bevor die Weiterleitung erfolgt.
 * @param int $time Die Zeit in Sekunden, bis die Weiterleitung erfolgt (Standard: 1 Sekunde).
 */
function redirect($url, $info, $time = 1)
{
    if ($url == "back" && $info != '' && isset($_SERVER['HTTP_REFERER'])) {
        // Wenn die URL 'back' ist und ein Referer existiert, leite zur vorherigen Seite weiter.
        $url = $_SERVER['HTTP_REFERER'];
        $info = '';
    } elseif ($url == "back" && $info != '') {
        // Wenn die URL 'back' ist, aber kein Referer existiert, benutze die angegebene Info als URL.
        $url = $info;
        $info = '';
    }
    
    // Zeigt eine Weiterleitung mit einer Nachricht an
    echo
        '<meta http-equiv="refresh" content="' . $time . ';URL=' . $url . '"><br />' .
        '<p style="color:#000000">' . $info . '</p><br /><br />';
}

/**
 * Überprüft, ob die aktuelle Seite eine statische Seite ist.
 *
 * @param int|null $staticID Die ID der statischen Seite, die überprüft wird (optional).
 * @return bool Gibt true zurück, wenn es sich um eine statische Seite handelt, andernfalls false.
 */
function isStaticPage($staticID = null)
{
    // Prüft, ob der aktuelle Seitentyp "static" ist
    if ($GLOBALS['site'] != "static") {
        return false;
    }

    // Wenn eine statische ID angegeben ist, vergleiche sie mit der aktuellen URL
    if ($staticID !== null) {
        if ($_GET['staticID'] != $staticID) {
            return false;
        }
    }

    return true;
}
