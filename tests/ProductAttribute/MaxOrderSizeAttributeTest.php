<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Tests\ProductAttribute;

use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\Tests\ProductAttributeTestCase;
use Isotope\CoreBundle\Product\CoreProduct;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Translation\TranslatableMessage;

class MaxOrderSizeAttributeTest extends ProductAttributeTestCase
{
    #[DataProvider('maxQuantityProvider')]
    public function testGetMaxQuantity(mixed $maxOrderSize, ?int $expected): void
    {
        $this->assertSame($expected, (new MaxOrderSizeAttribute())->getMaxQuantity($this->product($maxOrderSize)));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function maxQuantityProvider(): iterable
    {
        yield 'not set' => [null, null];
        yield 'empty string' => ['', null];
        // Zero means "no limit", not "nothing may be ordered"
        yield 'zero is no limit' => [0, null];
        yield 'limit' => [3, 3];
    }

    #[DataProvider('validateProvider')]
    public function testValidateQuantity(mixed $maxOrderSize, int $quantity, bool $expectMessage): void
    {
        $message = (new MaxOrderSizeAttribute())->validateQuantity($this->product($maxOrderSize), $quantity);

        if (!$expectMessage) {
            $this->assertNull($message);

            return;
        }

        $this->assertInstanceOf(TranslatableMessage::class, $message);
        $this->assertSame('MSC.maxOrderSizeExceeded', $message->getMessage());
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function validateProvider(): iterable
    {
        yield 'no limit set' => [null, 999, false];
        yield 'zero is no limit' => [0, 999, false];
        yield 'below the limit' => [3, 2, false];
        yield 'at the limit' => [3, 3, false];
        yield 'above the limit' => [3, 4, true];
    }

    private function product(mixed $maxOrderSize): CoreProduct
    {
        return $this->createProduct(['maxOrderSize' => $maxOrderSize], ['maxOrderSize']);
    }
}
