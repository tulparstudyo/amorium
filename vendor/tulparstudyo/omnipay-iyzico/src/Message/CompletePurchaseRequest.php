<?php

namespace Omnipay\Iyzico\Message;

use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\Locale;
use Iyzipay\Request\RetrieveCheckoutFormRequest;

/**
 * CompletePurchaseRequest confirms the Checkout Form token iyzico posted to the return URL.
 */
class CompletePurchaseRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('apiKey', 'secretKey', 'transactionReference');

        $token = (string) $this->getParameter('token');
        if ($token === '' && $this->httpRequest) {
            $token = (string) $this->httpRequest->request->get('token', '');
        }

        if ($token === '') {
            $this->validate('token');
        }

        return [
            'token' => $token,
            'transactionReference' => (string) $this->getTransactionReference(),
        ];
    }

    public function setToken($value)
    {
        return $this->setParameter('token', $value);
    }

    public function sendData($data)
    {
        $request = new RetrieveCheckoutFormRequest();
        $request->setLocale(Locale::TR);
        $request->setConversationId($data['transactionReference']);
        $request->setToken($data['token']);

        $payment = CheckoutForm::retrieve($request, $this->options());
        $paid = strcasecmp((string) $payment->getPaymentStatus(), 'SUCCESS') === 0
            && (string) $payment->getConversationId() === $data['transactionReference'];
        $currency = strtoupper((string) $payment->getCurrency());
        $amount = $this->money($payment->getPaidPrice() ?: 0);

        return $this->response = new Response($this, [
            'paymentStatus' => $paid ? 'SUCCESS' : (string) $payment->getPaymentStatus(),
            'status' => $paid ? 'paid' : 'failed',
            'transactionReference' => $data['transactionReference'],
            'currency' => $currency,
            'amount' => [
                'value' => $amount,
                'currency' => $currency,
            ],
            'paymentId' => $payment->getPaymentId(),
            'errorMessage' => $payment->getErrorMessage(),
        ]);
    }
}
