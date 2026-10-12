<div class="form-preview">
    <table class="table data">
        <tbody>
            <?php foreach ($formModel->getFields() as $field): ?>
                <tr>
                    <th class="text-nowrap" style="width: 25%"><?= e($field['label']) ?></th>
                    <td><?= nl2br(e($field['value'])) ?: '<span class="text-muted">—</span>' ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
