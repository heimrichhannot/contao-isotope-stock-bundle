# Contao Isotope Stock Bundle

> Oh no, another isotope stock bundle? Yes, but this is just the stock management part of [isotope_plus](https://github.com/heimrichhannot/contao-isotope_plus), [istope-bundle](https://github.com/heimrichhannot/contao-isotope-bundle) and [isotope-exstension](https://github.com/heimrichhannot/contao-isotope-extension-bundle). 
> It is a standalone bundle, so you can just migrate from the old extensions to this one if you only need the stock part. 
> Or maybe this make it easier to migrate to another bundle. Or you need a stock management extension and you like this one. Your choice :)

This bundle add stock management to [Isotope eCommerce](https://github.com/isotope/core#isotope-ecommerce) [Contao CMS](https://contao.org/de/) extension.

## Versions

| Branch | Contao | Isotope |
|---|---|---|
| `isotope-v3` | 5.7 | 3.x |
| `isotope-v2` (0.1.x) | 4.13 | 2.x |

There is no version that spans both: Isotope 2 requires Contao ^4.13 and Isotope 3 requires ^5.7.4,
so the Contao and the Isotope upgrade are one single step.

## Features

- add a stock management to Isoptope eCommerce
  - set stock (and optional initial stock) on products
  - evaluate stock on order process
- set max order size per product
- stock report content element
- twig filter

## Usage

### Installation

1. Install via composer or contao manager:

    ```bash
    composer require heimrichhannot/contao-isotope-stock-bundle
    ```

1. Update your database.

### Setup

To activate stock management, the initial stock field or the max order size feature,
just activate these attributes in your product type configuration.

### Stock report content element

The `iso_stockreport` content element shows all products with stock management and their stock.
Isotope 3 has no frontend modules; an `iso_stockreport` frontend module left over from an older
version is converted into a content element automatically when you run `contao:migrate`.

![screenshot_stock_report.png](docs%2Fscreenshot_stock_report.png)

### Twig filter

The twig filter `stock_attribute` can be used to check if a product uses a stock attribute.

```twig
{% if product|stock_attribute('initialStock') %}
    {{ (product.stock / product.initialStock * 100)|round }}%
{% endif %}
```

### Stock behaviour

- Adding to the cart, or changing a quantity in it, caps the quantity at whatever stock and max order
  size allow. A product that has sold out in the meantime is removed from the cart.
- Placing an order takes the ordered quantities out of stock. The checkout refuses to be placed while
  the cart asks for more than is available.
- An order status marked *stock-increasing* (a cancellation, say) puts the stock back when an order
  moves into it, and takes it out again on the way back. A transition that would drive the stock
  negative is refused.

### Development

The bundle has no DDEV project of its own; its tooling runs inside a Contao 5.7 project's container:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/ecs check
vendor/bin/rector process --dry-run
```