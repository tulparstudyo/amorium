<?= e(__(['select' => 'Select', 'number' => 'Number', 'text' => 'Text', 'boolean' => 'Yes / No'][$value] ?? $value)) ?>
<?php if ($record->unit): ?><span class="text-muted">(<?= e($record->unit) ?>)</span><?php endif ?>
