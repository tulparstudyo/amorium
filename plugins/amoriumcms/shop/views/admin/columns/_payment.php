<?php
$method = AmoriumCms\Shop\Classes\Payments\PaymentMethods::find($record->payment_method);
$name = $method
    ? Site::withContext($record->site_id, fn () => __($method->name()))
    : $record->payment_method;
?>
<?php if ($record->status !== AmoriumCms\Shop\Models\Order::STATUS_INCOMPLETE): ?>
    <span class="shop-badge shop-badge-<?= e($value) ?>"><?= e(__(AmoriumCms\Shop\Classes\StatusLabel::of($value))) ?></span>
<?php endif ?>
<div class="text-muted small"><?= e($name) ?></div>
