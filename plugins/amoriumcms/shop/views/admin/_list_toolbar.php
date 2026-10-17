<div data-control="toolbar">
    <?php if (!empty($newLabel)): ?>
        <a href="#/new" class="btn btn-primary oc-icon-plus"><?= e(__($newLabel)) ?></a>
    <?php endif ?>
    <?php if (!empty($importUrl)): ?>
        <a href="<?= e($importUrl) ?>" class="btn btn-default oc-icon-download"><?= e(__('Import from link')) ?></a>
    <?php endif ?>
</div>
