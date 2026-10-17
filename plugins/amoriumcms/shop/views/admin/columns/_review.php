<strong><?= e($record->title) ?></strong>
<?php if ($record->order_item_id): ?>
    <span class="shop-badge shop-badge-approved"><?= e(__('Verified purchase')) ?></span>
<?php endif ?>
<div class="text-muted small"><?= e(Str::limit((string) $record->body, 120)) ?></div>
