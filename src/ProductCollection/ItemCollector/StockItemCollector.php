<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\ProductCollection\ItemCollector;

use HeimrichHannot\IsotopeStockBundle\Stock\StockManager;
use Isotope\CoreBundle\Product\CoreProduct;
use Isotope\CoreBundle\ProductCollection\AbstractProductCollection;
use Isotope\CoreBundle\ProductCollection\Cart\Cart;
use Isotope\CoreBundle\ProductCollection\ItemCollector\ItemCollectorInterface;
use Isotope\CoreBundle\ProductCollection\LineItem\LineItem;
use Isotope\CoreBundle\Store\Context\StoreContext;
use Isotope\CoreBundle\Util\MessagesUtil;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Caps every product line item at the quantity its stock and max order size allow.
 *
 * Isotope 2 did this in the addProductToCollection and updateItemInCollection hooks. Isotope 3 fires
 * no hooks at all; the cart is rebuilt through the item collector chain on every recalculation
 * (adding, changing a quantity, simply loading the cart), which covers both cases in one place.
 *
 * Runs after Isotope's own ProductCollector (priority 100), which is what puts the product line
 * items into the collection in the first place.
 */
#[AutoconfigureTag('isotope.item_collector', [
    'priority' => 90,
])]
readonly class StockItemCollector implements ItemCollectorInterface
{
    public function __construct(
        private StockManager $stockManager,
        private MessagesUtil $messages,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {}

    public function process(AbstractProductCollection $collection, AbstractProductCollection $original, StoreContext $context): void
    {
        $modified = false;

        foreach ($collection->getLineItems()->products() as $item) {
            $product = $item->getProduct();

            if (!$product instanceof CoreProduct) {
                continue;
            }

            $maxQuantity = $this->stockManager->getMaxQuantity($product);

            if (null === $maxQuantity || $item->getQuantity() <= $maxQuantity) {
                continue;
            }

            // Ask against the requested quantity, before it is capped
            $messages = $this->stockManager->getMessages($product, $item->getQuantity());

            foreach ($messages as $message) {
                $this->addError($item, $message);
            }

            if ($maxQuantity < 1) {
                // Nothing left to sell. The item stays in the collection carrying its error, the
                // same way Isotope\CoreBundle\ProductCollection\EventListener\LoadProductsListener
                // marks a product that has become unavailable: the error is what the cart template
                // renders, and it makes AbstractProductCollection::hasErrors() true, which stops
                // GoToCheckoutAction and the checkout step listener from letting an order through.
                continue;
            }

            $item->setQuantity($maxQuantity);
            $modified = true;

            // Adding to the cart redirects, so the line item error is gone before anything renders
            // it. The flash message is what the customer actually gets to see, which is what the
            // $_SESSION['ISO_ERROR'] message of Isotope 2 did.
            foreach ($messages as $message) {
                $this->flash($message);
            }
        }

        // The calculator only persists a cart that knows it changed
        if ($modified && $collection instanceof Cart) {
            $collection->setModified();
        }
    }

    /**
     * AbstractCollectionCalculator::calculate() runs the collector chain until the collection hash
     * stops changing, and Isotope's ProductCollector hands the same LineItem instances to every
     * iteration. Adding a message unconditionally would therefore show it once per iteration.
     */
    private function addError(LineItem $item, TranslatableInterface $message): void
    {
        foreach ($item->getErrors() as $existing) {
            if ($existing == $message) {
                return;
            }
        }

        $item->addError($message);
    }

    /**
     * Isotope renders these flashes in content_element/_isotope_base.html.twig, so the message shows
     * up on the page the add-to-cart action redirects to. Guarded rather than delegated straight to
     * MessagesUtil, because the collector chain also runs where no session exists at all (backend,
     * cart purging), and MessagesUtil::add() throws in that case.
     */
    private function flash(TranslatableInterface $message): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request?->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        $text = $message->trans($this->translator);

        // The calculator runs the chain more than once per request
        if (\in_array($text, $session->getFlashBag()->peek(MessagesUtil::TYPE_ERROR), true)) {
            return;
        }

        $this->messages->error($text);
    }
}
