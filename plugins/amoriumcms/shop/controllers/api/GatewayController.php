<?php namespace AmoriumCms\Shop\Controllers\Api;

use Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use AmoriumCms\Shop\Classes\Payments\OmnipayMethod;
use AmoriumCms\Shop\Classes\Payments\PaymentMethods;
use AmoriumCms\Shop\Models\Payment;

/**
 * GatewayController receives the browser return and the webhook of every
 * Omnipay provider. The query string alone never confirms a payment.
 */
class GatewayController
{
    /**
     * returned sends the customer back to the order
     */
    public function returned(string $code): RedirectResponse
    {
        $method = $this->method($code);
        $payment = $method ? $this->payment($method, (string) Request::query('token'), (string) Request::query($method->referenceQuery())) : null;

        if (!$method || !$payment) {
            return redirect(\Url::to('/'));
        }

        $paid = $method->complete($payment, Request::query());

        return redirect($method->orderUrl($payment->order, $paid ? ['placed' => 1] : []));
    }

    /**
     * webhook confirms a payment the provider says is complete
     */
    public function webhook(string $code): Response
    {
        $method = $this->method($code);
        if (!$method) {
            return response('ignored', 200);
        }

        $result = $method->acceptWebhook(Request::getContent(), Request::header('Stripe-Signature'));

        return match ($result) {
            'invalid' => response('invalid signature', 400),
            'retry' => response('not confirmed', 500),
            default => response($result === 'ok' ? 'ok' : 'ignored', 200),
        };
    }

    /**
     * method is an Omnipay provider from the payment form
     */
    protected function method(string $code): ?OmnipayMethod
    {
        $method = PaymentMethods::find($code);

        return $method instanceof OmnipayMethod ? $method : null;
    }

    /**
     * payment is the provider payment addressed by the return token
     */
    protected function payment(OmnipayMethod $method, string $token, string $reference): ?Payment
    {
        if ($token === '' || $reference === '') {
            return null;
        }

        $payment = Payment::with('order')->where('method', $method->code())->where('reference', $reference)->first();
        $stored = (string) ($payment->meta['return_token'] ?? '');

        if (!$payment || !$payment->order || $stored === '' || !hash_equals($stored, $token)) {
            return null;
        }

        if ((int) $payment->order->site_id !== (int) \Site::getActiveSite()->id) {
            return null;
        }

        return $payment;
    }
}
