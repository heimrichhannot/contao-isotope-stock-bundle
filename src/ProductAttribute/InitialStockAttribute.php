<?php

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\Interfaces\IsotopeProduct;

class InitialStockAttribute extends AbstractAttribute
{
    public const NAME = 'initialStock';

    public static function getName(): string
    {
        return static::NAME;
    }

    public function isUsed(IsotopeProduct $product): bool
    {
        if (!parent::isUsed($product)) {
            return false;
        }

        if ($product->{static::getName()} === 0) {
            return false;
        }

        return true;
    }
}
