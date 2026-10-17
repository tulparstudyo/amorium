<?php

namespace Omnipay\PayTR;

use Omnipay\Common\AbstractGateway;
use Omnipay\PayTR\Message\CompletePurchaseRequest;
use Omnipay\PayTR\Message\PurchaseRequest;

/**
 * PayTR iFrame. The customer pays on PayTR and the card never reaches the shop.
 *
 * purchase() opens the hosted page. completePurchase() reads the order from PayTR.
 */
class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'PayTR';
    }

    public function getDefaultParameters()
    {
        return [
            'merchantId' => '',
            'merchantKey' => '',
            'merchantSalt' => '',
            'testMode' => false,
        ];
    }

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

    public function purchase(array $parameters = [])
    {
        return $this->createRequest(PurchaseRequest::class, $parameters);
    }

    public function completePurchase(array $parameters = [])
    {
        return $this->createRequest(CompletePurchaseRequest::class, $parameters);
    }
}
