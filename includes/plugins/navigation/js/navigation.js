document.addEventListener("DOMContentLoaded", () => {

    const html = document.documentElement;

    // DB-Wert (PHP setzt diesen in data-theme-db)
    const dbTheme = html.dataset.themeDb; // light, dark, auto

    // User-Wert aus localStorage
    const saved = localStorage.getItem("theme");

    // --------------------------------------------
    // THEME STEUERUNG
    // --------------------------------------------

    if (dbTheme === "light" || dbTheme === "dark") {
        // DB hat FIXES Theme → localStorage ignorieren
        html.dataset.bsTheme = dbTheme;
        localStorage.removeItem("theme");
    }

    else if (dbTheme === "auto") {
        // Auto → User darf umschalten
        if (saved) {
            html.dataset.bsTheme = saved;
        } else {
            html.dataset.bsTheme = "light"; // dein Auto-Start
            localStorage.setItem("theme", "light");
        }
    }

    const logo = document.getElementById("mainLogo");
    const toggle = document.getElementById("themeToggle");
    const icon = document.getElementById("themeIcon");

    function updateTheme() {
        if (!logo) return;

        const isDark = html.dataset.bsTheme === "dark";
        logo.src = isDark ? logo.dataset.dark : logo.dataset.light;

        if (icon) {
            icon.className = isDark
                ? "bi bi-sun-fill fs-5"
                : "bi bi-moon-stars fs-5";
        }
    }

    // Icon nur aktiv, wenn DB = auto
    if (dbTheme === "auto" && toggle) {
        toggle.addEventListener("click", () => {
            const newTheme =
                html.dataset.bsTheme === "dark" ? "light" : "dark";

            html.dataset.bsTheme = newTheme;
            localStorage.setItem("theme", newTheme);
            updateTheme();
        });
    }

    updateTheme();





    // --------------------------------------------
    // Dropdown Animation Control
    // --------------------------------------------
    const nav = document.getElementById("mainNavbar");
    let animation = "fade";

    if (nav) {
        const classes = nav.classList.value;
        // Find class like: nx-fade, nx-slide, nx-slidefade, nx-zoom
        animation = classes.match(/nx-(fade|slide|slidefade|zoom)/)
            ? RegExp.$1
            : "fade";
    }

    // --------------------------------------------
    // Dropdown Hover (Desktop)
    // --------------------------------------------
    document.querySelectorAll("#mainNavbar .dropdown").forEach(drop => {

        // Desktop HOVER
        drop.addEventListener("mouseenter", () => {
            if (window.innerWidth < 992) return;
            drop.classList.add("show");
            const menu = drop.querySelector(".dropdown-menu");
            if (menu) menu.classList.add("show", "nx-" + animation);
        });

        drop.addEventListener("mouseleave", () => {
            if (window.innerWidth < 992) return;
            drop.classList.remove("show");
            const menu = drop.querySelector(".dropdown-menu");
            if (menu) menu.classList.remove("show", "nx-" + animation);
        });

        // Mobile CLICK – Bootstrap regelt das
    });
});
