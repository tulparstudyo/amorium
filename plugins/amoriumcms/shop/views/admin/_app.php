<div id="shop-admin" class="shop-admin" data-config="<?= e(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
<link href="<?= Url::asset('plugins/amoriumcms/shop/assets/css/admin.css') ?>?v=<?= filemtime(plugins_path('amoriumcms/shop/assets/css/admin.css')) ?>" rel="stylesheet">
<script type="module">
    import { mount } from '<?= $appUrl ?>';
    mount(document.getElementById('shop-admin'));
</script>
