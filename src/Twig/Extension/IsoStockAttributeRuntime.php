<?php

namespace HeimrichHannot\IsotopeStockBundle\Twig\Extension;

use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\Interfaces\IsotopeProduct;
use Twig\Extension\RuntimeExtensionInterface;

class IsoStockAttributeRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private StockAttribute $stockAttribute,
        private InitialStockAttribute $initialStockAttribute,
        private MaxOrderSizeAttribute $maxStockAttribute,
    )
    {
    }

    public function isIsoStockAttributeActive($product, string $attribute = 'stock'): bool
    {
        if (!($product instanceof IsotopeProduct)) {
            return false;
        }

        return match ($attribute) {
            StockAttribute::getName() => $this->stockAttribute->isUsed($product),
            InitialStockAttribute::getName() => $this->initialStockAttribute->isUsed($product),
            MaxOrderSizeAttribute::getName() => $this->maxStockAttribute->isUsed($product),
            default => false,
        };
    }
}