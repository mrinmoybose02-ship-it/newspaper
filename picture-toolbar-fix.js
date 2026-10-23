// ============================================================================
//  FIXED: Picture Toolbar (imgAlignToolbar) for CKEditor in admin_dashboard.php
// ============================================================================
//  Problem: After clicking "Save", the Picture Toolbar stopped showing / working
//           when an image was clicked inside CKEditor.
//
//  Root causes (all fixed below):
//   1. `selectionChange` listener was attached BEFORE CKEDITOR finished
//      initialising, so after a page reload (save -> edit) it never bound.
//   2. CKEditor's image2 / image widget plugin WRAPS <img> inside a
//      <span class="cke_image_widget..."> wrapper. getSelectedElement()
//      therefore returns the wrapper (not the <img>), so the toolbar stayed
//      hidden even when the user clicked directly on the image.
//   3. alignImg()'s fallback used `findOne(function(){...})` — invalid in
//      CKEditor 4 (findOne expects a CSS selector string, not a function).
//   4. After applying an alignment style the editor selection was lost, so
//      clicking a second toolbar button (e.g. zoom after align) failed.
//
//  HOW TO APPLY:
//   Open your admin_dashboard.php file. Find the JavaScript block that begins
//   with `// Show/Hide Custom Image Toolbar on Selection` and ends at the end
//   of the `function alignImg(type) { ... }` definition.
//   Replace that entire block with the code below.
// ============================================================================


    // ===== FIX 1+2+4: Reliable selection tracking =====
    // Global variable that always holds the currently-selected image element,
    // even after CKEditor internally re-creates the selection (which happens
    // when style changes are applied). This is what makes the toolbar keep
    // working across multiple button clicks.
    window.__selectedImg = null;

    // Helper: try every known way to extract the <img> from whatever the
    // user clicked. Handles: direct <img>, image widget wrapper, figure,
    // link-wrapped image, and selection inside a paragraph.
    function __findImgFromSelection(editor, selectedElement) {
        // 1) Direct <img>
        if (selectedElement && selectedElement.is && selectedElement.is('img')) {
            return selectedElement;
        }
        // 2) Widget wrapper / figure / link / div containing an <img>
        if (selectedElement && selectedElement.findOne) {
            var inner = selectedElement.findOne('img');
            if (inner) return inner;
        }
        // 3) Walk up the DOM from the selection start, looking for an <img>
        try {
            var ranges = editor.getSelection().getRanges();
            if (ranges && ranges.length > 0) {
                var node = ranges[0].startContainer;
                if (node && node.type == CKEDITOR.NODE_TEXT && node.getParent()) {
                    node = node.getParent();
                }
                for (var i = 0; i < 6 && node; i++) {
                    if (node.type == CKEDITOR.NODE_ELEMENT) {
                        if (node.is && node.is('img')) return node;
                        var found = node.findOne ? node.findOne('img') : null;
                        if (found) return found;
                    }
                    node = node.getParent ? node.getParent() : null;
                }
            }
        } catch (err) {}
        return null;
    }

    // Bind the selection listener. Called both on instanceReady AND right now
    // (covers the case where instanceReady already fired during CKEDITOR.replace).
    function __bindImgSelectionListener(editor) {
        if (!editor || editor.__imgListenerBound) return;
        editor.__imgListenerBound = true;

        editor.on('selectionChange', function(e) {
            var ed = e.editor;
            var sel = ed.getSelection();
            var selectedElement = sel ? sel.getSelectedElement() : null;
            var img = __findImgFromSelection(ed, selectedElement);

            var toolbar = document.getElementById('imgAlignToolbar');
            if (!toolbar) return;

            if (img) {
                window.__selectedImg = img;
                toolbar.style.display = 'flex';
            } else {
                // Don't clear __selectedImg here — the user may have just
                // clicked a toolbar button (which moves focus out of the
                // editor and fires selectionChange with no image). Keep the
                // last known image so chained toolbar actions still work.
                // Only hide the toolbar if we genuinely have no image AND
                // focus has moved away from the toolbar area.
                if (!window.__selectedImg) {
                    toolbar.style.display = 'none';
                }
            }
        });

        // Also re-check on click inside the editable (covers widget clicks
        // that don't always fire selectionChange reliably).
        ed_documentClick: {
            var editable = editor.editable();
            if (editable) {
                editable.on('click', function(ev) {
                    // Defer so CKEditor updates its selection first
                    setTimeout(function() {
                        var sel = editor.getSelection();
                        var selectedElement = sel ? sel.getSelectedElement() : null;
                        var img = __findImgFromSelection(editor, selectedElement);
                        var toolbar = document.getElementById('imgAlignToolbar');
                        if (!toolbar) return;
                        if (img) {
                            window.__selectedImg = img;
                            toolbar.style.display = 'flex';
                        } else {
                            window.__selectedImg = null;
                            toolbar.style.display = 'none';
                        }
                    }, 50);
                });
            }
        }
    }

    // Bind immediately (works when replace() finishes synchronously)
    __bindImgSelectionListener(CKEDITOR.instances.editor1);
    // Bind on instanceReady (works for the async init case after page reload)
    CKEDITOR.instances.editor1.on('instanceReady', function() {
        __bindImgSelectionListener(CKEDITOR.instances.editor1);
    });
}


// ============================================================================
//  FIXED alignImg() — uses the tracked image + properly re-selects after
//  each change so the toolbar keeps working for the next click.
// ============================================================================
function alignImg(type) {
    if (typeof CKEDITOR === 'undefined' || !CKEDITOR.instances.editor1) return;
    var editor = CKEDITOR.instances.editor1;

    // Use tracked image, or try to find one from current selection
    var img = window.__selectedImg;
    if (!img || !img.is || !img.is('img') || !img.getParent()) {
        // Image no longer in DOM — re-find from selection
        var sel = editor.getSelection();
        var selectedElement = sel ? sel.getSelectedElement() : null;

        if (selectedElement && selectedElement.is && selectedElement.is('img')) {
            img = selectedElement;
        } else if (selectedElement && selectedElement.findOne) {
            var inner = selectedElement.findOne('img');
            if (inner) img = inner;
        } else {
            // Walk ranges
            try {
                var ranges = sel.getRanges();
                if (ranges && ranges.length > 0) {
                    var node = ranges[0].startContainer;
                    if (node && node.type == CKEDITOR.NODE_TEXT && node.getParent()) {
                        node = node.getParent();
                    }
                    for (var i = 0; i < 6 && node; i++) {
                        if (node.type == CKEDITOR.NODE_ELEMENT) {
                            if (node.is && node.is('img')) { img = node; break; }
                            var found = node.findOne ? node.findOne('img') : null;
                            if (found) { img = found; break; }
                        }
                        node = node.getParent ? node.getParent() : null;
                    }
                }
            } catch (err) {}
        }
    }

    if (!img || !img.is || !img.is('img')) {
        alert('Please click on an image inside the editor first.');
        return;
    }

    // Focus editor so style operations work reliably
    editor.focus();

    if (type === 'left' || type === 'right' || type === 'center') {
        img.removeStyle('float');
        img.removeStyle('display');
        img.removeStyle('margin-left');
        img.removeStyle('margin-right');
        img.removeStyle('vertical-align');
        img.removeStyle('clear');

        if (type === 'left') {
            img.setStyle('float', 'left');
            img.setStyle('margin-right', '15px');
            img.setStyle('margin-bottom', '10px');
        } else if (type === 'right') {
            img.setStyle('float', 'right');
            img.setStyle('margin-left', '15px');
            img.setStyle('margin-bottom', '10px');
        } else if (type === 'center') {
            img.setStyle('display', 'block');
            img.setStyle('margin-left', 'auto');
            img.setStyle('margin-right', 'auto');
            img.setStyle('margin-bottom', '10px');
        }
    } else if (type === 'top' || type === 'middle' || type === 'bottom' || type === 'none') {
        img.removeStyle('float');
        img.removeStyle('display');
        img.removeStyle('margin-left');
        img.removeStyle('margin-right');

        if (type === 'top') img.setStyle('vertical-align', 'top');
        else if (type === 'middle') img.setStyle('vertical-align', 'middle');
        else if (type === 'bottom') img.setStyle('vertical-align', 'bottom');
        else if (type === 'none') {
            img.removeStyle('vertical-align');
        }
    } else if (type === 'zoomin' || type === 'zoomout') {
        var currentWidth = img.getStyle('width') || img.getAttribute('width');
        if (!currentWidth) {
            // Use the rendered width as fallback
            currentWidth = (img.$ && img.$.width) ? img.$.width :
                           (img.$ && img.$.naturalWidth) ? img.$.naturalWidth : 300;
        }
        var numWidth = parseInt(currentWidth, 10);
        if (!isNaN(numWidth)) {
            if (type === 'zoomin') {
                numWidth = Math.round(numWidth * 1.1);
            } else {
                numWidth = Math.max(50, Math.round(numWidth * 0.9));
            }
            img.setStyle('width', numWidth + 'px');
            img.setStyle('height', 'auto');
            img.removeAttribute('width');
            img.removeAttribute('height');
        }
    } else if (type === 'drag') {
        try { editor.getSelection().selectElement(img); } catch (e) {}
        img.setAttribute('draggable', 'true');
        img.once('dragstart', function(ev) {
            if (ev.data && ev.data.$ && ev.data.$.dataTransfer) {
                ev.data.$.dataTransfer.setData('text/html', img.getOuterHtml());
                ev.data.$.dataTransfer.effectAllowed = 'move';
            }
            img.addClass('cke_draggable_img');
        });
        alert('Drag mode enabled! এখন ছবির উপর মাউসের বাম বোতাম চেপে ধরে রেখে যেকোনো জায়গায় নিয়ে ছেড়ে দিন।');
    }

    // ===== FIX 4: re-select the image so the next toolbar button click
    // still has a valid target. Without this, applying alignment moved
    // focus out and the next click failed.
    try {
        editor.getSelection().selectElement(img);
    } catch (e) {}

    // Keep our reference in sync (CKEditor may re-wrap the element)
    window.__selectedImg = img;

    // Fire a selectionChange so any dependent UI updates
    try { editor.fire('selectionChange'); } catch (e) {}
}
