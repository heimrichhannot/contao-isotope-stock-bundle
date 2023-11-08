<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use Contao\Controller;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\OrderStatus;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\ProductCollectionItem;
use Isotope\Module\Checkout;
use Isotope\ServiceAnnotation\IsotopeHook;

class ValidateStockListener
{

    public function __construct(
        private StockAttribute $stockAttribute,
        private Utils $utils,
    ) {}


    /**
     * @IsotopeHook("addProductToCollection")
     */
    public function onAddProductToCollection(IsotopeProduct $product, $quantity, IsotopeProductCollection $collection, array $config): int
    {

        if (!$this->stockAttribute->isActive($product)) {
            return $quantity;
        }

        if (!is_int($quantity)) {
            if (empty($quantity)) {
                $quantity = 1;
            }
        }

        if (!$this->stockAttribute->validateQuantity($product, $quantity)) {
            return 0;
        }

        return $quantity;
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
    public function onPostCheckout(Order $order, Checkout $module): void
    {
        $this->validateStockCheckout($order, true);
    }

    /**
     * @IsotopeHook("updateItemInCollection")
     */
    public function onUpdateItemInCollection(ProductCollectionItem $item, array $set, ProductCollection $collection): array
    {
        $product = $item->getProduct();
        if (!$product) {
            return $set;
        }

        if (!$this->stockAttribute->isActive($product)) {
            return $set;
        }

        if (!$this->stockAttribute->validateQuantity($product, (int)$set['quantity'])) {
            Controller::reload();
        }

        return $set;
    }

    /**
     * @IsotopeHook("preOrderStatusUpdate")
     *
     * @return bool Cancel the order status transition
     */
    public function onPreOrderStatusUpdate(Order $order, OrderStatus $newsStatus, array $updates): bool
    {
        // atm only for backend
        if ($this->utils->container()->isFrontend()) {
            return false;
        }

        $oldStatus = OrderStatus::findByPk($order->order_status);
        if (!$oldStatus) {
            return false;
        }

        // e.g. new -> cancelled => increase the stock based on the order item's setQuantity-values (no validation required, of course)
        if (!$oldStatus->stock_increaseStock && $newsStatus->stock_increaseStock) {
            foreach ($order->getItems() as $item) {
                $product = $item->getProduct();
                if (!$product) {
                    continue;
                }

                if (!$this->stockAttribute->isActive($product)) {
                    continue;
                }

                $product->stock = (int)$product->stock + (int)$item->quantity;
                $product->save();
            }
        }
        // e.g. cancelled -> new => decrease the stock after validation
        elseif ($oldStatus->stock_increaseStock && !$newsStatus->stock_increaseStock) {
            foreach ($order->getItems() as $item) {
                if (!$product = $item->getProduct()) {
                    continue;
                }


                if (null !== ($product = $item->getProduct())) {
                    if (!$this->stockAttribute->validateQuantity($product, $item->quantity)) {
                        // if the validation breaks for only one product collection item -> cancel the order status transition
                        return true;
                    }
                }

                $product->stock = (int)$product->stock - (int)$item->quantity;
                $product->save();
            }
        }

        return false;
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

            if (!$this->stockAttribute->isActive($product)) {
                continue;
            }

            if (!$this->stockAttribute->validateQuantity($product, $item->quantity)) {
                return false;
            }

            if ($isPostCheckout) {
                $orders[] = $item;
            }
        }

        // save new stock
        if ($isPostCheckout) {
            foreach ($orders as $item) {
                $product = $item->getProduct();
                $intQuantity = (int)$items->quantity;
                $product->stock = (int)$product->stock - $intQuantity;
                $product->save();
            }
        }

        return true;
    }
}