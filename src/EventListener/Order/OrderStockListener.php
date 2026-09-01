<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Order;

use HeimrichHannot\IsotopeStockBundle\Stock\StockManager;
use Isotope\CoreBundle\Store\Event\OrderTransactionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Takes the ordered quantities out of stock once the order exists.
 *
 * This replaces the postCheckout hook of Isotope 2. The line items are read from the event data
 * rather than from a collection because that is the shape the order has inside its transaction.
 */
#[AsEventListener(OrderTransactionEvent::NAME . '.post')]
readonly class OrderStockListener
{
    public function __construct(
        private StockManager $stockManager,
    ) {}

    public function __invoke(OrderTransactionEvent $event): void
    {
        // The transaction dispatcher fires .post even when the wrapped callback threw, or when a
        // .pre listener stopped propagation and the callback never ran at all — in both cases there
        // is no order to take anything out of stock for. The generic result type claims it cannot be
        // null, which is only true on the happy path.
        // @phpstan-ignore identical.alwaysFalse
        if (null !== $event->exception || null === $event->getResult()) {
            return;
        }

        $ordered = $this->stockManager->resolveOrderedQuantities(
            $event->getData()['line_items'] ?? [],
            $event->getStoreContext(),
        );

        foreach ($ordered as $item) {
            if (!$this->stockManager->isManaged($item->product)) {
                continue;
            }

            $this->stockManager->decrease($item->product, $item->quantity);
        }
    }
}
