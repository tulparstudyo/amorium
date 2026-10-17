<?php namespace AmoriumCms\Shop\Classes;

use Db;
use App;
use Str;
use Auth;
use Site;
use Request;
use Validator;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Classes\Payments\PaymentMethods;
use AmoriumCms\Shop\Classes\Payments\StartsPayment;
use AmoriumCms\Shop\Models\Cart;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Address;
use AmoriumCms\Shop\Models\Payment;
use AmoriumCms\Shop\Models\OrderItem;
use AmoriumCms\Shop\Models\ShopSetting;
use AmoriumCms\Shop\Models\ShippingMethod;

/**
 * CheckoutService runs the one page checkout: the selections are saved on the cart with
 * PUT /checkout and the order is placed with POST /checkout/place.
 */
class CheckoutService
{
    /**
     * show returns everything the checkout page needs in one answer
     */
    public static function show(Cart $cart): array
    {
        $totals = CartService::totals($cart);
        $user = Auth::user();

        $shippingMethods = ShippingMethod::available()->get()->map(
            fn ($method) => Presenter::shippingMethod($method, $method->priceFor($totals['subtotal']))
        )->values()->all();

        return [
            'cart' => CartService::present($cart),
            'is_guest' => !$user,
            'email' => $user ? $user->email : $cart->email,
            'addresses' => $user
                ? Address::where('user_id', $user->id)->orderBy('is_default', 'desc')->orderBy('id')->get()->map(fn ($address) => AddressPresenter::present($address))->values()->all()
                : [],
            'selection' => [
                'shipping_address_id' => $cart->shipping_address_id,
                'billing_address_id' => $cart->billing_address_id,
                'shipping_address' => $cart->guest_shipping_address,
                'billing_address' => $cart->guest_billing_address,
                'shipping_method_id' => $cart->shipping_method_id,
                'payment_method' => $cart->payment_method,
                'customer_note' => $cart->customer_note,
            ],
            'shipping_methods' => $shippingMethods,
            'payment_methods' => array_values(array_map(
                fn ($method) => PaymentMethods::present($method),
                PaymentMethods::available()
            )),
        ];
    }

    /**
     * update saves the selections. Customers choose addresses from their address book,
     * guests send the addresses themselves. Only the given fields change.
     */
    public static function update(Cart $cart, array $input): Cart
    {
        $user = Auth::user();

        $rules = [
            'shipping_method_id' => 'nullable|integer',
            'payment_method' => 'nullable|string',
            'customer_note' => 'nullable|string|max:1000',
            'billing_same_as_shipping' => 'nullable|boolean',
        ];

        if ($user) {
            $rules['shipping_address_id'] = 'nullable|integer';
            $rules['billing_address_id'] = 'nullable|integer';
        }
        else {
            $rules['email'] = 'nullable|email|max:255';
            $rules['shipping_address'] = 'nullable|array';
            $rules['billing_address'] = 'nullable|array';
            if (!empty($input['shipping_address'])) {
                $rules += Address::rules('shipping_address.');
            }
            if (!empty($input['billing_address'])) {
                $rules += Address::rules('billing_address.', true);
            }
        }

        $data = Validator::make($input, $rules)->validate();

        if ($user) {
            foreach (['shipping_address_id' => Address::TYPE_SHIPPING, 'billing_address_id' => Address::TYPE_BILLING] as $field => $type) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }

                if ($data[$field] && !Address::where('user_id', $user->id)->where('id', $data[$field])->exists()) {
                    throw ApiException::validation([$field => __('The address was not found in your address book.')]);
                }

                $cart->{$field} = $data[$field];
            }

            if (!empty($data['billing_same_as_shipping'])) {
                $cart->billing_address_id = $cart->shipping_address_id;
            }
        }
        else {
            if (array_key_exists('email', $data)) {
                $cart->email = $data['email'];
            }

            if (array_key_exists('shipping_address', $data)) {
                $cart->guest_shipping_address = $data['shipping_address'] ? array_only($data['shipping_address'], Address::SNAPSHOT_FIELDS) : null;
            }

            if (array_key_exists('billing_address', $data)) {
                $cart->guest_billing_address = $data['billing_address'] ? array_only($data['billing_address'], Address::SNAPSHOT_FIELDS) : null;
            }

            if (!empty($data['billing_same_as_shipping'])) {
                $cart->guest_billing_address = $cart->guest_shipping_address;
            }
        }

        if (array_key_exists('shipping_method_id', $data)) {
            if ($data['shipping_method_id'] && !ShippingMethod::available()->find($data['shipping_method_id'])) {
                throw ApiException::make(ApiCode::SHIPPING_METHOD_UNAVAILABLE);
            }

            $cart->shipping_method_id = $data['shipping_method_id'];
        }

        if (array_key_exists('payment_method', $data)) {
            if ($data['payment_method'] && !isset(PaymentMethods::available()[$data['payment_method']])) {
                throw ApiException::make(ApiCode::PAYMENT_METHOD_UNAVAILABLE);
            }

            $cart->payment_method = $data['payment_method'];
        }

        if (array_key_exists('customer_note', $data)) {
            $cart->customer_note = $data['customer_note'];
        }

        $cart->save();
        $cart->unsetRelation('shipping_method');

        return $cart;
    }

    /**
     * place creates the order. The same Idempotency-Key returns the order created before.
     * When expected_total is sent and the total has changed, PRICE_CHANGED is returned.
     */
    public static function place(?string $idempotencyKey, $expectedTotal = null): array
    {
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            throw ApiException::make(ApiCode::IDEMPOTENCY_KEY_MISSING);
        }

        if ($order = static::findDuplicate($idempotencyKey)) {
            return ['order' => $order, 'duplicate' => true];
        }

        $cart = CartService::resolve();
        $user = Auth::user();

        if (!$cart->items()->exists()) {
            throw ApiException::make(ApiCode::CART_EMPTY);
        }

        [$shippingAddress, $billingAddress, $email, $phone] = static::addresses($cart, $user);

        $shippingMethod = $cart->shipping_method_id ? ShippingMethod::available()->find($cart->shipping_method_id) : null;
        if (!$shippingMethod) {
            throw ApiException::make(ApiCode::SHIPPING_METHOD_UNAVAILABLE);
        }

        $paymentMethod = PaymentMethods::available()[$cart->payment_method] ?? null;
        if (!$paymentMethod) {
            throw ApiException::make(ApiCode::PAYMENT_METHOD_UNAVAILABLE);
        }

        $totals = CartService::totals($cart);

        $unavailable = array_filter($totals['lines'], fn ($line) => !$line['available']);
        if ($unavailable) {
            OutOfStockGuard::run($cart, function () use ($unavailable) {
                throw ApiException::make(ApiCode::OUT_OF_STOCK, ['items' => array_values(array_map(fn ($line) => [
                    'product_id' => $line['product_id'],
                    'barcode' => $line['barcode'],
                    'stock_status' => StockStatus::OUT_OF_STOCK,
                ], $unavailable))]);
            });
        }

        if ($expectedTotal !== null && $expectedTotal !== '' && Money::compare($expectedTotal, $totals['grand_total']) !== 0) {
            throw ApiException::make(ApiCode::PRICE_CHANGED, ['cart' => CartService::present($cart)]);
        }

        $order = OutOfStockGuard::run($cart, function () use ($cart, $user, $totals, $shippingMethod, $paymentMethod, $shippingAddress, $billingAddress, $email, $phone, $idempotencyKey) {
            return Db::transaction(function () use ($cart, $user, $totals, $shippingMethod, $paymentMethod, $shippingAddress, $billingAddress, $email, $phone, $idempotencyKey) {
                $cart = Cart::lockForUpdate()->find($cart->id);
                if ($cart->order_id) {
                    throw ApiException::make(ApiCode::CART_NOT_FOUND);
                }

                $order = Order::where('cart_id', $cart->id)->where('status', Order::STATUS_PAYMENT_NOT_MADE)->first() ?: new Order;
                $wasDraft = $order->exists;

                $order->fill([
                    'user_id' => $user?->id,
                    'cart_id' => $cart->id,
                    'site_id' => Site::getActiveSite()->id,
                    'locale' => App::getLocale(),
                    'currency' => Presenter::currency(),
                    'status' => $paymentMethod->orderStatus(),
                    'payment_status' => Order::PAYMENT_PENDING,
                    'payment_method' => $paymentMethod->code(),
                    'shipping_method_id' => $shippingMethod->id,
                    'shipping_method_name' => $shippingMethod->trans('name'),
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'shipping_total' => $totals['shipping_total'] ?? '0.00',
                    'payment_fee' => $totals['payment_fee'],
                    'grand_total' => $totals['grand_total'],
                    'shipping_address' => $shippingAddress,
                    'billing_address' => $billingAddress,
                    'customer_email' => $email,
                    'customer_phone' => $phone,
                    'customer_note' => $cart->customer_note,
                    'idempotency_key' => $idempotencyKey,
                    'guest_access_token' => $user ? null : Str::random(48),
                    'placed_at' => now(),
                ]);
                $order->save();

                $order->number = static::orderNumber($order);
                $order->save();

                static::writeItems($order, $totals['lines']);

                StockService::reserve(array_column($totals['lines'], 'quantity', 'product_id'), $order->id);

                $payment = Payment::create([
                    'order_id' => $order->id,
                    'method' => $paymentMethod->code(),
                    'amount' => $totals['grand_total'],
                    'status' => Payment::STATUS_PENDING,
                ]);

                if ($paymentMethod instanceof StartsPayment) {
                    $paymentMethod->start($order, $payment);
                }

                OrderService::record($order, $wasDraft ? Order::STATUS_PAYMENT_NOT_MADE : null, $order->status);

                $cart->order_id = $order->id;
                $cart->save();

                return $order;
            });
        });

        OrderMailer::placed($order);

        return ['order' => $order, 'duplicate' => false];
    }

    /**
     * trackPaymentNotMade keeps a draft order for a customer who reached the checkout
     * with an e-mail address, so the admins see the checkouts that were left unfinished.
     * The order takes no stock and no number; placing the cart completes the same order.
     */
    public static function trackPaymentNotMade(Cart $cart)
    {
        $user = Auth::user();
        $email = $user ? $user->email : $cart->email;

        Db::transaction(function () use ($cart, $user, $email) {
            $cart = Cart::lockForUpdate()->find($cart->id);
            if (!$cart || $cart->order_id) {
                return;
            }

            $order = Order::where('cart_id', $cart->id)->where('status', Order::STATUS_PAYMENT_NOT_MADE)->first();

            if (!$cart->items()->exists()) {
                $order?->delete();
                return;
            }

            if (!$email) {
                return;
            }

            if ($user) {
                $shipping = $cart->shipping_address_id ? Address::where('user_id', $user->id)->find($cart->shipping_address_id)?->snapshot() : null;
                $billing = $cart->billing_address_id ? Address::where('user_id', $user->id)->find($cart->billing_address_id)?->snapshot() : null;
            }
            else {
                $shipping = $cart->guest_shipping_address;
                $billing = $cart->guest_billing_address;
            }

            $totals = CartService::totals($cart);
            $shippingMethod = $totals['shipping_method'];

            $isNew = !$order;
            $order = $order ?: new Order(['cart_id' => $cart->id, 'status' => Order::STATUS_PAYMENT_NOT_MADE, 'payment_status' => Order::PAYMENT_PENDING]);

            $order->fill([
                'user_id' => $user?->id,
                'site_id' => $cart->site_id,
                'locale' => App::getLocale(),
                'currency' => Presenter::currency(),
                'payment_method' => (string) $cart->payment_method,
                'shipping_method_id' => $shippingMethod?->id,
                'shipping_method_name' => $shippingMethod?->trans('name'),
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'shipping_total' => $totals['shipping_total'] ?? '0.00',
                'payment_fee' => $totals['payment_fee'],
                'grand_total' => $totals['grand_total'],
                'shipping_address' => $shipping ?: [],
                'billing_address' => $billing ?: [],
                'customer_email' => $email,
                'customer_phone' => $user
                    ? (\AmoriumCms\Shop\Models\Customer::where('user_id', $user->id)->value('phone') ?: ($shipping['phone'] ?? null))
                    : ($shipping['phone'] ?? null),
                'customer_note' => $cart->customer_note,
                'placed_at' => now(),
            ]);
            $order->save();

            static::writeItems($order, $totals['lines']);

            if ($isNew) {
                OrderService::record($order, null, Order::STATUS_PAYMENT_NOT_MADE);
            }
        });
    }

    /**
     * writeItems replaces the lines of an order with a copy of the cart lines
     */
    protected static function writeItems(Order $order, array $lines)
    {
        $order->items()->delete();

        foreach ($lines as $line) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'group_id' => $line['group_id'],
                'barcode' => $line['barcode'],
                'sku' => $line['sku'],
                'name' => (string) $line['name'],
                'variant_label' => (array) $line['variant'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
            ]);
        }
    }

    /**
     * findDuplicate looks for an order placed with the same key from the same cart or customer
     */
    protected static function findDuplicate(string $key): ?Order
    {
        $query = Order::where('idempotency_key', $key);

        if ($user = Auth::user()) {
            return $query->where('user_id', $user->id)->first();
        }

        $token = (string) Request::header('X-Cart-Token');
        if ($token === '') {
            return null;
        }

        $cartId = Cart::where('token', $token)->whereNull('user_id')->value('id');

        return $cartId ? $query->where('cart_id', $cartId)->first() : null;
    }

    /**
     * addresses returns the address copies, e-mail and phone of the order
     */
    protected static function addresses(Cart $cart, $user): array
    {
        $errors = [];

        if ($user) {
            $shipping = $cart->shipping_address_id ? Address::where('user_id', $user->id)->find($cart->shipping_address_id) : null;
            $billing = $cart->billing_address_id ? Address::where('user_id', $user->id)->find($cart->billing_address_id) : null;

            if (!$shipping) {
                $errors['shipping_address_id'] = [__('Please choose a shipping address.')];
            }
            if (!$billing) {
                $errors['billing_address_id'] = [__('Please choose a billing address.')];
            }
            if ($errors) {
                throw ApiException::validation($errors);
            }

            $customerPhone = \AmoriumCms\Shop\Models\Customer::where('user_id', $user->id)->value('phone');

            return [$shipping->snapshot(), $billing->snapshot(), $user->email, $customerPhone ?: $shipping->phone];
        }

        if (!$cart->email) {
            $errors['email'] = [__('Please enter your e-mail address.')];
        }
        if (!$cart->guest_shipping_address) {
            $errors['shipping_address'] = [__('Please enter a shipping address.')];
        }
        if (!$cart->guest_billing_address) {
            $errors['billing_address'] = [__('Please enter a billing address.')];
        }
        if ($errors) {
            throw ApiException::validation($errors);
        }

        return [$cart->guest_shipping_address, $cart->guest_billing_address, $cart->email, $cart->guest_shipping_address['phone'] ?? null];
    }

    /**
     * orderNumber such as AM-2026-000123
     */
    protected static function orderNumber(Order $order): string
    {
        $prefix = trim((string) ShopSetting::value('order_number_prefix'));

        return ($prefix !== '' ? $prefix . '-' : '') . now()->format('Y') . '-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
    }
}
