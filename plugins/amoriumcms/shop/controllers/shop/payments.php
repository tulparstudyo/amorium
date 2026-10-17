<link href="<?= Url::asset('plugins/amoriumcms/shop/assets/css/payments.css') ?>?v=<?= filemtime(plugins_path('amoriumcms/shop/assets/css/payments.css')) ?>" rel="stylesheet">

<div id="payment-accordion" hidden data-offered="<?= e(__('Offered')) ?>" data-hidden="<?= e(__('Not offered')) ?>"></div>

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

<script src="<?= Url::asset('plugins/amoriumcms/shop/assets/js/payments.js') ?>?v=<?= filemtime(plugins_path('amoriumcms/shop/assets/js/payments.js')) ?>"></script>
