<div class="modal-header">
    <h4 class="modal-title"><?= e(__("Translate")) ?></h4>
    <button type="button" class="btn-close" data-dismiss="popup"></button>
</div>
<div class="modal-body">
    <p><?= e(__("The content of this record on :from will be translated into the language of :to.", [
        'from' => $primarySite?->name,
        'to' => $editSite?->name,
    ])) ?></p>
    <p class="mb-0"><strong><?= e(__("Should the existing content be overwritten?")) ?></strong></p>
    <p class="text-muted small"><?= e(__("Yes translates every field again, including the ones edited by hand. No translates only empty, untranslated or outdated fields.")) ?></p>
    <p class="text-muted small mb-0"><?= e(__("Unsaved changes in the form will be lost.")) ?></p>
</div>
<div class="modal-footer">
    <button
        type="button"
        class="btn btn-danger"
        data-request="onTranslateFromPrimary"
        data-request-data="{ overwrite: 1 }"
        data-popup-load-indicator>
        <?= e(__("Yes, overwrite")) ?>
    </button>
    <button
        type="button"
        class="btn btn-primary"
        data-request="onTranslateFromPrimary"
        data-request-data="{ overwrite: 0 }"
        data-popup-load-indicator>
        <?= e(__("No, translate only missing content")) ?>
    </button>
    <button type="button" class="btn btn-secondary" data-dismiss="popup">
        <?= e(__("Cancel")) ?>
    </button>
</div>
