<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Tests\Stock;

use Doctrine\DBAL\Connection;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\IsotopeStockBundle\Stock\OrderedQuantity;
use HeimrichHannot\IsotopeStockBundle\Stock\StockManager;
use HeimrichHannot\IsotopeStockBundle\Tests\ProductAttributeTestCase;
use Isotope\CoreBundle\Product\CoreProduct;
use Isotope\CoreBundle\Product\ProductBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class StockManagerTest extends ProductAttributeTestCase
{
    /**
     * @param array<string, mixed> $data
     * @param list<string>         $enabled
     */
    #[DataProvider('maxQuantityProvider')]
    public function testGetMaxQuantityTakesTheLowestCap(array $data, array $enabled, ?int $expected): void
    {
        $this->assertSame($expected, $this->manager()->getMaxQuantity($this->createProduct($data, $enabled)));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function maxQuantityProvider(): iterable
    {
        yield 'nothing enabled' => [['stock' => 2, 'maxOrderSize' => 1], [], null];

        yield 'only stock enabled' => [['stock' => 2, 'maxOrderSize' => 1], ['stock'], 2];

        yield 'only max order size enabled' => [['stock' => 2, 'maxOrderSize' => 1], ['maxOrderSize'], 1];

        yield 'max order size is lower' => [['stock' => 9, 'maxOrderSize' => 3], ['stock', 'maxOrderSize'], 3];

        yield 'stock is lower' => [['stock' => 2, 'maxOrderSize' => 3], ['stock', 'maxOrderSize'], 2];

        // An unmanaged stock must not remove the max order size cap, and vice versa
        yield 'unmanaged stock keeps the order size cap' => [['stock' => null, 'maxOrderSize' => 3], ['stock', 'maxOrderSize'], 3];

        yield 'no order size cap keeps the stock cap' => [['stock' => 2, 'maxOrderSize' => 0], ['stock', 'maxOrderSize'], 2];

        yield 'neither constrains' => [['stock' => null, 'maxOrderSize' => 0], ['stock', 'maxOrderSize'], null];

        yield 'sold out' => [['stock' => 0, 'maxOrderSize' => 3], ['stock', 'maxOrderSize'], 0];
    }

    public function testGetMessagesReportsEveryViolatedAttribute(): void
    {
        $product = $this->createProduct(['stock' => 2, 'maxOrderSize' => 3], ['stock', 'maxOrderSize']);

        $this->assertSame([], $this->messageKeys($product, 2));
        $this->assertSame(['MSC.stockExceeded'], $this->messageKeys($product, 3));
        $this->assertSame(['MSC.stockExceeded', 'MSC.maxOrderSizeExceeded'], $this->messageKeys($product, 4));
    }

    public function testValidateCollectsMessagesForEveryOrderedProduct(): void
    {
        $ordered = [
            new OrderedQuantity($this->createProduct(['stock' => 5], ['stock']), 1),
            new OrderedQuantity($this->createProduct(['stock' => 1], ['stock']), 2),
            new OrderedQuantity($this->createProduct(['stock' => 0], ['stock']), 1),
        ];

        $messages = array_map(
            self::messageKey(...),
            $this->manager()->validate($ordered),
        );

        $this->assertSame(['MSC.stockExceeded', 'MSC.stockEmpty'], $messages);
    }

    public function testIsManagedFollowsTheStockAttribute(): void
    {
        $manager = $this->manager();

        $this->assertTrue($manager->isManaged($this->createProduct(['stock' => 0], ['stock'])));
        $this->assertFalse($manager->isManaged($this->createProduct(['stock' => null], ['stock'])));
        $this->assertFalse($manager->isManaged($this->createProduct(['stock' => 5], [])));
    }

    #[DataProvider('underflowProvider')]
    public function testWouldUnderflow(int $stock, int $quantity, bool $expected): void
    {
        $product = $this->createProduct(['stock' => $stock], ['stock']);

        $this->assertSame($expected, $this->manager()->wouldUnderflow($product, $quantity));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function underflowProvider(): iterable
    {
        yield 'enough stock' => [5, 3, false];
        yield 'exactly enough' => [3, 3, false];
        // Regression: taking an order out of a cancelled state must not push the stock negative (0.1.3)
        yield 'not enough' => [2, 3, true];
        yield 'sold out' => [0, 1, true];
    }

    /**
     * @return list<string>
     */
    private function messageKeys(CoreProduct $product, int $quantity): array
    {
        return array_map(
            self::messageKey(...),
            $this->manager()->getMessages($product, $quantity),
        );
    }

    private static function messageKey(TranslatableInterface $message): string
    {
        self::assertInstanceOf(TranslatableMessage::class, $message);

        return $message->getMessage();
    }

    private function manager(): StockManager
    {
        return new StockManager(
            $this->createMock(ProductBuilder::class),
            $this->createMock(Connection::class),
            new StockAttribute(),
            new MaxOrderSizeAttribute(),
        );
    }
}
