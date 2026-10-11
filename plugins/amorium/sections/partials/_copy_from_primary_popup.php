<div class="modal-header">
    <h4 class="modal-title"><?= e(__("Copy Content from Primary Site")) ?></h4>
    <button type="button" class="btn-close" data-dismiss="popup"></button>
</div>
<div class="modal-body">
    <p><?= e(__("The records of :from will be copied to :to. Records that were copied before are left unchanged.", [
        'from' => $primarySite?->name,
        'to' => $editSite?->name,
    ])) ?></p>
    <p class="mb-0"><strong><?= e(__("Should the existing records be overwritten?")) ?></strong></p>
    <p class="text-muted small mb-0"><?= e(__("Yes replaces the content of the existing records with the content of the primary site, including translations.")) ?></p>
</div>
<div class="modal-footer">
    <button
        type="button"
        class="btn btn-danger"
        data-request="onCopyFromPrimary"
        data-request-data="{ overwrite: 1 }"
        data-request-success="$(this).trigger('close.oc.popup')"
        data-popup-load-indicator>
        <?= e(__("Yes, overwrite")) ?>
    </button>
    <button
        type="button"
        class="btn btn-primary"
        data-request="onCopyFromPrimary"
        data-request-data="{ overwrite: 0 }"
        data-request-success="$(this).trigger('close.oc.popup')"
        data-popup-load-indicator>
        <?= e(__("No, copy only missing records")) ?>
    </button>
    <button type="button" class="btn btn-secondary" data-dismiss="popup">
        <?= e(__("Cancel")) ?>
    </button>
</div>
