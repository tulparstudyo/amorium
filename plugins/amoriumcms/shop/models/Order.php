<?php namespace AmoriumCms\Shop\Models;

use Model;
use AmoriumCms\Shop\Classes\StatusLabel;
use AmoriumCms\Shop\Classes\Payments\PaymentMethods;

/**
 * Order keeps a copy of the products, prices and addresses as they were when it was placed.
 */
class Order extends Model
{
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_INCOMPLETE = 'incomplete';

    const STATUSES = [
        self::STATUS_INCOMPLETE,
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_REFUNDED,
        self::STATUS_CANCELLED,
    ];

    /**
     * @var array TRANSITIONS lists the allowed next statuses. An incomplete order is
     * completed only by the customer placing it.
     */
    const TRANSITIONS = [
        self::STATUS_INCOMPLETE => [],
        self::STATUS_PENDING_PAYMENT => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [self::STATUS_REFUNDED],
        self::STATUS_REFUNDED => [],
        self::STATUS_CANCELLED => [],
    ];

    /**
     * @var array NOT_COUNTED statuses are left out of the sales totals
     */
    const NOT_COUNTED = [self::STATUS_INCOMPLETE, self::STATUS_CANCELLED, self::STATUS_REFUNDED];

    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_REFUNDED = 'refunded';

    public $table = 'amoriumcms_shop_orders';

    protected $guarded = ['id'];

    protected $jsonable = ['shipping_address', 'billing_address'];

    protected $dates = ['placed_at'];

    public $belongsTo = [
        'user' => \RainLab\User\Models\User::class,
        'site' => \System\Models\SiteDefinition::class,
        'shipping_method' => ShippingMethod::class,
    ];

    public $hasMany = [
        'items' => [OrderItem::class, 'key' => 'order_id', 'delete' => true],
        'history' => [OrderStatusHistory::class, 'key' => 'order_id', 'order' => 'id', 'delete' => true],
        'payments' => [Payment::class, 'key' => 'order_id', 'order' => 'id', 'delete' => true],
    ];

    /**
     * scopeIsCounted keeps the orders that count as sales
     */
    public function scopeIsCounted($query)
    {
        return $query->whereNotIn('status', static::NOT_COUNTED);
    }

    /**
     * scopeIsPlaced leaves out the incomplete orders
     */
    public function scopeIsPlaced($query)
    {
        return $query->where('status', '<>', static::STATUS_INCOMPLETE);
    }

    /**
     * scopeSearchAdmin matches the order number, the e-mail or the phone
     */
    public function scopeSearchAdmin($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('number', 'like', "%{$term}%")
            ->orWhere('customer_email', 'like', "%{$term}%")
            ->orWhere('customer_phone', 'like', "%{$term}%"));
    }

    /**
     * getStatusFilterOptions for the backend list filter
     */
    public function getStatusFilterOptions(): array
    {
        return StatusLabel::options(static::STATUSES);
    }

    /**
     * getPaymentStatusFilterOptions for the backend list filter
     */
    public function getPaymentStatusFilterOptions(): array
    {
        return StatusLabel::options([static::PAYMENT_PENDING, static::PAYMENT_PAID, static::PAYMENT_REFUNDED]);
    }

    /**
     * getPaymentMethodFilterOptions for the backend list filter
     */
    public function getPaymentMethodFilterOptions(): array
    {
        return array_map(fn ($method) => $method->name(), PaymentMethods::all());
    }

    /**
     * getSiteFilterOptions for the backend list filter
     */
    public function getSiteFilterOptions(): array
    {
        return \Site::listEnabled()->pluck('name', 'id')->all();
    }

    /**
     * canMoveTo
     */
    public function canMoveTo(string $status): bool
    {
        return in_array($status, static::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * isGuest
     */
    public function isGuest(): bool
    {
        return !$this->user_id;
    }
}
