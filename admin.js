/**
 * WPCM HTML Canvas — Admin JS V14
 */
(function($) {
    'use strict';

    var idx = 0;
    var cmInstances = [];

    $(function() {
        idx = $('#wpcm-list .wpcm-block').length;

        initSortable();
        syncAllShortcodes();
        renderCatalog();

        $('.wpcm-tabs li').on('click', function() {
            var t = $(this).data('tab');
            $('.wpcm-tabs li').removeClass('active');
            $(this).addClass('active');
            $('.wpcm-panel').removeClass('active');
            $('#wpcm-tab-' + t).addClass('active');
            refreshAllCM();
            if (t === 'library') {
                renderCatalog();
            }
        });

        $('#wpcm-add').on('click', function() {
            addBlock();
        });

        $(document).on('click', '.wpcm-del', function() {
            if (confirm(WPCM.i18n.confirm_remove)) {
                var $b = $(this).closest('.wpcm-block');
                destroyCodeMirror($b);
                $b.slideUp(200, function() {
                    $(this).remove();
                    reindexBlocks();
                    renderCatalog();
                });
            }
        });

        $(document).on('click', '.wpcm-newid', function() {
            getNewId($(this).closest('.wpcm-block'));
        });

        $(document).on('click', '.wpcm-copy', function() {
            var $b = $(this).closest('.wpcm-block');
            var txt = currentShortcode($b);
            var btn = this;
            if (!txt) return;
            copy(txt).then(function() {
                flashButton(btn, WPCM.i18n.copied, WPCM.i18n.copy_shortcode);
            });
        });

        $(document).on('click', '.wpcm-insert-editor', function() {
            var $b = $(this).closest('.wpcm-block');
            var txt = currentShortcode($b);
            var btn = this;
            if (!txt) return;
            if (insertIntoEditor(txt)) {
                flashButton(btn, WPCM.i18n.inserted, WPCM.i18n.insert_editor);
            } else {
                copy(txt).then(function() {
                    flashButton(btn, WPCM.i18n.copied, WPCM.i18n.insert_editor);
                    window.alert(WPCM.i18n.classic_only);
                });
            }
        });

        $(document).on('click', '.wpcm-duplicate', function() {
            duplicateBlock($(this).closest('.wpcm-block'));
        });

        $(document).on('click', '.wpcm-enable-editor', function() {
            var $b = $(this).closest('.wpcm-block');
            var btn = this;
            ensureCodeMirror($b, function(enabled) {
                if (enabled) {
                    flashButton(btn, WPCM.i18n.editor_enabled, WPCM.i18n.enable_editor);
                }
            });
        });

        $(document).on('click', '.wpcm-preview-btn', function() {
            var $b = $(this).closest('.wpcm-block');
            var $p = $b.find('.wpcm-block-preview');
            ensureCodeMirror($b);
            $p.slideToggle(200, function() {
                if ($p.is(':visible')) showPreview($b);
            });
        });

        $(document).on('input change', '.wpcm-id, .wpcm-label-input', function() {
            syncBlock($(this).closest('.wpcm-block'));
            renderCatalog();
        });

        $('#wpcm-list .wpcm-block').each(function() {
            if (!$(this).find('.wpcm-id').val()) getNewId($(this));
            syncBlock($(this));
        });

        $(document).on('click', '.wpcm-catalog-copy', function() {
            var txt = $(this).data('shortcode') || '';
            var btn = this;
            if (!txt) return;
            copy(txt).then(function() {
                flashButton(btn, WPCM.i18n.copied, WPCM.i18n.copy_shortcode);
            });
        });

        $(document).on('click', '.wpcm-catalog-copy-external', function() {
            var txt = $(this).data('shortcode') || '';
            var btn = this;
            if (!txt) return;
            copy(txt).then(function() {
                flashButton(btn, WPCM.i18n.copied, WPCM.i18n.copy_shortcode);
            });
        });

        $(document).on('click', '.wpcm-catalog-insert', function() {
            var txt = $(this).data('shortcode') || '';
            var btn = this;
            if (!txt) return;
            if (insertIntoEditor(txt)) {
                flashButton(btn, WPCM.i18n.inserted, WPCM.i18n.insert_editor);
            } else {
                copy(txt).then(function() {
                    flashButton(btn, WPCM.i18n.copied, WPCM.i18n.insert_editor);
                    window.alert(WPCM.i18n.classic_only);
                });
            }
        });
    });

    function addBlock(prefill) {
        var html = $('#tmpl-wpcm-block').html().replace(/\{idx\}/g, idx);
        var $el  = $(html);
        $('#wpcm-list').append($el);
        if (prefill && prefill.label) {
            $el.find('.wpcm-label-input').val(prefill.label);
        }
        if (prefill && prefill.code) {
            var $ta = $el.find('.wpcm-editor');
            var inst = $ta.data('cm-instance');
            if (inst && inst.codemirror) {
                inst.codemirror.setValue(prefill.code);
                inst.codemirror.save();
            } else {
                $ta.val(prefill.code);
            }
        }
        getNewId($el);
        idx++;
        $('html,body').animate({ scrollTop: $el.offset().top - 80 }, 250);
        renderCatalog();
        return $el;
    }

    function duplicateBlock($source) {
        var label = $source.find('.wpcm-label-input').val() || WPCM.i18n.label_default;
        var $ta   = $source.find('.wpcm-editor');
        var inst  = $ta.data('cm-instance');
        var code  = (inst && inst.codemirror) ? inst.codemirror.getValue() : $ta.val();
        var $new  = addBlock({ label: label + ' (cópia)', code: code });
        flashButton($new.find('.wpcm-duplicate')[0], WPCM.i18n.duplicated, WPCM.i18n.duplicate);
    }

    function initSortable() {
        if (!$.fn.sortable) return;
        $('#wpcm-list').sortable({
            handle: '.wpcm-drag',
            items: '> .wpcm-block',
            placeholder: 'wpcm-sort-placeholder',
            forcePlaceholderSize: true,
            stop: function() {
                reindexBlocks();
                refreshAllCM();
                renderCatalog();
            }
        });
    }

    function reindexBlocks() {
        $('#wpcm-list .wpcm-block').each(function(i) {
            var $block = $(this);
            $block.attr('data-index', i);
            $block.find('[name]').each(function() {
                var name = $(this).attr('name');
                if (!name) return;
                $(this).attr('name', name.replace(/wpcm_s\[[^\]]+\]/, 'wpcm_s[' + i + ']'));
            });
        });
        idx = $('#wpcm-list .wpcm-block').length;
    }

    function initCodeMirror($block) {
        if (!WPCM.cm || !window.wp || !wp.codeEditor) return false;
        var $ta = $block.find('.wpcm-editor');
        if ($ta.length === 0 || $ta.data('cm-init')) return true;
        var instance = wp.codeEditor.initialize($ta, WPCM.cm);
        $ta.data('cm-init', true);
        $ta.data('cm-instance', instance);
        cmInstances.push(instance);
        instance.codemirror.on('change', function() {
            instance.codemirror.save();
        });
        $block.addClass('wpcm-editor-advanced-on');
        return true;
    }

    function ensureCodeMirror($block, done) {
        var ok = initCodeMirror($block);
        if (ok) {
            refreshBlockCM($block);
        }
        if (typeof done === 'function') done(ok);
        return ok;
    }

    function destroyCodeMirror($block) {
        var $ta = $block.find('.wpcm-editor');
        var inst = $ta.data('cm-instance');
        if (inst && inst.codemirror) {
            inst.codemirror.toTextArea();
            var i = cmInstances.indexOf(inst);
            if (i > -1) cmInstances.splice(i, 1);
        }
    }

    function refreshAllCM() {
        cmInstances.forEach(function(inst) {
            if (inst && inst.codemirror) {
                setTimeout(function() { inst.codemirror.refresh(); }, 30);
            }
        });
    }

    function refreshBlockCM($block) {
        var $ta = $block.find('.wpcm-editor');
        var inst = $ta.data('cm-instance');
        if (inst && inst.codemirror) {
            setTimeout(function() { inst.codemirror.refresh(); }, 30);
        }
    }

    function getNewId($block) {
        var $in  = $block.find('.wpcm-id');
        var $btn = $block.find('.wpcm-newid');
        $in.val(WPCM.i18n.generating);
        $btn.prop('disabled', true);
        $.post(WPCM.ajax, { action: 'wpcm_new_id', nonce: WPCM.nonce })
            .done(function(r) {
                setId($block, r.success && r.data.id ? r.data.id : localId());
            })
            .fail(function() {
                setId($block, localId());
            })
            .always(function() {
                $btn.prop('disabled', false);
            });
    }

    function setId($block, id) {
        $block.find('.wpcm-id').val(id).trigger('change');
    }

    function localId() {
        var p = WPCM.prefix || 'site';
        var c = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var s = '';
        for (var i = 0; i < 6; i++) s += c[Math.floor(Math.random() * c.length)];
        return p + s;
    }

    function currentShortcode($block) {
        var id = $block.find('.wpcm-id').val();
        return id ? '[wpcm_canvas id="' + id + '"]' : '';
    }

    function externalShortcode($block) {
        var id = $block.find('.wpcm-id').val();
        var postId = Number($('.wpcm-wrap').data('post-id') || WPCM.postId || 0);
        return (id && postId) ? '[wpcm_canvas id="' + id + '" post_id="' + postId + '"]' : '';
    }

    function syncBlock($block) {
        var label = $.trim($block.find('.wpcm-label-input').val()) || WPCM.i18n.label_default;
        $block.attr('data-block-id', $block.find('.wpcm-id').val() || '');
        $block.find('.wpcm-shortcode-current').text(currentShortcode($block));
        $block.find('.wpcm-shortcode-external').text(externalShortcode($block));
        $block.find('.wpcm-label-input').attr('data-label-preview', label);
    }

    function syncAllShortcodes() {
        $('#wpcm-list .wpcm-block').each(function() {
            syncBlock($(this));
        });
    }

    function renderCatalog() {
        var $catalog = $('#wpcm-shortcode-catalog');
        if (!$catalog.length) return;
        var items = [];
        $('#wpcm-list .wpcm-block').each(function(i) {
            var $block = $(this);
            var label = $.trim($block.find('.wpcm-label-input').val()) || (WPCM.i18n.label_default + ' ' + (i + 1));
            var shortcode = currentShortcode($block);
            var external = externalShortcode($block);
            var id = $block.find('.wpcm-id').val() || '';
            if (!id) return;
            items.push(
                '<div class="wpcm-catalog-card">' +
                    '<div class="wpcm-catalog-top">' +
                        '<strong>' + escapeHtml(label) + '</strong>' +
                        '<span class="wpcm-catalog-id">' + escapeHtml(id) + '</span>' +
                    '</div>' +
                    '<div class="wpcm-catalog-code"><small>Shortcode</small><code>' + escapeHtml(shortcode) + '</code></div>' +
                    '<div class="wpcm-catalog-code wpcm-catalog-code-secondary"><small>Uso externo</small><code>' + escapeHtml(external) + '</code></div>' +
                    '<div class="wpcm-catalog-actions">' +
                        '<button type="button" class="button button-secondary wpcm-catalog-copy" data-shortcode="' + attrEscape(shortcode) + '">' + WPCM.i18n.copy_shortcode + '</button>' +
                        '<button type="button" class="button button-secondary wpcm-catalog-insert" data-shortcode="' + attrEscape(shortcode) + '">' + WPCM.i18n.insert_editor + '</button>' +
                        '<button type="button" class="button button-link wpcm-catalog-copy-external" data-shortcode="' + attrEscape(external) + '">Copiar com post_id</button>' +
                    '</div>' +
                '</div>'
            );
        });
        if (!items.length) {
            $catalog.html('<div class="wpcm-catalog-empty">' + WPCM.i18n.catalog_empty + '</div>');
            return;
        }
        $catalog.html(
            '<div class="wpcm-catalog-note">' + WPCM.i18n.saved_tip + '</div>' +
            '<div class="wpcm-catalog-grid">' + items.join('') + '</div>'
        );
    }

    function insertIntoEditor(text) {
        if (!text) return false;

        if (window.wp && window.wp.media && typeof window.send_to_editor === 'function' && $('#content').length) {
            insertAtCursor(document.getElementById('content'), text + "\n\n");
            return true;
        }

        if (typeof window.tinymce !== 'undefined') {
            var editor = window.tinymce.get('content');
            if (editor && !editor.isHidden()) {
                editor.execCommand('mceInsertContent', false, text + '<p></p>');
                return true;
            }
        }

        var content = document.getElementById('content');
        if (content) {
            insertAtCursor(content, text + "\n\n");
            return true;
        }

        return false;
    }

    function insertAtCursor(field, text) {
        if (!field) return;
        field.focus();
        if (typeof field.selectionStart === 'number') {
            var start = field.selectionStart;
            var end = field.selectionEnd;
            field.value = field.value.substring(0, start) + text + field.value.substring(end);
            field.selectionStart = field.selectionEnd = start + text.length;
        } else {
            field.value += text;
        }
        $(field).trigger('change');
    }

    function showPreview($b) {
        var $ta  = $b.find('.wpcm-editor');
        var inst = $ta.data('cm-instance');
        var code = (inst && inst.codemirror) ? inst.codemirror.getValue() : $ta.val();
        var $ifr = $b.find('.wpcm-iframe');

        if (!code || !code.trim()) {
            write($ifr, '<p style="color:#999;font:14px sans-serif;padding:20px">' + WPCM.i18n.preview_empty + '</p>');
            return;
        }
        write($ifr, /<!doctype|<html/i.test(code) ? code :
            '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>' + code + '</body></html>');
    }

    function write($ifr, html) {
        var d = $ifr[0].contentDocument || $ifr[0].contentWindow.document;
        d.open();
        d.write(html);
        d.close();
    }

    function copy(txt) {
        if (navigator.clipboard) return navigator.clipboard.writeText(txt);
        return new Promise(function(ok) {
            var $t = $('<textarea>').val(txt).appendTo('body').select();
            document.execCommand('copy');
            $t.remove();
            ok();
        });
    }

    function flashButton(btn, temporary, original) {
        if (!btn) return;
        var $btn = $(btn);
        $btn.text(temporary);
        setTimeout(function() {
            $btn.text(original);
        }, 1600);
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function attrEscape(str) {
        return escapeHtml(str);
    }

})(jQuery);
