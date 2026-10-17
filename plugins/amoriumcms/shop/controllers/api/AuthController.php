<?php namespace AmoriumCms\Shop\Controllers\Api;

use Auth;
use Request;
use Validator;
use RainLab\User\Classes\ActionManager;
use RainLab\User\Helpers\User as UserHelper;
use System\Classes\RateLimiter as LoginLimiter;
use Illuminate\Validation\ValidationException;
use AmoriumCms\Shop\Classes\CartService;
use AmoriumCms\Shop\Classes\BearerTokens;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Customer;

/**
 * AuthController signs customers in with RainLab.User and returns a bearer token.
 * A guest cart sent with cart_token is merged into the customer cart.
 */
class AuthController
{
    /**
     * register
     */
    public function register()
    {
        $input = Request::only(['first_name', 'last_name', 'email', 'password', 'password_confirmation']);

        Validator::make(Request::all(), [
            'phone' => 'nullable|string|max:32',
            'cart_token' => 'nullable|string|max:64',
        ])->validate();

        $user = ActionManager::instance()->registerUser($input);

        if (!$user instanceof \RainLab\User\Models\User) {
            throw ApiException::make(ApiCode::SERVER_ERROR);
        }

        $customer = Customer::forUser($user);
        if ($phone = Request::input('phone')) {
            $customer->phone = $phone;
            $customer->save();
        }

        if (!ActionManager::instance()->canSignInAfterRegister($user)) {
            return ApiResponse::created([
                'user' => AccountController::presentUser($user),
                'token' => null,
                'requires_activation' => true,
            ]);
        }

        CartService::mergeOnSignIn($user, Request::input('cart_token'));

        return ApiResponse::created(BearerTokens::issue($user) + [
            'user' => AccountController::presentUser($user),
            'requires_activation' => false,
        ]);
    }

    /**
     * login
     */
    public function login()
    {
        $input = Validator::make(Request::all(), [
            'email' => 'required|string|max:255',
            'password' => 'required|string',
            'cart_token' => 'nullable|string|max:64',
        ])->validate();

        $username = UserHelper::username();
        $credentials = [$username => $input['email'], 'password' => $input['password']];

        $limiter = new LoginLimiter('login:' . $input['email']);
        if ($limiter->tooManyAttempts()) {
            $exception = ApiException::make(ApiCode::TOO_MANY_REQUESTS);
            $exception->headers = ['Retry-After' => $limiter->availableIn()];
            throw $exception;
        }

        try {
            ActionManager::instance()->login($credentials);
        }
        catch (ValidationException $ex) {
            $errors = $ex->errors();

            // RainLab reports banned, unverified and unapproved accounts on the password field
            if (isset($errors['password'])) {
                throw ApiException::make(ApiCode::ACCOUNT_NOT_ACTIVATED, null, $errors['password'][0]);
            }

            throw ApiException::make(ApiCode::INVALID_CREDENTIALS);
        }

        $user = Auth::user();
        if (!$user) {
            throw ApiException::make(ApiCode::INVALID_CREDENTIALS);
        }

        CartService::mergeOnSignIn($user, $input['cart_token'] ?? null);

        return ApiResponse::ok(BearerTokens::issue($user) + [
            'user' => AccountController::presentUser($user),
        ]);
    }

    /**
     * refresh returns a new token and revokes the one sent
     */
    public function refresh()
    {
        $user = Auth::user();

        BearerTokens::revoke((string) Request::bearerToken());

        return ApiResponse::ok(BearerTokens::issue($user));
    }

    /**
     * logout revokes the token sent with the request
     */
    public function logout()
    {
        BearerTokens::revoke((string) Request::bearerToken());

        return ApiResponse::ok();
    }

    /**
     * forgotPassword always answers the same, so it cannot be used to find accounts
     */
    public function forgotPassword()
    {
        $input = Validator::make(Request::all(), [
            'email' => 'required|email',
            'reset_url' => 'nullable|url',
        ])->validate();

        try {
            $options = [];
            if (!empty($input['reset_url'])) {
                $options['resetUrl'] = $input['reset_url'];
            }

            ActionManager::instance()->recoverPassword(['email' => $input['email']], $options);
        }
        catch (ValidationException $ex) {
        }

        return ApiResponse::ok(null, null, 200, ApiCode::OK, __('If the address is registered, a password reset link has been sent.'));
    }

    /**
     * resetPassword
     */
    public function resetPassword()
    {
        ActionManager::instance()->resetPassword(Request::only(['email', 'token', 'password', 'password_confirmation']));

        return ApiResponse::ok(null, null, 200, ApiCode::OK, __('Your password has been reset. Please sign in.'));
    }
}
