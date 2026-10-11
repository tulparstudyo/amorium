<?php
    $overwriteName = str_replace('_translate_now', '_translate_overwrite', $field->getName());
    $overwriteId = $field->getId().'-overwrite';
?>
<div class="form-check">
    <input type="hidden" name="<?= $field->getName() ?>" value="0" />
    <input
        class="form-check-input"
        type="checkbox"
        id="<?= $field->getId() ?>"
        name="<?= $field->getName() ?>"
        value="1"
    />
    <label class="form-check-label" for="<?= $field->getId() ?>">
        <?= e(__('Translate on save')) ?>
        <small class="text-muted ms-1"><?= e(__('Translates the text content to the other site languages when saving.')) ?></small>
    </label>
</div>

<div
    class="form-check mt-1"
    data-trigger="#<?= $field->getId() ?>"
    data-trigger-action="show"
    data-trigger-condition="checked"
>
    <input type="hidden" name="<?= $overwriteName ?>" value="0" />
    <input
        class="form-check-input"
        type="checkbox"
        id="<?= $overwriteId ?>"
        name="<?= $overwriteName ?>"
        value="1"
    />
    <label class="form-check-label" for="<?= $overwriteId ?>">
        <?= e(__('Overwrite')) ?>
        <small class="text-muted ms-1"><?= e(__('Also replaces translations that were edited by hand.')) ?></small>
    </label>
</div>
