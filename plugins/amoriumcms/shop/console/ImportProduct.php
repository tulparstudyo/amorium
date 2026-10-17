<?php namespace AmoriumCms\Shop\Console;

use Site;
use Illuminate\Console\Command;
use AmoriumCms\Shop\Models\Category;
use AmoriumCms\Shop\Classes\Import\SourceRegistry;
use AmoriumCms\Shop\Classes\Import\ImportedProduct;
use AmoriumCms\Shop\Classes\Import\ProductImporter;
use AmoriumCms\Shop\Classes\Import\ImportException;

/**
 * ImportProduct reads a product link and stores its variants, specs and images.
 * The domain of the link chooses the client and the adapter.
 */
class ImportProduct extends Command
{
    protected $signature = 'shop:import-product
        {url : Product page link}
        {--site= : Site id or code whose language receives the imported texts. Defaults to the primary site}
        {--category= : Category id the product is placed in}
        {--dry-run : Read the product without writing it}';

    protected $description = 'Import a product, with its variants, specs and images, from a shop link.';

    public function handle()
    {
        try {
            $url = (string) $this->argument('url');
            $source = (new SourceRegistry)->match($url);
            $product = $source->adapter->adapt($url, $source->client->fetch($url));

            if ($this->option('dry-run')) {
                $this->render($product);
                $this->comment('Nothing was written.');
                return 0;
            }

            $result = (new ProductImporter)->import($product, $this->siteId(), $this->categoryId());
        } catch (ImportException $exception) {
            $this->error($exception->getMessage());
            return 1;
        }

        $action = $result->created ? 'Created' : 'Updated';
        $this->info("{$action} {$result->name} ({$result->code}, group #{$result->groupId}).");
        $this->line("Barcodes: {$result->createdBarcodes} created, {$result->updatedBarcodes} updated, {$result->removedBarcodes} removed.");
        $this->line("Images: {$result->images}.");
        $this->line(__('The price was stored for every site.'));

        if ($result->category) {
            $this->line("Category: {$result->category}.");
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        $this->comment('Stock was not imported.');

        return 0;
    }

    /**
     * render prints the neutral product
     */
    protected function render(ImportedProduct $product): void
    {
        $this->info($product->name);
        $this->line("Source: {$product->source} #{$product->externalId}");
        $this->line("Code: {$product->code}");
        $this->line('Brand: ' . ($product->brand ?: '—'));
        $this->line("Tax: {$product->taxRate}%");
        $this->line('Currency: ' . ($product->currency ?: '—'));
        $this->line('Images: ' . count($product->images));

        $this->newLine();
        $this->line('Specs');
        $this->table(
            ['Spec', 'Value'],
            array_map(fn ($attribute) => [$attribute->name, $attribute->value], $product->attributes)
        );

        $this->line('Variants');
        $this->table(
            ['Barcode', 'SKU', 'Price', 'Compare at', 'Options'],
            array_map(function ($variant) {
                $options = array_map(fn ($attribute) => $attribute->name . ': ' . $attribute->value, $variant->attributes);

                return [
                    $variant->barcode,
                    $variant->sku,
                    $variant->price,
                    $variant->compareAtPrice,
                    implode(', ', $options),
                ];
            }, $product->variants)
        );
    }

    /**
     * siteId
     */
    protected function siteId(): int
    {
        $given = $this->option('site');
        $sites = Site::listEnabled();

        if ($given === null || $given === '') {
            $site = Site::getPrimarySite();
        } elseif (ctype_digit((string) $given)) {
            $site = $sites->firstWhere('id', (int) $given);
        } else {
            $site = $sites->firstWhere('code', (string) $given);
        }

        if (!$site) {
            throw new ImportException('The site was not found.');
        }

        return (int) $site->id;
    }

    /**
     * categoryId
     */
    protected function categoryId(): ?int
    {
        $given = $this->option('category');

        if ($given === null || $given === '') {
            return null;
        }

        if (!ctype_digit((string) $given) || !Category::whereKey((int) $given)->exists()) {
            throw new ImportException(__('The category was not found.'));
        }

        return (int) $given;
    }
}
