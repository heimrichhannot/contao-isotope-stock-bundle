<?php

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\Interfaces\IsotopeProduct;
use Symfony\Contracts\Translation\TranslatorInterface;

class MaxOrderSizeAttribute extends AbstractAttribute
{
    public const NAME = 'maxOrderSize';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public static function getName(): string
    {
        return static::NAME;
    }

    public function validateQuantity(IsotopeProduct $product, int $quantity): bool
    {
        if (null !== $product->maxOrderSize) {
            if ($quantity > $product->maxOrderSize) {
                $this->addErrorMessage($this->translator->trans('MSC.maxOrderSizeExceeded', [
                    $product->getName(), $product->maxOrderSize,
                ], 'contao_default'));

                return false;
            }
        }

        return true;
    }
}