<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\CoreBundle\Product\CoreProduct;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class StockAttribute extends AbstractStockAttribute
{
    public const NAME = 'stock';

    public static function getName(): string
    {
        return static::NAME;
    }

    public function getMaxQuantity(CoreProduct $product): ?int
    {
        if (!$this->hasStock($product)) {
            return null;
        }

        return \max(0, (int) $product->stock);
    }

    public function validateQuantity(CoreProduct $product, int $quantity): ?TranslatableInterface
    {
        if (!$this->hasStock($product)) {
            return null;
        }

        $stock = (int) $product->stock;

        if ($stock < 1) {
            return new TranslatableMessage('MSC.stockEmpty', [$product->getName()], 'contao_default');
        }

        if ($quantity > $stock) {
            return new TranslatableMessage('MSC.stockExceeded', [$product->getName(), $stock], 'contao_default');
        }

        return null;
    }

    /**
     * An empty stock field means "not managed", which is not the same as a stock of zero.
     */
    private function hasStock(CoreProduct $product): bool
    {
        return '' !== $product->stock && null !== $product->stock;
    }
}
