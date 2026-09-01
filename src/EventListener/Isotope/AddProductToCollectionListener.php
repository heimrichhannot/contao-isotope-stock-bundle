<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\ServiceAnnotation\IsotopeHook;

/**
 * @IsotopeHook("addProductToCollection")
 */
readonly class AddProductToCollectionListener
{
    public function __construct(
        private StockAttribute        $stockAttribute,
        private MaxOrderSizeAttribute $maxOrderSizeAttribute,
    ) {}

    public function __invoke(
        IsotopeProduct           $product,
        mixed                    $quantity,
        IsotopeProductCollection $collection,
        array                    $config,
    ): int {
        if (!\is_int($quantity))
        {
            $quantity = ($quantity === null || $quantity === '')
                ? 1
                : (int) $quantity;
        }

        $quantity = $this->validateStock($product, $quantity);

        return $this->validateMaxOrder($product, $quantity);
    }

    private function validateStock(IsotopeProduct $product, int $quantity): int
    {
        if (!$this->stockAttribute->isActive($product)) {
            return $quantity;
        }

        if (!$this->stockAttribute->validateQuantity($product, $quantity)) {
            return 0;
        }

        return $quantity;
    }

    private function validateMaxOrder(IsotopeProduct $product, int $quantity): int
    {
        if (!$this->maxOrderSizeAttribute->isActive($product)) {
            return $quantity;
        }

        if ($this->maxOrderSizeAttribute->validateQuantity($product, $quantity)) {
            return $quantity;
        }

        return $product->maxOrderSize;
    }
}
