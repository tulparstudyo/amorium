<?php namespace AmoriumCms\Shop\Controllers\Api;

use Url;
use Request;
use Cms\Facades\Cms;
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
     * returned sends the customer back to the shop. The provider posts or redirects here,
     * so this action never renders a page that would start a new browser session.
     */
    public function returned(string $code): RedirectResponse
    {
        $method = $this->method($code);
        $posted = request()->request->all();
        $reference = $method ? (string) (Request::query($method->referenceQuery()) ?: ($posted[$method->referenceQuery()] ?? '')) : '';
        $payment = $method ? $this->payment($method, (string) Request::query('token'), $reference) : null;

        if (!$method || !$payment) {
            return redirect($this->checkoutUrl(['payment_error' => 1]));
        }

        $paid = $method->complete($payment, Request::query(), (string) ($posted['token'] ?? ''), $posted);

        if (!$paid) {
            return redirect($this->checkoutUrl());
        }

        return redirect($method->orderUrl($payment->order, ['placed' => 1]));
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

    /**
     * checkoutUrl is where an unfinished payment returns the customer, with the cart still open
     */
    protected function checkoutUrl(array $query = []): string
    {
        $url = Url::to(Cms::pageUrl('shop/checkout') ?: '/shop/checkout');

        return $query ? $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query) : $url;
    }
}
