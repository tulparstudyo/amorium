<?php $address = (array) $record->billing_address; ?>
<?= e(trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''))) ?>
<div class="text-muted small">
    <?= e($record->customer_email) ?>
    <?php if (!$record->user_id): ?> · <?= e(__('Guest')) ?><?php endif ?>
</div>
