(function () {
  "use strict";

  // ------------------------------------------------------------
  // Global helper: Live card filter (used by installers)
  // ------------------------------------------------------------
  window.initLiveCardFilter = window.initLiveCardFilter || function (inputId, cardSelector, opts) {
    try {
      opts = opts || {};
      var input = document.getElementById(inputId);
      if (!input) return;

      function getText(el) {
        if (!el) return "";
        if (typeof opts.getText === "function") return String(opts.getText(el) || "");
        return (el.textContent || "");
      }

      function applyFilter() {
        var q = (input.value || "").toLowerCase().trim();
        var cards = document.querySelectorAll(cardSelector);

        for (var i = 0; i < cards.length; i++) {
          var card = cards[i];
          var txt = getText(card).toLowerCase();
          var show = (!q || txt.indexOf(q) !== -1);
          card.style.display = show ? "" : "none";
        }
      }

      input.addEventListener("input", applyFilter);
      applyFilter();
    } catch (e) {
      if (window.console && typeof console.warn === "function") console.warn("initLiveCardFilter failed:", e);
    }
  };

  document.addEventListener("DOMContentLoaded", function () {

    /* ------------------------------------------------------------
     * 1) Layout: Navbar / Sidebar / page-wrapper Höhe
     * ------------------------------------------------------------ */
    function adjustLayout() {
      var topOffset = 50;
      var width = window.innerWidth > 0 ? window.innerWidth : screen.width;

      if (width < 768) {
        document.querySelectorAll("div.navbar-collapse")
          .forEach(el => el.classList.add("collapse"));
        topOffset = 100;
      } else {
        document.querySelectorAll("div.navbar-collapse")
          .forEach(el => el.classList.remove("collapse"));
      }

      var height = (window.innerHeight > 0 ? window.innerHeight : screen.height) - 1;
      height = height - topOffset;
      if (height < 1) height = 1;

      var pageWrapper = document.getElementById("page-wrapper");
      if (pageWrapper && height > topOffset) {
        pageWrapper.style.minHeight = height + "px";
      }
    }

    window.addEventListener("load", adjustLayout);
    window.addEventListener("resize", adjustLayout);
    adjustLayout();

    /* ------------------------------------------------------------
     * 2) Active Navigation Item (Sidebar)
     * ------------------------------------------------------------ */
    (function setActiveNav() {
      var url = window.location.href;
      var links = document.querySelectorAll("ul.nav a");

      links.forEach(function (a) {
        if (a.href === url) {
          a.classList.add("active");

          var el = a.parentElement;
          while (el && el.tagName === "LI") {
            var parent = el.parentElement;
            if (parent) parent.classList.add("in");
            el = parent ? parent.closest("li") : null;
          }
        }
      });
    })();

    /* ------------------------------------------------------------
     * 3) Bootstrap 5 Tooltips
     * ------------------------------------------------------------ */
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new bootstrap.Tooltip(el, { html: true });
    });

    /* ------------------------------------------------------------
     * 4) Bootstrap 5 Popovers (Widget Screens etc.)
     * ------------------------------------------------------------ */
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
      new bootstrap.Popover(el, {
        container: "body",
        html: true,
        sanitize: true,
        trigger: el.getAttribute("data-bs-trigger") || "hover",
        content: function () {
          var img = el.getAttribute("data-img");
          return img ? '<img class="img-fluid" src="' + img + '">' : "";
        },
        title: el.getAttribute("data-bs-title") || ""
      });
    });

    /* ------------------------------------------------------------
     * 4a) Ripple Effect (Buttons + Sidebar)
     * ------------------------------------------------------------ */
    (function rippleEffect() {
      var RIPPLE_SELECTOR = [
        ".btn",
        ".ac-sidebar-link",
        ".ac-sidebar-subnav .nav-link",
        ".ac-sidebar-accordion .accordion-button"
      ].join(",");

      function parseRgb(rgbString) {
        if (!rgbString) return null;
        var m = rgbString.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
        if (!m) return null;
        return { r: parseInt(m[1], 10), g: parseInt(m[2], 10), b: parseInt(m[3], 10) };
      }

      function perceivedLuminance(rgb) {
        return (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255;
      }

      function getRippleColor(el) {
        var cs = window.getComputedStyle(el);
        var text = parseRgb(cs.color);
        if (text) {
          var lum = perceivedLuminance(text);
          return lum > 0.65 ? "rgba(255,255,255,0.35)" : "rgba(0,0,0,0.14)";
        }

        return "rgba(0,0,0,0.14)";
      }

      function shouldSkip(el) {
        if (!el) return true;
        if (el.disabled) return true;
        if (el.getAttribute("aria-disabled") === "true") return true;
        if (el.classList.contains("disabled")) return true;
        return false;
      }

      function ensureHostClass(el) {
        if (!el.classList.contains("ac-ripple-host")) el.classList.add("ac-ripple-host");
      }

      function spawnRipple(e, el) {
        ensureHostClass(el);

        var rect = el.getBoundingClientRect();
        var x = (e.clientX || 0) - rect.left;
        var y = (e.clientY || 0) - rect.top;
        var size = Math.max(rect.width, rect.height) * 2;

        el.style.setProperty("--ac-ripple-x", x + "px");
        el.style.setProperty("--ac-ripple-y", y + "px");
        el.style.setProperty("--ac-ripple-size", size + "px");
        el.style.setProperty("--ac-ripple-color", getRippleColor(el));

        el.classList.remove("ac-rippling");
        void el.offsetWidth;
        el.classList.add("ac-rippling");
        window.setTimeout(function () {
          el.classList.remove("ac-rippling");
        }, 650);
      }

      document.addEventListener("pointerdown", function (e) {
        if (typeof e.button === "number" && e.button !== 0) return;

        var target = e.target && e.target.closest ? e.target.closest(RIPPLE_SELECTOR) : null;
        if (!target || shouldSkip(target)) return;
        var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : "";
        if (tag === "input" || tag === "textarea" || tag === "select" || tag === "option") return;

        spawnRipple(e, target);
      }, { passive: true });
    })();

    /* ------------------------------------------------------------
     * 4b) Bootstrap 5 Toast: Ungelesene Nachrichten
     *  - wird serverseitig nur bei unreadCount > 0 gerendert
     * ------------------------------------------------------------ */
    (function unreadMessagesToast() {
      var toastEl = document.getElementById("unreadMessagesToast");
      if (!toastEl) return;

      if (typeof window.bootstrap === "undefined" || !bootstrap.Toast) return;

      window.setTimeout(function () {
        try {
          var delayAttr = toastEl.getAttribute("data-bs-delay");
          var delay = delayAttr ? parseInt(delayAttr, 10) : 10000;

          var toast = (bootstrap.Toast.getOrCreateInstance)
            ? bootstrap.Toast.getOrCreateInstance(toastEl, { autohide: true, delay: delay })
            : new bootstrap.Toast(toastEl, { autohide: true, delay: delay });

          toast.show();
        } catch (e) {
        }
      }, 50);
    })();

    /* ------------------------------------------------------------
     * 5) Confirm Delete Modal (Bootstrap 5)
     * ------------------------------------------------------------ */
    var confirmModal = document.getElementById("confirmDeleteModal");
    if (confirmModal) {
      var headerEl   = confirmModal.querySelector(".modal-header");
      var titleEl    = confirmModal.querySelector(".modal-title");
      var bodyEl     = confirmModal.querySelector(".modal-body");
      var cancelBtn  = confirmModal.querySelector(".modal-footer .btn.btn-secondary");
      var confirmBtn = confirmModal.querySelector("#confirmDeleteBtn");

      var defaults = {
        headerClass: headerEl?.className || "",
        titleHtml:   titleEl?.innerHTML || "",
        bodyHtml:    bodyEl?.innerHTML || "",
        cancelHtml:  cancelBtn?.innerHTML || "",
        confirmHtml: confirmBtn?.innerHTML || "",
        confirmClass: confirmBtn?.className || ""
      };

      confirmModal.addEventListener("show.bs.modal", function (event) {
        var trigger = event.relatedTarget;
        if (!trigger || !confirmBtn) return;

        var url = trigger.dataset.confirmUrl || trigger.dataset.deleteUrl;
        if (url) confirmBtn.setAttribute("href", url);

        if (headerEl && trigger.dataset.headerClass)
          headerEl.className = "modal-header " + trigger.dataset.headerClass;

        if (titleEl && trigger.dataset.modalTitle)
          titleEl.textContent = trigger.dataset.modalTitle;

        if (bodyEl && trigger.dataset.modalBody)
          bodyEl.innerHTML =
            '<div class="text-center"><p class="mb-0">' +
            trigger.dataset.modalBody.replace(/\n/g, "<br>") +
            "</p></div>";

        if (cancelBtn && trigger.dataset.cancelText)
          cancelBtn.textContent = trigger.dataset.cancelText;

        if (confirmBtn && trigger.dataset.confirmText)
          confirmBtn.textContent = trigger.dataset.confirmText;

        if (confirmBtn && trigger.dataset.confirmClass)
          confirmBtn.className = "btn " + trigger.dataset.confirmClass;
      });

      confirmModal.addEventListener("hidden.bs.modal", function () {
        if (headerEl) headerEl.className = defaults.headerClass;
        if (titleEl) titleEl.innerHTML = defaults.titleHtml;
        if (bodyEl) bodyEl.innerHTML = defaults.bodyHtml;
        if (cancelBtn) cancelBtn.innerHTML = defaults.cancelHtml;
        if (confirmBtn) {
          confirmBtn.innerHTML = defaults.confirmHtml;
          confirmBtn.className = defaults.confirmClass;
          confirmBtn.setAttribute("href", "#");
        }
      });
    }

    /* ------------------------------------------------------------
     * 6) Global Admin Live Search
     * ------------------------------------------------------------ */
    (function globalAdminSearch() {
      var input   = document.getElementById("globalAdminSearch");
      var results = document.getElementById("globalAdminSearchResults");
      if (!input || !results) return;

      var links = document.querySelectorAll('.sidebar a[href*="admincenter.php"]');
      var index = [];

      links.forEach(function (a) {
        var label =
          a.querySelector(".ac-sidebar-link-text")?.textContent ||
          a.textContent || "";

        label = label.replace(/\s+/g, " ").trim();
        if (label.length < 2) return;

        var acc = a.closest(".accordion-item");
        var cat = acc?.querySelector(".accordion-header")?.textContent.trim() || "";

        index.push({
          title: label,
          category: cat,
          href: a.getAttribute("href")
        });
      });

      function render(items, q) {
        results.innerHTML = "";
        if (!items.length) {
          results.innerHTML =
            '<div class="list-group-item small text-muted">Keine Treffer für <strong>' +
            q + "</strong></div>";
          results.style.display = "block";
          return;
        }

        items.slice(0, 12).forEach(function (it) {
          results.insertAdjacentHTML(
            "beforeend",
            '<a class="list-group-item list-group-item-action" href="' + it.href + '">' +
              '<div class="fw-semibold">' + it.title + '</div>' +
              (it.category ? '<div class="small text-muted">' + it.category + '</div>' : '') +
            "</a>"
          );
        });

        results.style.display = "block";
      }

      input.addEventListener("input", function () {
        var q = input.value.toLowerCase().trim();
        if (!q) {
          results.style.display = "none";
          results.innerHTML = "";
          return;
        }

        var hits = index.filter(it =>
          (it.title + " " + it.category).toLowerCase().includes(q)
        );

        render(hits, input.value);
      });

      document.addEventListener("click", function (e) {
        if (e.target !== input && !results.contains(e.target)) {
          results.style.display = "none";
        }
      });
    })();

  });

})();