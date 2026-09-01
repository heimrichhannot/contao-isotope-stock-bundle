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
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

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
    ) {}

    public function process(AbstractProductCollection $collection, AbstractProductCollection $original, StoreContext $context): void
    {
        $unavailable = [];
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
            $modified = true;

            if ($maxQuantity < 1) {
                // Nothing left to sell — the item cannot stay in the collection at all
                foreach ($messages as $message) {
                    $collection->addError($message);
                }

                $unavailable[] = $item;

                continue;
            }

            foreach ($messages as $message) {
                $item->addError($message);
            }

            $item->setQuantity($maxQuantity);
        }

        if ([] !== $unavailable) {
            $collection->setLineItems(
                $collection->getLineItems()->filter(
                    static fn (LineItem $item) => !\in_array($item, $unavailable, true),
                ),
            );
        }

        // The calculator only persists a cart that knows it changed
        if ($modified && $collection instanceof Cart) {
            $collection->setModified();
        }
    }
}
