<?php if ($value === null || $value === ''): ?>
    —
<?php else: ?>
    <?= e(Illuminate\Support\Number::format((float) $value, precision: 2, locale: App::getLocale())) ?> <?= e($record->currency) ?>
<?php endif ?>
