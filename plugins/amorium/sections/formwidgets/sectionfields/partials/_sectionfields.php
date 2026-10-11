<?php if ($definitionError): ?>
    <div class="callout callout-danger no-subheader">
        <div class="header">
            <h3><?= e(__('The field definition of this section is not valid YAML.')) ?></h3>
            <p><?= e($definitionError) ?></p>
        </div>
    </div>
<?php elseif ($hasFields): ?>
    <div class="nested-form <?= $this->showPanel ? 'is-paneled' : '' ?>">
        <?= $this->formWidget->render() ?>
    </div>
<?php endif ?>
