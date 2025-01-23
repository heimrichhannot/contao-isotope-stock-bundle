<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use Contao\Model;
use Doctrine\DBAL\Connection;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\Checkout;
use Isotope\ServiceAnnotation\IsotopeHook;

class CheckoutListener
{
    public function __construct(
        private readonly StockAttribute $stockAttribute,
        private readonly MaxOrderSizeAttribute $maxOrderSizeAttribute,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @IsotopeHook("preCheckout")
     */
    public function onPreCheckout(?Order $order, Checkout $module): bool
    {
        if (!$order) {
            return true;
        }

        return $this->validateStockCheckout($order);
    }

    /**
     * @IsotopeHook("postCheckout")
     */
    public function onPostCheckout(Order $order, array $tokens): void
    {
        $this->validateStockCheckout($order, true);
    }

    private function validateStockCheckout(Order $order, bool $isPostCheckout = false): bool
    {
        $items = $order->getItems();
        $postCheckoutActiveStockItems = [];

        foreach ($items as $item)
        {
            if (!$product = $item->getProduct()) {
                continue;
            }

            $stockActive = $this->stockAttribute->isActive($product);
            if ($stockActive && !$this->stockAttribute->validateQuantity($product, $item->quantity)) {
                return false;
            }

            $maxActive = $this->maxOrderSizeAttribute->isActive($product);
            if ($maxActive && !$this->maxOrderSizeAttribute->validateQuantity($product, $item->quantity)) {
                return false;
            }

            if ($isPostCheckout && $stockActive) {
                $postCheckoutActiveStockItems[] = $item;
            }
        }

        // save new stock
        foreach ($postCheckoutActiveStockItems as $item)
        {
            if (!$product = $item->getProduct()) {
                continue;
            }

            $newStock = (int) $product->stock - (int) $item->quantity;

            if ($newStock < 0) {
                $newStock = 0;
            }

            $table = $product instanceof Model ? $product::getTable() : Product::getTable();

            $this->connection->executeQuery("UPDATE $table SET stock = ? WHERE id = ?", [$newStock, $product->id]);
        }

        return true;
    }
}
