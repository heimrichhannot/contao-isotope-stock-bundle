<?php

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\Interfaces\IsotopeProduct;
use Symfony\Contracts\Translation\TranslatorInterface;

class MaxOrderSizeAttribute extends AbstractAttribute
{
    public const NAME = 'maxOrderSize';

    public function __construct(
        private TranslatorInterface $translator,
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
                    '%name%' => $product->getName(),
                    '%count%' => $product->maxOrderSize,
                ], 'contao_default'));
                $strErrorMessage = sprintf($GLOBALS['TL_LANG']['MSC']['maxOrderSizeExceeded'], $product->name, $product->maxOrderSize);

                return false;
            }
        }

        return true;
    }
}