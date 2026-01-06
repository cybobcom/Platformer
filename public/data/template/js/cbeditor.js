/**
 * CBEditor - Minimal WYSIWYG Editor
 * Features: Bold, Italic, Underline, Headings (H1-H6), Lists, Links, Images
 * Line breaks: <br> only (no <p> wrapping)
 * Multiple instances supported
 */
class CBEditor {
    constructor(selector, options = {}) {
        this.element = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!this.element) {
            console.error('CBEditor: Element not found', selector);
            return;
        }

        this.options = {
            height: options.height || '300px',
            placeholder: options.placeholder || 'Start typing...',
            ...options
        };

        this.id = 'cbeditor-' + Math.random().toString(36).substr(2, 9);
        this.sourceMode = false;
        this.init();
    }

    init() {
        // Check if element is textarea
        if (this.element.tagName !== 'TEXTAREA') {
            console.error('CBEditor: Element must be a textarea', this.element);
            return;
        }

        // Store original display and styling
        this.originalDisplay = this.element.style.display;

        // Get initial content
        const initialContent = this.element.value || '';

        // Create wrapper
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'cbeditor-wrapper';
        this.wrapper.id = this.id;

        // Create toolbar
        this.toolbar = this.createToolbar();
        this.wrapper.appendChild(this.toolbar);

        // Create editor area
        this.editor = document.createElement('div');
        this.editor.className = 'cbeditor-content';
        this.editor.contentEditable = true;
        this.editor.style.minHeight = this.options.height;
        this.editor.setAttribute('data-placeholder', this.options.placeholder);
        this.editor.innerHTML = initialContent;

        this.wrapper.appendChild(this.editor);

        // Insert wrapper before textarea
        this.element.parentNode.insertBefore(this.wrapper, this.element);

        // Style original textarea for source mode
        this.element.style.fontFamily = 'monospace';
        this.element.style.fontSize = '14px';
        this.element.style.minHeight = this.options.height;
        this.element.style.display = 'none';
        this.element.classList.add('cbeditor-source');

        // Bind events
        this.bindEvents();
    }

    createToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'cbeditor-toolbar sticky-top bg-light border-bottom p-2';

        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group btn-group-sm me-2';
        btnGroup.setAttribute('role', 'group');

        // Bold
        btnGroup.appendChild(this.createButton('bold', 'bi-type-bold', 'Bold'));

        // Italic
        btnGroup.appendChild(this.createButton('italic', 'bi-type-italic', 'Italic'));

        // Underline
        btnGroup.appendChild(this.createButton('underline', 'bi-type-underline', 'Underline'));

        toolbar.appendChild(btnGroup);

        // Heading dropdown
        const headingGroup = document.createElement('div');
        headingGroup.className = 'btn-group btn-group-sm me-2';
        headingGroup.innerHTML = `
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-type-h1"></i> Heading
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" data-command="heading" data-value="h1">H1</a></li>
                <li><a class="dropdown-item" href="#" data-command="heading" data-value="h2">H2</a></li>
                <li><a class="dropdown-item" href="#" data-command="heading" data-value="h3">H3</a></li>
                <li><a class="dropdown-item" href="#" data-command="heading" data-value="h4">H4</a></li>
                <li><a class="dropdown-item" href="#" data-command="heading" data-value="h5">H5</a></li>
                <li><a class="dropdown-item" href="#" data-command="heading" data-value="h6">H6</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-command="heading" data-value="p">Normal</a></li>
            </ul>
        `;
        toolbar.appendChild(headingGroup);

        // Lists
        const listGroup = document.createElement('div');
        listGroup.className = 'btn-group btn-group-sm me-2';
        listGroup.appendChild(this.createButton('insertUnorderedList', 'bi-list-ul', 'Bullet List'));
        listGroup.appendChild(this.createButton('insertOrderedList', 'bi-list-ol', 'Numbered List'));
        toolbar.appendChild(listGroup);

        // Link & Image
        const mediaGroup = document.createElement('div');
        mediaGroup.className = 'btn-group btn-group-sm me-2';
        mediaGroup.appendChild(this.createButton('createLink', 'bi-link-45deg', 'Insert Link'));
        mediaGroup.appendChild(this.createButton('insertImage', 'bi-image', 'Insert Image'));
        toolbar.appendChild(mediaGroup);

        // Source Code Toggle
        const sourceGroup = document.createElement('div');
        sourceGroup.className = 'btn-group btn-group-sm';
        sourceGroup.appendChild(this.createButton('toggleSource', 'bi-code-slash', 'Toggle Source Code'));
        toolbar.appendChild(sourceGroup);

        return toolbar;
    }

    createButton(command, icon, title) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary';
        btn.title = title;
        btn.innerHTML = `<i class="bi ${icon}"></i>`;
        btn.dataset.command = command;
        return btn;
    }

    bindEvents() {
        // Toolbar clicks
        this.toolbar.addEventListener('click', (e) => {
            e.preventDefault();
            const btn = e.target.closest('[data-command]');
            if (!btn) return;

            const command = btn.dataset.command;
            const value = btn.dataset.value;

            this.executeCommand(command, value);
        });

        // Force BR on Enter
        this.editor.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.execCommand('insertHTML', false, '<br>');
            }
        });

        // Update hidden element on input
        this.editor.addEventListener('input', () => {
            this.updateValue();
        });

        // Keyboard shortcuts
        this.editor.addEventListener('keydown', (e) => {
            const isMac = /Mac|iPhone|iPod|iPad/.test(navigator.platform);
            const cmdKey = isMac ? e.metaKey : e.ctrlKey;

            if (cmdKey) {
                if (e.key === 'b' || e.key === 'B') {
                    e.preventDefault();
                    this.executeCommand('bold');
                    return;
                }
                if (e.key === 'i' || e.key === 'I') {
                    e.preventDefault();
                    this.executeCommand('italic');
                    return;
                }
                if (e.key === 'u' || e.key === 'U') {
                    e.preventDefault();
                    this.executeCommand('underline');
                    return;
                }
            }
        });

        // Clean paste - remove Word/CSS mess
        this.editor.addEventListener('paste', (e) => {
            e.preventDefault();

            // Get text from clipboard
            const clipboardData = e.clipboardData || window.clipboardData;
            const html = clipboardData.getData('text/html');
            const text = clipboardData.getData('text/plain');

            // If HTML available, clean it
            if (html) {
                const cleaned = this.cleanPastedHTML(html);
                document.execCommand('insertHTML', false, cleaned);
            } else {
                // Plain text - just insert and convert line breaks
                const cleanText = text.replace(/\r?\n/g, '<br>');
                document.execCommand('insertHTML', false, cleanText);
            }

            // Sanitize after paste
            setTimeout(() => {
                this.sanitizeContent();
                this.updateValue();
            }, 100);
        });

        // Prevent default formatBlock behavior
        this.editor.addEventListener('keydown', (e) => {
            // Prevent default paragraph creation
            if (e.key === 'Enter') {
                const selection = window.getSelection();
                const range = selection.getRangeAt(0);
                const container = range.commonAncestorContainer;
                const parentBlock = container.nodeType === 3 ? container.parentNode : container;

                // Check if we're in a heading
                if (parentBlock.tagName && /^H[1-6]$/.test(parentBlock.tagName)) {
                    e.preventDefault();
                    // Exit heading and insert BR
                    const br = document.createElement('br');
                    const afterBr = document.createElement('br');
                    range.deleteContents();
                    range.insertNode(afterBr);
                    range.insertNode(br);
                    range.setStartAfter(afterBr);
                    range.setEndAfter(afterBr);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }
        });
    }

    executeCommand(command, value = null) {
        this.editor.focus();

        if (command === 'toggleSource') {
            this.toggleSourceMode();
            return;
        }

        if (command === 'createLink') {
            const url = prompt('Enter URL:', 'https://');
            if (url) {
                document.execCommand(command, false, url);
            }
            return;
        }

        if (command === 'insertImage') {
            const url = prompt('Enter image URL:', 'https://');
            if (url) {
                document.execCommand(command, false, url);
            }
            return;
        }

        if (command === 'heading') {
            document.execCommand('formatBlock', false, value);
            return;
        }

        document.execCommand(command, false, value);
        this.updateValue();
    }

    updateValue() {
        let html = this.editor.innerHTML;

        // Clean up: Remove <div> and replace with <br>
        html = html.replace(/<div>/gi, '<br>');
        html = html.replace(/<\/div>/gi, '');

        // Clean up: Remove empty <p> tags
        html = html.replace(/<p><\/p>/gi, '');
        html = html.replace(/<p><br><\/p>/gi, '<br>');

        // Replace &nbsp; with regular spaces
        html = html.replace(/&nbsp;/g, ' ');

        // Update original textarea
        this.element.value = html;
    }

    getContent() {
        return this.editor.innerHTML;
    }

    setContent(html) {
        this.editor.innerHTML = html;
        this.updateValue();
    }

    sanitizeContent() {
        // Get current HTML
        let html = this.editor.innerHTML;

        // Create temporary div to parse and clean
        const temp = document.createElement('div');
        temp.innerHTML = html;

        // Remove all style and class attributes
        temp.querySelectorAll('[style]').forEach(el => el.removeAttribute('style'));
        temp.querySelectorAll('[class]').forEach(el => el.removeAttribute('class'));

        // Remove all span tags but keep content
        temp.querySelectorAll('span').forEach(span => {
            const parent = span.parentNode;
            while (span.firstChild) {
                parent.insertBefore(span.firstChild, span);
            }
            span.remove();
        });

        // Remove script tags
        temp.querySelectorAll('script').forEach(el => el.remove());

        // Only allow specific tags
        const allowedTags = ['B', 'STRONG', 'I', 'EM', 'U', 'A', 'BR', 'UL', 'OL', 'LI', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6'];
        const cleanNode = (node) => {
            const nodesToRemove = [];

            node.childNodes.forEach(child => {
                if (child.nodeType === 1) { // Element node
                    const tagName = child.tagName.toUpperCase();

                    if (allowedTags.includes(tagName)) {
                        // Keep allowed tag but clean its children
                        cleanNode(child);

                        // For links, keep only href
                        if (tagName === 'A') {
                            if (child.hasAttribute('href')) {
                                const href = child.getAttribute('href');
                                Array.from(child.attributes).forEach(attr => {
                                    if (attr.name !== 'href') {
                                        child.removeAttribute(attr.name);
                                    }
                                });
                            } else {
                                // Remove links without href
                                nodesToRemove.push(child);
                            }
                        } else {
                            // Remove all attributes from other allowed tags
                            Array.from(child.attributes).forEach(attr => {
                                child.removeAttribute(attr.name);
                            });
                        }
                    } else {
                        // Unwrap disallowed tags but keep content
                        nodesToRemove.push(child);
                    }
                }
            });

            // Unwrap unwanted nodes
            nodesToRemove.forEach(child => {
                while (child.firstChild) {
                    node.insertBefore(child.firstChild, child);
                }
                child.remove();
            });
        };

        cleanNode(temp);

        // Get cleaned HTML
        let cleaned = temp.innerHTML;

        // Replace &nbsp; with regular spaces
        cleaned = cleaned.replace(/&nbsp;/g, ' ');

        // Replace multiple spaces with single space
        cleaned = cleaned.replace(/\s{2,}/g, ' ');

        // Remove empty tags
        cleaned = cleaned.replace(/<(b|strong|i|em|u|a)>\s*<\/\1>/gi, '');

        // Update editor if content changed
        if (this.editor.innerHTML !== cleaned) {
            // Save cursor position
            const selection = window.getSelection();
            const range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;
            const cursorOffset = range ? range.startOffset : 0;
            const cursorNode = range ? range.startContainer : null;

            this.editor.innerHTML = cleaned;

            // Try to restore cursor position (best effort)
            if (cursorNode && this.editor.contains(cursorNode)) {
                try {
                    const newRange = document.createRange();
                    newRange.setStart(cursorNode, Math.min(cursorOffset, cursorNode.length));
                    newRange.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(newRange);
                } catch (e) {
                    // Cursor restoration failed, ignore
                }
            }
        }
    }

    cleanPastedHTML(html) {
        // Create temporary div to parse HTML
        const temp = document.createElement('div');
        temp.innerHTML = html;

        // Remove all style attributes and classes
        temp.querySelectorAll('[style]').forEach(el => el.removeAttribute('style'));
        temp.querySelectorAll('[class]').forEach(el => el.removeAttribute('class'));

        // Remove script tags
        temp.querySelectorAll('script').forEach(el => el.remove());

        // Walk through all nodes and keep only allowed tags
        const allowedTags = ['B', 'STRONG', 'I', 'EM', 'U', 'A', 'BR'];
        const cleanNode = (node) => {
            const nodesToRemove = [];

            node.childNodes.forEach(child => {
                if (child.nodeType === 1) { // Element node
                    const tagName = child.tagName.toUpperCase();

                    if (allowedTags.includes(tagName)) {
                        // Keep allowed tag but clean its children
                        cleanNode(child);

                        // For links, keep only href
                        if (tagName === 'A' && child.hasAttribute('href')) {
                            const href = child.getAttribute('href');
                            Array.from(child.attributes).forEach(attr => {
                                if (attr.name !== 'href') {
                                    child.removeAttribute(attr.name);
                                }
                            });
                        } else if (tagName === 'A') {
                            // Remove links without href
                            nodesToRemove.push(child);
                        }
                    } else {
                        // Unwrap tag but keep content
                        nodesToRemove.push(child);
                    }
                } else if (child.nodeType === 3) {
                    // Text node - keep
                }
            });

            // Remove/unwrap unwanted nodes
            nodesToRemove.forEach(child => {
                while (child.firstChild) {
                    node.insertBefore(child.firstChild, child);
                }
                child.remove();
            });
        };

        cleanNode(temp);

        // Convert block elements to br
        let cleaned = temp.innerHTML;
        cleaned = cleaned.replace(/<\/(p|div|h[1-6])>/gi, '<br>');
        cleaned = cleaned.replace(/<(p|div|h[1-6])[^>]*>/gi, '');

        // Remove multiple consecutive br tags
        cleaned = cleaned.replace(/(<br\s*\/?>\s*){3,}/gi, '<br><br>');

        return cleaned;
    }

    toggleSourceMode() {
        this.sourceMode = !this.sourceMode;

        if (this.sourceMode) {
            // Switch to source mode
            this.updateValue(); // Sync current editor content to textarea
            this.editor.style.display = 'none';
            this.element.style.display = 'block';
        } else {
            // Switch to WYSIWYG mode
            this.editor.innerHTML = this.element.value;
            this.element.style.display = 'none';
            this.editor.style.display = 'block';

            // Sanitize content from source
            this.sanitizeContent();
            this.updateValue();
        }
    }

    destroy() {
        this.wrapper.remove();
        this.element.style.display = this.originalDisplay || '';
        this.element.classList.remove('cbeditor-source');
    }
}