
/**
 * NxEditor Lazy Loader
 * Initialisiert NxEditor nur, wenn <textarea data-editor="nx_editor"> existiert
 */
document.addEventListener('DOMContentLoaded', function () {

    const editors = document.querySelectorAll('textarea[data-editor="nx_editor"]');
    if (editors.length === 0) return;

    // NxEditor vorhanden?
    if (!window.NxEditor) {
        console.error('NxEditor nicht geladen');
        return;
    }

    initNxEditors(editors);
});

/**
 * Initialisiert alle markierten Textareas als NxEditor-Instanzen
 */
function initNxEditors(editors) {
    editors.forEach(textarea => {

        // ID erzwingen (wie bei CKEditor)
        if (!textarea.id) {
            textarea.id = 'nx_editor_' + Math.random().toString(36).substr(2, 8);
        }

        // Doppelte Initialisierung verhindern
        if (!NxEditor.instances.has(textarea)) {
            NxEditor.init(textarea);
        }
    });
}
