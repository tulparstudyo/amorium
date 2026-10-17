<?php $image = $record->images->first(); ?>
<?php if ($image && $image->image): ?>
    <img src="<?= e($image->image->getThumb(80, 80, ['mode' => 'crop'])) ?>" class="shop-thumb" alt="">
<?php else: ?>
    <div class="shop-thumb"></div>
<?php endif ?>
