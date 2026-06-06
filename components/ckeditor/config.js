/**
 * @license Copyright (c) 2003-2020, CKSource - Frederico Knabben.
 * CKEditor 4 – OSS
 */

CKEDITOR.editorConfig = function (config) {

    /* =========================================
       TEXT / FORMAT
    ========================================= */
    config.enterMode = CKEDITOR.ENTER_BR;
    config.shiftEnterMode = CKEDITOR.ENTER_BR;

    config.autoParagraph = false;
    config.fillEmptyBlocks = false;

    config.entities = false;
    config.basicEntities = false;
    config.encodeEntities = false;
    config.forceSimpleAmpersand = true;

    config.allowedContent = true;
    config.formatOutput = false;

    /* =========================================
       🔥 EXTREM WICHTIG: UPLOADS DEAKTIVIEREN
    ========================================= */
    config.removePlugins =
        'uploadimage,' +
        'uploadwidget,' +
        'filetools,' +
        'filebrowser';

    // Doppelte Absicherung
    config.filebrowserUploadUrl = '';
    config.imageUploadUrl = '';

};
CKEDITOR.on('instanceReady', function (evt) {

    const editor = evt.editor;

    // ❌ Drag & Drop INS Editor-Feld blockieren
    editor.on('contentDom', function () {

        editor.document.on('drop', function (e) {
            e.data.preventDefault(true);
            e.stop();
        });

        editor.document.on('paste', function (e) {
            const data = e.data.$.clipboardData || window.clipboardData;
            if (data && data.files && data.files.length > 0) {
                e.data.preventDefault(true);
                e.stop();
            }
        });

    });

});
