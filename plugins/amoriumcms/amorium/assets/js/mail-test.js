/*
 * Adds a button to the mail settings page that sends a test email with the values in the form.
 */
(function () {
    'use strict';

    var text = document.currentScript.dataset;

    function init() {
        var container = document.querySelector('.form-buttons .control-loader-container');
        if (!container || container.querySelector('[data-request="onTestMail"]')) {
            return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary';
        button.textContent = text.label;
        button.setAttribute('data-request', 'onTestMail');
        button.setAttribute('data-request-message', text.message);
        button.setAttribute('data-attach-loading', '');

        var separator = container.querySelector(':scope > .btn-text');
        container.insertBefore(button, separator);
        container.insertBefore(document.createTextNode(' '), separator);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
