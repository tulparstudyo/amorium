<?php namespace AmoriumCms\Shop\Controllers\Api;

use Log;
use Site;
use Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use AmoriumCms\Shop\Classes\Payments\Stripe;
use AmoriumCms\Shop\Models\Payment;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * StripeController receives the browser return from Checkout and the signed webhook.
 * The query string alone never confirms a payment; the session is read from Stripe.
 */
class StripeController
{
    /**
     * returned sends the customer back to the order after Checkout
     */
    public function returned(): RedirectResponse
    {
        $stripe = new Stripe;
        $payment = $this->payment((string) Request::query('session_id'), (string) Request::query('token'));

        if (!$payment) {
            return redirect(\Url::to('/'));
        }

        $paid = $stripe->complete($payment, $payment->reference);

        return redirect($stripe->orderUrl($payment->order, $paid ? ['placed' => 1] : []));
    }

    /**
     * webhook confirms a paid Checkout Session. Stripe retries anything other than 2xx.
     */
    public function webhook(): Response
    {
        $payload = Request::getContent();
        $secret = trim((string) ShopSetting::value('stripe_webhook_secret'));

        if (!$this->signature($payload, Request::header('Stripe-Signature'), $secret)) {
            return response('invalid signature', 400);
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';
        $session = $event['data']['object'] ?? [];

        if (!in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return response('ignored', 200);
        }

        $payment = Payment::where('method', 'stripe')->where('reference', (string) ($session['id'] ?? ''))->first();
        if (!$payment || !$payment->order || (int) $payment->order->site_id !== (int) Site::getActiveSite()->id) {
            Log::warning('Stripe webhook did not match a payment.', ['type' => $type]);

            return response('ignored', 200);
        }

        $pending = $payment->status === Payment::STATUS_PENDING;
        $paid = (new Stripe)->complete($payment, $payment->reference);

        if (!$paid && $pending && ($session['payment_status'] ?? '') === 'paid') {
            return response('not confirmed', 500);
        }

        return response('ok', 200);
    }

    /**
     * payment is the pending or paid Stripe payment addressed by the return token
     */
    protected function payment(string $sessionId, string $token): ?Payment
    {
        if ($sessionId === '' || $token === '') {
            return null;
        }

        $payment = Payment::with('order')->where('method', 'stripe')->where('reference', $sessionId)->first();
        $stored = (string) ($payment->meta['return_token'] ?? '');

        if (!$payment || !$payment->order || $stored === '' || !hash_equals($stored, $token)) {
            return null;
        }

        if ((int) $payment->order->site_id !== (int) Site::getActiveSite()->id) {
            return null;
        }

        return $payment;
    }

    /**
     * signature checks the Stripe-Signature header against the raw body
     */
    protected function signature(string $payload, ?string $header, string $secret): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]][] = $pair[1];
            }
        }

        $timestamp = $parts['t'][0] ?? '';
        if ($timestamp === '' || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
