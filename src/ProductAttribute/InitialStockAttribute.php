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
        if (parent::isUsed($product)) {
            if (0 === $product->{static::getName()}) {
                return false;
            }

            return true;
        }

        return false;
    }
}
