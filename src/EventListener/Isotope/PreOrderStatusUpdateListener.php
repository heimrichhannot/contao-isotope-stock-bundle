<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Isotope\Model\OrderStatus;
use Isotope\Model\ProductCollection\Order;
use Isotope\ServiceAnnotation\IsotopeHook;

/**
 * @IsotopeHook("preOrderStatusUpdate")
 */
class PreOrderStatusUpdateListener
{
    public function __construct(
        private Utils $utils,
        private StockAttribute $stockAttribute,
        private MaxOrderSizeAttribute $maxOrderSizeAttribute,
    )
    {
    }

    /**
     * @return bool Cancel the order status transition
     */
    public function __invoke(Order $order, OrderStatus $newsStatus, array $updates): bool
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

                if ($this->stockAttribute->isActive($product)) {
                    $product->stock = (int)$product->stock + (int)$item->quantity;
                    $product->save();
                }

                if ($this->maxOrderSizeAttribute->isActive($product)) {
                    if (!$this->maxOrderSizeAttribute->validateQuantity($product, $item->quantity)) {
                        return true;
                    }
                }

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
}