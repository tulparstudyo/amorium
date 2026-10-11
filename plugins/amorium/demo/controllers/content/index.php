<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('system/settings') ?>"><?= __("Settings") ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e(__($this->pageTitle)) ?></li>
    </ol>
<?php Block::endPut() ?>

<div class="padded-container">
    <?php if ($summary): ?>
        <p><?= e(__("The theme contains demo content saved on :date:", ['date' => $summary['exported_at']])) ?></p>
        <p><strong><?= e($this->formatCounts($summary['counts'])) ?></strong></p>

        <?php if ($summary['installed_at']): ?>
            <p class="text-muted"><?= e(__("Demo content was last installed on this site on :date.", ['date' => $summary['installed_at']])) ?></p>
        <?php endif ?>

        <div class="callout callout-warning no-subheader">
            <div class="header">
                <p><?= e(__("Installing replaces the sites, pages, posts, categories, menus, sections, business information and theme settings of this site. Media files with the same name are overwritten.")) ?></p>
            </div>
        </div>
    <?php else: ?>
        <p><?= e(__("The theme does not contain demo content.")) ?></p>
    <?php endif ?>

    <?php if ($canExport): ?>
        <p class="text-muted"><?= e(__("Saving writes the content and media library of this site to the demo folder of the theme so it is included in the theme package.")) ?></p>
    <?php endif ?>
</div>

<div class="form-buttons padded-container">
    <div data-control="loader-container" class="control-loader-container">
        <?php if ($summary): ?>
            <?= Ui::ajaxButton(
                label: __("Install Demo Content"),
                handler: 'onInstall',
                icon: 'icon-download',
                primary: true,
                dataRequestConfirm: __("The sites, pages, posts, menus and sections of this site will be replaced with the demo content. Continue?"),
                dataRequestMessage: __("Installing demo content...")
            ) ?>
        <?php endif ?>

        <?php if ($canExport): ?>
            <?= Ui::ajaxButton(
                label: __("Save Current Content as Demo"),
                handler: 'onExport',
                icon: 'icon-upload',
                secondary: true,
                dataRequestConfirm: __("The demo content in the theme will be replaced with the content of this site. Continue?"),
                dataRequestMessage: __("Saving demo content...")
            ) ?>
        <?php endif ?>
    </div>
</div>
