<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use HeimrichHannot\IsotopeStockBundle\Stock\StockReservation;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Isotope\Model\OrderStatus;
use Isotope\Model\ProductCollection\Order;
use Isotope\ServiceAnnotation\IsotopeHook;

/**
 * @IsotopeHook("preOrderStatusUpdate")
 */
readonly class PreOrderStatusUpdateListener
{
    public function __construct(
        private Utils $utils,
        private StockReservation $stockReservation,
    ) {
    }

    /**
     * Books the order's stock back when it changes into a stock-increasing status (e.g. new -> cancelled) and takes
     * it again (atomically, conditionally) when it changes out of one (e.g. cancelled -> new).
     *
     * Known limit: if a later preOrderStatusUpdate hook cancels the transition, the stock change made here stays.
     *
     * @return bool true to cancel the order status transition (not enough stock to leave the stock-increasing status)
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

        if ((bool) $oldStatus->stock_increaseStock === (bool) $newsStatus->stock_increaseStock) {
            // No stock action change? Nothing to do here.
            return false;
        }

        if ($newsStatus->stock_increaseStock) {
            // e.g. new -> cancelled: book the order's stock back
            $this->stockReservation->restockOrder($order);

            return false;
        }

        // e.g. cancelled -> new: take the stock again, cancel the transition if there is not enough
        return !$this->stockReservation->unrestockOrder($order);
    }
}
