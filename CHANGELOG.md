# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] - Isotope 3 / Contao 5.7

Breaking: this line supports **Contao 5.7 and Isotope 3 only**. Stay on the `isotope-v2` branch
(0.1.x) for Contao 4.13 and Isotope 2.

- Changed: platform floors are now PHP `^8.3`, `contao/core-bundle ^5.7.4`, `isotope/isotope-core ^3.0`,
  `doctrine/dbal ^4.3` and Symfony `^7.4`.
- Changed: **all four Isotope hooks are gone.** Isotope 3 registers `#[AsIsotopeHook]` listeners but
  dispatches no hooks at all, so every extension point had to be re-found:
  - `addProductToCollection` and `updateItemInCollection` → a single `StockItemCollector`
    (`isotope.item_collector`, priority 90), which caps quantities on every cart recalculation.
  - `preCheckout` → `CheckoutStockListener` on the checkout summary step. Isotope 3 offers no
    graceful way to abort an order once `CartManager::order()` has been entered, so the refusal now
    happens one step earlier, at the summary.
  - `postCheckout` → `OrderStockListener` on `isotope.order.post`.
  - `preOrderStatusUpdate` → `OrderStatusStockListener` on `isotope.order_status.pre`/`.post`, plus
    `OrderStatusStockGuardListener` on the order status workflow's guard event, which is what
    cancels a transition that would drive the stock negative.
- Changed: the stock report is a **content element** (`iso_stockreport`), not a frontend module —
  Isotope 3 has no frontend modules. Existing `tl_module` rows are converted automatically by
  `StockReportModuleToElementMigration`. Its template moved to
  `contao/templates/content_element/iso_stockreport.html.twig` and its label from `TL_LANG.FMD` to
  `TL_LANG.CTE`.
- Changed: `tl_iso_orderstatus.stock_increaseStock` is added to the `order` palette, since Isotope 3
  splits order statuses into order/transaction/delivery types.
- Changed: `stock` and `initialStock` are no longer mandatory in the backend. An empty value is what
  expresses "stock is not managed for this product", which a mandatory field cannot hold.
- Changed: error messages now go through `LineItem::addError()` and the Isotope flash bag instead of
  `$_SESSION['ISO_ERROR']`.
- Changed: dropped the `heimrichhannot/contao-utils-bundle` dependency. The serialized-blob query it
  provided cannot express Isotope 3's new `tl_iso_producttype.attributes` format, which stores
  name/enabled pairs; the product types are read through `ProductTypeModel` instead.
- Fixed: `IsotopeStockExtension` declared `implements Twig\Extension\AbstractExtension`, which is a
  class, not an interface — a fatal error as soon as the service was instantiated.
- Added: PHPUnit test suite covering the attribute classes and the stock arithmetic.

## [0.1.5] - 2025-02-03
- Fixed: migration issue (Issue: [#3](https://github.com/heimrichhannot/contao-isotope-stock-bundle/issues/3), Fix: [#4](https://github.com/heimrichhannot/contao-isotope-stock-bundle/pull/4))

## [0.1.4] - 2025-01-23
- Refactored: IsoCheckoutListener.php

## [0.1.3] - 2024-12-03
- Fixed: [#2](https://github.com/heimrichhannot/contao-isotope-stock-bundle/issues/2) stock decrease on order cancel

## [0.1.2] - 2024-09-16
- Fixed: exception in migration if fields not exist

## [0.1.1] - 2023-12-18
- Fixed: exception in stock calculation on post checkout

## [0.1.0] - 2023-11-17
Initial version.
