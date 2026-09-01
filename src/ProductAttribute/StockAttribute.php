<?php

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\Interfaces\IsotopeProduct;
use Symfony\Contracts\Translation\TranslatorInterface;

class StockAttribute extends AbstractAttribute
{
    public const NAME = 'stock';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public static function getName(): string
    {
        return static::NAME;
    }

    public function validateQuantity(IsotopeProduct $product, int $quantity): bool
    {
        if ('' === $product->stock || null === $product->stock) {
            return true;
        }

        $stock = (int) $product->stock;

        if ($stock < 1)
        {
            $this->addErrorMessage($this->translator->trans('MSC.stockEmpty', [$product->getName()], 'contao_default'));

            return false;
        }

        if ($quantity > $stock)
        {
            $this->addErrorMessage($this->translator->trans('MSC.stockExceeded', [
                $product->getName(), $stock,
            ], 'contao_default'));

            return false;
        }

        return true;
    }
}
