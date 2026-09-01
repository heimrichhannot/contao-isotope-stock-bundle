<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Tests\ProductAttribute;

use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\IsotopeStockBundle\Tests\ProductAttributeTestCase;
use Isotope\CoreBundle\Product\CoreProduct;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Translation\TranslatableMessage;

class StockAttributeTest extends ProductAttributeTestCase
{
    public function testIsActiveOnlyWhenTheProductTypeEnablesIt(): void
    {
        $attribute = new StockAttribute();

        $this->assertTrue($attribute->isActive($this->createProduct(['stock' => 5], ['stock'])));
        $this->assertFalse($attribute->isActive($this->createProduct(['stock' => 5], ['maxOrderSize'])));
    }

    public function testIsUsedRequiresAValue(): void
    {
        $attribute = new StockAttribute();

        $this->assertTrue($attribute->isUsed($this->createProduct(['stock' => 0], ['stock'])));
        $this->assertFalse($attribute->isUsed($this->createProduct(['stock' => null], ['stock'])));
        $this->assertFalse($attribute->isUsed($this->createProduct(['stock' => 5], [])));
    }

    #[DataProvider('maxQuantityProvider')]
    public function testGetMaxQuantity(mixed $stock, ?int $expected): void
    {
        $this->assertSame($expected, (new StockAttribute())->getMaxQuantity($this->product($stock)));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function maxQuantityProvider(): iterable
    {
        // An empty stock means "not managed", which must not cap the quantity
        yield 'not managed (null)' => [null, null];
        yield 'not managed (empty string)' => ['', null];
        yield 'sold out' => [0, 0];
        yield 'in stock' => [7, 7];
        yield 'numeric string' => ['7', 7];
        yield 'negative stock is treated as sold out' => [-3, 0];
    }

    #[DataProvider('validateProvider')]
    public function testValidateQuantity(mixed $stock, int $quantity, ?string $expectedMessage): void
    {
        $message = (new StockAttribute())->validateQuantity($this->product($stock), $quantity);

        if (null === $expectedMessage) {
            $this->assertNull($message);

            return;
        }

        $this->assertInstanceOf(TranslatableMessage::class, $message);
        $this->assertSame($expectedMessage, $message->getMessage());
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function validateProvider(): iterable
    {
        yield 'unmanaged stock accepts anything' => [null, 999, null];
        yield 'empty stock accepts anything' => ['', 999, null];
        yield 'quantity below stock' => [5, 3, null];
        yield 'quantity equal to stock' => [5, 5, null];
        yield 'quantity above stock' => [5, 6, 'MSC.stockExceeded'];
        yield 'sold out' => [0, 1, 'MSC.stockEmpty'];
        // Regression: a sold out product must complain even when nothing is being ordered (0.1.1)
        yield 'sold out with zero quantity' => [0, 0, 'MSC.stockEmpty'];
    }

    private function product(mixed $stock): CoreProduct
    {
        return $this->createProduct(['stock' => $stock], ['stock']);
    }
}
