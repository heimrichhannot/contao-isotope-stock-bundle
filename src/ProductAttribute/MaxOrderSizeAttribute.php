<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\CoreBundle\Product\CoreProduct;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class MaxOrderSizeAttribute extends AbstractStockAttribute
{
    public const NAME = 'maxOrderSize';

    public static function getName(): string
    {
        return static::NAME;
    }

    public function getMaxQuantity(CoreProduct $product): ?int
    {
        $maxOrderSize = (int) $product->maxOrderSize;

        return $maxOrderSize < 1 ? null : $maxOrderSize;
    }

    public function validateQuantity(CoreProduct $product, int $quantity): ?TranslatableInterface
    {
        $maxOrderSize = $this->getMaxQuantity($product);

        if (null === $maxOrderSize || $quantity <= $maxOrderSize) {
            return null;
        }

        return new TranslatableMessage(
            'MSC.maxOrderSizeExceeded',
            [$product->getName(), $maxOrderSize],
            'contao_default',
        );
    }
}
