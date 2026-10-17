<?php

namespace Omnipay\Iyzico\Message;

use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Model\Status;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;

/**
 * PurchaseRequest opens an iyzico Checkout Form for one shop order.
 */
class PurchaseRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('apiKey', 'secretKey', 'amount', 'currency', 'transactionId', 'returnUrl');

        return [
            'transactionId' => (string) $this->getTransactionId(),
            'amount' => $this->money($this->getAmount()),
            'currency' => $this->currencyCode(),
            'description' => (string) ($this->getDescription() ?: $this->getTransactionId()),
            'returnUrl' => (string) $this->getReturnUrl(),
        ];
    }

    public function sendData($data)
    {
        $request = new CreateCheckoutFormInitializeRequest();
        $request->setLocale(Locale::TR);
        $request->setConversationId($data['transactionId']);
        $request->setPrice($data['amount']);
        $request->setPaidPrice($data['amount']);
        $request->setCurrency($data['currency']);
        $request->setBasketId($data['transactionId']);
        $request->setPaymentGroup(PaymentGroup::PRODUCT);
        $request->setCallbackUrl($this->callbackUrl($data['returnUrl'], $data['transactionId']));
        $request->setBuyer($this->buyer($data));
        $request->setShippingAddress($this->address());
        $request->setBillingAddress($this->address());
        $request->setBasketItems([$this->basketItem($data)]);

        $initialized = CheckoutFormInitialize::create($request, $this->options());

        return $this->response = new Response($this, [
            'transactionReference' => $data['transactionId'],
            'paymentPageUrl' => $initialized->getStatus() === Status::SUCCESS ? $initialized->getPaymentPageUrl() : null,
            'token' => $initialized->getToken(),
            'errorMessage' => $initialized->getErrorMessage(),
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

    /**
     * buyer fills the fields iyzico requires. A card on the request replaces the placeholders.
     */
    protected function buyer(array $data): Buyer
    {
        $card = $this->getCard();
        $buyer = new Buyer();
        $buyer->setId($data['transactionId']);
        $buyer->setName($card && $card->getFirstName() ? $card->getFirstName() : 'Customer');
        $buyer->setSurname($card && $card->getLastName() ? $card->getLastName() : 'Customer');
        $buyer->setGsmNumber($card && $card->getPhone() ? $card->getPhone() : '+905350000000');
        $buyer->setEmail($card && $card->getEmail() ? $card->getEmail() : 'customer@example.com');
        $buyer->setIdentityNumber((string) ($this->getIdentityNumber() ?: '11111111111'));
        $buyer->setRegistrationAddress($this->line($card));
        $buyer->setIp($this->httpRequest ? ($this->httpRequest->getClientIp() ?: '127.0.0.1') : '127.0.0.1');
        $buyer->setCity($card && $card->getBillingCity() ? $card->getBillingCity() : 'Istanbul');
        $buyer->setCountry($card && $card->getBillingCountry() ? $card->getBillingCountry() : 'Turkey');
        $buyer->setZipCode($card && $card->getBillingPostcode() ? $card->getBillingPostcode() : '34000');

        return $buyer;
    }

    /**
     * address is the billing or shipping block Checkout Form requires
     */
    protected function address(): Address
    {
        $card = $this->getCard();
        $address = new Address();
        $address->setContactName(trim(($card ? $card->getName() : '') ?: 'Customer'));
        $address->setCity($card && $card->getBillingCity() ? $card->getBillingCity() : 'Istanbul');
        $address->setCountry($card && $card->getBillingCountry() ? $card->getBillingCountry() : 'Turkey');
        $address->setAddress($this->line($card));
        $address->setZipCode($card && $card->getBillingPostcode() ? $card->getBillingPostcode() : '34000');

        return $address;
    }

    /**
     * basketItem is the whole order when the caller did not send a basket
     */
    protected function basketItem(array $data): BasketItem
    {
        $item = new BasketItem();
        $item->setId($data['transactionId']);
        $item->setName($data['description']);
        $item->setCategory1('General');
        $item->setItemType(BasketItemType::PHYSICAL);
        $item->setPrice($data['amount']);

        return $item;
    }

    /**
     * line is a street address iyzico will accept
     */
    protected function line($card): string
    {
        if ($card && $card->getBillingAddress1()) {
            return $card->getBillingAddress1();
        }

        return 'Address';
    }
}
