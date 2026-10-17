<?php namespace AmoriumCms\Shop\Classes;

use Db;
use Site;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Classes\Payments\PaymentMethods;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Payment;
use AmoriumCms\Shop\Models\OrderStatusHistory;

/**
 * OrderService moves orders between statuses and confirms payments. A cancelled order
 * returns its stock; a refunded order does not. A delivered cash on delivery order is marked as paid.
 */
class OrderService
{
    /**
     * changeStatus
     */
    public static function changeStatus(Order $order, string $status, ?string $note = null, ?int $backendUserId = null, bool $notify = false): Order
    {
        return Db::transaction(function () use ($order, $status, $note, $backendUserId, $notify) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (!$order->canMoveTo($status)) {
                throw ApiException::make(ApiCode::INVALID_STATUS_TRANSITION, [
                    'status' => $order->status,
                    'allowed' => Order::TRANSITIONS[$order->status] ?? [],
                ]);
            }

            $from = $order->status;
            $order->status = $status;

            if ($status === Order::STATUS_CANCELLED) {
                StockService::release($order, $backendUserId);

                $order->payments()->where('status', Payment::STATUS_PENDING)->update(['status' => Payment::STATUS_FAILED]);
            }

            if ($status === Order::STATUS_REFUNDED) {
                $order->payments()->where('status', Payment::STATUS_PENDING)->update(['status' => Payment::STATUS_FAILED]);

                if ($order->payments()->where('status', Payment::STATUS_COMPLETED)->update(['status' => Payment::STATUS_REFUNDED])) {
                    $order->payment_status = Order::PAYMENT_REFUNDED;
                }
            }

            if ($status === Order::STATUS_DELIVERED && $order->payment_status === Order::PAYMENT_PENDING) {
                foreach ($order->payments()->where('status', Payment::STATUS_PENDING)->get() as $payment) {
                    $payment->status = Payment::STATUS_COMPLETED;
                    $payment->paid_at = now();
                    $payment->save();
                }

                $order->payment_status = Order::PAYMENT_PAID;
            }

            $order->save();

            static::record($order, $from, $status, $note, $backendUserId, $notify);

            return $order;
        });
    }

    /**
     * confirmPayment marks a pending payment as received. A bank transfer order waiting
     * for payment moves on to processing.
     */
    public static function confirmPayment(Order $order, Payment $payment, ?int $backendUserId = null): Order
    {
        return Db::transaction(function () use ($order, $payment, $backendUserId) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $payment = Payment::lockForUpdate()->where('order_id', $order->id)->findOrFail($payment->id);

            if ($payment->status === Payment::STATUS_COMPLETED) {
                throw ApiException::make(ApiCode::PAYMENT_ALREADY_CONFIRMED);
            }

            if (in_array($order->status, Order::NOT_COUNTED, true)) {
                throw ApiException::make(ApiCode::INVALID_STATUS_TRANSITION, ['status' => $order->status, 'allowed' => []]);
            }

            $payment->status = Payment::STATUS_COMPLETED;
            $payment->paid_at = now();
            $payment->save();

            $order->payment_status = Order::PAYMENT_PAID;

            if ($order->status === Order::STATUS_PENDING_PAYMENT) {
                $order->status = Order::STATUS_PROCESSING;
                static::record($order, Order::STATUS_PENDING_PAYMENT, Order::STATUS_PROCESSING, __('Payment received.'), $backendUserId);
            }

            $order->save();

            return $order;
        });
    }

    /**
     * cancelByCustomer is allowed only while the order waits for payment
     */
    public static function cancelByCustomer(Order $order): Order
    {
        if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
            throw ApiException::make(ApiCode::ORDER_NOT_CANCELLABLE);
        }

        return static::changeStatus($order, Order::STATUS_CANCELLED, __('Cancelled by the customer.'));
    }

    /**
     * record
     */
    public static function record(Order $order, ?string $from, string $to, ?string $note = null, ?int $backendUserId = null, bool $notify = false)
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'backend_user_id' => $backendUserId,
            'customer_notified' => $notify,
        ]);

        if ($notify) {
            OrderMailer::statusChanged($order, $note);
        }
    }

    /**
     * present returns an order for the customer or the admin API
     */
    public static function present(Order $order, bool $admin = false): array
    {
        $order->loadMissing(['items', 'payments', 'history']);

        $method = PaymentMethods::find($order->payment_method);
        $pendingPayment = $order->payments->firstWhere('status', Payment::STATUS_PENDING);
        $paymentView = Site::withContext($order->site_id, function () use ($method, $order, $pendingPayment) {
            return [
                'name' => $method ? __($method->name()) : $order->payment_method,
                'instructions' => $method && $pendingPayment && $order->status !== Order::STATUS_CANCELLED
                    ? $method->instructions($order, $pendingPayment)
                    : null,
            ];
        });

        $data = [
            'number' => $order->number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => [
                'code' => $order->payment_method,
                'name' => $paymentView['name'],
            ],
            'payment_instructions' => $paymentView['instructions'],
            'shipping_method' => $order->shipping_method_name,
            'currency' => $order->currency,
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'barcode' => $item->barcode,
                'sku' => $item->sku,
                'name' => $item->name,
                'variant' => (object) ($item->variant_label ?: []),
                'quantity' => $item->quantity,
                'unit_price' => Money::toFloat($item->unit_price),
                'tax_rate' => (float) $item->tax_rate,
                'tax_amount' => Money::toFloat($item->tax_amount),
                'line_total' => Money::toFloat($item->line_total),
            ])->values()->all(),
            'totals' => [
                'subtotal' => Money::toFloat($order->subtotal),
                'shipping_total' => Money::toFloat($order->shipping_total),
                'payment_fee' => Money::toFloat($order->payment_fee),
                'tax_total' => Money::toFloat($order->tax_total),
                'grand_total' => Money::toFloat($order->grand_total),
            ],
            'shipping_address' => $order->shipping_address,
            'billing_address' => $order->billing_address,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'customer_note' => $order->customer_note,
            'history' => $order->history->map(fn ($row) => [
                'status' => $row->to_status,
                'note' => $admin || $row->customer_notified || !$row->backend_user_id ? $row->note : null,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values()->all(),
            'can_cancel' => $order->status === Order::STATUS_PENDING_PAYMENT,
            'placed_at' => $order->placed_at?->toIso8601String(),
        ];

        if ($admin) {
            $data['id'] = $order->id;
            $data['user_id'] = $order->user_id;
            $data['site_id'] = $order->site_id;
            $data['locale'] = $order->locale;
            $data['admin_note'] = $order->admin_note;
            $data['allowed_statuses'] = Order::TRANSITIONS[$order->status] ?? [];
            $data['payments'] = $order->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount' => Money::toFloat($payment->amount),
                'status' => $payment->status,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->values()->all();
            $data['history'] = $order->history->load('backend_user')->map(fn ($row) => [
                'from_status' => $row->from_status,
                'status' => $row->to_status,
                'note' => $row->note,
                'backend_user' => $row->backend_user?->full_name,
                'customer_notified' => $row->customer_notified,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }
}
