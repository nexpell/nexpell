document.addEventListener('DOMContentLoaded', function () {
    if (!window.CKEDITOR) return;

    // Alle Textareas mit Klasse 'ckeditor' ersetzen
    document.querySelectorAll('textarea.ckeditor').forEach(el => {
        // Prüfen, ob bereits eine Instanz existiert
        if (!CKEDITOR.instances[el.id || el.name]) {
            CKEDITOR.replace(el.id || el.name, {
                customConfig: '/components/ckeditor/config.js',
                removePlugins: 'exportpdf', // PDF-Plugin deaktivieren, um den Token-Fehler zu vermeiden
            });
        }
    });
});
