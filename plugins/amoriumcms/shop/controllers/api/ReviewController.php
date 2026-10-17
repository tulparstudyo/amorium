<?php namespace AmoriumCms\Shop\Controllers\Api;

use App;
use Auth;
use Request;
use Validator;
use AmoriumCms\Shop\Classes\Storefront;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Review;
use AmoriumCms\Shop\Models\OrderItem;
use AmoriumCms\Shop\Models\ProductGroup;

/**
 * ReviewController lists the approved reviews of a product group and lets customers
 * write one review per group. New and edited reviews wait for approval.
 */
class ReviewController
{
    /**
     * index
     */
    public function index(string $slug)
    {
        $result = Storefront::reviews($slug, (int) Request::query('page', 1), (int) Request::query('per_page', 10));

        if (!$result) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return ApiResponse::ok($result['data'], $result['meta']);
    }

    /**
     * mine lists the reviews of the customer, in every status
     */
    public function mine()
    {
        $paginator = Review::with(['group.translations', 'product.spec_values.spec.translations', 'product.spec_values.option.translations'])
            ->where('user_id', Auth::id())
            ->orderBy('id', 'desc')
            ->paginate(20);

        return ApiResponse::paginated($paginator, fn ($review) => $this->present($review, true));
    }

    /**
     * store
     */
    public function store(string $slug)
    {
        $group = ProductGroup::findBySlug($slug, fn ($q) => $q->isActive());
        if (!$group) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        $input = $this->validate();

        $product = $group->products()->when(
            $input['product_id'] ?? null,
            fn ($q, $id) => $q->where('id', $id),
            fn ($q) => $q->orderBy('sort_order')
        )->first();

        if (!$product) {
            throw ApiException::validation(['product_id' => __('The product does not belong to this group.')]);
        }

        if (Review::where('user_id', Auth::id())->where('group_id', $group->id)->exists()) {
            throw ApiException::make(ApiCode::REVIEW_ALREADY_EXISTS);
        }

        $review = Review::create([
            'product_id' => $product->id,
            'group_id' => $group->id,
            'user_id' => Auth::id(),
            'order_item_id' => $this->purchasedItemId($group->id),
            'rating' => $input['rating'],
            'title' => $input['title'] ?? null,
            'body' => $input['body'] ?? null,
            'locale' => App::getLocale(),
            'status' => Review::STATUS_PENDING,
        ]);

        return ApiResponse::created($this->present($review->fresh(['group.translations', 'product']), true));
    }

    /**
     * update sends the review back to moderation
     */
    public function update(int $id)
    {
        $review = $this->findOwn($id);
        $input = $this->validate(false);

        $review->fill(array_only($input, ['rating', 'title', 'body']));
        $review->status = Review::STATUS_PENDING;
        $review->save();

        return ApiResponse::ok($this->present($review->fresh(['group.translations', 'product']), true));
    }

    /**
     * destroy
     */
    public function destroy(int $id)
    {
        $this->findOwn($id)->delete();

        return ApiResponse::ok();
    }

    /**
     * validate
     */
    protected function validate(bool $withProduct = true): array
    {
        $rules = [
            'rating' => 'required|integer|between:1,5',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:5000',
        ];

        if ($withProduct) {
            $rules['product_id'] = 'nullable|integer';
        }

        return Validator::make(Request::all(), $rules)->validate();
    }

    /**
     * findOwn
     */
    protected function findOwn(int $id): Review
    {
        $review = Review::where('user_id', Auth::id())->find($id);
        if (!$review) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return $review;
    }

    /**
     * purchasedItemId returns a delivered order line of the group, which marks the review as verified
     */
    protected function purchasedItemId(int $groupId): ?int
    {
        return OrderItem::where('group_id', $groupId)
            ->whereHas('order', fn ($q) => $q->where('user_id', Auth::id())->where('status', Order::STATUS_DELIVERED))
            ->value('id');
    }

    /**
     * present
     */
    protected function present(Review $review, bool $own = false): array
    {
        return Storefront::review($review, $own);
    }
}
