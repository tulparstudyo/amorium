<?php

namespace Omnipay\PayTR\Message;

/**
 * CompletePurchaseRequest reads the PayTR status of the stored order number.
 *
 * The hash is merchant id + order id + salt, as in the PayTR status query.
 */
class CompletePurchaseRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('merchantId', 'merchantKey', 'merchantSalt', 'transactionReference');

        return [
            'merchant_id' => (string) $this->getMerchantId(),
            'merchant_oid' => (string) $this->getTransactionReference(),
        ];
    }

    public function sendData($data)
    {
        $data['paytr_token'] = $this->token($data['merchant_id'] . $data['merchant_oid']);
        $result = $this->post('https://www.paytr.com/odeme/durum-sorgu', $data);
        $paid = ($result['status'] ?? '') === 'success';
        $currency = $this->shopCurrency((string) ($result['currency'] ?? ''));
        $amount = number_format((float) ($result['payment_amount'] ?? 0), 2, '.', '');

        return $this->response = new Response($this, [
            'paymentStatus' => $paid ? 'success' : 'failed',
            'status' => $paid ? 'paid' : 'failed',
            'transactionReference' => $data['merchant_oid'],
            'currency' => $currency,
            'amount' => [
                'value' => $amount,
                'currency' => $currency,
            ],
            'errorMessage' => $result['failed_reason_msg'] ?? $result['err_msg'] ?? null,
            'failed_reason_msg' => $result['failed_reason_msg'] ?? null,
            'err_msg' => $result['err_msg'] ?? null,
        ]);
    }
}
