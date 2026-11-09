# Changelog

All notable changes to this project will be documented in this file.

## [0.1.6] - unreleased
- Fixed: overbooking of stock caused by read-modify-write updates in `postCheckout` and on order status changes; stock is now changed atomically with relative, conditional SQL updates
- Fixed: stock changes now target the parent product if a variant inherits the stock
- Fixed: the backend product form no longer overwrites concurrent stock changes (change is applied as delta; a change that would make the stock negative is rejected)
- Fixed: cancelling an order into a stock-increasing status no longer fails on the max order size (the transition was cancelled after the stock of earlier items had already been increased, so a retry could restock twice)
- Changed: stock is reserved in `preCheckout` (instead of being decreased in `postCheckout`) and released when an incomplete order is deleted
- Changed: the max order size is checked for all items when the stock is reserved
- Changed: new column `tl_iso_product_collection.stock_reservation` -> run `contao:migrate` (or update the database)
- Added: MSC label `stockReservationFailed`
- Note: orders that are in the middle of checkout during the deployment are not deducted from the stock

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
