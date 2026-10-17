<?php if ($record->logo): ?>
    <img src="<?= e($record->logo->getThumb(80, 80, ['mode' => 'auto'])) ?>" class="shop-thumb" style="object-fit: contain" alt="">
<?php else: ?>
    <div class="shop-thumb"></div>
<?php endif ?>
