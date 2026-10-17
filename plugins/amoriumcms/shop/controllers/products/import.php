<link href="<?= Url::asset('plugins/amoriumcms/shop/assets/css/admin.css') ?>?v=<?= filemtime(plugins_path('amoriumcms/shop/assets/css/admin.css')) ?>" rel="stylesheet">

<div class="shop-admin">
    <div class="shop-toolbar">
        <a href="<?= Backend::url('amoriumcms/shop/products') ?>" class="btn btn-default oc-icon-arrow-left"><?= e(__('Back to products')) ?></a>
    </div>

    <div class="shop-panel">
        <h4><?= e(__('Import product')) ?></h4>
        <p class="shop-muted"><?= e(__('Paste a product page link. The links are imported one by one. The page waits until the queue has been processed.')) ?></p>

        <form method="POST" data-request="onImport" data-request-flash>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="import-url"><?= e(__('Product link')) ?></label>
                <input id="import-url" type="url" name="url" class="form-control" required maxlength="2048" placeholder="https://www.trendyol.com/..." value="">
            </div>
            <div class="mb-3">
                <label class="form-label" for="import-category"><?= e(__('Category')) ?></label>
                <select id="import-category" name="category_id" class="form-select" required>
                    <option value=""><?= e(__('Select a category')) ?></option>
                    <?php foreach ($categories as $id => $name): ?>
                        <option value="<?= (int) $id ?>"><?= e($name) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="import-site"><?= e(__('Language')) ?></label>
                <select id="import-site" name="site_id" class="form-select">
                    <option value=""><?= e(__('Primary language')) ?></option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?= (int) $site->id ?>"><?= e($site->name) ?></option>
                    <?php endforeach ?>
                </select>
                <p class="help-block"><?= e(__('The imported texts are saved in this language. The product and its price are added to every site. Each site can use a different price.')) ?></p>
            </div>
            <button type="submit" class="btn btn-primary oc-icon-download" data-attach-loading><?= e(__('Import from link')) ?></button>
        </form>
    </div>

    <div class="shop-panel">
        <h4><?= e(__('Queued imports')) ?></h4>
        <?php if ($jobs->isEmpty()): ?>
            <p class="shop-muted"><?= e(__('No imports yet.')) ?></p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= e(__('Product link')) ?></th>
                        <th><?= e(__('Category')) ?></th>
                        <th><?= e(__('Status')) ?></th>
                        <th><?= e(__('Started')) ?></th>
                        <th><?= e(__('Finished')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td>
                                <div style="max-width: 420px; overflow-wrap: anywhere;"><?= e($job->url) ?></div>
                                <?php if ($job->result): ?>
                                    <details class="mt-1">
                                        <summary><?= e(__('Result')) ?></summary>
                                        <pre class="mt-2 mb-0" style="white-space: pre-wrap;"><?= e($job->result) ?></pre>
                                    </details>
                                <?php endif ?>
                            </td>
                            <td><?= e($job->category?->trans('name') ?: '—') ?></td>
                            <td><span class="shop-badge shop-badge-<?= e($job->status) ?>"><?= e(__($job->label())) ?></span></td>
                            <td><?= e($job->started_at?->format('Y-m-d H:i')) ?></td>
                            <td><?= e($job->finished_at?->format('Y-m-d H:i')) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
</div>
