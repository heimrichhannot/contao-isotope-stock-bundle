<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Order;

use HeimrichHannot\IsotopeStockBundle\Stock\StockManager;
use Isotope\CoreBundle\Model\OrderStatusModel;
use Isotope\CoreBundle\ProductCollection\Order\OrderFactory;
use Isotope\CoreBundle\Store\Event\OrderStatusTransactionEvent;
use Isotope\CoreBundle\Store\Order\StatusType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Puts stock back when an order moves into a stock-increasing status, and takes it out again on the
 * way back.
 *
 * This replaces the preOrderStatusUpdate hook of Isotope 2. OrderManager::updateOrder() is the single
 * funnel for every status write — the backend DCA callback, payment methods and the PayPal IPN
 * controller all go through it — so unlike the v2 hook this is no longer backend-only. Refusing a
 * transition is not this listener's job; see OrderStatusStockGuardListener.
 *
 * The old status has to be captured in the .pre phase: by the time .post runs, the workflow has
 * already applied the transition and written the new status onto the order model. Both phases belong
 * to the same TransactionEventDispatcher::dispatch() call, so the pairing is guaranteed.
 */
class OrderStatusStockListener
{
    /**
     * @var array<int, string|null>
     */
    private array $previousStatus = [];

    public function __construct(
        private readonly StockManager $stockManager,
        private readonly OrderFactory $orderFactory,
    ) {}

    #[AsEventListener(OrderStatusTransactionEvent::NAME . '.pre')]
    public function onPre(OrderStatusTransactionEvent $event): void
    {
        $this->previousStatus[(int) $event->getOrder()->id] = $event->getOrder()->getOrderStatus();
    }

    #[AsEventListener(OrderStatusTransactionEvent::NAME . '.post')]
    public function onPost(OrderStatusTransactionEvent $event): void
    {
        $orderId = (int) $event->getOrder()->id;
        $previous = $this->previousStatus[$orderId] ?? null;
        unset($this->previousStatus[$orderId]);

        if (null !== $event->exception) {
            return;
        }

        $applied = $event->getResult();

        // updateOrder() drops a transition the workflow does not permit, so only the values it
        // actually applied say anything about the new status
        if (!isset($applied['order_status'])) {
            return;
        }

        $increase = self::increasesStock($applied['order_status']);

        if ($increase === self::increasesStock($previous)) {
            // No change in stock behaviour — nothing to do
            return;
        }

        $order = $this->orderFactory->load($event->getOrder());

        foreach ($this->stockManager->resolveFromLineItems($order->getLineItems()) as $item) {
            if (!$this->stockManager->isManaged($item->product)) {
                continue;
            }

            if ($increase) {
                $this->stockManager->increase($item->product, $item->quantity);
            } else {
                $this->stockManager->decrease($item->product, $item->quantity);
            }
        }
    }

    /**
     * Whether an order status alias marks the order's items as returned to stock.
     */
    public static function increasesStock(?string $alias): bool
    {
        if (null === $alias || '' === $alias) {
            return false;
        }

        $status = OrderStatusModel::findOneByAlias($alias, StatusType::Order);

        return (bool) $status?->stock_increaseStock;
    }
}
