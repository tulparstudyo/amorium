<?php

namespace Omnipay\Iyzico;

use Omnipay\Common\AbstractGateway;
use Omnipay\Iyzico\Message\CompletePurchaseRequest;
use Omnipay\Iyzico\Message\PurchaseRequest;

/**
 * iyzico Checkout Form. The customer pays on iyzico and the card never reaches the shop.
 *
 * purchase() opens the hosted page. completePurchase() reads the token iyzico posts
 * back and checks the captured amount.
 */
class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'iyzico';
    }

    public function getDefaultParameters()
    {
        return [
            'apiKey' => '',
            'secretKey' => '',
            'baseUrl' => '',
            'testMode' => false,
            'identityNumber' => '11111111111',
        ];
    }

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
        return $this->getParameter('baseUrl');
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

    public function purchase(array $parameters = [])
    {
        return $this->createRequest(PurchaseRequest::class, $parameters);
    }

    public function completePurchase(array $parameters = [])
    {
        return $this->createRequest(CompletePurchaseRequest::class, $parameters);
    }
}
