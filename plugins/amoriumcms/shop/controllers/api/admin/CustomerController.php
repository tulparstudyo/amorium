<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Db;
use Request;
use RainLab\User\Models\User;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\AddressPresenter;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Controllers\Api\AccountController;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Review;

/**
 * CustomerController lists the customers with their order totals. Accounts are edited
 * on the RainLab.User pages.
 */
class CustomerController extends AdminController
{
    /**
     * index. Filters: q
     */
    public function index()
    {
        $paginator = User::query()
            ->when(Request::query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('email', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")))
            ->orderBy('id', 'desc')
            ->paginate($this->perPage());

        $stats = $this->orderStats(collect($paginator->items())->pluck('id')->all());

        return ApiResponse::paginated($paginator, fn ($user) => [
            'id' => $user->id,
            'email' => $user->email,
            'name' => trim($user->first_name . ' ' . $user->last_name),
            'is_banned' => (bool) $user->banned_at,
            'orders_count' => (int) ($stats[$user->id]->orders_count ?? 0),
            'orders_total' => Money::toFloat($stats[$user->id]->orders_total ?? 0),
            'created_at' => $user->created_at?->toIso8601String(),
            'last_seen' => $user->last_seen?->toIso8601String(),
        ]);
    }

    /**
     * show
     */
    public function show(int $id)
    {
        $user = $this->findOrFail(User::class, $id, ['shop_addresses']);
        $stats = $this->orderStats([$user->id])[$user->id] ?? null;

        $orders = Order::where('user_id', $user->id)->orderBy('id', 'desc')->limit(10)->get();

        return ApiResponse::ok(AccountController::presentUser($user) + [
            'is_banned' => (bool) $user->banned_at,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_seen' => $user->last_seen?->toIso8601String(),
            'orders_count' => (int) ($stats->orders_count ?? 0),
            'orders_total' => Money::toFloat($stats->orders_total ?? 0),
            'addresses' => $user->shop_addresses->map(fn ($address) => AddressPresenter::present($address))->values()->all(),
            'recent_orders' => $orders->map(fn ($order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'grand_total' => Money::toFloat($order->grand_total),
                'currency' => $order->currency,
                'placed_at' => $order->placed_at?->toIso8601String(),
            ])->values()->all(),
            'reviews_count' => Review::where('user_id', $user->id)->count(),
            'favorites_count' => Db::table('amoriumcms_shop_favorites')->where('user_id', $user->id)->count(),
        ]);
    }

    /**
     * orderStats counts the orders that count as sales. Totals of different currencies are added up.
     */
    protected function orderStats(array $userIds)
    {
        if (!$userIds) {
            return collect();
        }

        return Order::whereIn('user_id', $userIds)
            ->isCounted()
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as orders_count, sum(grand_total) as orders_total')
            ->get()
            ->keyBy('user_id');
    }
}
