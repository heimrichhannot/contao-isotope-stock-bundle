<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Order;

use HeimrichHannot\IsotopeStockBundle\Stock\StockManager;
use Isotope\CoreBundle\Checkout\Event\CheckoutStepEvent;
use Isotope\CoreBundle\Util\MessagesUtil;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Stops the checkout from being placed while the cart asks for more than is in stock.
 *
 * This replaces the preCheckout hook of Isotope 2. Isotope 3 has no graceful way to abort an order
 * once CartManager::order() has been entered — a ConstraintViolationException from
 * "isotope.validation.order.create" is caught by nobody, and stopping propagation on
 * "isotope.order.pre" leaves CartManager::order() with a null result. The checkout summary step is
 * the last point where refusing is still a rendered page rather than an error: setHasError() keeps
 * CheckoutController from calling CartManager::order() at all.
 */
#[AsEventListener]
readonly class CheckoutStockListener
{
    public function __construct(
        private StockManager $stockManager,
        private MessagesUtil $messages,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(CheckoutStepEvent $event): void
    {
        if ('_summary' !== $event->getType()) {
            return;
        }

        $cart = $event->getContext()->getCart();
        $messages = $this->stockManager->validate(
            $this->stockManager->resolveFromLineItems($cart->getLineItems()),
        );

        if ([] === $messages) {
            return;
        }

        $event->setHasError();

        // Only shout when the customer actually tried to place the order — the summary step is
        // rendered on every checkout request
        if (!$event->isSubmitted()) {
            return;
        }

        foreach ($messages as $message) {
            $this->messages->error($message->trans($this->translator));
        }
    }
}
