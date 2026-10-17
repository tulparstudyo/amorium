<div id="shop-list" class="shop-list">
    <?= $this->listRender() ?>
</div>
<script>
    if (location.hash.replace(/^#\/?/, '')) {
        document.getElementById('shop-list').hidden = true;
    }
</script>
<?= $this->makePartial('app') ?>
