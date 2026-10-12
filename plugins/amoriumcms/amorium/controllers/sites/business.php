<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('system/settings') ?>"><?= __("Settings") ?></a></li>
        <li class="breadcrumb-item"><a href="<?= Backend::url('system/sites') ?>"><?= __("Sites") ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e(__($this->pageTitle)) ?></li>
    </ol>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
    <?= Form::open(['class' => 'd-flex flex-column h-100 design-settings']) ?>
        <div class="flex-grow-1">
            <?= $businessForm->render() ?>
        </div>

        <div class="form-buttons">
            <div data-control="loader-container" class="control-loader-container">
                <?= Ui::ajaxButton(
                    label: __("Save"),
                    handler: 'onSave',
                    primary: true,
                    hotkey: ['ctrl+s', 'cmd+s'],
                    dataRequestData: "redirect: false",
                    dataRequestMessage: __("Saving...")
                ) ?>

                <?= Ui::ajaxButton(
                    label: __("Save & Close"),
                    handler: 'onSave',
                    secondary: true,
                    hotkey: ['ctrl+enter', 'cmd+enter'],
                    dataRequestData: "close: true",
                    dataRequestMessage: __("Saving...")
                ) ?>

                <span class="btn-text">
                    <span class="button-separator"><?= __("or") ?></span>
                    <?= Ui::button(
                        label: __("Cancel"),
                        href: Backend::url('system/sites'),
                        class: 'btn-link p-0'
                    ) ?>
                </span>

                <span class="pull-right btn-text">
                    <?= Ui::ajaxButton(
                        label: __("Reset to Default"),
                        handler: 'onResetDefault',
                        href: 'javascript:;',
                        class: 'btn-link p-0',
                        dataRequestData: "redirect: false",
                        dataRequestConfirm: __("Are you sure?"),
                        dataRequestMessage: __("Resetting...")
                    ) ?>
                </span>
            </div>
        </div>
    <?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e(__($this->fatalError)) ?></p>
    <p>
        <?= Ui::button(
            label: __("Return to Sites"),
            href: Backend::url('system/sites'),
            secondary: true
        ) ?>
    </p>
<?php endif ?>
