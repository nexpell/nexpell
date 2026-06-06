<?php
/*function getSeoMeta(string $site): array
{
    $defaults = [
        'title' => 'Nexpell CMS – Die modulare Community-Plattform',
        'description' => 'Erstelle deine eigene Clan- oder Community-Webseite mit dem Nexpell CMS. Open Source, modular & responsiv.',
    ];

    $meta = [
        'home' => [
            'title' => 'Nexpell CMS – Die Plattform für Clan- und Community-Seiten',
            'description' => 'Erstelle mit Nexpell leistungsstarke Clan- und Community-Webseiten. Kostenlos, modular, responsiv.',
        ],
        'about' => [
            'title' => 'Über uns – Das Team hinter Nexpell',
            'description' => 'Lerne das Team und die Geschichte von Nexpell kennen. Ein modernes Open-Source-CMS für Gamer.',
        ],
        'downloads' => [
            'title' => 'Downloads – Erweiterungen für dein Nexpell CMS',
            'description' => 'Lade Module, Themes und Erweiterungen für dein Nexpell CMS herunter. Direkt einsatzbereit.',
        ],
        'forum' => [
            'title' => 'Community Forum – Fragen, Hilfe & Austausch',
            'description' => 'Diskutiere Ideen und tausche dich mit anderen Nexpell-Nutzern im Forum aus.',
        ],
        'shoutbox' => [
            'title' => 'Shoutbox – Kurznachrichten deiner Community',
            'description' => 'Poste schnelle Nachrichten und bleibe mit deinem Clan in Kontakt – direkt auf deiner Seite.',
        ],
        'gametracker' => [
            'title' => 'Game Server Übersicht – Echtzeit-Serverstatus',
            'description' => 'Behalte den Überblick über deine Gameserver. Mit Karten, Spielern und Serverstatus.',
        ],
        'seo' => [
            'title' => 'SEO bei Nexpell – Mehr Sichtbarkeit für deine Seite',
            'description' => 'Nutze das SEO-Plugin von Nexpell, um deine Inhalte bei Google & Co. sichtbar zu machen.',
        ],
        'rules' => [
            'title' => 'Regeln – Community-Richtlinien bei Nexpell',
            'description' => 'Unsere Regeln für ein faires Miteinander in der Nexpell-Community. Klar und verbindlich.',
        ],
        'imprint' => [
            'title' => 'Impressum – Rechtliche Angaben zu Nexpell',
            'description' => 'Verantwortlich für Inhalte und rechtliche Informationen zu Nexpell gemäß §5 TMG.',
        ],
        'privacy_policy' => [
            'title' => 'Datenschutz – Umgang mit deinen Daten',
            'description' => 'Erfahre, wie wir deine Daten schützen. Unsere Datenschutzrichtlinien – DSGVO-konform.',
        ],
    ];

    return $meta[$site] ?? $defaults;
}*/

/*function getSeoMeta(string $site): array {
    global $_database;

    // Sprache automatisch aus der Session holen, Fallback 'de'
    $language = $_SESSION['language'] ?? 'de';

    $stmt = $_database->prepare("
        SELECT title, description 
        FROM settings_seo_meta 
        WHERE site = ? AND language = ?
    ");
    $stmt->bind_param("ss", $site, $language);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return [
            'title' => $row['title'],
            'description' => $row['description'],
        ];
    }

    // Fallback: Standardwerte je Sprache
    switch ($language) {
        case 'en':
            return [
                'title' => 'Nexpell CMS – The Modular CMS for Communities and Clans',
                'description' => 'Nexpell is a modern open-source CMS designed for clan and community websites. Modular, customizable, and free to use.',
            ];
        case 'it':
            return [
                'title' => 'Nexpell CMS – Il CMS modulare per community e clan',
                'description' => 'Nexpell è un moderno CMS open source per siti web di clan e community. Modulare, personalizzabile e completamente gratuito.',
            ];
        case 'de':
        default:
            return [
                'title' => 'Nexpell CMS – Das modulare CMS für Communities und Clans',
                'description' => 'Nexpell ist ein modernes Open-Source-CMS für Clan- und Community-Webseiten. Modular aufgebaut, leicht anpassbar und kostenlos verfügbar.',
            ];
    }
}
*/

function getSeoMeta(string $site): array {
    global $_database;

    // Sprache aus Session, Fallback 'de'
    $language = $_SESSION['language'] ?? 'de';

    // 1️⃣ – Versuche, einen Eintrag für die Seite und Sprache zu finden
    $stmt = $_database->prepare("
        SELECT title, description 
        FROM settings_seo_meta 
        WHERE site = ? AND language = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $site, $language);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return [
            'title' => $row['title'],
            'description' => $row['description'],
        ];
    }
    $stmt->close();

    // 2️⃣ – Fallback: "default" oder "global" in settings_seo_meta
    $fallbackKeys = ['default', 'global', 'index', 'home'];
    foreach ($fallbackKeys as $fallbackSite) {
        $stmt = $_database->prepare("
            SELECT title, description 
            FROM settings_seo_meta 
            WHERE site = ? AND language = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $fallbackSite, $language);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return [
                'title' => $row['title'],
                'description' => $row['description'],
            ];
        }
        $stmt->close();
    }

    // 3️⃣ – Letzter Fallback: statische Minimalwerte (nur falls DB leer)
    return [
        'title' => 'Nexpell CMS',
        'description' => 'Ein modulares Open-Source-CMS für Communities und Clans.',
    ];
}



