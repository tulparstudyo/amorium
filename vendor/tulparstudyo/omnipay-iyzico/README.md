# Omnipay: iyzico

iyzico Checkout Form gateway for [Omnipay](https://omnipay.thephpleague.com/) 3. The customer pays on iyzico. Card details stay there.

The shop calls `purchase` with `amount`, `currency`, `transactionId`, `returnUrl` and `cancelUrl`. The response redirects to `paymentPageUrl`. iyzico posts its `token` back to the return address, which also carries `paymentId` set to the order number. `completePurchase` retrieves that token and reports the captured amount as `status: paid`.

```php
$gateway = Omnipay\Omnipay::create('Iyzico');
$gateway->setApiKey('api key');
$gateway->setSecretKey('secret key');
$gateway->setTestMode(true);

$response = $gateway->purchase([
    'amount' => '10.00',
    'currency' => 'TRY',
    'transactionId' => '1001',
    'description' => 'Order 1001',
    'returnUrl' => 'https://shop.example/payments/iyzico/return?token=return-token',
    'cancelUrl' => 'https://shop.example/order/1001',
])->send();

$completed = $gateway->completePurchase([
    'transactionReference' => '1001',
])->send();
```

`setTestMode(true)` uses `https://sandbox-api.iyzipay.com`. `setBaseUrl` overrides that address. `setIdentityNumber` replaces the sandbox identity number `11111111111`. A card passed to `purchase` supplies the buyer name, email, phone and address.
