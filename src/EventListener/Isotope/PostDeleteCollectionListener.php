<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use HeimrichHannot\IsotopeStockBundle\Stock\StockReservation;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Order;
use Isotope\ServiceAnnotation\IsotopeHook;

/**
 * Books the stock reservation of a deleted, not completed order back.
 *
 * ProductCollection::delete() calls the postDeleteCollection hook after the row was deleted (also for delete(true)),
 * so the reservation map is only left in the model. The order must not be saved here, that would create it anew.
 * Isotope calls the hook even if the DELETE affected no row (already deleted elsewhere): Contao's Model::delete()
 * only resets the model's id if it deleted the row, so a set id means nothing to release.
 * Accepted residual: the in-memory map is trusted, so a re-reservation of the same draft by another request after
 * this model was loaded is not seen.
 *
 * @IsotopeHook("postDeleteCollection")
 */
readonly class PostDeleteCollectionListener
{
    public function __construct(
        private StockReservation $stockReservation,
    ) {
    }

    /**
     * @param int|string $pid the id the collection had before it was deleted
     */
    public function __invoke(ProductCollection $collection, int|string $pid): void
    {
        if (!$collection instanceof Order || $collection->isCheckoutComplete() || (int) $pid <= 0) {
            return;
        }

        $row = $collection->row();

        if (null !== ($row['id'] ?? null)) {
            // this call deleted nothing (Model::delete() resets the id only if a row was deleted)
            return;
        }

        $map = $row['stock_reservation'] ?? null;

        if (null === $map || '' === (string) $map) {
            return;
        }

        $this->stockReservation->releaseReservationMap((int) $pid, (string) $map);
    }
}
