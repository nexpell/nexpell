/**
 * ==========================================================
 * NEXPELL CORE JS
 * - NxEditor
 * - Multi Language Editor
 * - Tooltips
 * ==========================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    initNxEditors();
    initLangEditors();
    initLangPaneEditors();
    initLangValueSync();
    initTooltips();
});

/* ==========================================================
   NX EDITOR
========================================================== */

window.NxEditor = {

    instances: new Map(),
    imageModalElements: null,
    linkModalElements: null,

    getLocale() {
        const htmlLang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
        if (htmlLang.startsWith('de')) return 'de';
        if (htmlLang.startsWith('it')) return 'it';
        return 'en';
    },

    getI18n() {
        const locale = this.getLocale();
        const messages = {
            de: {
                title: 'Bildeinstellungen',
                intro: 'Groesse, optionalen Link und Alternativtext an einer Stelle festlegen.',
                width: 'Breite',
                widthPlaceholder: '300 oder 100%',
                height: 'Hoehe',
                heightPlaceholder: '200 oder auto',
                link: 'Link',
                linkPlaceholder: 'https://example.com',
                alt: 'Alt-Text',
                altPlaceholder: 'Beschreibung',
                cancel: 'Abbrechen',
                apply: 'Uebernehmen',
                linkTitle: 'Link einfuegen',
                linkIntro: 'URL und Linktext gemeinsam festlegen.',
                linkUrl: 'Link',
                linkUrlPlaceholder: 'https://example.com',
                linkText: 'Linktext',
                linkTextPlaceholder: 'Name des Links'
            },
            en: {
                title: 'Image settings',
                intro: 'Set image size, optional link and alt text in one place.',
                width: 'Width',
                widthPlaceholder: '300 or 100%',
                height: 'Height',
                heightPlaceholder: '200 or auto',
                link: 'Link',
                linkPlaceholder: 'https://example.com',
                alt: 'Alt text',
                altPlaceholder: 'Description',
                cancel: 'Cancel',
                apply: 'Apply',
                linkTitle: 'Insert link',
                linkIntro: 'Set URL and link text in one place.',
                linkUrl: 'Link',
                linkUrlPlaceholder: 'https://example.com',
                linkText: 'Link text',
                linkTextPlaceholder: 'Name of the link'
            },
            it: {
                title: 'Impostazioni immagine',
                intro: 'Imposta dimensione, link opzionale e testo alternativo in un unico punto.',
                width: 'Larghezza',
                widthPlaceholder: '300 o 100%',
                height: 'Altezza',
                heightPlaceholder: '200 o auto',
                link: 'Link',
                linkPlaceholder: 'https://example.com',
                alt: 'Testo alt',
                altPlaceholder: 'Descrizione',
                cancel: 'Annulla',
                apply: 'Applica',
                linkTitle: 'Inserisci link',
                linkIntro: 'Imposta URL e testo del link in un unico punto.',
                linkUrl: 'Link',
                linkUrlPlaceholder: 'https://example.com',
                linkText: 'Testo del link',
                linkTextPlaceholder: 'Nome del link'
            }
        };

        return messages[locale] || messages.en;
    },

    ensureImageModal() {
        if (this.imageModalElements) return this.imageModalElements;

        const i18n = this.getI18n();
        const host = document.createElement('div');
        host.innerHTML = `
            <div class="modal fade nx-editor-image-modal" id="nxEditorImageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content nx-editor-image-modal__content">
                        <div class="modal-header nx-editor-image-modal__header">
                            <h5 class="modal-title">${i18n.title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body nx-editor-image-modal__body">
                            <div class="nx-editor-image-modal__intro">
                                ${i18n.intro}
                            </div>
                            <div class="nx-editor-image-modal__grid">
                                <div>
                                    <label class="form-label nx-editor-image-modal__label" for="nx-editor-image-width">${i18n.width}</label>
                                    <input type="text" class="form-control nx-editor-image-modal__input" id="nx-editor-image-width" placeholder="${i18n.widthPlaceholder}">
                                </div>
                                <div>
                                    <label class="form-label nx-editor-image-modal__label" for="nx-editor-image-height">${i18n.height}</label>
                                    <input type="text" class="form-control nx-editor-image-modal__input" id="nx-editor-image-height" placeholder="${i18n.heightPlaceholder}">
                                </div>
                                <div>
                                    <label class="form-label nx-editor-image-modal__label" for="nx-editor-image-link">${i18n.link}</label>
                                    <input type="text" class="form-control nx-editor-image-modal__input" id="nx-editor-image-link" placeholder="${i18n.linkPlaceholder}">
                                </div>
                                <div>
                                    <label class="form-label nx-editor-image-modal__label" for="nx-editor-image-alt">${i18n.alt}</label>
                                    <input type="text" class="form-control nx-editor-image-modal__input" id="nx-editor-image-alt" placeholder="${i18n.altPlaceholder}">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer nx-editor-image-modal__footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">${i18n.cancel}</button>
                            <button type="button" class="btn btn-primary" data-role="save">${i18n.apply}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const modalElement = host.firstElementChild;
        document.body.appendChild(modalElement);

        this.imageModalElements = {
            modalElement,
            width: modalElement.querySelector('#nx-editor-image-width'),
            height: modalElement.querySelector('#nx-editor-image-height'),
            link: modalElement.querySelector('#nx-editor-image-link'),
            alt: modalElement.querySelector('#nx-editor-image-alt'),
            save: modalElement.querySelector('[data-role="save"]')
        };

        return this.imageModalElements;
    },

    ensureLinkModal() {
        if (this.linkModalElements) return this.linkModalElements;

        const i18n = this.getI18n();
        const host = document.createElement('div');
        host.innerHTML = `
            <div class="modal fade nx-editor-link-modal" id="nxEditorLinkModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content nx-editor-link-modal__content">
                        <div class="modal-header nx-editor-link-modal__header">
                            <h5 class="modal-title">${i18n.linkTitle}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body nx-editor-link-modal__body">
                            <div class="nx-editor-link-modal__intro">${i18n.linkIntro}</div>
                            <div class="nx-editor-link-modal__stack">
                                <div>
                                    <label class="form-label nx-editor-link-modal__label" for="nx-editor-link-url">${i18n.linkUrl}</label>
                                    <input type="text" class="form-control nx-editor-link-modal__input" id="nx-editor-link-url" placeholder="${i18n.linkUrlPlaceholder}">
                                </div>
                                <div>
                                    <label class="form-label nx-editor-link-modal__label" for="nx-editor-link-text">${i18n.linkText}</label>
                                    <input type="text" class="form-control nx-editor-link-modal__input" id="nx-editor-link-text" placeholder="${i18n.linkTextPlaceholder}">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer nx-editor-link-modal__footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">${i18n.cancel}</button>
                            <button type="button" class="btn btn-primary" data-role="save">${i18n.apply}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const modalElement = host.firstElementChild;
        document.body.appendChild(modalElement);

        this.linkModalElements = {
            modalElement,
            url: modalElement.querySelector('#nx-editor-link-url'),
            text: modalElement.querySelector('#nx-editor-link-text'),
            save: modalElement.querySelector('[data-role="save"]')
        };

        return this.linkModalElements;
    },

    extractManagedImageUrls(html) {
        const value = typeof html === 'string' ? html : '';
        const matches = value.match(/\/images\/uploads\/nx_editor\/[^\s"'<>]+/g) || [];
        return Array.from(new Set(matches));
    },

    init(textarea) {
        if (this.instances.has(textarea)) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'nx-editor';
        wrapper.innerHTML = `
            <div class="nx-editor-toolbar">
                <select data-cmd="formatBlock" data-tooltip="Text structure">
                    <option value="p">Paragraph</option>
                    <option value="h1">Heading 1</option>
                    <option value="h2">Heading 2</option>
                </select>

                <select data-cmd="fontSize" data-tooltip="Font size">
                    <option value="3">Normal</option>
                    <option value="2">Small</option>
                    <option value="4">Large</option>
                </select>

                <input type="color" data-cmd="foreColor" value="#000000" data-tooltip="Text color">
                <input type="color" data-cmd="hiliteColor" value="#ffffff" data-tooltip="Highlight color">

                <button type="button" data-cmd="bold" data-tooltip="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" data-tooltip="Italic"><i>I</i></button>
                <button type="button" data-cmd="underline" data-tooltip="Underline"><u>U</u></button>
                <button type="button" data-cmd="strikeThrough" data-tooltip="Strike"><s>S</s></button>
                <button type="button" data-cmd="inlineCode" data-tooltip="Inline code">&lt;code&gt;</button>
                <button type="button" data-cmd="codeBlock" data-tooltip="Code block">&lt;pre&gt;</button>

                <button type="button" data-cmd="createLink" data-tooltip="Insert link">Link</button>
                <button type="button" data-cmd="insertUnorderedList" data-tooltip="Bullet list">•</button>
                <button type="button" data-cmd="insertOrderedList" data-tooltip="Numbered list">1-2-3</button>

                <button type="button" data-cmd="insertImage" data-tooltip="Insert image">Img</button>
                <button type="button" data-cmd="toggleSource" data-tooltip="Toggle HTML source">HTML</button>
            </div>
            <div class="nx-editor-content" contenteditable="true"></div>
            <textarea class="nx-editor-source"></textarea>
            <input type="file" class="nx-editor-image-input" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none;">
        `;

        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

        const editor = wrapper.querySelector('.nx-editor-content');
        const source = wrapper.querySelector('.nx-editor-source');
        const imageInput = wrapper.querySelector('.nx-editor-image-input');
        editor.innerHTML = textarea.value;
        source.value = textarea.value;
        const trackedManagedImages = new Set(this.extractManagedImageUrls(textarea.value));

        let sourceMode = false;

        const syncFromEditor = () => {
            source.value = editor.innerHTML;
        };

        const syncFromSource = () => {
            editor.innerHTML = source.value;
        };

        const setSourceMode = enabled => {
            sourceMode = enabled;
            wrapper.classList.toggle('is-source-mode', enabled);

            if (enabled) {
                syncFromEditor();
                source.focus();
            } else {
                syncFromSource();
                editor.focus();
            }
        };

        const getCsrfToken = () => {
            const form = textarea.form || wrapper.closest('form');
            const hidden = form?.querySelector('input[name="csrf_token"]');
            if (hidden?.value) return hidden.value;

            const cookieMatch = document.cookie.match(/(?:^|; )NXCSRF=([^;]+)/);
            return cookieMatch ? decodeURIComponent(cookieMatch[1]) : '';
        };

        const collectReferencedHtml = currentHtml => {
            const htmlParts = [typeof currentHtml === 'string' ? currentHtml : ''];
            const langEditor = wrapper.closest('.nx-lang-editor');

            if (langEditor) {
                langEditor.querySelectorAll('[id^="content_"]').forEach(field => {
                    if (field === textarea) return;
                    if ('value' in field && typeof field.value === 'string') {
                        htmlParts.push(field.value);
                    }
                });
            }

            return htmlParts.join('\n');
        };

        const insertHtmlIntoSource = html => {
            const start = source.selectionStart ?? source.value.length;
            const end = source.selectionEnd ?? source.value.length;
            source.value = source.value.slice(0, start) + html + source.value.slice(end);
            source.focus();
            source.selectionStart = source.selectionEnd = start + html.length;
        };

        const escapeHtml = value => String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const wrapSelectionWithTag = tagName => {
            if (sourceMode) {
                const selected = source.value.slice(source.selectionStart ?? 0, source.selectionEnd ?? 0) || 'code';
                insertHtmlIntoSource(`<${tagName}>${selected}</${tagName}>`);
                syncFromSource();
                return;
            }

            const selection = window.getSelection ? window.getSelection() : null;
            if (!selection || selection.rangeCount === 0) return;

            const range = selection.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) return;

            const node = document.createElement(tagName);
            node.textContent = range.toString() || 'code';

            range.deleteContents();
            range.insertNode(node);

            range.setStartAfter(node);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            syncFromEditor();
        };

        const insertCodeBlock = () => {
            if (sourceMode) {
                const selected = source.value.slice(source.selectionStart ?? 0, source.selectionEnd ?? 0) || 'code';
                insertHtmlIntoSource(`<pre><code>${escapeHtml(selected)}</code></pre>`);
                syncFromSource();
                return;
            }

            const selection = window.getSelection ? window.getSelection() : null;
            if (!selection || selection.rangeCount === 0) return;

            const range = selection.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) return;

            const pre = document.createElement('pre');
            const code = document.createElement('code');
            code.textContent = range.toString() || 'code';
            pre.appendChild(code);

            range.deleteContents();
            range.insertNode(pre);

            const nextRange = document.createRange();
            nextRange.setStartAfter(pre);
            nextRange.collapse(true);
            selection.removeAllRanges();
            selection.addRange(nextRange);
            syncFromEditor();
        };

        const buildImageHtml = (url, options = {}) => {
            if (!url) return '';

            const width = (options.width || '').trim();
            const height = (options.height || '').trim();
            const link = (options.link || '').trim();
            const alt = (options.alt || '').trim();

            const attrs = [`src="${url}"`, `alt="${alt.replace(/"/g, '&quot;')}"`];
            const styleParts = [];

            if (width) {
                styleParts.push(`width:${/^\d+$/.test(width) ? width + 'px' : width}`);
            }

            if (height) {
                styleParts.push(`height:${/^\d+$/.test(height) ? height + 'px' : height}`);
            }

            if (styleParts.length) {
                attrs.push(`style="${styleParts.join(';')}"`);
            }

            let html = `<img ${attrs.join(' ')}>`;
            if (link) {
                html = `<a href="${link}">${html}</a>`;
            }

            return html;
        };

        const askImageOptions = (initial = {}) => {
            return new Promise(resolve => {
                if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    resolve(null);
                    return;
                }

                const modalParts = this.ensureImageModal();
                const modal = bootstrap.Modal.getOrCreateInstance(modalParts.modalElement);

                modalParts.width.value = initial.width || '';
                modalParts.height.value = initial.height || '';
                modalParts.link.value = initial.link || '';
                modalParts.alt.value = initial.alt || '';

                let settled = false;

                const cleanup = result => {
                    if (settled) return;
                    settled = true;
                    modalParts.save.removeEventListener('click', onSave);
                    modalParts.modalElement.removeEventListener('hidden.bs.modal', onHidden);
                    resolve(result);
                };

                const onSave = () => {
                    cleanup({
                        width: modalParts.width.value,
                        height: modalParts.height.value,
                        link: modalParts.link.value,
                        alt: modalParts.alt.value
                    });
                    modal.hide();
                };

                const onHidden = () => cleanup(null);

                modalParts.save.addEventListener('click', onSave);
                modalParts.modalElement.addEventListener('hidden.bs.modal', onHidden, { once: true });
                modal.show();
                setTimeout(() => modalParts.width.focus(), 100);
            });
        };

        const askLinkOptions = (initial = {}) => {
            return new Promise(resolve => {
                if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    resolve(null);
                    return;
                }

                const modalParts = this.ensureLinkModal();
                const modal = bootstrap.Modal.getOrCreateInstance(modalParts.modalElement);

                modalParts.url.value = initial.url || '';
                modalParts.text.value = initial.text || '';

                let settled = false;

                const cleanup = result => {
                    if (settled) return;
                    settled = true;
                    modalParts.save.removeEventListener('click', onSave);
                    modalParts.modalElement.removeEventListener('hidden.bs.modal', onHidden);
                    resolve(result);
                };

                const onSave = () => {
                    cleanup({
                        url: modalParts.url.value,
                        text: modalParts.text.value
                    });
                    modal.hide();
                };

                const onHidden = () => cleanup(null);

                modalParts.save.addEventListener('click', onSave);
                modalParts.modalElement.addEventListener('hidden.bs.modal', onHidden, { once: true });
                modal.show();
                setTimeout(() => modalParts.url.focus(), 100);
            });
        };

        const getImageOptionsFromElement = image => {
            const linkNode = image.closest('a');
            return {
                width: image.style.width || image.getAttribute('width') || '',
                height: image.style.height || image.getAttribute('height') || '',
                link: linkNode ? (linkNode.getAttribute('href') || '') : '',
                alt: image.getAttribute('alt') || ''
            };
        };

        const updateImageElement = (image, options = {}) => {
            if (!image) return;

            const width = (options.width || '').trim();
            const height = (options.height || '').trim();
            const link = (options.link || '').trim();
            const alt = (options.alt || '').trim();

            image.setAttribute('alt', alt);

            if (width) {
                image.style.width = /^\d+$/.test(width) ? `${width}px` : width;
            } else {
                image.style.removeProperty('width');
            }

            if (height) {
                image.style.height = /^\d+$/.test(height) ? `${height}px` : height;
            } else {
                image.style.removeProperty('height');
            }

            const currentLink = image.closest('a');
            if (link) {
                if (currentLink) {
                    currentLink.setAttribute('href', link);
                } else {
                    const anchor = document.createElement('a');
                    anchor.setAttribute('href', link);
                    image.parentNode.insertBefore(anchor, image);
                    anchor.appendChild(image);
                }
            } else if (currentLink) {
                currentLink.parentNode.insertBefore(image, currentLink);
                currentLink.remove();
            }
        };

        const insertImageAtCursor = (url, options = {}) => {
            if (!url) return;
            trackedManagedImages.add(url);
            const html = buildImageHtml(url, options);
            if (!html) return;

            if (sourceMode) {
                insertHtmlIntoSource(html);
                syncFromSource();
                return;
            }

            editor.focus();
            document.execCommand('insertHTML', false, html);
            syncFromEditor();
        };

        const uploadImage = async file => {
            const formData = new FormData();
            formData.append('image', file);

            const csrfToken = getCsrfToken();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            const response = await fetch('/components/upload_nx_editor_image.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.success || !payload.url) {
                throw new Error(payload.message || 'Upload failed');
            }

            return payload.url;
        };

        const deleteRemovedImages = html => {
            const referencedHtml = collectReferencedHtml(html);
            const currentManagedImages = this.extractManagedImageUrls(referencedHtml);
            const removedImages = Array.from(trackedManagedImages).filter(url => !currentManagedImages.includes(url));
            if (!removedImages.length) return;

            const formData = new FormData();
            removedImages.forEach(url => formData.append('urls[]', url));

            const csrfToken = getCsrfToken();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            if (navigator.sendBeacon) {
                navigator.sendBeacon('/components/delete_nx_editor_images.php', formData);
                return;
            }

            fetch('/components/delete_nx_editor_images.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                keepalive: true
            }).catch(() => {});
        };

        wrapper.querySelectorAll('[data-cmd]').forEach(btn => {
            btn.addEventListener('click', async e => {
                e.preventDefault();
                const cmd = btn.dataset.cmd;

                if (cmd === 'toggleSource') {
                    setSourceMode(!sourceMode);
                    btn.classList.toggle('active', sourceMode);
                    return;
                }

                if (cmd === 'createLink') {
                    const selectedText = sourceMode
                        ? (source.value.slice(source.selectionStart ?? 0, source.selectionEnd ?? 0) || '')
                        : (window.getSelection ? String(window.getSelection()) : '');
                    const options = await askLinkOptions({ url: 'https://', text: selectedText });
                    if (!options || !options.url || !options.text) return;

                    if (sourceMode) {
                        insertHtmlIntoSource(`<a href="${options.url}">${options.text}</a>`);
                        syncFromSource();
                        return;
                    }

                    editor.focus();
                    document.execCommand('styleWithCSS', false, true);
                    document.execCommand('insertHTML', false, `<a href="${options.url}">${options.text}</a>`);
                    syncFromEditor();
                    return;
                }

                if (cmd === 'inlineCode') {
                    wrapSelectionWithTag('code');
                    return;
                }

                if (cmd === 'codeBlock') {
                    insertCodeBlock();
                    return;
                }

                if (cmd === 'insertImage') {
                    imageInput.click();
                    return;
                }

                if (sourceMode) return;

                editor.focus();
                document.execCommand('styleWithCSS', false, true);
                document.execCommand(cmd, false, null);
                syncFromEditor();
            });
        });

        imageInput.addEventListener('change', async () => {
            const file = imageInput.files?.[0];
            imageInput.value = '';
            if (!file) return;

            try {
                const url = await uploadImage(file);
                const options = await askImageOptions();
                if (!options) return;
                insertImageAtCursor(url, options);
            } catch (error) {
                alert('Image upload failed.');
            }
        });

        editor.addEventListener('dblclick', async event => {
            const image = event.target.closest('img');
            if (!image || !editor.contains(image)) return;

            event.preventDefault();

            const options = await askImageOptions(getImageOptionsFromElement(image));
            if (!options) return;

            updateImageElement(image, options);
            syncFromEditor();
        });

        editor.addEventListener('input', syncFromEditor);
        source.addEventListener('input', syncFromSource);

        textarea.form?.addEventListener('submit', () => {
            textarea.value = sourceMode ? source.value : editor.innerHTML;
            deleteRemovedImages(textarea.value);
        });

        this.instances.set(textarea, { wrapper, editor, source });
    },

    destroy(textarea) {
        const instance = this.instances.get(textarea);
        if (!instance) return;

        textarea.value = instance.source && instance.source.style.display !== 'none'
            ? instance.source.value
            : instance.editor.innerHTML;

        instance.wrapper.remove();
        textarea.style.display = '';
        this.instances.delete(textarea);
    }
};

function initNxEditors() {
    document.querySelectorAll('textarea[data-editor="nx_editor"]').forEach(textarea => {
        NxEditor.init(textarea);
    });
}

/* ==========================================================
   LANGUAGE EDITOR (GLOBAL AND SCALABLE)
========================================================== */

function initLangEditors() {

    document.querySelectorAll('.nx-lang-editor').forEach(container => {

        const textarea = container.querySelector('#nx-editor-main');
        const langSwitch = container.querySelector('#lang-switch');
        const activeLangInput = container.querySelector('#active_lang');
        const titleInput = container.querySelector('#nx-title-input');
        const form = container.closest('form');

        if (!textarea || !langSwitch || !activeLangInput) return;

        function getContent() {
            const instance = NxEditor.instances.get(textarea);
            if (!instance) return textarea.value;
            return instance.source.style.display !== 'none'
                ? instance.source.value
                : instance.editor.innerHTML;
        }

        function setContent(val) {
            const instance = NxEditor.instances.get(textarea);
            if (instance) {
                instance.editor.innerHTML = val;
                instance.source.value = val;
            } else {
                textarea.value = val;
            }
        }

        function updateDateDisplay(lang) {
            const dateSpan = container.querySelector('#last-update-text');
            if (!dateSpan) return;

            if (typeof lastUpdateByLang !== 'undefined' && lastUpdateByLang[lang]) {
                const raw = lastUpdateByLang[lang];
                const d = new Date(raw.replace(' ', 'T'));
                const pad = n => String(n).padStart(2, '0');

                dateSpan.textContent =
                    pad(d.getDate()) + '.' +
                    pad(d.getMonth() + 1) + '.' +
                    d.getFullYear() + ' ' +
                    pad(d.getHours()) + ':' +
                    pad(d.getMinutes());
            } else {
                dateSpan.textContent = '-';
            }
        }

        updateDateDisplay(activeLangInput.value);

        langSwitch.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', function () {
                const newLang = this.dataset.lang;
                const activeBtn = langSwitch.querySelector('.btn-primary');
                if (!activeBtn) return;

                const oldLang = activeBtn.dataset.lang;
                if (newLang === oldLang) return;

                const oldHidden = container.querySelector('#content_' + oldLang);
                if (oldHidden) oldHidden.value = getContent();

                if (titleInput) {
                    const oldTitleHidden = container.querySelector('#title_' + oldLang);
                    if (oldTitleHidden) oldTitleHidden.value = titleInput.value;
                }

                const newHidden = container.querySelector('#content_' + newLang);
                setContent(newHidden ? newHidden.value : '');

                if (titleInput) {
                    const newTitleHidden = container.querySelector('#title_' + newLang);
                    titleInput.value = newTitleHidden ? newTitleHidden.value : '';
                }

                updateDateDisplay(newLang);

                activeLangInput.value = newLang;

                activeBtn.classList.remove('btn-primary');
                activeBtn.classList.add('btn-secondary');

                this.classList.remove('btn-secondary');
                this.classList.add('btn-primary');
            });
        });

        if (form) {
            form.addEventListener('submit', function () {
                const activeBtn = langSwitch.querySelector('.btn-primary');
                if (!activeBtn) return;

                const currentLang = activeBtn.dataset.lang;
                const activeHidden = container.querySelector('#content_' + currentLang);
                if (activeHidden) activeHidden.value = getContent();

                if (titleInput) {
                    const activeTitle = container.querySelector('#title_' + currentLang);
                    if (activeTitle) activeTitle.value = titleInput.value;
                }
            });
        }

    });
}

/* ==========================================================
   BOOTSTRAP TOOLTIPS
========================================================== */

function initTooltips() {
    if (typeof bootstrap === 'undefined') return;

    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.setAttribute('data-bs-toggle', 'tooltip');
        el.setAttribute('data-bs-placement', 'top');
        el.setAttribute('data-bs-title', el.dataset.tooltip);
        new bootstrap.Tooltip(el);
    });
}

/* ==========================================================
   LANGUAGE PANE SWITCHER
========================================================== */

function initLangPaneEditors() {

    document.querySelectorAll('[data-nx-lang-pane]').forEach(container => {

        const langSwitch = container.querySelector('#lang-switch');
        const activeLangInput = container.querySelector('#active_lang');
        if (!langSwitch || !activeLangInput) return;

        const classPrefix = container.dataset.nxLangClassPrefix || 'lang-';
        const paneSelector = container.dataset.nxLangPaneSelector || '.lang-pane';

        function switchLang(lang) {
            activeLangInput.value = lang;

            container.querySelectorAll(paneSelector).forEach(el => {
                el.style.display = el.classList.contains(classPrefix + lang) ? '' : 'none';
            });

            langSwitch.querySelectorAll('button[data-lang]').forEach(btn => {
                const active = btn.dataset.lang === lang;
                btn.classList.toggle('btn-primary', active);
                btn.classList.toggle('btn-secondary', !active);
            });
        }

        langSwitch.querySelectorAll('button[data-lang]').forEach(btn => {
            btn.addEventListener('click', () => {
                const next = btn.dataset.lang;
                if (next) switchLang(next);
            });
        });

        switchLang(activeLangInput.value || 'de');
    });
}

/* ==========================================================
   LANGUAGE VALUE SYNC (MAIN <-> HIDDEN BY LANG)
========================================================== */

function initLangValueSync() {

    document.querySelectorAll('[data-nx-lang-hidden-prefix]').forEach(mainInput => {

        const scopeSelector = mainInput.dataset.nxLangScope || '';
        const scope = scopeSelector ? document.querySelector(scopeSelector) : (mainInput.closest('form') || document);
        if (!scope) return;

        const switchSelector = mainInput.dataset.nxLangSwitch || '#lang-switch';
        const activeSelector = mainInput.dataset.nxLangActive || '#active_lang';
        const hiddenPrefix = mainInput.dataset.nxLangHiddenPrefix || '';
        if (!hiddenPrefix) return;

        const langSwitch = scope.querySelector(switchSelector) || document.querySelector(switchSelector);
        const activeLangInput = scope.querySelector(activeSelector) || document.querySelector(activeSelector);
        if (!langSwitch || !activeLangInput) return;

        let current = activeLangInput.value || 'de';

        function getMainValue() {
            return typeof mainInput.value === 'string' ? mainInput.value : '';
        }

        function setMainValue(value) {
            if (typeof mainInput.value === 'string') {
                mainInput.value = value;
            }
        }

        function hiddenByLang(lang) {
            return scope.querySelector('#' + hiddenPrefix + lang) || document.getElementById(hiddenPrefix + lang);
        }

        function applyButtons() {
            langSwitch.querySelectorAll('button[data-lang]').forEach(btn => {
                const active = btn.dataset.lang === current;
                btn.classList.toggle('btn-primary', active);
                btn.classList.toggle('btn-secondary', !active);
            });
        }

        function switchLang(next) {
            if (!next) return;

            const currentHidden = hiddenByLang(current);
            if (currentHidden) currentHidden.value = getMainValue();

            current = next;
            activeLangInput.value = next;

            const nextHidden = hiddenByLang(next);
            setMainValue(nextHidden ? nextHidden.value : '');
            applyButtons();
        }

        langSwitch.querySelectorAll('button[data-lang]').forEach(btn => {
            btn.addEventListener('click', () => {
                const next = btn.dataset.lang;
                if (!next || next === current) return;
                switchLang(next);
            });
        });

        const form = mainInput.closest('form');
        if (form) {
            form.addEventListener('submit', () => {
                const currentHidden = hiddenByLang(current);
                if (currentHidden) currentHidden.value = getMainValue();
            });
        }

        switchLang(current);
    });
}
