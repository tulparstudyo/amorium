<?php namespace AmoriumCms\Shop\Controllers\Api;

use Auth;
use Request;
use Validator;
use AmoriumCms\Shop\Classes\AddressPresenter;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Address;

/**
 * AddressController manages the shipping and billing address book of the customer.
 */
class AddressController
{
    /**
     * index
     */
    public function index()
    {
        $type = Request::query('type');

        $addresses = Address::where('user_id', Auth::id())
            ->when(in_array($type, [Address::TYPE_SHIPPING, Address::TYPE_BILLING], true), fn ($q) => $q->where('type', $type))
            ->orderBy('is_default', 'desc')
            ->orderBy('id')
            ->get();

        return ApiResponse::ok($addresses->map(fn ($address) => AddressPresenter::present($address))->values()->all());
    }

    /**
     * store
     */
    public function store()
    {
        $data = $this->validate(Request::input('type'));

        $address = new Address($data);
        $address->user_id = Auth::id();
        $address->is_default = $data['is_default'] ?? !Address::where('user_id', Auth::id())->where('type', $data['type'])->exists();
        $address->save();

        return ApiResponse::created(AddressPresenter::present($address));
    }

    /**
     * update
     */
    public function update(int $id)
    {
        $address = $this->findOwn($id);

        $data = $this->validate(Request::input('type', $address->type));
        $address->fill($data);
        $address->save();

        return ApiResponse::ok(AddressPresenter::present($address));
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
    protected function validate(?string $type): array
    {
        $rules = [
            'type' => 'required|in:shipping,billing',
            'title' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ] + Address::rules('', $type === Address::TYPE_BILLING);

        $data = Validator::make(Request::all(), $rules)->validate();
        $data['country_code'] = strtoupper($data['country_code'] ?? 'TR');

        return $data;
    }

    /**
     * findOwn
     */
    protected function findOwn(int $id): Address
    {
        $address = Address::where('user_id', Auth::id())->find($id);
        if (!$address) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return $address;
    }
}
