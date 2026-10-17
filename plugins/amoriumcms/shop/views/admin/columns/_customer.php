<strong><?= e($value) ?></strong>
<?php if ($record->banned_at): ?>
    <span class="shop-badge shop-badge-cancelled"><?= e(__('Banned')) ?></span>
<?php endif ?>
