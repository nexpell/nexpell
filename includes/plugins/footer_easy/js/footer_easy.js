(function() {
  "use strict";

  // ==============================
  // Footer-Kontrast & Links
  // ==============================
  function getContrastYIQ(rgbString) {
    const rgb = rgbString.replace(/[^\d,]/g, '').split(',').map(Number);
    if (rgb.length !== 3) return 'dark';
    const yiq = ((rgb[0] * 299) + (rgb[1] * 587) + (rgb[2] * 114)) / 1000;
    return yiq >= 128 ? 'dark' : 'light';
  }

  function applyFooterColors() {
    const footer = document.querySelector('footer.footer');
    if (!footer) return;

    const bodyStyles = getComputedStyle(document.body);
    const bgColor = bodyStyles.getPropertyValue('--bs-body-bg').trim() || bodyStyles.backgroundColor;

    const mode = getContrastYIQ(bgColor);

    let contrastColor = '#000000';
    let hoverColor = 'rgba(0,0,0,0.7)';

    if (mode === 'dark') {
      footer.classList.remove('bg-light', 'text-dark');
      footer.classList.add('bg-dark', 'text-white');
      contrastColor = '#ffffff';
      hoverColor = 'rgba(255,255,255,0.7)';
    } else {
      footer.classList.remove('bg-dark', 'text-white');
      footer.classList.add('bg-light', 'text-dark');
      contrastColor = '#000000';
      hoverColor = 'rgba(0,0,0,0.7)';
    }

    // Alle Links und Icons im Footer sicher anpassen
    const links = footer.querySelectorAll('a, .bi');
    links.forEach(link => {
      if (!link) return; // Extra Sicherheit
      link.style.color = contrastColor;

      link.addEventListener('mouseover', () => link.style.color = hoverColor);
      link.addEventListener('mouseout', () => link.style.color = contrastColor);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    applyFooterColors();

    // MutationObserver, falls Widgets/Links später nachgeladen werden
    const footer = document.querySelector('footer.footer');
    if (footer) {
      const observer = new MutationObserver(() => {
        applyFooterColors();
      });
      observer.observe(footer, { childList: true, subtree: true });
    }
  });

  // ==============================
  // Heartbeat (hält User online)
  // ==============================
  setInterval(() => {
    fetch('/../../system/heartbeat.php')
      .then(res => console.log("Heartbeat OK", res.status))
      .catch(err => console.error("Heartbeat error:", err));
  }, 60000);

  // ==============================
  // Sofort-Logout beim Schließen
  // ==============================
  // Sofort-Logout beim Schließen des Tabs/Fensters
  let isLoggingOut = false;

  // Logout-Link markieren
  document.querySelector('#logoutLink').addEventListener('click', function() {
      isLoggingOut = true;
  });

  window.addEventListener("beforeunload", () => {
    if (!isLoggingOut) {
      navigator.sendBeacon(
        "/includes/modules/logout.php",
        new Blob([], { type: "application/x-www-form-urlencoded" })
      );
    }
  });

})();