<?= e(implode(' · ', array_map(fn ($field) => $field['label'] . ': ' . $field['value'], $record->getFields()))) ?>
