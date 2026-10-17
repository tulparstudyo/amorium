<?php

namespace Omnipay\Iyzico\Message;

use Iyzipay\Options;

/**
 * Shared iyzico credentials and the buyer fields Checkout Form requires.
 */
abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    public function getApiKey()
    {
        return $this->getParameter('apiKey');
    }

    public function setApiKey($value)
    {
        return $this->setParameter('apiKey', $value);
    }

    public function getSecretKey()
    {
        return $this->getParameter('secretKey');
    }

    public function setSecretKey($value)
    {
        return $this->setParameter('secretKey', $value);
    }

    public function getBaseUrl()
    {
        $url = trim((string) $this->getParameter('baseUrl'));
        if ($url !== '') {
            return $url;
        }

        return $this->getTestMode()
            ? 'https://sandbox-api.iyzipay.com'
            : 'https://api.iyzipay.com';
    }

    public function setBaseUrl($value)
    {
        return $this->setParameter('baseUrl', $value);
    }

    public function getIdentityNumber()
    {
        return $this->getParameter('identityNumber');
    }

    public function setIdentityNumber($value)
    {
        return $this->setParameter('identityNumber', $value);
    }

    /**
     * options is the official client configuration
     */
    protected function options(): Options
    {
        $options = new Options();
        $options->setApiKey((string) $this->getApiKey());
        $options->setSecretKey((string) $this->getSecretKey());
        $options->setBaseUrl($this->getBaseUrl());

        return $options;
    }

    /**
     * money is the major-unit amount iyzico expects
     */
    protected function money($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * currencyCode maps the shop currency onto an iyzico code
     */
    protected function currencyCode(): string
    {
        $currency = strtoupper((string) $this->getCurrency());

        return $currency === 'TL' ? 'TRY' : $currency;
    }
}
