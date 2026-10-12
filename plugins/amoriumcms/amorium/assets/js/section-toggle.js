/*
 * Adds an enabled switch to the header of each page section item, bound to
 * the hidden is_disabled field. Disabled sections are not shown on the site.
 */
(function () {
    'use strict';

    var text = document.currentScript.dataset;

    function findField(item) {
        var inputs = item.querySelectorAll('[data-field-name="is_disabled"] input[type="checkbox"]');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].closest('.field-repeater-item') === item) {
                return inputs[i];
            }
        }

        return null;
    }

    function setState(item, toggle, enabled) {
        toggle.checked = enabled;
        toggle.title = enabled ? text.labelEnabled : text.labelDisabled;
        item.classList.toggle('is-section-disabled', !enabled);
    }

    function initItem(item) {
        var field = findField(item),
            header = item.querySelector(':scope > .repeater-header');

        if (!field || !header || header.querySelector(':scope > .repeater-item-enabled')) {
            return;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'repeater-item-enabled form-check form-switch';

        var toggle = document.createElement('input');
        toggle.type = 'checkbox';
        toggle.className = 'form-check-input';
        toggle.disabled = field.disabled;
        wrapper.appendChild(toggle);

        var title = header.querySelector(':scope > .repeater-item-title');
        header.insertBefore(wrapper, title ? title.nextSibling : header.firstChild);

        setState(item, toggle, !field.checked);

        toggle.addEventListener('change', function () {
            field.checked = !toggle.checked;
            field.dispatchEvent(new Event('change', { bubbles: true }));
            setState(item, toggle, toggle.checked);
        });
    }

    function initAll() {
        document.querySelectorAll('.form-group[data-field-name="sections"] > .field-repeater > ul > li.field-repeater-item').forEach(initItem);
    }

    new MutationObserver(initAll).observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', initAll);
})();
