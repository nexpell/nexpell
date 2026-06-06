/**
 * @license Copyright (c) 2003-2020, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function(config) {

    config.enterMode = CKEDITOR.ENTER_BR;
    config.shiftEnterMode = CKEDITOR.ENTER_BR;

    config.autoParagraph = false;
    config.fillEmptyBlocks = false;

    config.entities = false;
    config.basicEntities = false;
    config.encodeEntities = false;
    config.forceSimpleAmpersand = true;

    config.allowedContent = true;

    // ❗ extrem wichtig
    config.formatOutput = false;
};



//CKEDITOR.replace('message');