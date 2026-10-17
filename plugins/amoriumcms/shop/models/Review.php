<?php namespace AmoriumCms\Shop\Models;

use Model;
use AmoriumCms\Shop\Classes\CatalogCache;
use AmoriumCms\Shop\Classes\StatusLabel;

/**
 * Review is written for a barcode and listed for its whole group.
 * One customer can review a group once. Reviews are shown after approval.
 */
class Review extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    public $table = 'amoriumcms_shop_reviews';

    protected $fillable = ['product_id', 'group_id', 'user_id', 'order_item_id', 'rating', 'title', 'body', 'locale', 'status'];

    protected $casts = ['rating' => 'integer'];

    public $belongsTo = [
        'product' => Product::class,
        'group' => ProductGroup::class,
        'user' => \RainLab\User\Models\User::class,
        'order_item' => OrderItem::class,
    ];

    /**
     * scopeIsApproved
     */
    public function scopeIsApproved($query)
    {
        return $query->where('status', static::STATUS_APPROVED);
    }

    /**
     * scopeSearchAdmin matches the title or the text
     */
    public function scopeSearchAdmin($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('title', 'like', "%{$term}%")
            ->orWhere('body', 'like', "%{$term}%"));
    }

    /**
     * getStatusFilterOptions for the backend list filter
     */
    public function getStatusFilterOptions(): array
    {
        return StatusLabel::options(static::STATUSES);
    }

    /**
     * getRatingFilterOptions for the backend list filter
     */
    public function getRatingFilterOptions(): array
    {
        return array_combine([5, 4, 3, 2, 1], array_map(fn ($n) => str_repeat('★', $n), [5, 4, 3, 2, 1]));
    }

    public function afterSave()
    {
        CatalogCache::bump(CatalogCache::REVIEWS);
    }

    public function afterDelete()
    {
        CatalogCache::bump(CatalogCache::REVIEWS);
    }
}
