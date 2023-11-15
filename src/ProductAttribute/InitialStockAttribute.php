<?php

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

class InitialStockAttribute extends AbstractAttribute
{
    public const NAME = 'initialStock';

    public static function getName(): string
    {
        return static::NAME;
    }
}