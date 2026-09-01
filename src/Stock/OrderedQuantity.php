<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Stock;

use Isotope\CoreBundle\Product\CoreProduct;

/**
 * A product together with the total quantity ordered of it.
 */
final readonly class OrderedQuantity
{
    public function __construct(
        public CoreProduct $product,
        public int $quantity,
    ) {}
}
