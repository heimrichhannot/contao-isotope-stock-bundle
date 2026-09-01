<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Tests\ProductAttribute;

use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\Tests\ProductAttributeTestCase;

class InitialStockAttributeTest extends ProductAttributeTestCase
{
    public function testIsUsedIgnoresAnInitialStockOfZero(): void
    {
        $attribute = new InitialStockAttribute();

        $this->assertTrue($attribute->isUsed($this->createProduct(['initialStock' => 10], ['initialStock'])));
        // Zero carries no information: the percentage display cannot divide by it
        $this->assertFalse($attribute->isUsed($this->createProduct(['initialStock' => 0], ['initialStock'])));
        $this->assertFalse($attribute->isUsed($this->createProduct(['initialStock' => null], ['initialStock'])));
        $this->assertFalse($attribute->isUsed($this->createProduct(['initialStock' => 10], [])));
    }

    public function testDoesNotCapTheQuantity(): void
    {
        $attribute = new InitialStockAttribute();
        $product = $this->createProduct(['initialStock' => 10], ['initialStock']);

        $this->assertNull($attribute->getMaxQuantity($product));
        $this->assertNull($attribute->validateQuantity($product, 999));
    }
}
