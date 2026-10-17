<?php namespace AmoriumCms\Shop\Controllers\Api;

use Auth;
use Request;
use Validator;
use RainLab\User\Classes\ActionManager;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Models\Customer;

/**
 * AccountController shows and updates the profile of the signed in customer.
 */
class AccountController
{
    /**
     * show
     */
    public function show()
    {
        return ApiResponse::ok(static::presentUser(Auth::user()));
    }

    /**
     * update
     */
    public function update()
    {
        $user = Auth::user();

        $data = Validator::make(Request::all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:32',
            'type' => 'sometimes|in:individual,corporate',
            'company_name' => 'sometimes|nullable|string|max:255',
            'tax_number' => 'sometimes|nullable|string|max:32',
            'tax_office' => 'sometimes|nullable|string|max:255',
        ])->validate();

        $userFields = array_only($data, ['first_name', 'last_name']);
        if ($userFields) {
            $user->fill($userFields);
            $user->save();
        }

        $customer = Customer::forUser($user);
        $customer->fill(array_only($data, ['phone', 'type', 'company_name', 'tax_number', 'tax_office']));
        $customer->save();

        return ApiResponse::ok(static::presentUser($user->fresh()));
    }

    /**
     * password
     */
    public function password()
    {
        ActionManager::instance()->changePassword(Request::only(['current_password', 'password', 'password_confirmation']));

        return ApiResponse::ok(null, null, 200, 'OK', __('Your password has been changed.'));
    }

    /**
     * presentUser
     */
    public static function presentUser($user): array
    {
        $customer = Customer::where('user_id', $user->id)->first();

        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $customer?->phone,
            'type' => $customer?->type ?: Customer::TYPE_INDIVIDUAL,
            'company_name' => $customer?->company_name,
            'tax_number' => $customer?->tax_number,
            'tax_office' => $customer?->tax_office,
            'email_verified' => method_exists($user, 'hasVerifiedEmail') ? $user->hasVerifiedEmail() : true,
        ];
    }
}
