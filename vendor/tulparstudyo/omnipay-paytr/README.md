# Omnipay: PayTR

PayTR iFrame gateway for [Omnipay](https://omnipay.thephpleague.com/) 3. The customer pays on PayTR. Card details stay there.

The token hash matches the iFrame request in the [PayTR Postman collection](https://github.com/paytr/paytr-postman). `purchase` returns the hosted page. The return address carries `paymentId` set to the order number. `completePurchase` calls the PayTR status query and reports a successful payment as `status: paid`.

`TRY` is sent to PayTR as `TL` and read back as `TRY`. Installments are turned off so the captured amount stays the order total.

```php
$gateway = Omnipay\Omnipay::create('PayTR');
$gateway->setMerchantId('merchant id');
$gateway->setMerchantKey('merchant key');
$gateway->setMerchantSalt('merchant salt');
$gateway->setTestMode(true);

$response = $gateway->purchase([
    'amount' => '10.00',
    'currency' => 'TRY',
    'transactionId' => '1001',
    'description' => 'Order 1001',
    'returnUrl' => 'https://shop.example/payments/paytr/return?token=return-token',
    'cancelUrl' => 'https://shop.example/order/1001',
])->send();
```
