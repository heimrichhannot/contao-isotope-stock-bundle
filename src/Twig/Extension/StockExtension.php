<?php

namespace HeimrichHannot\IsotopeStockBundle\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class StockExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('stock_attribute', [IsoStockAttributeRuntime::class, 'isIsoStockAttributeActive']),
        ];
    }
}