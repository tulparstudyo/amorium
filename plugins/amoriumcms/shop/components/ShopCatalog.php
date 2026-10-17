<?php namespace AmoriumCms\Shop\Components;

use Request;
use Cms\Classes\ComponentBase;
use AmoriumCms\Shop\Classes\Storefront;
use AmoriumCms\Shop\Classes\CatalogQuery;

/**
 * ShopCatalog renders a product listing on the server: every product, a category or a brand.
 * The search, sorting, filters and page are read from the query string, with the same
 * parameters as GET /products of the API.
 */
class ShopCatalog extends ComponentBase
{
    const MODE_ALL = 'all';
    const MODE_CATEGORY = 'category';
    const MODE_BRAND = 'brand';

    /**
     * @var array params of the listing, as normalized by CatalogQuery
     */
    public $params = [];

    public function componentDetails()
    {
        return [
            'name' => 'Shop Catalog',
            'description' => 'Lists the products of the shop, a category or a brand.',
        ];
    }

    public function defineProperties()
    {
        return [
            'mode' => [
                'title' => 'Listing',
                'type' => 'dropdown',
                'default' => self::MODE_ALL,
                'options' => [
                    self::MODE_ALL => 'All products',
                    self::MODE_CATEGORY => 'Category',
                    self::MODE_BRAND => 'Brand',
                ],
            ],
            'slug' => [
                'title' => 'Slug',
                'description' => 'The URL parameter of the category or brand.',
                'default' => '{{ :slug }}',
                'type' => 'string',
            ],
            'perPage' => [
                'title' => 'Products per page',
                'default' => 24,
                'type' => 'string',
                'validationPattern' => '^[0-9]+$',
            ],
        ];
    }

    public function onRun()
    {
        $input = Request::query();
        unset($input['category'], $input['brand']);

        $mode = $this->property('mode');
        $slug = (string) $this->property('slug');

        if ($mode === self::MODE_CATEGORY) {
            if ($slug === '' || !$category = Storefront::category($slug)) {
                return $this->notFound();
            }
            $input['category'] = $slug;
            $this->page['category'] = $category;
        }
        elseif ($mode === self::MODE_BRAND) {
            if ($slug === '' || !$brand = Storefront::brand($slug)) {
                return $this->notFound();
            }
            $input['brand'] = $slug;
            $this->page['brand'] = $brand;
        }
        elseif (isset(Request::query()['brand'])) {
            $input['brand'] = Request::query('brand');
        }

        $input['per_page'] = (int) $this->property('perPage') ?: 24;

        $this->params = CatalogQuery::normalize($input);
        $result = Storefront::products($input);

        $this->page['products'] = $result['data'];
        $this->page['pagination'] = $result['meta'];
        $this->page['filters'] = Storefront::filters($input);
        $this->page['params'] = $this->params;
    }

    /**
     * categories returns the active category tree
     */
    public function categories(): array
    {
        return Storefront::categories();
    }

    /**
     * url returns the address of the listing with changed query parameters. A null value
     * removes the parameter; the page goes back to 1 unless it is given.
     */
    public function url(array $changes = []): string
    {
        $query = array_except(Request::query(), ['category']);

        if (!array_key_exists('page', $changes)) {
            unset($query['page']);
        }

        foreach ($changes as $key => $value) {
            if ($value === null || $value === '' || ($key === 'page' && (int) $value <= 1)) {
                unset($query[$key]);
            }
            else {
                $query[$key] = $value;
            }
        }

        if ($this->property('mode') === self::MODE_BRAND) {
            unset($query['brand']);
        }

        $query = array_filter($query, fn ($value) => $value !== null && $value !== '' && $value !== []);

        return Request::url() . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * filterUrl adds or removes an option of a select or boolean spec filter
     */
    public function filterUrl(string $code, string $value): string
    {
        $filters = Request::query('filter');
        $filters = is_array($filters) ? $filters : [];
        $current = $this->filterValues($code);

        $current = in_array($value, $current, true)
            ? array_values(array_diff($current, [$value]))
            : array_merge($current, [$value]);

        if ($current) {
            $filters[$code] = implode(',', $current);
        }
        else {
            unset($filters[$code]);
        }

        return $this->url(['filter' => $filters ?: null]);
    }

    /**
     * isFiltered tells whether an option of a spec filter is chosen
     */
    public function isFiltered(string $code, string $value): bool
    {
        return in_array($value, $this->filterValues($code), true);
    }

    /**
     * hasFilters tells whether the listing is narrowed by anything but the category or brand
     */
    public function hasFilters(): bool
    {
        $query = Request::query();

        return !empty($query['filter']) || isset($query['price_min']) || isset($query['price_max']) || !empty($query['q'])
            || ($this->property('mode') !== self::MODE_BRAND && !empty($query['brand']));
    }

    /**
     * clearUrl removes the search and every filter but keeps the sorting
     */
    public function clearUrl(): string
    {
        return $this->url(['q' => null, 'filter' => null, 'price_min' => null, 'price_max' => null, 'brand' => null]);
    }

    /**
     * hiddenFields returns the current query as name => value pairs for the hidden inputs of
     * a GET form, leaving out the fields the form sends itself
     */
    public function hiddenFields(array $except = []): array
    {
        $query = array_except(Request::query(), array_merge(['page', 'category'], $except));

        if ($this->property('mode') === self::MODE_BRAND) {
            unset($query['brand']);
        }

        $fields = [];
        foreach (explode('&', http_build_query($query)) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($name);

            if (!$this->isExcluded($name, $except)) {
                $fields[$name] = urldecode($value);
            }
        }

        return $fields;
    }

    /**
     * isExcluded matches a field name such as filter[ram][min] against prefixes such as filter[ram]
     */
    protected function isExcluded(string $name, array $except): bool
    {
        foreach ($except as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix . '[')) {
                return true;
            }
        }

        return false;
    }

    /**
     * filterValues
     */
    protected function filterValues(string $code): array
    {
        $filters = Request::query('filter');
        $value = is_array($filters) ? ($filters[$code] ?? '') : '';

        return is_string($value) && $value !== '' ? array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen')) : [];
    }

    /**
     * notFound shows the 404 page
     */
    protected function notFound()
    {
        return $this->controller->run('404');
    }
}
