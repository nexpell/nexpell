(function () {
    if (!window.nexpellPresence || !window.nexpellPresence.enabled) {
        return;
    }

    const endpoint = window.nexpellPresence.endpoint || "/system/user_presence.php";
    const heartbeatMs = Math.max(parseInt(window.nexpellPresence.heartbeatMs, 10) || 60000, 15000);
    let internalNavigation = false;
    let offlineSent = false;

    function post(action) {
        const body = new URLSearchParams({ action });

        if (action === "offline" && navigator.sendBeacon) {
            return navigator.sendBeacon(endpoint, body);
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

    function isInternalUrl(url) {
        try {
            const target = new URL(url, window.location.href);
            return target.origin === window.location.origin;
        } catch (_) {
            return false;
        }
    }

    function markInternalNavigation(eventTarget) {
        if (!eventTarget) {
            return;
        }

        const link = eventTarget.closest("a[href]");
        if (link) {
            const href = link.getAttribute("href") || "";
            const target = (link.getAttribute("target") || "").toLowerCase();

            if (
                href &&
                !href.startsWith("#") &&
                !href.startsWith("javascript:") &&
                target !== "_blank" &&
                isInternalUrl(href)
            ) {
                internalNavigation = true;
            }
            return;
        }

        const form = eventTarget.closest("form");
        if (form) {
            const action = form.getAttribute("action") || window.location.href;
            const target = (form.getAttribute("target") || "").toLowerCase();
            if (target !== "_blank" && isInternalUrl(action)) {
                internalNavigation = true;
            }
        }
    }

    function sendOffline() {
        if (offlineSent || internalNavigation) {
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

    document.addEventListener("click", function (event) {
        markInternalNavigation(event.target);
    }, true);

    document.addEventListener("submit", function (event) {
        markInternalNavigation(event.target);
    }, true);

    window.addEventListener("beforeunload", sendOffline);
    window.addEventListener("pagehide", function (event) {
        if (event.persisted) {
            return;
        }
        sendOffline();
    });
})();
