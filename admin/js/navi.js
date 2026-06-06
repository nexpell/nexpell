$(function () {
    // Initialisiere das MetisMenu
    var menu = $("#side-bar").metisMenu({
        activeClass: 'active'
    });

    // Überprüfe die URL, ob 'site=settings' oder 'action=social_setting' vorhanden ist
    var url = window.location.href;
    
    // Extrahiere nur den Teil der URL bis zum ersten "&" nach "site=" (z.B. admincenter.php?site=settings)
    var baseUrl = url.split('&')[0];

    // Prüfe, ob 'site=settings' in der Basis-URL vorhanden ist
    if (baseUrl.indexOf("site=settings") !== -1) {
        // Finde das Element, das zur aktuellen Seite gehört und aktiviere es
        var element = $('ul.nav a').filter(function() {
            // Vergleiche nur den Teil der URL bis "site=" und ignoriert alles danach
            return this.href.split('&')[0] == baseUrl;
        }).addClass('active').parent();

        // Rekursive Schleife, um die Eltern-Elemente zu erweitern
        while (true) {
            if (element.is('li')) {
                // Füge "mm-show" nur für das aktuelle Element hinzu
                element = element.parent().addClass('mm-show').parent();
            } else {
                break;
            }
        }

        // Verhindere, dass andere Kategorien ebenfalls offen bleiben
        // Schließe alle anderen Untermenüs, die nicht relevant sind
        $('ul.nav li').not(element).each(function() {
            var submenu = $(this).find('ul');
            if (submenu.length) {
                submenu.removeClass('mm-show');
            }
        });
    }

    // Sicherstellen, dass die Sidebar richtig funktioniert, wenn das Fenster geändert wird
    $(window).bind("load resize", function() {
        var topOffset = 50;
        var width = (this.window.innerWidth > 0) ? this.window.innerWidth : this.screen.width;
        if (width < 768) {
            $('div.navbar-collapse').addClass('collapse');
            topOffset = 100; // für 2-Row-Menü
        } else {
            $('div.navbar-collapse').removeClass('collapse');
        }

        var height = ((this.window.innerHeight > 0) ? this.window.innerHeight : this.screen.height) - 1;
        height = height - topOffset;
        if (height < 1) height = 1;
        if (height > topOffset) {
            $("#page-wrapper").css("min-height", (height) + "px");
        }
    });
});
