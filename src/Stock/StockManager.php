<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Stock;

use Doctrine\DBAL\Connection;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\AbstractStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\CoreBundle\Model\ProductModel;
use Isotope\CoreBundle\Product\CoreProduct;
use Isotope\CoreBundle\Product\DataCollector\ProductDto;
use Isotope\CoreBundle\Product\DataCollector\ProductDtoCollection;
use Isotope\CoreBundle\Product\ProductBuilder;
use Isotope\CoreBundle\ProductCollection\LineItem\LineItem;
use Isotope\CoreBundle\ProductCollection\LineItem\LineItemCollection;
use Isotope\CoreBundle\Store\Context\StoreContext;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Reads and writes product stock, and answers whether an ordered quantity is still available.
 *
 * Works from raw line item data rather than line item objects, because that is what the order
 * transaction events carry: the order is assembled as an array before it becomes a collection.
 * Products are resolved through Isotope's own ProductBuilder, so the stock value seen here is the
 * one Isotope would resolve, inheritance for variants included, and it is read fresh from the
 * database at the moment of the call.
 */
readonly class StockManager
{
    public function __construct(
        private ProductBuilder $productBuilder,
        private Connection $connection,
        private StockAttribute $stockAttribute,
        private MaxOrderSizeAttribute $maxOrderSizeAttribute,
    ) {}

    /**
     * Resolves the product line items of an order into products with their total ordered quantity.
     *
     * Several line items can point at the same product with different configurations; for stock
     * purposes they count as one.
     *
     * @param list<array<string, mixed>> $lineItems
     *
     * @return list<OrderedQuantity>
     */
    public function resolveOrderedQuantities(array $lineItems, StoreContext $context): array
    {
        $quantities = [];

        foreach ($lineItems as $lineItem) {
            if (LineItem::TYPE_PRODUCT !== ($lineItem['type'] ?? null)) {
                continue;
            }

            $identifier = $lineItem['identifier'] ?? null;

            if (!\is_string($identifier) || '' === $identifier) {
                continue;
            }

            $quantities[$identifier] = ($quantities[$identifier] ?? 0) + (int) ($lineItem['quantity'] ?? 0);
        }

        if ([] === $quantities) {
            return [];
        }

        $products = $this->productBuilder->build(
            new ProductDtoCollection(array_map(
                static fn (string $identifier) => new ProductDto($identifier),
                array_keys($quantities),
            )),
            $context,
        );

        $ordered = [];

        foreach ($quantities as $identifier => $quantity) {
            $product = $products->findByIdentifier($identifier);

            if ($product instanceof CoreProduct) {
                $ordered[] = new OrderedQuantity($product, $quantity);
            }
        }

        return $ordered;
    }

    /**
     * Same as resolveOrderedQuantities(), but for a collection whose products are already loaded.
     *
     * @return list<OrderedQuantity>
     */
    public function resolveFromLineItems(LineItemCollection $lineItems): array
    {
        $quantities = [];
        $products = [];

        foreach ($lineItems->products() as $item) {
            $product = $item->getProduct();

            if (!$product instanceof CoreProduct) {
                continue;
            }

            $identifier = $product->getIdentifier();
            $products[$identifier] = $product;
            $quantities[$identifier] = ($quantities[$identifier] ?? 0) + $item->getQuantity();
        }

        $ordered = [];

        foreach ($quantities as $identifier => $quantity) {
            $ordered[] = new OrderedQuantity($products[$identifier], $quantity);
        }

        return $ordered;
    }

    /**
     * @param list<OrderedQuantity> $ordered
     *
     * @return list<TranslatableInterface> empty if every ordered quantity is available
     */
    public function validate(array $ordered): array
    {
        $messages = [];

        foreach ($ordered as $item) {
            foreach ($this->getMessages($item->product, $item->quantity) as $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * The highest quantity of these products that may be ordered, across all active attributes, or
     * null if none of them constrains the quantity.
     */
    public function getMaxQuantity(CoreProduct $product): ?int
    {
        $maxQuantity = null;

        foreach ($this->getAttributes() as $attribute) {
            if (!$attribute->isActive($product)) {
                continue;
            }

            $limit = $attribute->getMaxQuantity($product);

            if (null === $limit) {
                continue;
            }

            $maxQuantity = null === $maxQuantity ? $limit : min($maxQuantity, $limit);
        }

        return $maxQuantity;
    }

    /**
     * Why the given quantity of this product cannot be ordered.
     *
     * @return list<TranslatableInterface> empty if the quantity is fine
     */
    public function getMessages(CoreProduct $product, int $quantity): array
    {
        $messages = [];

        foreach ($this->getAttributes() as $attribute) {
            if (!$attribute->isActive($product)) {
                continue;
            }

            if ($message = $attribute->validateQuantity($product, $quantity)) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * @return list<AbstractStockAttribute>
     */
    private function getAttributes(): array
    {
        return [$this->stockAttribute, $this->maxOrderSizeAttribute];
    }

    /**
     * Whether stock is managed for this product, i.e. the attribute is enabled and has a value.
     */
    public function isManaged(CoreProduct $product): bool
    {
        return $this->stockAttribute->isUsed($product);
    }

    /**
     * Lowers the stock by the given quantity, never below zero.
     */
    public function decrease(CoreProduct $product, int $quantity): void
    {
        $this->write($product, max(0, (int) $product->stock - $quantity));
    }

    /**
     * Raises the stock by the given quantity.
     */
    public function increase(CoreProduct $product, int $quantity): void
    {
        $this->write($product, (int) $product->stock + $quantity);
    }

    /**
     * Whether lowering the stock by the given quantity would leave it negative.
     */
    public function wouldUnderflow(CoreProduct $product, int $quantity): bool
    {
        return (int) $product->stock - $quantity < 0;
    }

    private function write(CoreProduct $product, int $stock): void
    {
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET stock = ? WHERE id = ?', ProductModel::getTable()),
            [$stock, $product->id],
        );

        // Keep the in-memory product in step, so a second pass in the same request sees the new value
        $product->stock = $stock;
    }
}
