<?php namespace AmoriumCms\Shop\Classes;

use Auth;
use Cache;
use Config;

/**
 * BearerTokens issues RainLab.User JWT tokens and keeps signed out tokens revoked until they expire.
 */
class BearerTokens
{
    /**
     * issue returns the token and its lifetime for the API response
     */
    public static function issue($user): array
    {
        return [
            'token' => Auth::getBearerToken($user),
            'token_type' => 'Bearer',
            'expires_in' => (int) (Config::get('rainlab.user::bearer_token.ttl') ?? 60) * 60,
        ];
    }

    /**
     * payload reads the claims without checking the signature
     */
    public static function payload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * isExpired
     */
    public static function isExpired(string $token): bool
    {
        $payload = static::payload($token);

        return isset($payload['exp']) && $payload['exp'] < time();
    }

    /**
     * revoke
     */
    public static function revoke(string $token)
    {
        $payload = static::payload($token);
        if (!isset($payload['jti'], $payload['exp'])) {
            return;
        }

        $seconds = max(1, (int) $payload['exp'] - time() + 120);

        Cache::put('shop:revoked-token:' . md5($payload['jti']), true, $seconds);
    }

    /**
     * isRevoked
     */
    public static function isRevoked(string $token): bool
    {
        $payload = static::payload($token);

        return isset($payload['jti']) && Cache::has('shop:revoked-token:' . md5($payload['jti']));
    }
}
