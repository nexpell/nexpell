// Funktion zum Setzen eines Cookies mit einem Namen, Wert und Ablaufdatum in Tagen.
function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/`;
}

// Funktion zum Abrufen des Werts eines Cookies anhand seines Namens.
function getCookie(name) {
    const cookies = document.cookie.split('; ');
    for (const cookie of cookies) {
        const [key, val] = cookie.split('=');
        if (key === name) return decodeURIComponent(val);
    }
    return '';
}

// Funktion zum Laden von Twitch-Embeds basierend auf einer Liste von Kanälen.
function loadTwitchEmbeds(mainChannel, extraChannels) {
    if (document.getElementById('twitch-embed-script')) return;

    const script = document.createElement('script');
    script.id = 'twitch-embed-script';
    script.src = "https://embed.twitch.tv/embed/v1.js";
    script.onload = () => {
        new Twitch.Embed("main-stream", {
            width: "100%",
            height: 500,
            channel: mainChannel,
            layout: "video-with-chat",
            parent: [window.location.hostname]
        });

        extraChannels.forEach((channel, index) => {
            const div = document.createElement("div");
            div.className = "stream";
            div.id = "extra-stream-" + (index + 1);
            document.getElementById("extra-streams").appendChild(div);

            new Twitch.Embed(div.id, {
                width: "100%",
                height: 400,
                channel: channel,
                layout: "video",
                parent: [window.location.hostname]
            });
        });
    };
    document.body.appendChild(script);
}

// Funktion zum Laden eines Discord-Widgets.
function loadDiscordWidget(serverID) {
    const discordCard = document.getElementById("discord-card");
    const discordWidget = document.getElementById("discord-widget");
    if (!discordCard || !discordWidget) return;

    discordWidget.innerHTML = `
        <iframe
            src="https://discord.com/widget?id=${serverID}&theme=dark"
            width="100%" height="500"
            allowtransparency="true"
            frameborder="0"
            class="w-100 border-0 bg-dark"
            sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts">
        </iframe>
    `;
    discordCard.style.display = 'block';
}

// Neue Funktion, die die YouTube-Videos rendert, wenn die Zustimmung erteilt wurde.
// Diese Funktion sendet jetzt eine Fetch-Anfrage an die neue PHP-Datei.
function renderYoutubeVideos(config) {
    const container = document.getElementById("youtube-video-container");
    if (!container) return;
    
    // Leeren Sie den Container zuerst, um Duplikate zu vermeiden.
    container.innerHTML = '';
    
    // Erstellen der URL für die Fetch-Anfrage
    const params = new URLSearchParams();
    if (config.fullWidthVideoId) {
        params.append('fullWidthVideoId', config.fullWidthVideoId);
    }
    if (config.otherVideoIds.length > 0) {
        params.append('otherVideoIds', config.otherVideoIds.join(','));
    }
    params.append('displayMode', config.displayMode);
    // Fügt die Paginierungsvariablen zur URL hinzu
    params.append('page', config.currentPage);
    params.append('totalVideos', config.totalVideos);
    params.append('videosPerPageFirst', config.videosPerPageFirst);
    params.append('videosPerPageOther', config.videosPerPageOther);
    
    // Senden der Anfrage an die neue PHP-Datei
    fetch('/includes/plugins/youtube/youtube-content.php?' + params.toString())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error fetching YouTube content:', error);
            container.innerHTML = 'Ein Fehler ist aufgetreten. Videos konnten nicht geladen werden.';
        });
}


document.addEventListener("DOMContentLoaded", function () {
    const banner = document.getElementById('cookie-consent-banner');
    const twitchSwitch = document.getElementById('twitch-switch');
    const discordSwitch = document.getElementById('discord-switch');
    const youtubeSwitch = document.getElementById('youtube-switch');
    const twitchCheckbox = document.getElementById('consent-twitch');
    const discordCheckbox = document.getElementById('consent-discord');
    const youtubeCheckbox = document.getElementById('consent-youtube');
    const allCheckbox = document.getElementById('consent-all');
    const streamWrapper = document.getElementById("stream-wrapper");
    const fallbackTwitch = document.getElementById("fallback-twitch");
    const discordCard = document.getElementById("discord-card");
    const fallbackDiscord = document.getElementById('fallback-discord');
    const youtubeContainer = document.getElementById('youtube-video-container'); // Container-ID angepasst
    const fallbackYoutube = document.getElementById('fallback-youtube');
    const settingsIcon = document.getElementById("cookie-settings-icon");

    const thirdPartySwitches = document.getElementById('third-party-switches');
    if (!PLUGIN_INSTALLED.twitch && !PLUGIN_INSTALLED.discord && !PLUGIN_INSTALLED.youtube && thirdPartySwitches) {
        thirdPartySwitches.style.display = 'none';
    }

    // Zahnrad: Einstellungen nachträglich anzeigen
    if (settingsIcon) {
        settingsIcon.addEventListener("click", () => {
            if (PLUGIN_INSTALLED.twitch && twitchCheckbox) {
                twitchCheckbox.checked = (getCookie('nexpell_consent_twitch') === 'accepted');
            }
            if (PLUGIN_INSTALLED.discord && discordCheckbox) {
                discordCheckbox.checked = (getCookie('nexpell_consent_discord') === 'accepted');
            }
            if (PLUGIN_INSTALLED.youtube && youtubeCheckbox) {
                youtubeCheckbox.checked = (getCookie('nexpell_consent_youtube') === 'accepted');
            }
            if (allCheckbox) {
                const twitchOK = !PLUGIN_INSTALLED.twitch || twitchCheckbox.checked;
                const discordOK = !PLUGIN_INSTALLED.discord || discordCheckbox.checked;
                const youtubeOK = !PLUGIN_INSTALLED.youtube || youtubeCheckbox.checked;
                allCheckbox.checked = twitchOK && discordOK && youtubeOK;
            }
            showBanner();
        });
    }

    // Plugin-Schalter ausblenden, wenn nicht installiert
    if (!PLUGIN_INSTALLED.twitch && twitchSwitch) twitchSwitch.classList.add('d-none');
    if (!PLUGIN_INSTALLED.discord && discordSwitch) discordSwitch.classList.add('d-none');
    if (!PLUGIN_INSTALLED.youtube && youtubeSwitch) youtubeSwitch.classList.add('d-none');

    // "Alle erlauben"-Checkbox synchronisieren
    if (allCheckbox) {
        allCheckbox.addEventListener('change', () => {
            if (PLUGIN_INSTALLED.twitch && twitchCheckbox) twitchCheckbox.checked = allCheckbox.checked;
            if (PLUGIN_INSTALLED.discord && discordCheckbox) discordCheckbox.checked = allCheckbox.checked;
            if (PLUGIN_INSTALLED.youtube && youtubeCheckbox) youtubeCheckbox.checked = allCheckbox.checked;
        });
    }

    [twitchCheckbox, discordCheckbox, youtubeCheckbox].forEach(chk => {
        chk?.addEventListener('change', () => {
            if (allCheckbox) {
                const twitchOK = !PLUGIN_INSTALLED.twitch || twitchCheckbox.checked;
                const discordOK = !PLUGIN_INSTALLED.discord || discordCheckbox.checked;
                const youtubeOK = !PLUGIN_INSTALLED.youtube || youtubeCheckbox.checked;
                allCheckbox.checked = twitchOK && discordOK && youtubeOK;
            }
        });
    });

    function applyConsentDisplay() {
        // Twitch
        if (PLUGIN_INSTALLED.twitch) {
            if (getCookie('nexpell_consent_twitch') === 'accepted') {
                if (streamWrapper) streamWrapper.style.display = 'block';
                if (fallbackTwitch) fallbackTwitch.style.display = 'none';
                if (typeof TWITCH_CONFIG !== 'undefined') {
                    const main = TWITCH_CONFIG.main;
                    const extra = TWITCH_CONFIG.extra.split(',').map(c => c.trim()).filter(Boolean);
                    loadTwitchEmbeds(main, extra);
                }
            } else {
                if (streamWrapper) streamWrapper.style.display = 'none';
                if (fallbackTwitch) fallbackTwitch.style.display = 'block';
            }
        }

        // Discord
        if (PLUGIN_INSTALLED.discord) {
            if (getCookie('nexpell_consent_discord') === 'accepted') {
                if (discordCard) discordCard.style.display = 'block';
                if (fallbackDiscord) fallbackDiscord.style.display = 'none';
                if (typeof DISCORD_CONFIG !== 'undefined' && DISCORD_CONFIG.serverID) {
                    loadDiscordWidget(DISCORD_CONFIG.serverID);
                }
            } else {
                if (discordCard) discordCard.style.display = 'none';
                if (fallbackDiscord) fallbackDiscord.style.display = 'block';
            }
        }

        // YouTube
        if (PLUGIN_INSTALLED.youtube) {
            if (getCookie('nexpell_consent_youtube') === 'accepted') {
                if (youtubeContainer) {
                    youtubeContainer.style.display = 'block';
                    // Ruft die neue Render-Funktion auf, wenn die Zustimmung erteilt wird.
                    if (typeof YOUTUBE_CONFIG !== 'undefined') {
                        renderYoutubeVideos(YOUTUBE_CONFIG);
                    }
                }
                if (fallbackYoutube) fallbackYoutube.style.display = 'none';
            } else {
                if (youtubeContainer) youtubeContainer.style.display = 'none';
                if (fallbackYoutube) fallbackYoutube.style.display = 'block';
            }
        }
    }

    function showBanner() {
        banner.classList.remove('d-none');
        const overlay = document.getElementById('cookie-overlay');
        if (overlay) overlay.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Scroll sperren
    }

    function hideBanner() {
        banner.classList.add('d-none');
        const overlay = document.getElementById('cookie-overlay');
        if (overlay) overlay.style.display = 'none';
        document.body.style.overflow = ''; // Scroll erlauben
    }

    // Zustimmen
    document.getElementById('cookie-accept')?.addEventListener('click', function () {
        if (PLUGIN_INSTALLED.twitch) {
            setCookie('nexpell_consent_twitch', twitchCheckbox.checked ? 'accepted' : 'declined', 180);
        } else {
            setCookie('nexpell_consent_twitch', 'declined', 180);
        }

        if (PLUGIN_INSTALLED.discord) {
            setCookie('nexpell_consent_discord', discordCheckbox.checked ? 'accepted' : 'declined', 180);
        } else {
            setCookie('nexpell_consent_discord', 'declined', 180);
        }

        if (PLUGIN_INSTALLED.youtube) {
            setCookie('nexpell_consent_youtube', youtubeCheckbox.checked ? 'accepted' : 'declined', 180);
        } else {
            setCookie('nexpell_consent_youtube', 'declined', 180);
        }

        hideBanner();
        applyConsentDisplay();
    });

    // Ablehnen
    document.getElementById('cookie-decline')?.addEventListener('click', function () {
        if (PLUGIN_INSTALLED.twitch) setCookie('nexpell_consent_twitch', 'declined', 180);
        if (PLUGIN_INSTALLED.discord) setCookie('nexpell_consent_discord', 'declined', 180);
        if (PLUGIN_INSTALLED.youtube) setCookie('nexpell_consent_youtube', 'declined', 180);

        hideBanner();
        applyConsentDisplay();
    });

    // Wenn irgendein Consent fehlt, Banner anzeigen
    const consentCookies = [
        getCookie('nexpell_consent_twitch'),
        getCookie('nexpell_consent_discord'),
        getCookie('nexpell_consent_youtube')
    ];

    const hasAnyConsent = consentCookies.some(val => val !== '');

    if (!hasAnyConsent) {
        showBanner();
    } else {
        applyConsentDisplay();
    }
});
