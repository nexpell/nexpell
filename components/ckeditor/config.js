/**
 * @license Copyright (c) 2003-2020, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

/*CKEDITOR.editorConfig = function(config) {
// ==============================
// Grundkonfiguration
// ==============================
config.codeSnippet_theme = 'school_book';
config.enterMode = CKEDITOR.ENTER_BR; // <br /> bei Enter
config.shiftEnterMode = CKEDITOR.ENTER_P; // <p> bei Shift+Enter
config.autoParagraph = false;
config.entities = false;
config.basicEntities = false;
config.encodeEntities = false;
config.forceSimpleAmpersand = true;
config.fillEmptyBlocks = false;
config.FormatOutput = false;
config.removePlugins = 'exportpdf,elementspath'; // PDF-Plugin deaktiviert
config.allowedContent = true; // Alle Inhalte erlaubt
config.resize_enabled = true; // Editorgröße veränderbar

// ==============================
// Sprache automatisch setzen
// ==============================
config.language = document.documentElement.lang || 'de';

// ==============================
// Toolbar
// ==============================
config.toolbar = [
    { name: 'clipboard', items: ['Undo','Redo'] },
    { name: 'basicstyles', items: ['Bold','Italic','Underline','Strike'] },
    { name: 'paragraph', items: ['NumberedList','BulletedList','-','Blockquote'] },
    { name: 'links', items: ['Link','Unlink'] },
    { name: 'insert', items: ['Image','Table','CodeSnippet','HorizontalRule','Smiley'] },
    { name: 'styles', items: ['Format','Font','FontSize'] },
    { name: 'colors', items: ['TextColor','BGColor'] },
    { name: 'tools', items: ['Maximize'] }
];

// ==============================
// Plugins
// ==============================
config.extraPlugins = 'codesnippet,uploadimage,uploadfile';
config.codeSnippet_languages = {
    javascript: 'JavaScript',
    php: 'PHP',
    css: 'CSS',
    html: 'HTML',
    sql: 'SQL'
};

// ==============================
// Upload Settings (Forum & Admin)
// ==============================
config.filebrowserUploadUrl = '/includes/plugins/forum/upload_image.php';
config.filebrowserUploadMethod = 'form';

};
*/
CKEDITOR.editorConfig = function( config ) {
    // Define changes to default configuration here. For example:
    // config.language = 'fr';
    // config.uiColor = '#AADC6E';
    config.codeSnippet_theme = 'school_book';
    //   config.enterMode = 2; //disabled <p> completely
        config.enterMode = CKEDITOR.ENTER_BR; // pressing the ENTER KEY input <br/>
        config.shiftEnterMode = CKEDITOR.ENTER_P; //pressing the SHIFT + ENTER KEYS input <p>
        config.autoParagraph = false; // stops automatic insertion of <p> on focus
        config.removePlugins = 'exportpdf,elementspath'; // PDF-Plugin deaktiviert
        config.removePlugins = 'exportpdf'; // PDF-Plugin deaktivieren
};


//CKEDITOR.replace('message');