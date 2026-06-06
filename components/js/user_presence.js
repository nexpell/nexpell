(function () {
    if (!window.nexpellPresence || !window.nexpellPresence.enabled) {
        return;
    }

    const endpoint = window.nexpellPresence.endpoint || "/system/user_presence.php";
    const heartbeatMs = Math.max(parseInt(window.nexpellPresence.heartbeatMs, 10) || 60000, 15000);
    let offlineSent = false;

    function post(action) {
        const body = new URLSearchParams({ action });

        if (action === "offline" && navigator.sendBeacon) {
            offlineSent = navigator.sendBeacon(endpoint, body);
            return offlineSent;
        }

        fetch(endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: body.toString(),
            credentials: "same-origin",
            keepalive: action === "offline"
        }).catch(() => {});

        return true;
    }

    function sendOffline() {
        if (offlineSent) {
            return;
        }

        offlineSent = true;
        if (!post("offline")) {
            offlineSent = false;
        }
    }

    post("ping");
    window.setInterval(function () {
        post("ping");
    }, heartbeatMs);

    window.addEventListener("beforeunload", sendOffline);
    window.addEventListener("pagehide", sendOffline);
})();
