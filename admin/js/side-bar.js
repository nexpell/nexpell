$(function () {
    $("#side-bar").metisMenu({
        activeClass: 'active'
      });
});


//Loads the correct sidebar on window load,
//collapses the sidebar on window resize.
// Sets the min-height of #page-wrapper to window size
$(function() {
    $(window).bind("load resize", function() {
        var topOffset = 50;
        var width = (this.window.innerWidth > 0) ? this.window.innerWidth : this.screen.width;
        if (width < 768) {
            $('div.navbar-collapse').addClass('collapse');
            topOffset = 100; // 2-row-menu
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

    var url = window.location.href.split('&')[0];
    // var element = $('ul.nav a').filter(function() {
    //     return this.href == url;
    // }).addClass('active').parent().parent().addClass('in').parent();
    var element = $('ul.nav a').filter(function() {
     return this.href == url;
    }).addClass('active').parent();

    while(true){
        if (element.is('li')){
            element = element.parent().addClass('mm-show').parent();
        } else {
            break;
        }
    }
});


$(function () {
    // Initialisiere MetisMenu
    $("#side-bar").metisMenu({
        activeClass: 'active'
    });

    // Klick auf eine .has-arrow (Kategorie)
    $(".has-arrow").on("click", function (e) {
        e.preventDefault();  // Verhindert das Standardverhalten des Links

        // Toggle die 'active' Klasse für das aktuelle .has-arrow Element
        $(this).toggleClass("active");

        // Wenn die Kategorie aktiv ist, öffne das Untermenü
        if ($(this).hasClass("active")) {
            $(this).next(".nav-third-level").slideDown();  // Untermenü öffnen
        } else {
            $(this).next(".nav-third-level").slideUp();  // Untermenü schließen
        }

        // Schließe alle anderen Untermenüs und entferne 'active' von anderen Kategorien
        $(".has-arrow").not(this).removeClass("active").next(".nav-third-level").slideUp();
    });

    // Klick auf einen Link im Untermenü
    $(".nav-third-level a").on("click", function () {
        // Navigiere zum Link
        window.location.href = $(this).attr("href");
    });

    // Überprüfe, ob eine Kategorie beim Laden der Seite aktiv sein soll
    var url = window.location.href;
    $("ul.nav a").each(function () {
        // Vergleiche die URL des Links mit der aktuellen URL
        if (this.href === url) {
            // Finde das übergeordnete .has-arrow Element des Links
            var element = $(this).closest(".has-arrow");

            // Markiere das Element als aktiv und öffne das zugehörige Untermenü
            element.addClass("active");
            element.next(".nav-third-level").slideDown();  // Das Untermenü öffnen

            // Optional: Markiere auch das linke Menü-Element als aktiv
            $(this).addClass("active");
        }
    });
});

$(function () {
    var url = window.location.href; // Aktuelle URL der Seite

    // Überprüfe, ob eine Kategorie beim Laden der Seite aktiv sein soll
    $("ul.nav a").each(function () {
        // Vergleiche die URL des Links mit der aktuellen URL
        if (this.href === url) {
            // Finde das übergeordnete .has-arrow Element des Links
            var element = $(this).closest(".has-arrow");

            // Markiere das Element als aktiv und öffne das zugehörige Untermenü
            element.addClass("active");
            element.next(".nav-third-level").slideDown();  // Das Untermenü öffnen

            // Optional: Markiere auch das linke Menü-Element als aktiv
            $(this).addClass("active");
        }
    });
});

/*
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
*/