<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\CoreBundle\Product\CoreProduct;

class InitialStockAttribute extends AbstractStockAttribute
{
    public const NAME = 'initialStock';

    public static function getName(): string
    {
        return static::NAME;
    }

    /**
     * An initial stock of zero carries no information — the percentage display cannot use it.
     */
    public function isUsed(CoreProduct $product): bool
    {
        if (!parent::isUsed($product)) {
            return false;
        }

        return 0 !== (int) $product->{static::getName()};
    }
}
