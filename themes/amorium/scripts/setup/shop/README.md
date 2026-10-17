# AmoriumCms.Shop

October CMS 4 shop plugin for the [Amorium theme](https://github.com/AmoriumCms/theme).

Requirement: An installed October CMS 4 site (PHP 8.2+, MySQL InnoDB) and [RainLab.User](https://github.com/rainlab/user-plugin).

## Features

Feature | What it does
------------- | -------------
Catalog | Products, variants, categories, brands, specs and reviews
Customers | Accounts, addresses and favorites on RainLab.User
Orders | Cart, checkout, stock movements and order status history
Payments | Bank transfer, cash on delivery, Stripe Checkout and PayPal
Shipping | Shipping methods with a free-shipping threshold
Multisite | Prices, names and payment settings for each site

## Installation

```bash
php artisan plugin:install AmoriumCms.Shop --from=https://github.com/AmoriumCms/shop.git
php artisan october:migrate
```

The theme pages that call the shop live in the [theme repository](https://github.com/AmoriumCms/theme). Card providers are configured under **Shop > Payment Methods**. A new Omnipay provider is its Composer package plus a section in the payment form.

## Updating

```bash
php artisan october:update
```

Releases are marked with `vX.Y.Z` tags.

## Development

This repository is not edited by hand. The `themes/amorium/scripts/package.ps1` script in the development project writes the plugin to this repository, commits it and adds the version tag from `updates/version.yaml`.
