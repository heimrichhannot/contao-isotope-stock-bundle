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