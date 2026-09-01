<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Order;

use HeimrichHannot\IsotopeStockBundle\Stock\StockManager;
use Isotope\CoreBundle\Model\ProductCollectionModel;
use Isotope\CoreBundle\ProductCollection\Order\OrderFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Refuses an order status transition that would take more out of stock than there is.
 *
 * Isotope 2 cancelled the transition by returning true from preOrderStatusUpdate. In Isotope 3 that
 * has no equivalent: stopping propagation on "isotope.order_status.pre" would make
 * OrderManager::updateOrder() return null from an array return type. The order status is a Symfony
 * Workflow, and updateOrder() consults Workflow::can() before applying a transition — blocking the
 * guard makes it drop the transition quietly, which is exactly the v2 behaviour.
 */
#[AsEventListener('workflow.tl_iso_product_collection.order_status.guard')]
readonly class OrderStatusStockGuardListener
{
    public function __construct(
        private StockManager $stockManager,
        private OrderFactory $orderFactory,
    ) {}

    public function __invoke(GuardEvent $event): void
    {
        $collection = $event->getSubject();

        if (!$collection instanceof ProductCollectionModel) {
            return;
        }

        $target = $event->getTransition()->getName();

        // Only the way back out of a stock-increasing status can run the stock down
        if (
            OrderStatusStockListener::increasesStock($target)
            || !OrderStatusStockListener::increasesStock($collection->getOrderStatus())
        ) {
            return;
        }

        $order = $this->orderFactory->load($collection);

        foreach ($this->stockManager->resolveFromLineItems($order->getLineItems()) as $item) {
            if (!$this->stockManager->isManaged($item->product)) {
                continue;
            }

            if ($this->stockManager->wouldUnderflow($item->product, $item->quantity)) {
                $event->setBlocked(true, \sprintf(
                    'Not enough stock of "%s" to take the order out of a stock-increasing status.',
                    $item->product->getName(),
                ));

                return;
            }
        }
    }
}
