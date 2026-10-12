<div data-control="toolbar">
    <?= Ui::ajaxButton(
        label: __("Delete Selected"),
        handler: 'onDelete',
        icon: 'oc-icon-delete',
        secondary: true,
        dataRequestConfirm: __("Are you sure?"),
        dataListCheckedTrigger: true,
        dataListCheckedRequest: true,
        dataStripeLoadIndicator: true
    ) ?>
</div>
