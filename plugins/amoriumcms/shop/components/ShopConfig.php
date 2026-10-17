<?php namespace AmoriumCms\Shop\Components;

use App;
use Url;
use Auth;
use Site;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Number;
use AmoriumCms\Shop\Classes\Presenter;
use AmoriumCms\Shop\Classes\Storefront;

/**
 * ShopConfig gives the theme what the storefront scripts need: the API address of the
 * active site, a bearer token for the signed in customer and money formatting.
 */
class ShopConfig extends ComponentBase
{
    /**
     * @var string|null token is created once per request
     */
    protected $token;

    public function componentDetails()
    {
        return [
            'name' => 'Shop Config',
            'description' => 'Gives the storefront scripts the API address, the sign in token and the currency.',
        ];
    }

    /**
     * apiBase of the active site, such as /api/v1/tr
     */
    public function apiBase(): string
    {
        return Url::to('api/v1/' . Site::getActiveSite()?->code);
    }

    /**
     * token returns a bearer token of the signed in customer
     */
    public function token(): ?string
    {
        if ($this->token === null && Auth::check()) {
            $this->token = Auth::getBearerToken();
        }

        return $this->token;
    }

    /**
     * currency of the active site
     */
    public function currency(): string
    {
        return Presenter::currency();
    }

    /**
     * money formats an amount in the currency of the active site
     */
    public function money($amount, ?string $currency = null): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $currency = $currency ?: $this->currency();

        if (!$currency) {
            return Number::format((float) $amount, precision: 2, locale: App::getLocale());
        }

        return Number::currency((float) $amount, $currency, App::getLocale());
    }

    /**
     * categories returns the active category tree, for menus
     */
    public function categories(): array
    {
        return Storefront::categories();
    }

    /**
     * brands returns the active brands
     */
    public function brands(): array
    {
        return Storefront::brands();
    }

    /**
     * onShopToken renews the token of a page that stayed open longer than the token lifetime
     */
    public function onShopToken()
    {
        return ['token' => $this->token()];
    }
}
