/**
 * @license Copyright (c) 2003-2020, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function( config ) {
	config.codeSnippet_theme = 'school_book';
    config.enterMode = CKEDITOR.ENTER_BR; // <br /> bei Enter
    config.shiftEnterMode = CKEDITOR.ENTER_P; // <p> bei Shift + Enter
    config.autoParagraph = false; // Verhindert automatische <p>-Tags
    config.entities = false; // Verhindert HTML Entities wie &lt;br /&gt;
    config.basicEntities = false; // Verhindert grundlegende Entitäten wie &lt; und &gt;
    config.encodeEntities = false; // Verhindert die Kodierung von HTML-Zeichen
		config.removeButtons = 'Source';


};
