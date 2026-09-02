<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Tests\ProductCollection\ItemCollector;

use Doctrine\DBAL\Connection;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductCollection\ItemCollector\StockItemCollector;
use HeimrichHannot\IsotopeStockBundle\Stock\StockManager;
use HeimrichHannot\IsotopeStockBundle\Tests\ProductAttributeTestCase;
use Isotope\CoreBundle\Product\CoreProduct;
use Isotope\CoreBundle\Product\ProductBuilder;
use Isotope\CoreBundle\ProductCollection\Cart\Cart;
use Isotope\CoreBundle\ProductCollection\LineItem\LineItem;
use Isotope\CoreBundle\Store\Context\StoreContext;
use Isotope\CoreBundle\Util\MessagesUtil;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class StockItemCollectorTest extends ProductAttributeTestCase
{
    public function testCapsTheQuantityAtTheStock(): void
    {
        $cart = $this->cart($this->createProduct(['stock' => 2], ['stock']), 5);

        $this->collector()->process($cart, $cart, $this->context());

        $item = $cart->getLineItems()->first();

        $this->assertSame(2, $item->getQuantity());
        $this->assertCount(1, $item->getErrors());
        $this->assertTrue($cart->isModified());
    }

    public function testCapsTheQuantityAtTheMaxOrderSize(): void
    {
        $cart = $this->cart($this->createProduct(['stock' => 50, 'maxOrderSize' => 3], ['stock', 'maxOrderSize']), 5);

        $this->collector()->process($cart, $cart, $this->context());

        $this->assertSame(3, $cart->getLineItems()->first()->getQuantity());
    }

    public function testLeavesAnUnmanagedProductAlone(): void
    {
        $cart = $this->cart($this->createProduct(['stock' => null], ['stock']), 10);

        $this->collector()->process($cart, $cart, $this->context());

        $this->assertSame(10, $cart->getLineItems()->first()->getQuantity());
        $this->assertSame([], $cart->getLineItems()->first()->getErrors());
        $this->assertFalse($cart->isModified());
    }

    /**
     * A sold out product cannot be capped to a usable quantity, so the item has to stay in the cart
     * carrying its error — that error is what the cart template renders and what blocks the checkout.
     */
    public function testKeepsASoldOutItemWithAnError(): void
    {
        $cart = $this->cart($this->createProduct(['stock' => 0], ['stock']), 1);

        $this->collector()->process($cart, $cart, $this->context());

        $this->assertCount(1, $cart->getLineItems());
        $this->assertSame(1, $cart->getLineItems()->first()->getQuantity());
        $this->assertCount(1, $cart->getLineItems()->first()->getErrors());
    }

    /**
     * AbstractCollectionCalculator runs the collector chain repeatedly on the same line items until
     * the collection hash stops changing, so a message must not be added twice.
     */
    public function testDoesNotRepeatTheErrorOnASecondRun(): void
    {
        $cart = $this->cart($this->createProduct(['stock' => 0], ['stock']), 1);
        $collector = $this->collector();

        $collector->process($cart, $cart, $this->context());
        $collector->process($cart, $cart, $this->context());

        $this->assertCount(1, $cart->getLineItems()->first()->getErrors());
    }

    /**
     * The collector never touches the context, and StoreContext is final with a dozen constructor
     * arguments, so an uninitialised instance is the cheapest honest stand-in.
     */
    private function context(): StoreContext
    {
        return (new \ReflectionClass(StoreContext::class))->newInstanceWithoutConstructor();
    }

    private function cart(CoreProduct $product, int $quantity): Cart
    {
        $item = new LineItem(LineItem::TYPE_PRODUCT, 'tl_iso_product.1', $quantity);
        $item->setProduct($product);

        $cart = new Cart('cart');
        $cart->add($item);

        return $cart;
    }

    private function collector(): StockItemCollector
    {
        $manager = new StockManager(
            $this->createMock(ProductBuilder::class),
            $this->createMock(Connection::class),
            new StockAttribute(),
            new MaxOrderSizeAttribute(),
        );

        // An empty request stack means no session, which is what makes the flash messages a no-op here
        return new StockItemCollector(
            $manager,
            $this->createMock(MessagesUtil::class),
            new RequestStack(),
            $this->createMock(TranslatorInterface::class),
        );
    }
}
