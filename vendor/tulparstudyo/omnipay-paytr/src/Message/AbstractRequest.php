<?php

namespace Omnipay\PayTR\Message;

/**
 * Shared PayTR credentials and the HMAC the iFrame and status APIs require.
 */
abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    public function getMerchantId()
    {
        return $this->getParameter('merchantId');
    }

    public function setMerchantId($value)
    {
        return $this->setParameter('merchantId', $value);
    }

    public function getMerchantKey()
    {
        return $this->getParameter('merchantKey');
    }

    public function setMerchantKey($value)
    {
        return $this->setParameter('merchantKey', $value);
    }

    public function getMerchantSalt()
    {
        return $this->getParameter('merchantSalt');
    }

    public function setMerchantSalt($value)
    {
        return $this->setParameter('merchantSalt', $value);
    }

    /**
     * token is the base64 HMAC PayTR calls paytr_token
     */
    protected function token(string $value): string
    {
        return base64_encode(hash_hmac('sha256', $value . $this->getMerchantSalt(), (string) $this->getMerchantKey(), true));
    }

    /**
     * post sends a form to PayTR and returns the JSON body
     */
    protected function post(string $url, array $fields): array
    {
        $response = $this->httpClient->request('POST', $url, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], http_build_query($fields));

        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * minorAmount is the order total in kuruş
     */
    protected function minorAmount($amount): string
    {
        return bcmul(number_format((float) $amount, 2, '.', ''), '100', 0);
    }

    /**
     * currencyCode maps the shop currency onto a PayTR code
     */
    protected function currencyCode(): string
    {
        $currency = strtoupper((string) $this->getCurrency());

        return $currency === 'TRY' ? 'TL' : $currency;
    }

    /**
     * shopCurrency maps a PayTR code back onto the shop code
     */
    protected function shopCurrency(string $currency): string
    {
        return strtoupper($currency) === 'TL' ? 'TRY' : strtoupper($currency);
    }
}
