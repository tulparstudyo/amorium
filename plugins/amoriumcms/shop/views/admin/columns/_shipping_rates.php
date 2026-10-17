<?php
use Illuminate\Support\Number;
use AmoriumCms\Shop\Models\ShopSetting;

$money = fn ($value) => Number::format((float) $value, precision: 2, locale: App::getLocale());
?>
<?php foreach ($record->rates as $rate): ?>
    <?php
        $site = Site::getSiteFromId($rate->site_id);
        $currency = Site::withContext($rate->site_id, fn () => ShopSetting::value('currency'));
    ?>
    <div class="small">
        <?= e($site?->name ?? $rate->site_id) ?>: <?= e($money($rate->price)) ?> <?= e($currency) ?>
        <?php if ($rate->free_over !== null): ?>
            <span class="text-muted">· <?= e(__('Free over :amount', ['amount' => $money($rate->free_over) . ' ' . $currency])) ?></span>
        <?php endif ?>
    </div>
<?php endforeach ?>
<?php if (!count($record->rates)): ?>—<?php endif ?>
