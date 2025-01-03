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
        $orders = [];

        foreach ($items as $item) {
            $product = $item->getProduct();
            if (!$product) {
                continue;
            }

            $continue = false;
            if ($this->stockAttribute->isActive($product)) {
                if (!$this->stockAttribute->validateQuantity($product, $item->quantity)) {
                    return false;
                }
                $continue = true;
            }
            if ($this->maxOrderSizeAttribute->isActive($product)) {
                if (!$this->maxOrderSizeAttribute->validateQuantity($product, $item->quantity)) {
                    return false;
                }
                $continue = true;
            }

            if (!$continue) {
                continue;
            }

            if ($isPostCheckout) {
                $orders[] = $item;
            }
        }

        // save new stock
        if ($isPostCheckout) {
            foreach ($orders as $item) {
                $product = $item->getProduct();
                if ($this->stockAttribute->isActive($product)) {
                    $intQuantity = (int) $item->quantity;
                    $newStock = (int) $product->stock - $intQuantity;
                    if ($newStock < 0) {
                        $newStock = 0;
                    }
                    if ($product instanceof Model) {
                        $table = $product::getTable();
                    } else {
                        $table = Product::getTable();
                    }
                    $this->connection->executeQuery("UPDATE $table SET stock = ? WHERE id = ?", [$newStock, $product->id]);
                }
            }
        }

        return true;
    }
}
