<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('amorium/form/submissions') ?>"><?= __("Form Submissions") ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e(__($this->pageTitle)) ?></li>
    </ol>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>

    <div class="scoreboard">
        <div data-control="toolbar">
            <div class="scoreboard-item title-value">
                <h4><?= __("Form") ?></h4>
                <p><?= e($formModel->form_name ?: '—') ?></p>
            </div>
            <div class="scoreboard-item title-value">
                <h4><?= __("Date") ?></h4>
                <p><?= e($formModel->created_at?->format('d.m.Y H:i')) ?></p>
            </div>
            <?php if ($formModel->site): ?>
                <div class="scoreboard-item title-value">
                    <h4><?= __("Site") ?></h4>
                    <p><?= e($formModel->site->name) ?></p>
                </div>
            <?php endif ?>
            <div class="scoreboard-item title-value">
                <h4><?= __("IP Address") ?></h4>
                <p><?= e($formModel->ip_address ?: '—') ?></p>
            </div>
            <?php if ($formModel->page_url): ?>
                <div class="scoreboard-item title-value">
                    <h4><?= __("Page") ?></h4>
                    <p><a href="<?= e($formModel->page_url) ?>" target="_blank" rel="noopener noreferrer"><?= e(parse_url($formModel->page_url, PHP_URL_PATH) ?: '/') ?></a></p>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="flex-grow-1">
        <?= $this->formRenderPreview() ?>
    </div>

    <div class="form-buttons">
        <?= Ui::button(
            label: __("Return to Form Submissions"),
            href: Backend::url('amorium/form/submissions'),
            icon: 'oc-icon-chevron-left',
            secondary: true
        ) ?>
        <?= Ui::ajaxButton(
            label: __("Delete"),
            handler: 'onDelete',
            icon: 'oc-icon-delete',
            class: 'btn-link text-danger',
            dataRequestConfirm: __("Are you sure?")
        ) ?>
    </div>

<?php else: ?>

    <p class="flash-message static error"><?= e(__($this->fatalError)) ?></p>
    <p>
        <?= Ui::button(
            label: __("Return to Form Submissions"),
            href: Backend::url('amorium/form/submissions'),
            secondary: true
        ) ?>
    </p>

<?php endif ?>
