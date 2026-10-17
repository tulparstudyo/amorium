<?php namespace AmoriumCms\Shop\Controllers;

use Site;
use Flash;
use Backend;
use BackendAuth;
use AmoriumCms\Shop\Classes\BackendListPage;
use AmoriumCms\Shop\Classes\Import\ImportQueue;
use AmoriumCms\Shop\Classes\Import\ImportException;
use AmoriumCms\Shop\Classes\Import\SourceRegistry;
use AmoriumCms\Shop\Models\ImportJob;
use AmoriumCms\Shop\Models\ProductGroup;

/**
 * Products lists the product groups. Groups, barcodes and images are edited in the Vue page.
 */
class Products extends BackendListPage
{
    public $requiredPermissions = ['shop.catalog.manage'];

    protected $section = 'products';

    protected $title = 'Products';

    protected $newLabel = 'New product';

    public function index()
    {
        parent::index();
        $this->vars['importUrl'] = Backend::url('amoriumcms/shop/products/import');
    }

    /**
     * import shows the queue form
     */
    public function import()
    {
        $this->bootImport();
    }

    /**
     * onImport stores the link and runs the pending imports in order
     */
    public function onImport()
    {
        $this->bootImport();

        try {
            $job = $this->queueImport();
        } catch (ImportException $exception) {
            Flash::error($exception->getMessage());
            return;
        }

        ImportQueue::drain();
        $job->refresh();

        if ($job->status === ImportJob::COMPLETED) {
            Flash::success(__('The import has been completed.'));
        } elseif ($job->status === ImportJob::FAILED) {
            Flash::error(__('The import failed.'));
        } else {
            Flash::success(__('The link was added to the queue.'));
        }

        return Backend::redirect('amoriumcms/shop/products/import');
    }

    /**
     * bootImport
     */
    protected function bootImport(): void
    {
        $this->bootPage('products', 'Import product');
        $this->vars['sites'] = Site::listEnabled();
        $this->vars['categories'] = (new ProductGroup)->getCategoryFilterOptions();
        $this->vars['jobs'] = ImportJob::with('category.translations')->orderBy('id', 'desc')->limit(50)->get();
    }

    /**
     * queueImport stores the posted link when it is not already waiting
     */
    protected function queueImport(): ImportJob
    {
        $url = trim((string) post('url'));
        (new SourceRegistry)->match($url);

        $siteId = post('site_id');
        $siteId = $siteId === null || $siteId === '' ? null : (int) $siteId;

        if ($siteId && !Site::listEnabled()->contains('id', $siteId)) {
            throw new ImportException(__('The site was not found.'));
        }

        $categoryId = (int) post('category_id');

        if (!$categoryId || !array_key_exists($categoryId, (new ProductGroup)->getCategoryFilterOptions())) {
            throw new ImportException(__('The category was not found.'));
        }

        $waiting = ImportJob::where('url', $url)
            ->whereIn('status', [ImportJob::PENDING, ImportJob::PROCESSING])
            ->exists();

        if ($waiting) {
            throw new ImportException(__('This link is already queued.'));
        }

        return ImportJob::create([
            'url' => $url,
            'site_id' => $siteId,
            'category_id' => $categoryId,
            'status' => ImportJob::PENDING,
            'backend_user_id' => BackendAuth::getUser()?->id,
        ]);
    }

    public function listExtendColumns($list)
    {
        $list->getColumn('name')->sqlSelect($this->translatedSelect('amoriumcms_shop_product_group_translations', 'group_id'));
        $list->getColumn('brand_name')->sqlSelect($this->translatedSelect('amoriumcms_shop_brand_translations', 'brand_id', 'brand_id'));
        $list->getColumn('color_name')->sqlSelect($this->translatedSelect('amoriumcms_shop_color_translations', 'color_id', 'color_id'));
        $list->getColumn('category_name')->sqlSelect($this->translatedSelect('amoriumcms_shop_category_translations', 'category_id', 'primary_category_id'));
    }

    public function listExtendQuery($query)
    {
        $query->with(['images' => fn ($q) => $q->orderBy('sort_order')->with('image')]);
    }
}
