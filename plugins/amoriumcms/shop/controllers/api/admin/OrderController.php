<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Request;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\OrderService;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Order;

/**
 * OrderController lists orders, changes their status and confirms bank transfers.
 */
class OrderController extends AdminController
{
    /**
     * index. Filters: status, payment_status, payment_method, site_id, user_id, q, date_from, date_to
     */
    public function index()
    {
        $input = $this->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ], Request::query());

        $paginator = Order::query()
            ->withCount('items')
            ->when(Request::query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(Request::query('payment_status'), fn ($q, $status) => $q->where('payment_status', $status))
            ->when(Request::query('payment_method'), fn ($q, $method) => $q->where('payment_method', $method))
            ->when($this->siteId(), fn ($q, $siteId) => $q->where('site_id', $siteId))
            ->when(Request::query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->searchAdmin(Request::query('q'))
            ->when($input['date_from'] ?? null, fn ($q, $date) => $q->where('placed_at', '>=', \Carbon\Carbon::parse($date)->startOfDay()))
            ->when($input['date_to'] ?? null, fn ($q, $date) => $q->where('placed_at', '<=', \Carbon\Carbon::parse($date)->endOfDay()))
            ->orderBy('id', 'desc')
            ->paginate($this->perPage());

        return ApiResponse::paginated($paginator, fn ($order) => [
            'id' => $order->id,
            'number' => $order->number,
            'site_id' => $order->site_id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'customer' => [
                'user_id' => $order->user_id,
                'name' => trim(($order->billing_address['first_name'] ?? '') . ' ' . ($order->billing_address['last_name'] ?? '')),
                'email' => $order->customer_email,
            ],
            'items_count' => $order->items_count,
            'grand_total' => Money::toFloat($order->grand_total),
            'currency' => $order->currency,
            'placed_at' => $order->placed_at?->toIso8601String(),
        ]);
    }

    /**
     * show
     */
    public function show(int $id)
    {
        return ApiResponse::ok(OrderService::present($this->findOrFail(Order::class, $id), true));
    }

    /**
     * updateStatus: { status, note, notify }
     */
    public function updateStatus(int $id)
    {
        $order = $this->findOrFail(Order::class, $id);

        $input = $this->validate([
            'status' => 'required|in:' . implode(',', Order::STATUSES),
            'note' => 'nullable|string|max:1000',
            'notify' => 'boolean',
        ]);

        $order = OrderService::changeStatus($order, $input['status'], $input['note'] ?? null, $this->backendUserId(), (bool) ($input['notify'] ?? false));

        return ApiResponse::ok(OrderService::present($order->fresh(), true));
    }

    /**
     * updateNote stores the note that only admins see: { admin_note }
     */
    public function updateNote(int $id)
    {
        $order = $this->findOrFail(Order::class, $id);

        $input = $this->validate(['admin_note' => 'nullable|string|max:10000']);

        $order->admin_note = $input['admin_note'] ?? null;
        $order->save();

        return ApiResponse::ok(OrderService::present($order->fresh(), true));
    }

    /**
     * confirmPayment marks a bank transfer as received
     */
    public function confirmPayment(int $id, int $paymentId)
    {
        $order = $this->findOrFail(Order::class, $id);
        $payment = $order->payments()->find($paymentId);
        if (!$payment) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        $order = OrderService::confirmPayment($order, $payment, $this->backendUserId());

        return ApiResponse::ok(OrderService::present($order->fresh(), true));
    }
}
