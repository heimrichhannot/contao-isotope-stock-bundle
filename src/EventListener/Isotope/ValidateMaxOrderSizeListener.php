<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use Contao\Controller;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollectionItem;
use Isotope\ServiceAnnotation\IsotopeHook;

class ValidateMaxOrderSizeListener
{

    public function __construct(
        private MaxOrderSizeAttribute $maxOrderSizeAttribute,
    )
    {
    }

    /**
     * @IsotopeHook("addProductToCollection")
     */
    public function onAddProductToCollection(IsotopeProduct $product, $quantity, IsotopeProductCollection $collection, array $config): int
    {
        if (!$this->maxOrderSizeAttribute->isActive($product)) {
            return $quantity;
        }

        if ($this->maxOrderSizeAttribute->validateQuantity($product, $quantity)) {
            return $quantity;
        }

        return $product->maxOrderSize;
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

        if ($oldStatus->stock_increaseStock && !$newsStatus->stock_increaseStock) {
            foreach ($order->getItems() as $item) {
                if (!$product = $item->getProduct()) {
                    continue;
                }

                if (!$this->maxOrderSizeAttribute->isActive($product)) {
                    continue;
                }

                if (!$this->maxOrderSizeAttribute->validateQuantity($product, $item->quantity)) {
                    return true;
                }
            }
        }

        return false;
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

        if (!$this->maxOrderSizeAttribute->isActive($product)) {
            return $set;
        }

        if (!$this->maxOrderSizeAttribute->validateQuantity($product, (int)$set['quantity'])) {
            Controller::reload();
        }

        return $set;
    }
}