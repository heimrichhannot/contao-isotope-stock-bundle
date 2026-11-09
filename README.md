# Contao Isotope Stock Bundle

> Oh no, another isotope stock bundle? Yes, but this is just the stock management part of [isotope_plus](https://github.com/heimrichhannot/contao-isotope_plus), [istope-bundle](https://github.com/heimrichhannot/contao-isotope-bundle) and [isotope-exstension](https://github.com/heimrichhannot/contao-isotope-extension-bundle). 
> It is a standalone bundle, so you can just migrate from the old extensions to this one if you only need the stock part. 
> Or maybe this make it easier to migrate to another bundle. Or you need a stock management extension and you like this one. Your choice :)

This bundle add stock management to [Isotope eCommerce](https://github.com/isotope/core#isotope-ecommerce) [Contao CMS](https://contao.org/de/) extension.

## Features

- add a stock management to Isoptope eCommerce
  - set stock (and optional initial stock) on products
  - evaluate stock on order process
- set max order size per product
- stock report frontend module
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

### Stock reservation

- the stock is reserved atomically when the checkout starts (`preCheckout`, priority -100), so concurrent orders cannot overbook a product; if a product is no longer available or the max order size is exceeded, the checkout is aborted with a message (`stockEmpty`, `stockExceeded`, `maxOrderSizeExceeded` or `stockReservationFailed`)
- the max order size is checked for all items, also for products without stock management
- if a variant inherits the stock from its parent product, the stock of the parent product is changed
- the reserved quantities are stored on the order in `tl_iso_product_collection.stock_reservation` (serialized map product id => quantity); after updating run `contao:migrate` (or update the database)
- the reservation is released when an incomplete order is deleted (e.g. by Isotope's cron)
- completed orders keep their reservation (it is their booking); in the backend the stock is booked back when an order changes into a stock-increasing status (e.g. cancelled) and taken again when it changes out of one
- backend product form: a stock change is applied as delta against the value displayed in the form, so stock changes made meanwhile (orders, other editors) are kept; a change that would make the stock negative is rejected with a message at the field

#### Known limitations

- locked orders that were never completed (`type='order' AND locked>0 AND order_status=0`) keep their reservation and are not shown in the backend order list. Find them with:

  ```sql
  SELECT id, tstamp, stock_reservation FROM tl_iso_product_collection
  WHERE type='order' AND locked>0 AND order_status=0 AND stock_reservation IS NOT NULL;
  ```

  To book the stock back, unserialize `stock_reservation` (map product id => quantity) and, for every entry, run

  ```sql
  UPDATE tl_iso_product SET stock = stock + <qty> WHERE id = <product id> AND stock IS NOT NULL;
  ```

  then set `stock_reservation = NULL` on the order.
- deleting a reserved draft order in the backend does not release its reservation (the backend deletes the row directly, without Isotope's delete hook); draft orders are not listed in the backend anyway. Reserved incomplete orders are found with `type='order' AND order_status=0 AND stock_reservation IS NOT NULL` (this includes checkouts currently in progress); book them back as described above
- if a `preCheckout` hook running after this bundle's (priority below -100) cancels the checkout, the reservation stays on the draft order until the customer retries or Isotope's cron deletes it (`iso_orderTimeout`, default 7 days)
- if a later `preOrderStatusUpdate` hook cancels a status transition, the stock change made by this bundle stays
- backend product form: a window of a few milliseconds remains, and if two tabs edit the same product the one opened last wins
- changing an order into a stock-increasing status (e.g. cancelling it) no longer fails because of the max order size; before, the transition could be cancelled after the stock of some items had already been increased, so a retry restocked them twice

### Stock report frontend module

The stock report frontend module shows all products with stock management and their stock.

![screenshot_stock_report.png](docs%2Fscreenshot_stock_report.png)

### Twig filter

The twig filter `stock_attribute` can be used to check if a product uses a stock attribute.

```twig
{% if product|stock_attribute('initialStock') %}
    {{ (roduct.stock / product.initialStock * 100)|round }}%
{% endif %}
```