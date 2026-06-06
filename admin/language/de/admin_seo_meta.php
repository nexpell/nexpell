<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\NavigationUpdater;

$_SESSION['language'] = $_SESSION['language'] ?? 'en';

// Falls Sprache über das Sprach-Formular geändert wurde
if (isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];
}

$language = $_SESSION['language'];

global $languageService;
$languageService = new LanguageService($_database);
$languageService->readPluginModule('seo');

AccessControl::checkAdminAccess('seo');

// Verfügbare Seiten (aus der Navigation)
$availableSites = [];

$result = $_database->query("SELECT url, name FROM navigation_website_sub WHERE indropdown = 1 ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $url = $row['url'];
        $site = null;
        if (preg_match('/site=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $site = $matches[1];
        } else {
            $site = trim($url, '/');
        }
        if ($site) {
            $availableSites[$site] = $row['name'] ?: $site;
        }
    }
    $result->free();
}

// Gewählte Seite
$site = $_POST['site'] ?? $_GET['site'] ?? 'seo';

// SEO-Daten speichern
if (isset($_POST['save'])) {
    $stmt = $_database->prepare("
        REPLACE INTO settings_seo_meta (site, language, title, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $site, $language, $_POST['title'], $_POST['description']);
    $stmt->execute();

    NavigationUpdater::updateFromAdminFile($site);

    echo '<div class="alert alert-success">SEO-Daten wurden gespeichert.</div>';
}

// Aktuelle Daten laden
$stmt = $_database->prepare("
    SELECT title, description FROM settings_seo_meta
    WHERE site = ? AND language = ?
");
$stmt->bind_param("ss", $site, $language);
$stmt->execute();
$result = $stmt->get_result();
$seo = $result->fetch_assoc() ?: ['title' => '', 'description' => ''];

// Alle Einträge (Sprache beachten)
$allSeo = [];
$resAll = $_database->prepare("
    SELECT site, title, description FROM settings_seo_meta
    WHERE language = ?
    ORDER BY site ASC
");
$resAll->bind_param("s", $language);
$resAll->execute();
$resAllResult = $resAll->get_result();
while ($row = $resAllResult->fetch_assoc()) {
    $allSeo[] = $row;
}

?>

<h2>SEO-Metadaten bearbeiten</h2>

<!-- Sprachwahl -->
<form method="post" id="languageForm" class="form-inline mb-3">
    <label for="language" class="mr-2">Sprache wählen:</label>
    <select name="language" id="language" class="form-select" onchange="document.getElementById('languageForm').submit();">
        <option value="de" <?= $language === 'de' ? 'selected' : '' ?>>Deutsch</option>
        <option value="en" <?= $language === 'en' ? 'selected' : '' ?>>English</option>
        <option value="it" <?= $language === 'it' ? 'selected' : '' ?>>Italiano</option>
    </select>
    <input type="hidden" name="site" value="<?= htmlspecialchars($site) ?>">
</form>

<!-- Hauptformular -->
<form method="post" class="form">
    <div class="form-group">
        <label for="site">Seite auswählen</label>
        <select id="site" name="site" class="form-select" onchange="this.form.submit()">
            <option value="" <?= empty($site) ? 'selected' : '' ?>>Bitte wählen</option>
            <?php foreach ($availableSites as $key => $label):
                $translate = new multiLanguage($language);
                $translate->detectLanguages($label); ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= ($key === $site) ? 'selected' : '' ?>>
                    <?= $translate->getTextByLanguage($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="title">Meta-Titel</label>
        <input type="text" id="title" name="title" class="form-control" maxlength="255" value="<?= htmlspecialchars($seo['title']) ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Meta-Beschreibung</label>
        <textarea id="description" name="description" class="form-control" rows="4" required><?= htmlspecialchars($seo['description']) ?></textarea>
    </div>

    <button type="submit" name="save" class="btn btn-primary mt-3 mb-3">Speichern</button>
</form>

<hr>

<h3>Alle SEO-Einträge (Sprache: <?= htmlspecialchars($language) ?>)</h3>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Seite</th>
            <th>Meta-Titel</th>
            <th>Meta-Beschreibung</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($allSeo as $entry): ?>
            <tr>
                <td>
                    <?php
                    $siteKey = $availableSites[$entry['site']] ?? $entry['site'];
                    $translate = new multiLanguage($language);
                    $translate->detectLanguages($siteKey);
                    echo $translate->getTextByLanguage($siteKey);
                    ?>
                </td>
                <td><?= htmlspecialchars($entry['title']) ?></td>
                <td><?= htmlspecialchars($entry['description']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($allSeo)): ?>
            <tr><td colspan="3">Keine SEO-Daten gefunden.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
