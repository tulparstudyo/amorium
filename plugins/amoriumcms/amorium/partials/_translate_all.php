<?php
    $isSelected = ($mode ?? null) === \AmoriumCms\Amorium\Classes\BulkTranslation::MODE_SELECTED;
    $title = $isSelected ? __("Translate Selected") : __("Translate All");
    $templateId = $templateId ?? ($isSelected ? 'translateSelectedTemplate' : 'translateAllTemplate');
    $description = $description ?? ($isSelected
        ? __("Translates the selected records into the languages of the other sites.")
        : __("Translates every record into the languages of the other sites."));
?>
<div class="toolbar-divider"></div>

<button
    type="button"
    class="btn btn-secondary"
    data-translate-all="#<?= $templateId ?>"
    <?php if ($isSelected): ?>
        data-translate-selected
        data-list-checked-trigger
        disabled
    <?php endif ?>>
    <i class="icon-language"></i> <?= e($title) ?>
    <?php if ($isSelected): ?><span data-list-checked-counter></span><?php endif ?>
</button>

<script type="text/template" id="<?= $templateId ?>">
    <div
        data-translate-all-popup
        data-handler-list="<?= e($handlerList ?? 'onTranslateAllList') ?>"
        data-handler-record="<?= e($handlerRecord ?? 'onTranslateAllRecord') ?>"
        data-text-progress="<?= e(__(":done of :total records")) ?>"
        data-text-translated="<?= e(__("Translated")) ?>"
        data-text-unchanged="<?= e(__("No changes")) ?>"
        data-text-failed="<?= e(__("Failed")) ?>"
        data-text-loading="<?= e(__("Loading records...")) ?>"
        data-text-completed="<?= e(__("Completed")) ?>"
        data-text-cancelled="<?= e(__("Cancelled")) ?>"
        data-text-empty="<?= e($isSelected ? __("None of the selected records can be translated.") : __("There are no records to translate.")) ?>">
        <div class="modal-header">
            <h4 class="modal-title"><?= e($title) ?></h4>
            <button type="button" class="btn-close" data-dismiss="popup"></button>
        </div>
        <div class="modal-body">
            <p class="text-muted"><?= e($description) ?></p>

            <div class="form-check mb-3" data-translate-options>
                <input class="form-check-input" type="checkbox" id="<?= $templateId ?>Overwrite" data-translate-overwrite>
                <label class="form-check-label" for="<?= $templateId ?>Overwrite"><?= e(__("Overwrite")) ?></label>
                <small class="text-muted ms-1"><?= e(__("Also replaces translations that were edited by hand.")) ?></small>
            </div>

            <div class="d-none" data-translate-progress>
                <div class="progress mb-2" style="height: 20px">
                    <div class="progress-bar" role="progressbar" style="width: 0%" data-translate-bar>0%</div>
                </div>
                <p class="mb-2" data-translate-status></p>
                <ul class="list-unstyled small mb-0" style="max-height: 240px; overflow-y: auto" data-translate-log></ul>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-translate-start>
                <?= e(__("Start")) ?>
            </button>
            <button type="button" class="btn btn-secondary d-none" data-translate-cancel>
                <?= e(__("Cancel")) ?>
            </button>
            <button type="button" class="btn btn-secondary" data-dismiss="popup" data-translate-close>
                <?= e(__("Close")) ?>
            </button>
        </div>
    </div>
</script>
