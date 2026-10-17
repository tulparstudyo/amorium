<?php

namespace Omnipay\Iyzico\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * Checkout Form start redirects. A completed payment is successful only when iyzico reports SUCCESS.
 */
class Response extends AbstractResponse implements RedirectResponseInterface
{
    public function isSuccessful()
    {
        if ($this->isRedirect()) {
            return false;
        }

        return ($this->data['paymentStatus'] ?? '') === 'SUCCESS';
    }

    public function isRedirect()
    {
        return !empty($this->data['paymentPageUrl']);
    }

    public function getRedirectUrl()
    {
        return $this->data['paymentPageUrl'] ?? null;
    }

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectData()
    {
        return [];
    }

    public function getTransactionReference()
    {
        return $this->data['transactionReference'] ?? null;
    }

    public function getTransactionId()
    {
        return $this->data['transactionReference'] ?? null;
    }

    public function getMessage()
    {
        return $this->data['errorMessage'] ?? null;
    }
}
