<div
    class="field-fontpicker"
    data-control="amorium-fontpicker"
    data-allow-empty="<?= $allowEmpty ? 'true' : 'false' ?>"
    data-placeholder="<?= e($placeholder) ?>"
>
    <input type="hidden" name="<?= e($name) ?>" id="<?= e($field->getId()) ?>" value="<?= e($value) ?>" />
    <button type="button" class="form-control" <?= $field->disabled || $field->readOnly ? 'disabled' : '' ?>><?= e($value ?: $placeholder) ?></button>
</div>
