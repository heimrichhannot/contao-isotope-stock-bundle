<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use HeimrichHannot\IsotopeStockBundle\Stock\StockReservation;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\Checkout;
use Isotope\ServiceAnnotation\IsotopeHook;

readonly class CheckoutListener
{
    public function __construct(
        private StockReservation $stockReservation,
    ) {
    }

    /**
     * Reserves (atomically decreases) the stock of the order's items, see StockReservation::reserveOrder().
     * Runs late, so the reservation is only made if the other preCheckout hooks didn't cancel the checkout before.
     *
     * @IsotopeHook("preCheckout", priority=-100)
     */
    public function onPreCheckout(?Order $order, Checkout $module): bool
    {
        if (!$order) {
            return true;
        }

        return $this->stockReservation->reserveOrder($order);
    }
}
