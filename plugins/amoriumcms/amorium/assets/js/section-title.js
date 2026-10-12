/*
 * Copies the name of the selected section into the hidden section_title
 * field, which the page sections repeater uses as its collapsed title.
 */
(function () {
    function syncItem(item) {
        var input = item.querySelector(':scope [data-field-name="section_title"] input');
        if (!input) {
            return;
        }

        var name = item.querySelector('[data-field-name="section"] .recordname');
        var title = name ? name.textContent.trim() : '';

        if (input.value !== title) {
            input.value = title;
        }
    }

    function syncAll() {
        document.querySelectorAll('[data-field-name="section_title"]').forEach(function (field) {
            var item = field.closest('.field-repeater-item');
            if (item) {
                syncItem(item);
            }
        });
    }

    new MutationObserver(syncAll).observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', syncAll);
})();
