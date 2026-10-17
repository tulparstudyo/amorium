<?php namespace AmoriumCms\Shop\Components;

use Cms\Classes\ComponentBase;
use AmoriumCms\Shop\Classes\Storefront;

/**
 * ShopProduct renders a product page on the server with the first page of its reviews.
 * Stock, the cart and favorites are handled by the storefront scripts through the API.
 */
class ShopProduct extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Shop Product',
            'description' => 'Shows a product with its variants, specs and reviews.',
        ];
    }

    public function defineProperties()
    {
        return [
            'slug' => [
                'title' => 'Slug',
                'description' => 'The URL parameter of the product.',
                'default' => '{{ :slug }}',
                'type' => 'string',
            ],
            'reviewsPerPage' => [
                'title' => 'Reviews per page',
                'default' => 5,
                'type' => 'string',
                'validationPattern' => '^[0-9]+$',
            ],
        ];
    }

    public function onRun()
    {
        $slug = (string) $this->property('slug');

        if ($slug === '' || !$product = Storefront::product($slug)) {
            return $this->controller->run('404');
        }

        $this->page['product'] = static::plain($product);
        $this->page['reviews'] = static::plain(Storefront::reviews($slug, 1, (int) $this->property('reviewsPerPage') ?: 5));
    }

    /**
     * plain turns the objects kept for empty JSON maps into arrays, so Twig can loop over them
     */
    protected static function plain($data)
    {
        return json_decode(json_encode($data), true);
    }
}
