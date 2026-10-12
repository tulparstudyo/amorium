/*
 * Translate All / Translate Selected: requests the record list, then
 * translates the records one by one and shows the progress in a popup.
 * With data-translate-selected only the records checked in the list are sent.
 */
(function ($) {
    'use strict';

    function request(handler, data) {
        return new Promise(function (resolve, reject) {
            $.request(handler, {
                data: data || {},
                success: function (response) { resolve(response); },
                error: function (response) { reject(response); }
            });
        });
    }

    function errorText(response) {
        if (typeof response === 'string') {
            return response;
        }

        return (response && (response.message || response.result)) || '';
    }

    function TranslateAll($popup, ids) {
        this.$popup = $popup;
        this.ids = ids;
        this.text = $popup.data();
        this.running = false;
        this.cancelled = false;

        $popup.on('click', '[data-translate-start]', this.start.bind(this));
        $popup.on('click', '[data-translate-cancel]', this.cancel.bind(this));
    }

    TranslateAll.prototype.find = function (selector) {
        return this.$popup.find(selector);
    };

    TranslateAll.prototype.setStatus = function (message) {
        this.find('[data-translate-status]').text(message);
    };

    TranslateAll.prototype.setProgress = function (done, total) {
        var percent = total ? Math.round(done / total * 100) : 100;

        this.find('[data-translate-bar]').css('width', percent + '%').text(percent + '%');
        this.setStatus(this.text.textProgress.replace(':done', done).replace(':total', total));
    };

    TranslateAll.prototype.addLog = function (title, message, type) {
        var $item = $('<li class="py-1 border-bottom" />');

        $('<span />').text(title || '-').appendTo($item);
        $('<span class="ms-2" />').addClass('text-' + type).text(message).appendTo($item);

        this.find('[data-translate-log]').prepend($item);
    };

    TranslateAll.prototype.setRunning = function (running) {
        this.running = running;
        this.find('[data-translate-start], [data-translate-options]').toggleClass('d-none', running || this.started);
        this.find('[data-translate-cancel]').toggleClass('d-none', !running);
        this.find('[data-translate-close], [data-dismiss=popup]').prop('disabled', running);
    };

    TranslateAll.prototype.cancel = function () {
        this.cancelled = true;
        this.find('[data-translate-cancel]').prop('disabled', true);
    };

    TranslateAll.prototype.start = async function () {
        var overwrite = this.find('[data-translate-overwrite]').is(':checked') ? 1 : 0;
        var records, done = 0;

        this.started = true;
        this.setRunning(true);
        this.find('[data-translate-progress]').removeClass('d-none');
        this.setStatus(this.text.textLoading);

        try {
            records = (await request(this.text.handlerList, this.ids ? { ids: this.ids } : {})).records || [];
        }
        catch (response) {
            this.setStatus(this.text.textFailed + ': ' + errorText(response));
            this.setRunning(false);
            return;
        }

        if (!records.length) {
            this.setStatus(this.text.textEmpty);
            this.setRunning(false);
            return;
        }

        this.setProgress(0, records.length);

        for (var i = 0; i < records.length; i++) {
            if (this.cancelled) {
                break;
            }

            try {
                var result = await request(this.text.handlerRecord, { id: records[i].id, overwrite: overwrite });

                if (result.written > 0) {
                    this.addLog(records[i].title, this.text.textTranslated, 'success');
                }
                else {
                    this.addLog(records[i].title, this.text.textUnchanged, 'muted');
                }
            }
            catch (response) {
                this.addLog(records[i].title, this.text.textFailed + ': ' + errorText(response), 'danger');
            }

            done++;
            this.setProgress(done, records.length);
        }

        this.setStatus(
            this.text.textProgress.replace(':done', done).replace(':total', records.length) +
            ' - ' + (this.cancelled ? this.text.textCancelled : this.text.textCompleted)
        );

        this.setRunning(false);
    };

    function checkedIds($button) {
        var listId = $button.closest('[data-list-linkage]').data('list-linkage');

        return listId ? $.oc.listGetChecked('#' + listId + ' > .control-list:first') : [];
    }

    $(document).on('click', '[data-translate-all]', function () {
        var $button = $(this),
            ids = null;

        if ($button.is('[data-translate-selected]')) {
            ids = checkedIds($button);
            if (!ids.length) {
                return;
            }
        }

        $.popup({ content: $($button.data('translate-all')).html(), keyboard: false });

        var $popup = $('.modal [data-translate-all-popup]').last();
        if ($popup.length && !$popup.data('translateAll')) {
            $popup.data('translateAll', new TranslateAll($popup, ids));
        }
    });
})(jQuery);
