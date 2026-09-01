<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Twig\Extension;

use HeimrichHannot\IsotopeStockBundle\ProductAttribute\AbstractStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\CoreBundle\Product\CoreProduct;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class IsotopeStockExtension extends AbstractExtension
{
    public function __construct(
        private readonly StockAttribute $stockAttribute,
        private readonly InitialStockAttribute $initialStockAttribute,
        private readonly MaxOrderSizeAttribute $maxOrderSizeAttribute,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('stock_attribute', $this->isStockAttributeUsed(...)),
        ];
    }

    /**
     * Whether the given product has the named stock attribute enabled and filled.
     */
    public function isStockAttributeUsed(mixed $product, string $attribute = StockAttribute::NAME): bool
    {
        if (!$product instanceof CoreProduct) {
            return false;
        }

        return $this->getAttribute($attribute)?->isUsed($product) ?? false;
    }

    private function getAttribute(string $name): ?AbstractStockAttribute
    {
        return match ($name) {
            StockAttribute::NAME => $this->stockAttribute,
            InitialStockAttribute::NAME => $this->initialStockAttribute,
            MaxOrderSizeAttribute::NAME => $this->maxOrderSizeAttribute,
            default => null,
        };
    }
}
