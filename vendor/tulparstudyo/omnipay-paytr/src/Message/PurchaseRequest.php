<?php

namespace Omnipay\PayTR\Message;

/**
 * PurchaseRequest opens a PayTR iFrame for one shop order.
 *
 * The hash follows the iFrame token in the PayTR Postman collection:
 * merchant id, IP, order id, email, amount, basket, installment flags, currency and test mode.
 */
class PurchaseRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('merchantId', 'merchantKey', 'merchantSalt', 'amount', 'currency', 'transactionId', 'returnUrl');

        $card = $this->getCard();
        $amount = number_format((float) $this->getAmount(), 2, '.', '');
        $description = (string) ($this->getDescription() ?: $this->getTransactionId());
        $basket = base64_encode(json_encode([[$description, $amount, 1]], JSON_UNESCAPED_UNICODE));
        $email = $card && $card->getEmail() ? $card->getEmail() : 'customer@example.com';
        $testMode = $this->getTestMode() ? '1' : '0';
        $currency = $this->currencyCode();
        $ip = $this->httpRequest ? ($this->httpRequest->getClientIp() ?: '127.0.0.1') : '127.0.0.1';

        return [
            'merchant_id' => (string) $this->getMerchantId(),
            'user_ip' => $ip,
            'merchant_oid' => (string) $this->getTransactionId(),
            'email' => $email,
            'payment_amount' => $this->minorAmount($amount),
            'user_basket' => $basket,
            'no_installment' => '1',
            'max_installment' => '0',
            'currency' => $currency,
            'test_mode' => $testMode,
            'user_name' => trim(($card ? $card->getName() : '') ?: 'Customer'),
            'user_address' => $card && $card->getBillingAddress1() ? $card->getBillingAddress1() : 'Address',
            'user_phone' => $card && $card->getPhone() ? $card->getPhone() : '5551234567',
            'merchant_ok_url' => $this->callbackUrl((string) $this->getReturnUrl(), (string) $this->getTransactionId()),
            'merchant_fail_url' => (string) ($this->getCancelUrl() ?: $this->getReturnUrl()),
            'timeout_limit' => '30',
            'debug_on' => $testMode,
            'lang' => 'tr',
        ];
    }

    public function sendData($data)
    {
        $hash = $data['merchant_id']
            . $data['user_ip']
            . $data['merchant_oid']
            . $data['email']
            . $data['payment_amount']
            . $data['user_basket']
            . $data['no_installment']
            . $data['max_installment']
            . $data['currency']
            . $data['test_mode'];
        $data['paytr_token'] = $this->token($hash);

        $result = $this->post('https://www.paytr.com/odeme/api/get-token', $data);
        $token = ($result['status'] ?? '') === 'success' ? (string) ($result['token'] ?? '') : '';

        return $this->response = new Response($this, [
            'transactionReference' => $data['merchant_oid'],
            'paymentPageUrl' => $token !== '' ? 'https://www.paytr.com/odeme/guvenli/' . $token : null,
            'errorMessage' => $result['reason'] ?? null,
        ]);
    }

    /**
     * callbackUrl keeps the shop return token and adds the order number the shop looks up
     */
    protected function callbackUrl(string $returnUrl, string $transactionId): string
    {
        $separator = str_contains($returnUrl, '?') ? '&' : '?';

        return $returnUrl . $separator . 'paymentId=' . rawurlencode($transactionId);
    }
}
