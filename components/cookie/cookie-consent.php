<?php
// Funktion zur Überprüfung, ob ein bestimmtes Plugin installiert ist.
function isPluginInstalled($modulname, $_database) {
    $stmt = $_database->prepare("SELECT COUNT(*) AS count FROM settings_plugins_installed WHERE modulname = ?");
    $stmt->bind_param('s', $modulname);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return (int)$result['count'] > 0;
}

// Überprüfen, ob die Plugins für Twitch, Discord und jetzt auch YouTube installiert sind.
// Das Ergebnis wird als 'true' oder 'false' in einen String umgewandelt.
$twitchInstalled = isPluginInstalled('twitch', $_database) ? 'true' : 'false';
$discordInstalled = isPluginInstalled('discord', $_database) ? 'true' : 'false';
$youtubeInstalled = isPluginInstalled('youtube', $_database) ? 'true' : 'false';
?>

<!-- JavaScript-Objekt, das den Installationsstatus der Plugins speichert. -->
<script>
const PLUGIN_INSTALLED = {
    twitch: <?= $twitchInstalled ?>,
    discord: <?= $discordInstalled ?>,
    youtube: <?= $youtubeInstalled ?> // Neuer Eintrag für YouTube
};
</script>

<!-- Das eigentliche Cookie-Zustimmungs-Banner -->
<div id="cookie-consent-banner" class="cookie-banner position-fixed bottom-0 start-0 end-0 p-4 bg-dark text-white d-none shadow-lg" style="z-index: 9999;">
  <div class="container">
    <div class="row gy-4 align-items-start">
      
      <!-- Textinhalt -->
      <div class="col-12 col-md-8 d-flex flex-column justify-content-between" id="consent-content">
        <div>
          <h5 class="mb-2">Datenschutzeinstellungen</h5>
          <p>Wir verwenden externe Inhalte von Twitch, Discord und YouTube. Du kannst selbst entscheiden, was geladen werden darf.</p>

          <div class="mb-4">
            <h6>Notwendige Cookies</h6>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" checked disabled>
              <label class="form-check-label">Diese Cookies sind immer aktiv</label>
            </div>
            <p class="small text-white-50 mb-0">
              Diese Cookies sind für den Betrieb der Website und ihrer grundlegenden Funktionen zwingend erforderlich. Dazu zählen z. B. Session-Cookies zur Anmeldung, zur Verwaltung Ihrer Datenschutzeinstellungen oder zum sicheren Zugriff auf geschützte Bereiche.
              <br>
              Sie werden automatisch gesetzt und können nicht deaktiviert werden. Ohne diese Cookies funktioniert unsere Website nicht korrekt.
            </p>
          </div>

          <div id="third-party-switches" class="mb-3">
            <h6>Cookies von Drittanbietern (Twitch, Discord & YouTube)</h6>
            <p class="small text-white-50 mb-3">
              Beim Laden externer Inhalte wie Twitch-Streams, Discord-Widgets oder YouTube-Videos werden Cookies von diesen Plattformen gesetzt. Diese dienen z. B. der Nutzererkennung, Analyse oder der Optimierung der Dienste.
              <br>
              Mit deiner Zustimmung erlaubst du die Nutzung dieser Inhalte gemäß den Datenschutzrichtlinien der jeweiligen Anbieter. Ohne Zustimmung werden diese Inhalte nicht geladen.
            </p>

            <div class="form-check form-switch mb-2" id="twitch-switch">
              <input class="form-check-input" type="checkbox" id="consent-twitch" />
              <label class="form-check-label" for="consent-twitch">Twitch erlauben</label>
            </div>

            <div class="form-check form-switch mb-2" id="discord-switch">
              <input class="form-check-input" type="checkbox" id="consent-discord" />
              <label class="form-check-label" for="consent-discord">Discord erlauben</label>
            </div>

            <!-- Neuer Schalter für YouTube -->
            <div class="form-check form-switch mb-2" id="youtube-switch">
              <input class="form-check-input" type="checkbox" id="consent-youtube" />
              <label class="form-check-label" for="consent-youtube">YouTube erlauben</label>
            </div>
            
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="consent-all" />
              <label class="form-check-label" for="consent-all">Alle erlauben</label>
            </div>
          </div>
        </div>
      </div>

      <!-- Buttons -->
      <div class="col-12 col-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-end align-items-stretch align-items-md-end gap-2 h-100">
          <button id="cookie-accept" class="btn btn-primary w-100 w-md-auto">Speichern</button>
          <button id="cookie-decline" class="btn btn-outline-light w-100 w-md-auto">Ablehnen</button>
        </div>
      </div>

    </div>
  </div>
</div>
