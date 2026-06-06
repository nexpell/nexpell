$(function () {
    // Initialisiere das MetisMenu
    $("#side-bar").metisMenu({
        activeClass: 'active'
    });

    // Beim Laden oder Ändern der Fenstergröße sicherstellen, dass die Sidebar korrekt funktioniert
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

    var url = window.location.href.split('&')[0]; // Die URL nur bis zum ersten "&" verwenden (z.B. admincenter.php?site=settings)
    
    // Suche nach dem Link, der zur aktuellen URL passt
    var element = $('ul.nav a').filter(function() {
        return this.href.split('&')[0] == url;
    }).addClass('active').parent();

    // Nun rekursiv die Eltern-Elemente öffnen
    while (element.length) {
        if (element.is('li')) {
            element = element.parent().addClass('mm-show').parent();
        } else {
            break;
        }
    }
});
