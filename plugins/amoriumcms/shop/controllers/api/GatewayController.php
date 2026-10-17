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
        $posted = request()->request->all() ?: (array) $_POST;
        $referenceName = $method ? $method->referenceQuery() : 'paymentId';
        $reference = (string) (Request::query($referenceName) ?: ($posted[$referenceName] ?? $posted['merchant_oid'] ?? $posted['conversationId'] ?? ''));
        $providerToken = (string) ($posted['token'] ?? '');
        $payment = $method ? $this->payment($method, (string) Request::query('token'), $reference, $providerToken) : null;
        $gatewayMessage = OmnipayMethod::failureText($posted);

        if (!$method || !$payment) {
            return redirect($this->checkoutUrl($this->errorQuery($gatewayMessage)));
        }

        $paid = $method->complete($payment, Request::query(), $providerToken, $posted);

        if (!$paid) {
            $stored = trim((string) ($payment->fresh()->meta['return_error'] ?? ''));

            return redirect($this->checkoutUrl($this->errorQuery($stored !== '' ? $stored : $gatewayMessage)));
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
    protected function payment(OmnipayMethod $method, string $token, string $reference, string $providerToken = ''): ?Payment
    {
        $payment = $reference !== ''
            ? Payment::with('order')->where('method', $method->code())->where('reference', $reference)->first()
            : null;

        if (!$payment && $providerToken !== '') {
            $payment = Payment::with('order')
                ->where('method', $method->code())
                ->where('status', Payment::STATUS_PENDING)
                ->where('meta->provider_token', $providerToken)
                ->first();
        }

        if (!$payment || !$payment->order) {
            return null;
        }

        $stored = (string) ($payment->meta['return_token'] ?? '');

        if ($token !== '' && ($stored === '' || !hash_equals($stored, $token))) {
            return null;
        }

        if ((int) $payment->order->site_id !== (int) \Site::getActiveSite()->id) {
            return null;
        }

        return $payment;
    }

    /**
     * errorQuery marks the checkout as a failed return and carries the provider text
     */
    protected function errorQuery(string $message): array
    {
        $query = ['payment_error' => 1];
        if ($message !== '') {
            $query['payment_message'] = $message;
        }

        return $query;
    }

    protected function checkoutUrl(array $query = []): string
    {
        $url = Url::to(Cms::pageUrl('shop/checkout') ?: '/shop/checkout');

        return $query ? $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query) : $url;
    }
}
