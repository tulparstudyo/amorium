/*
 * Review with AI: sends the rich editor content to the AI, which fixes spelling
 * and grammar, improves SEO and replaces the content with the corrected version.
 */
(function ($) {
    'use strict';

    var text = $(document.currentScript).data(),
        running = false;

    function findTitle(editor) {
        return $(editor.native.$oel).closest('form').find('input[name$="[title]"]').first().val() || '';
    }

    oc.richEditor.registerButton('amoriumReviewContent', {
        label: text.label,
        icon: { html: '<i class="icon-magic"></i>' },
        toolbar: 'end',
        separator: 'before',
        onClick: function (editor) {
            var content = editor.getContent();

            if (running) {
                return;
            }

            if (!$.trim($('<div />').html(content).text()) && !/<(img|iframe|video)/i.test(content)) {
                $.oc.flashMsg({ text: text.textEmpty, class: 'warning' });
                return;
            }

            running = true;
            $.oc.stripeLoadIndicator.show();
            $.oc.flashMsg({ text: text.textWorking, class: 'info' });

            $.request('onReviewContent', {
                data: { content: content, title: findTitle(editor) },
                success: function (response) {
                    editor.saveUndoStep();
                    editor.setContent(response.content);
                    editor.saveUndoStep();
                    editor.native.events.trigger('contentChanged');

                    $.oc.flashMsg({ text: text.textDone, class: 'success' });
                },
                complete: function () {
                    running = false;
                    $.oc.stripeLoadIndicator.hide();
                }
            });
        }
    });
})(jQuery);
