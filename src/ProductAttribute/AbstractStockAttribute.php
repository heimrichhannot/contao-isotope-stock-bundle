<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Isotope\CoreBundle\Product\CoreProduct;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Helper around one of the bundle's three tl_iso_product fields.
 *
 * These are not Isotope attribute types: Isotope 3 turns every tl_iso_product DCA field that is not
 * marked as a system column into an attribute on its own (see Isotope\CoreBundle\Attribute\AttributeRegistry).
 * The classes here only answer questions about such a field for a concrete product.
 *
 * They deliberately know nothing about where a message ends up — the caller decides whether it
 * belongs on a line item, in a flash bag or in the backend message stack.
 */
abstract class AbstractStockAttribute
{
    abstract public static function getName(): string;

    /**
     * Whether the product's type has this attribute enabled.
     */
    public function isActive(CoreProduct $product): bool
    {
        return \in_array(static::getName(), $product->getProductType()->getAttributes(), true);
    }

    /**
     * Whether the attribute is enabled and actually carries a value.
     */
    public function isUsed(CoreProduct $product): bool
    {
        if (!$this->isActive($product)) {
            return false;
        }

        return null !== $product->{static::getName()};
    }

    /**
     * The highest quantity this attribute allows, or null if it does not constrain the quantity.
     */
    public function getMaxQuantity(CoreProduct $product): ?int
    {
        return null;
    }

    /**
     * @return TranslatableInterface|null null if the quantity is acceptable, otherwise the reason
     */
    public function validateQuantity(CoreProduct $product, int $quantity): ?TranslatableInterface
    {
        return null;
    }
}
