<?= Form::open(['class' => 'layout']) ?>

    <div class="layout-row">
        <?= $this->paymentForm->render() ?>
    </div>

    <div class="form-buttons">
        <div class="loading-indicator-container">
            <button
                type="submit"
                data-request="onSave"
                data-hotkey="ctrl+s, cmd+s"
                data-load-indicator="<?= e(trans('backend::lang.form.saving')) ?>"
                class="btn btn-primary">
                <?= e(trans('backend::lang.form.save')) ?>
            </button>
        </div>
    </div>

<?= Form::close() ?>
