<?php

// IP-Adresse des Clients ermitteln
$GLOBALS['ip'] = $_SERVER['REMOTE_ADDR'] ?? getenv('REMOTE_ADDR') ?? 'Unbekannt';

/**
 * Gibt die tatsächliche IP-Adresse des Clients zurück,
 * auch wenn ein Proxy oder Load Balancer dazwischensteht.
 *
 * @return string IP-Adresse
 */
function getClientIP(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Falls mehrere IPs angegeben sind (z.B. bei Proxys), die erste nehmen
        $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($forwardedIps[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? getenv('REMOTE_ADDR') ?? 'Unbekannte IP';
}

// Aktualisiere globale IP mit der tatsächlichen Client-IP
$GLOBALS['ip'] = getClientIP();

