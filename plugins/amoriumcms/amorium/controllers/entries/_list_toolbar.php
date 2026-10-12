<?php
    $section = $this->activeSource;

    if (\AmoriumCms\Amorium\Classes\EntryCreateGuard::denies($section)) {
        echo '<div data-control="toolbar" data-list-linkage="'.$this->listGetId().'">';
        if (\AmoriumCms\Amorium\Classes\PrimaryCopy::allows($this)) {
            echo Ui::popupButton(
                label: __("Copy Content from Primary Site"),
                handler: 'onCopyFromPrimaryPopup',
                icon: 'icon-copy',
                secondary: true
            );
        }
        foreach (Event::fire('amorium.sections.extendListToolbar', [$this, $section]) as $html) {
            echo $html;
        }
        echo '</div>';
        return;
    }

    $canCreate = $this->hasSourcePermission('create');
    $showExport = $section->showExport ?? true;
    $showImport = ($section->showImport ?? true) && $canCreate;
    $showResetStructure = $section instanceof \Tailor\Classes\Blueprint\StructureBlueprint;
    $hasDropdown = $showImport || $showExport || $showResetStructure;
?>
<div data-control="toolbar" data-list-linkage="<?= $this->listGetId() ?>">

    <?php if ($canCreate): ?>
        <?= Ui::button(
            label: $section->getMessage('buttonCreate', "New :name", ['name' => e(__($section->name))]),
            href: Backend::url('tailor/entries/'.$section->handleSlug.'/create'),
            icon: 'icon-plus',
            primary: true,
            labelHtml: true
        ) ?>

        <div class="toolbar-divider"></div>
    <?php endif ?>

    <?php if ($this->hasSourcePermission('publish', 'delete')): ?>
        <div id="listBulkActions" class="btn-container">
            <?= $this->makePartial('list_bulk_actions') ?>
        </div>
    <?php endif ?>

    <?php if ($hasDropdown): ?>
        <?php Ui::dropdownButton(
            title: __("More Actions"),
            icon: 'icon-ellipsis-v',
            secondary: true,
            caret: false,
            class: 'btn-circle'
        )->slot() ?>

            <?php if ($showImport): ?>
                <?= Ui::dropdownItem(
                    label: __("Import"),
                    href: Backend::url('tailor/bulkactions/'.$section->handleSlug.'/import'),
                    icon: 'icon-upload'
                ) ?>
            <?php endif ?>
            <?php if ($showExport): ?>
                <?= Ui::dropdownItem(
                    label: __("Export"),
                    href: Backend::url('tailor/bulkactions/'.$section->handleSlug.'/export'),
                    icon: 'icon-download'
                ) ?>
            <?php endif ?>
            <?php if ($showResetStructure): ?>
                <?= Ui::dropdownItem(
                    label: __("Reset Structure"),
                    handler: 'onResetStructure',
                    icon: 'icon-set-parent',
                    dataRequestConfirm: __("Are you sure?")
                ) ?>
            <?php endif ?>

        <?php Ui::end() ?>
    <?php endif ?>

    <?php if ($canCreate && $section->handle === \AmoriumCms\Amorium\Classes\BaseSections::HANDLE): ?>
        <?= Ui::ajaxButton(
            label: __("Create Base Sections"),
            handler: 'onCreateBaseSections',
            icon: 'icon-magic',
            secondary: true,
            dataRequestConfirm: __("Sections in the base sections file that do not exist yet will be created. Continue?"),
            dataRequestMessage: __("Creating sections...")
        ) ?>
    <?php endif ?>

    <?php foreach (Event::fire('amorium.sections.extendListToolbar', [$this, $section]) as $html): ?>
        <?= $html ?>
    <?php endforeach ?>
</div>
