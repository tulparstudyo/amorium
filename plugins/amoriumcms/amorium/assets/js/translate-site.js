/*
 * Translate: adds a button to the entry document toolbar of a non primary site,
 * which asks whether to overwrite and translates the primary site content.
 */
(function ($) {
    'use strict';

    var COMMAND = 'amorium:translate-from-primary',
        label = $(document.currentScript).data('label');

    function addButton(attempt) {
        var element = document.querySelector('[data-control="vue-entry-document"]'),
            control = element && oc.fetchControl(element, 'vue-entry-document'),
            elements = control && control.state && control.state.toolbarElements;

        if (!elements || !elements.length) {
            if (attempt < 50) {
                setTimeout(function () { addButton(attempt + 1); }, 100);
            }
            return;
        }

        if (elements.some(function (item) { return item && item.command === COMMAND; })) {
            return;
        }

        elements.push({ type: 'separator' });
        elements.push({ type: 'button', icon: 'icon-language', label: label, command: COMMAND });
    }

    $(document).on('documenttoolbarcmd', function (ev, data) {
        if (data && data.command === COMMAND) {
            $.popup({ handler: 'onTranslateFromPrimaryPopup' });
        }
    });

    $(document).on('render', function () { addButton(0); });
    addButton(0);
})(jQuery);
