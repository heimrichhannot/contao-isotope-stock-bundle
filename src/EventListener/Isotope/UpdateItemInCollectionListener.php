<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use Contao\Controller;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollectionItem;
use Isotope\ServiceAnnotation\IsotopeHook;

/**
 * @IsotopeHook("updateItemInCollection")
 */
class UpdateItemInCollectionListener
{
    public function __construct(
        private StockAttribute $stockAttribute,
        private MaxOrderSizeAttribute $maxOrderSizeAttribute,
    ) {}

    public function __invoke(ProductCollectionItem $item, array $set, ProductCollection $collection): array
    {
        $product = $item->getProduct();
        if (!$product) {
            return $set;
        }

        if ($this->stockAttribute->isActive($product)) {
            if (!$this->stockAttribute->validateQuantity($product, (int)$set['quantity'])) {
                Controller::reload();
            }
        }

        if ($this->maxOrderSizeAttribute->isActive($product)) {
            if (!$this->maxOrderSizeAttribute->validateQuantity($product, (int)$set['quantity'])) {
                Controller::reload();
            }
        }

        return $set;
    }
}