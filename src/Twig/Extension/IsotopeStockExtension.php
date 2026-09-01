<?php

namespace HeimrichHannot\IsotopeStockBundle\Twig\Extension;

use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\Interfaces\IsotopeProduct;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class IsotopeStockExtension implements AbstractExtension
{
    public function __construct(
        private readonly StockAttribute $stockAttribute,
        private readonly InitialStockAttribute $initialStockAttribute,
        private readonly MaxOrderSizeAttribute $maxStockAttribute,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('stock_attribute', $this->isIsoStockAttributeActive(...)),
        ];
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
