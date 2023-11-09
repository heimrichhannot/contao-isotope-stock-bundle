<?php

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\Interfaces\IsotopeProduct;
use Symfony\Contracts\Translation\TranslatorInterface;

class StockAttribute extends AbstractAttribute
{
    public const NAME = 'stock';

    public function __construct(
        private TranslatorInterface $translator,
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

        if (0 === (int)$product->stock) {
            $this->addErrorMessage($this->translator->trans('MSC.stockEmpty', [$product->getName()], 'contao_default'));
            return false;
        }

        if ($quantity > (int)$product->stock) {
            $this->addErrorMessage($this->translator->trans('MSC.stockExceeded', [
                $product->getName(), (int)$product->stock,
            ], 'contao_default'));
            return false;
        }

        return true;
    }

}