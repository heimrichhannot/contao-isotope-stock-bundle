<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use Doctrine\DBAL\Connection;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Isotope\Model\OrderStatus;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\ProductCollectionItem;
use Isotope\ServiceAnnotation\IsotopeHook;

/**
 * @IsotopeHook("preOrderStatusUpdate")
 */
class PreOrderStatusUpdateListener
{
    public function __construct(
        private readonly Connection            $connection,
        private readonly Utils                 $utils,
        private readonly StockAttribute        $stockAttribute,
        private readonly MaxOrderSizeAttribute $maxOrderSizeAttribute,
    )
    {
    }

    /**
     * @return bool Cancel the order status transition if the stock increase/decrease fails
     */
    public function __invoke(Order $order, OrderStatus $newsStatus, array $updates): bool
    {
        // atm only for backend
        if ($this->utils->container()->isFrontend()) {
            return false;
        }

        if (!$oldStatus = OrderStatus::findByPk($order->order_status)) {
            return false;
        }

        if ((bool) $oldStatus->stock_increaseStock === (bool) $newsStatus->stock_increaseStock)
            // No stock action change? Nothing to do here.
        {
            return false;
        }

        // Determine the appropriate callback based on stock change
        //   e.g. new -> cancelled: increase the stock based on the ordered item's quantity
        //   e.g. cancelled -> new: decrease the stock
        $callback = !$oldStatus->stock_increaseStock && $newsStatus->stock_increaseStock
            ? 'increaseStock'
            : 'decreaseStock';

        foreach ($order->getItems() as $item)
        {
            if (!$this->$callback($item))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ProductCollectionItem $item
     * @return bool False if the stock increase failed
     * @noinspection PhpUnused
     */
    protected function increaseStock(ProductCollectionItem $item): bool
    {
        if (!$product = $item->getProduct()) {
            return true;
        }

        if ($this->stockAttribute->isActive($product))
        {
            $newStock = (int)$product->stock + (int)$item->quantity;

            $this->connection
                ->prepare("UPDATE `{$product::getTable()}` SET stock = ? WHERE id = ?")
                ->executeStatement([$newStock, $product->id]);

            $product->stock = $newStock;
        }

        if ($this->maxOrderSizeAttribute->isActive($product)
            && !$this->maxOrderSizeAttribute->validateQuantity($product, $item->quantity))
            // validation needed after stock increase
        {
            return false;
        }

        return true;
    }

    /**
     * @param ProductCollectionItem $item
     * @return bool False if the stock decrease failed
     * @noinspection PhpUnused
     */
    protected function decreaseStock(ProductCollectionItem $item): bool
    {
        if (!$product = $item->getProduct()) {
            return true;
        }

        if (!$this->stockAttribute->validateQuantity($product, $item->quantity))
            // validation needed before stock decrease
            // if the validation breaks, cancel the order status transition
        {
            return false;
        }

        $newStock = (int)$product->stock - (int)$item->quantity;

        if ($newStock < 0) {
            return false;
        }

        $this->connection
            ->prepare("UPDATE `{$product::getTable()}` SET stock = ? WHERE id = ?")
            ->executeStatement([$newStock, $product->id]);

        $product->stock = $newStock;

        return true;
    }
}