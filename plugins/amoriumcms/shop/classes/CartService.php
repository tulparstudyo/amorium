<?php namespace AmoriumCms\Shop\Classes;

use Db;
use Auth;
use Site;
use Request;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Classes\Payments\PaymentMethods;
use AmoriumCms\Shop\Classes\Payments\StartsPayment;
use AmoriumCms\Shop\Models\Cart;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Product;
use AmoriumCms\Shop\Models\CartItem;
use AmoriumCms\Shop\Models\ShopSetting;
use AmoriumCms\Shop\Models\ShippingMethod;

/**
 * CartService finds the cart of the request and calculates its totals from the current prices.
 * Guests send the X-Cart-Token header; a signed in customer has one open cart per site,
 * and a guest cart sent with the token is merged into it.
 */
class CartService
{
    const MAX_QUANTITY = 999;

    /**
     * resolve
     */
    public static function resolve(bool $create = false): Cart
    {
        $siteId = (int) Site::getActiveSite()->id;
        $token = (string) Request::header('X-Cart-Token');
        $user = Auth::user();

        $tokenCart = $token !== ''
            ? Cart::isOpen()->where('token', $token)->where('site_id', $siteId)->first()
            : null;

        if ($user) {
            $cart = Cart::isOpen()->where('user_id', $user->id)->where('site_id', $siteId)->orderBy('id', 'desc')->first();

            if ($tokenCart && !$tokenCart->user_id) {
                if ($cart) {
                    static::merge($tokenCart, $cart);
                }
                else {
                    $tokenCart->user_id = $user->id;
                    $tokenCart->save();
                    $cart = $tokenCart;
                }
            }

            if (!$cart) {
                $cart = static::reopenUnpaid($user->id, $siteId);
            }

            if (!$cart) {
                $cart = static::create();
            }
        }
        else {
            $cart = $tokenCart && !$tokenCart->user_id ? $tokenCart : null;

            if (!$cart && $create) {
                $cart = static::create();
            }
        }

        if (!$cart) {
            throw ApiException::make(ApiCode::CART_NOT_FOUND);
        }

        if (!$cart->expires_at || $cart->expires_at->lt(static::expiry()->subDay())) {
            $cart->expires_at = static::expiry();
            $cart->save();
        }

        return $cart;
    }

    /**
     * reopenUnpaid gives back the cart of a card payment the customer left unfinished.
     * The cart stays closed only after the payment is confirmed.
     */
    protected static function reopenUnpaid(int $userId, int $siteId): ?Cart
    {
        $cart = Cart::where('user_id', $userId)->where('site_id', $siteId)->whereNotNull('order_id')->orderBy('id', 'desc')->first();
        if (!$cart) {
            return null;
        }

        $order = Order::where('id', $cart->order_id)->where('payment_status', Order::PAYMENT_PENDING)->where('status', Order::STATUS_PENDING_PAYMENT)->first();
        $method = $order ? PaymentMethods::find($order->payment_method) : null;

        if (!$order || !$method instanceof StartsPayment) {
            return null;
        }

        $cart->order_id = null;
        $cart->save();

        return $cart;
    }

    /**
     * create
     */
    public static function create(): Cart
    {
        return Cart::create([
            'user_id' => Auth::id(),
            'site_id' => Site::getActiveSite()->id,
            'expires_at' => static::expiry(),
        ]);
    }

    /**
     * merge moves the items of a guest cart into a customer cart
     */
    public static function merge(Cart $from, Cart $to)
    {
        Db::transaction(function () use ($from, $to) {
            foreach ($from->items as $item) {
                $target = $to->items()->firstOrNew(['product_id' => $item->product_id]);
                $target->quantity = min(static::MAX_QUANTITY, (int) $target->quantity + $item->quantity);
                $target->save();
            }

            Order::where('cart_id', $from->id)->where('status', Order::STATUS_PAYMENT_NOT_MADE)->get()->each->delete();
            $from->delete();
        });

        $to->unsetRelation('items');
    }

    /**
     * mergeOnSignIn merges the guest cart sent with the sign in request
     */
    public static function mergeOnSignIn($user, ?string $token)
    {
        if (!$token) {
            return;
        }

        $siteId = (int) Site::getActiveSite()->id;

        $guestCart = Cart::isOpen()->where('token', $token)->where('site_id', $siteId)->whereNull('user_id')->first();
        if (!$guestCart) {
            return;
        }

        $cart = Cart::isOpen()->where('user_id', $user->id)->where('site_id', $siteId)->orderBy('id', 'desc')->first();
        if ($cart) {
            static::merge($guestCart, $cart);
        }
        else {
            $guestCart->user_id = $user->id;
            $guestCart->save();
        }
    }

    /**
     * setQuantity sets the quantity of a barcode; zero removes it. More than the stock
     * returns OUT_OF_STOCK, which is counted by the OutOfStockGuard.
     */
    public static function setQuantity(Cart $cart, int $productId, int $quantity)
    {
        if ($quantity <= 0) {
            $cart->items()->where('product_id', $productId)->delete();
            $cart->unsetRelation('items');
            return;
        }

        $product = Product::with(['group', 'prices'])->find($productId);
        if (!$product || !$product->group) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        if (!$product->is_active || !$product->group->is_active) {
            throw ApiException::make(ApiCode::PRODUCT_INACTIVE);
        }

        if (!$product->priceFor()) {
            throw ApiException::make(ApiCode::PRICE_NOT_SET);
        }

        OutOfStockGuard::run($cart, function () use ($product, $quantity) {
            if ($product->stock_quantity < $quantity) {
                throw ApiException::make(ApiCode::OUT_OF_STOCK, ['items' => [StockService::shortage($product, $product->id)]]);
            }
        });

        $item = $cart->items()->firstOrNew(['product_id' => $productId]);
        $item->quantity = $quantity;
        $item->save();

        $cart->unsetRelation('items');
    }

    /**
     * currentQuantity
     */
    public static function currentQuantity(Cart $cart, int $productId): int
    {
        return (int) CartItem::where('cart_id', $cart->id)->where('product_id', $productId)->value('quantity');
    }

    /**
     * totals calculates the cart with the current prices. Lines that cannot be sold are
     * returned as unavailable and left out of the totals.
     */
    public static function totals(Cart $cart): array
    {
        $cart->loadMissing([
            'items.product.group.translations',
            'items.product.group.images.image',
            'items.product.prices',
            'items.product.spec_values.spec.translations',
            'items.product.spec_values.option.translations',
        ]);

        $lines = [];
        $subtotal = '0';
        $taxTotal = '0';

        foreach ($cart->items->sortBy('id') as $item) {
            $product = $item->product;
            $available = $product && $product->isSellable();
            $price = $available ? $product->priceFor() : null;

            $line = [
                'product_id' => $item->product_id,
                'group_id' => $product?->group_id,
                'barcode' => $product?->barcode,
                'sku' => $product?->sku,
                'name' => $product?->group?->trans('name'),
                'slug' => $product?->group?->trans('slug'),
                'variant' => $product ? (object) $product->variantLabel() : null,
                'image' => static::imageFor($product),
                'quantity' => $item->quantity,
                'available' => $available,
                'stock_status' => $product ? StockStatus::forProduct($product) : StockStatus::OUT_OF_STOCK,
                'unit_price' => null,
                'tax_rate' => null,
                'tax_amount' => null,
                'line_total' => null,
            ];

            if ($price) {
                $lineTotal = Money::multiply($price->price, $item->quantity);
                $tax = Money::includedTax($lineTotal, $product->tax_rate);

                $line['unit_price'] = Money::round($price->price);
                $line['tax_rate'] = (float) $product->tax_rate;
                $line['tax_amount'] = $tax;
                $line['line_total'] = $lineTotal;

                $subtotal = Money::add($subtotal, $lineTotal);
                $taxTotal = Money::add($taxTotal, $tax);
            }

            $lines[] = $line;
        }

        $serviceTaxRate = (string) ShopSetting::value('service_tax_rate');

        $shipping = null;
        $shippingMethod = $cart->shipping_method_id ? ShippingMethod::available()->find($cart->shipping_method_id) : null;
        if ($shippingMethod && $lines) {
            $shipping = $shippingMethod->priceFor($subtotal);
            $taxTotal = Money::add($taxTotal, Money::includedTax($shipping, $serviceTaxRate));
        }

        $fee = '0.00';
        if ($method = PaymentMethods::find($cart->payment_method)) {
            $fee = $method->fee();
            $taxTotal = Money::add($taxTotal, Money::includedTax($fee, $serviceTaxRate));
        }

        return [
            'lines' => $lines,
            'shipping_method' => $shippingMethod,
            'subtotal' => Money::round($subtotal),
            'shipping_total' => $shipping,
            'payment_fee' => $fee,
            'tax_total' => Money::round($taxTotal),
            'grand_total' => Money::add($subtotal, $shipping ?? '0', $fee),
        ];
    }

    /**
     * present
     */
    public static function present(Cart $cart): array
    {
        $totals = static::totals($cart);

        return [
            'token' => $cart->token,
            'currency' => Presenter::currency(),
            'items' => array_map(fn ($line) => static::presentLine($line), $totals['lines']),
            'item_count' => array_sum(array_column($totals['lines'], 'quantity')),
            'totals' => static::presentTotals($totals),
        ];
    }

    /**
     * presentTotals
     */
    public static function presentTotals(array $totals): array
    {
        return [
            'subtotal' => Money::toFloat($totals['subtotal']),
            'shipping_total' => Money::toFloat($totals['shipping_total']),
            'payment_fee' => Money::toFloat($totals['payment_fee']),
            'tax_total' => Money::toFloat($totals['tax_total']),
            'grand_total' => Money::toFloat($totals['grand_total']),
        ];
    }

    /**
     * presentLine
     */
    protected static function presentLine(array $line): array
    {
        foreach (['unit_price', 'tax_amount', 'line_total'] as $key) {
            $line[$key] = Money::toFloat($line[$key]);
        }

        return $line;
    }

    /**
     * imageFor prefers an image bound to one of the variant options of the barcode
     */
    protected static function imageFor(?Product $product): ?array
    {
        if (!$product || !$product->group) {
            return null;
        }

        $optionIds = $product->variantOptions()->pluck('spec_option_id')->all();
        $images = $product->group->images->filter(fn ($image) => $image->image);

        $image = $images->first(fn ($image) => $image->spec_option_id && in_array($image->spec_option_id, $optionIds))
            ?: $images->first(fn ($image) => !$image->spec_option_id)
            ?: $images->first();

        return $image ? Presenter::image($image) : null;
    }

    /**
     * expiry
     */
    protected static function expiry()
    {
        return now()->addDays((int) ShopSetting::value('cart_ttl_days'));
    }
}
