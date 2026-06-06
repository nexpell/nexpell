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

function getSeoMeta(string $site): array {
    global $_database;

    $language = strtolower((string)($_SESSION['language'] ?? 'de'));

    $title = '';
    $description = '';

    // Preferred schema (since 1.0.3.3): settings_seo_meta_lang
    $hasNewTable = false;
    if ($check = $_database->query("SHOW TABLES LIKE 'settings_seo_meta_lang'")) {
        $hasNewTable = ($check->num_rows > 0);
    }
    if ($hasNewTable) {
        $titleKey = 'seo_title_' . $site;
        $descKey  = 'seo_description_' . $site;

        $stmt = $_database->prepare("
            SELECT content_key, content
            FROM settings_seo_meta_lang
            WHERE language = ?
              AND content_key IN (?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("sss", $language, $titleKey, $descKey);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (($row['content_key'] ?? '') === $titleKey) {
                    $title = (string)($row['content'] ?? '');
                } elseif (($row['content_key'] ?? '') === $descKey) {
                    $description = (string)($row['content'] ?? '');
                }
            }
            $stmt->close();
        }
    }

    // Legacy fallback table (pre-1.0.3.3)
    if ($title === '' || $description === '') {
        $hasOldTable = false;
        if ($checkOld = $_database->query("SHOW TABLES LIKE 'settings_seo_meta'")) {
            $hasOldTable = ($checkOld->num_rows > 0);
        }
        if ($hasOldTable) {
            $stmt = $_database->prepare("
                SELECT title, description
                FROM settings_seo_meta
                WHERE site = ? AND language = ?
            ");
            if ($stmt) {
                $stmt->bind_param("ss", $site, $language);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    if ($title === '') {
                        $title = (string)($row['title'] ?? '');
                    }
                    if ($description === '') {
                        $description = (string)($row['description'] ?? '');
                    }
                }
                $stmt->close();
            }
        }
    }

    if ($title !== '' || $description !== '') {
        return [
            'title' => $title,
            'description' => $description,
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


