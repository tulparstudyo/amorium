<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Request;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Models\Review;

/**
 * ReviewController lets moderators approve, reject and delete reviews.
 */
class ReviewController extends AdminController
{
    /**
     * index. Filters: status, rating, group_id, user_id, q
     */
    public function index()
    {
        $paginator = Review::with(['user', 'group.translations', 'product.spec_values.spec.translations', 'product.spec_values.option.translations'])
            ->when(Request::query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(Request::query('rating'), fn ($q, $rating) => $q->where('rating', $rating))
            ->when(Request::query('group_id'), fn ($q, $id) => $q->where('group_id', $id))
            ->when(Request::query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->searchAdmin(Request::query('q'))
            ->orderBy('id', 'desc')
            ->paginate($this->perPage());

        return ApiResponse::paginated($paginator, fn ($review) => $this->present($review), [
            'pending_count' => Review::where('status', Review::STATUS_PENDING)->count(),
        ]);
    }

    /**
     * show
     */
    public function show(int $id)
    {
        return ApiResponse::ok($this->present($this->findOrFail(Review::class, $id, ['user', 'group.translations', 'product'])));
    }

    /**
     * update changes the status: { status }
     */
    public function update(int $id)
    {
        $review = $this->findOrFail(Review::class, $id);

        $input = $this->validate([
            'status' => 'required|in:' . implode(',', Review::STATUSES),
        ]);

        $review->status = $input['status'];
        $review->save();

        return ApiResponse::ok($this->present($review->fresh(['user', 'group.translations', 'product'])));
    }

    /**
     * destroy
     */
    public function destroy(int $id)
    {
        $this->findOrFail(Review::class, $id)->delete();

        return ApiResponse::ok();
    }

    /**
     * present
     */
    protected function present(Review $review): array
    {
        return [
            'id' => $review->id,
            'status' => $review->status,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'locale' => $review->locale,
            'is_verified_purchase' => (bool) $review->order_item_id,
            'user' => $review->user ? [
                'id' => $review->user->id,
                'name' => trim($review->user->first_name . ' ' . $review->user->last_name),
                'email' => $review->user->email,
            ] : null,
            'group' => $review->group ? [
                'id' => $review->group->id,
                'code' => $review->group->code,
                'name' => $review->group->trans('name'),
            ] : null,
            'product' => $review->product ? [
                'id' => $review->product->id,
                'barcode' => $review->product->barcode,
                'variant' => (object) $review->product->variantLabel(),
            ] : null,
            'created_at' => $review->created_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }
}
