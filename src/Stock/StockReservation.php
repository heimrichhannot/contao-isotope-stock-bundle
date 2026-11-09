<?php

namespace HeimrichHannot\IsotopeStockBundle\Stock;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Message;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Model\Attribute;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\ProductType;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Atomic stock updates and per-order stock reservations.
 *
 * The stock (tl_iso_product.stock, int unsigned NULL; NULL = unmanaged) is only changed with relative, conditional
 * SQL updates, never by writing an absolute value computed in PHP. tl_iso_product is MyISAM (no transactions), so
 * partial failures are compensated by booking the already changed quantities back.
 *
 * The reservation of an order is stored in tl_iso_product_collection.stock_reservation as serialized map
 * (id of the product row holding the stock => quantity). It is only set and cleared with compare-and-swap SQL
 * updates, never via $order->save().
 */
readonly class StockReservation
{
    public function __construct(
        private Connection $connection,
        private StockAttribute $stockAttribute,
        private MaxOrderSizeAttribute $maxOrderSizeAttribute,
        private TranslatorInterface $translator,
        private Utils $utils,
        private LoggerInterface $contaoLogger,
    ) {
    }

    /**
     * Returns the id of the product row that holds the stock: the parent product if the variant inherits the stock
     * attribute, otherwise the product itself.
     *
     * Like Isotope's Standard::setRow(), a variant inherits the stock if it is no variant attribute of the product type
     * or if it is listed in the variant's inherit field (or is an always inherited attribute).
     */
    public function getTargetId(IsotopeProduct $product): int
    {
        if ($product->isVariant()) {
            $type = $product->getType();
            $variantAttributes = $type instanceof ProductType ? (array) $type->getVariantAttributes() : [];

            if (!\in_array(StockAttribute::NAME, $variantAttributes, true)) {
                return (int) $product->getProductId();
            }

            $inherited = array_merge(StringUtil::deserialize($product->inherit, true), Attribute::getInheritFields());

            if (\in_array(StockAttribute::NAME, $inherited, true)) {
                return (int) $product->getProductId();
            }
        }

        return (int) $product->getId();
    }

    /**
     * Atomically decreases the stock of the product's stock row by $quantity. The update only applies if the stock is
     * managed (not NULL) and sufficient, so the unsigned column never underflows. Afterwards the current stock is
     * always re-read into $product->stock.
     *
     * @return bool true if the stock was decreased, false otherwise (insufficient or unmanaged stock, row gone)
     */
    public function decrease(IsotopeProduct $product, int $quantity): bool
    {
        $targetId = $this->getTargetId($product);

        if ($quantity <= 0) {
            $this->refreshStock($product, $targetId);

            return true;
        }

        $affected = $this->connection->executeStatement(
            'UPDATE tl_iso_product SET stock = stock - :quantity WHERE id = :id AND stock IS NOT NULL AND stock >= :quantity',
            [
                'quantity' => $quantity,
                'id' => $targetId,
            ],
            [
                'quantity' => ParameterType::INTEGER,
                'id' => ParameterType::INTEGER,
            ],
        );

        $this->refreshStock($product, $targetId);

        return 1 === (int) $affected;
    }

    /**
     * Atomically increases the stock of the given product row by $quantity (only if its stock is managed).
     */
    public function increaseById(int $targetId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE tl_iso_product SET stock = stock + :quantity WHERE id = :id AND stock IS NOT NULL',
            [
                'quantity' => $quantity,
                'id' => $targetId,
            ],
            [
                'quantity' => ParameterType::INTEGER,
                'id' => ParameterType::INTEGER,
            ],
        );
    }

    /**
     * Reserves (decreases) the stock of all items of the order and stores what was reserved in the order's
     * stock_reservation field.
     *
     * If the order already holds exactly the reservation its items need, it is kept as is. Otherwise an existing
     * reservation is released first and the stock is reserved anew. Items with unmanaged stock, a deleted product or
     * a quantity <= 0 are skipped (the max order size is checked for all items first, also when the reservation is
     * kept). If an item can't be reserved, an error message is reported, everything already decreased is booked back
     * and false is returned.
     */
    public function reserveOrder(Order $order): bool
    {
        $orderId = (int) $order->id;

        if ($orderId <= 0) {
            // the reservation can only be stored on a saved order
            $this->addReservationFailedError();

            return false;
        }

        // max order size applies to all items, also those without managed stock (checked before touching any stock)
        foreach ($order->getItems() as $item) {
            $quantity = (int) $item->quantity;
            $product = $item->getProduct();

            if ($quantity <= 0 || null === $product) {
                continue;
            }

            if ($this->maxOrderSizeAttribute->isActive($product) && !$this->maxOrderSizeAttribute->validateQuantity($product, $quantity)) {
                return false;
            }
        }

        $currentMap = $this->fetchReservationMap($orderId);

        if (null !== $currentMap && $this->unserializeReservationMap($currentMap) === $this->getRequiredReservation($order)) {
            // releasing first would open a window in which a concurrent request could take the stock
            $this->syncReservationField($order, $currentMap);

            return true;
        }

        $this->releaseOrder($order);

        /** @var array<int, int> $reserved */
        $reserved = [];

        foreach ($order->getItems() as $item) {
            $quantity = (int) $item->quantity;
            $product = $item->getProduct();

            if ($quantity <= 0 || null === $product) {
                continue;
            }

            $targetId = $this->getTargetId($product);

            // pre-check (and its message) with the current stock, not the one the model was loaded with
            $this->refreshStock($product, $targetId);

            if (!$this->stockAttribute->isUsed($product)) {
                continue;
            }

            if (!$this->validateQuantity($product, $quantity)) {
                $this->bookBack($reserved);

                return false;
            }

            if (!$this->decrease($product, $quantity)) {
                // the stock changed since the pre-check: report it with the stock decrease() just re-read
                if ($this->validateQuantity($product, $quantity)) {
                    // the stock rose again meanwhile (or the row vanished): don't leave the user without a message
                    $this->addReservationFailedError();
                }

                $this->bookBack($reserved);

                return false;
            }

            $reserved[$targetId] = ($reserved[$targetId] ?? 0) + $quantity;
        }

        if (!$reserved) {
            return true;
        }

        ksort($reserved);
        $map = serialize($reserved);

        $affected = $this->connection->executeStatement(
            'UPDATE tl_iso_product_collection SET stock_reservation = :map WHERE id = :id AND stock_reservation IS NULL',
            [
                'map' => $map,
                'id' => $orderId,
            ],
            [
                'map' => ParameterType::STRING,
                'id' => ParameterType::INTEGER,
            ],
        );

        if (1 !== (int) $affected) {
            // reserved concurrently by another request: that reservation applies, take ours back
            $this->bookBack($reserved);

            $map = $this->fetchReservationMap($orderId);

            if (null === $map) {
                // ... but it is gone again: nothing is reserved for this order, fail closed
                $this->syncReservationField($order, null);
                $this->addReservationFailedError();

                return false;
            }
        }

        $this->syncReservationField($order, $map);

        return true;
    }

    /**
     * Releases the order's stock reservation (if any): clears the stock_reservation field and books the reserved
     * quantities back. Only the request that actually cleared the field books back.
     */
    public function releaseOrder(Order $order): void
    {
        $orderId = (int) $order->id;
        $map = $orderId > 0 ? $this->fetchReservationMap($orderId) : null;

        if (null !== $map) {
            $affected = $this->connection->executeStatement(
                'UPDATE tl_iso_product_collection SET stock_reservation = NULL WHERE id = :id AND stock_reservation = :map',
                [
                    'id' => $orderId,
                    'map' => $map,
                ],
                [
                    'id' => ParameterType::INTEGER,
                    'map' => ParameterType::STRING,
                ],
            );

            if (1 === (int) $affected) {
                $this->bookBack($this->unserializeReservationMap($map));
            }
        }

        $this->syncReservationField($order, null);
    }

    /**
     * Books a serialized reservation map back without compare-and-swap and without touching the order.
     *
     * Only for orders that are already deleted (the map can't be released concurrently anymore). $orderId is only
     * used for the log entry.
     */
    public function releaseReservationMap(int $orderId, string $serializedMap): void
    {
        $reserved = $this->unserializeReservationMap($serializedMap);

        if (!$reserved) {
            return;
        }

        $this->bookBack($reserved);

        $this->contaoLogger->info(
            \sprintf('Released the stock reservation of deleted order ID %s: %s', $orderId, json_encode($reserved)),
            [
                'contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL),
            ],
        );
    }

    /**
     * Returns the stock quantities the order holds (id of the stock row => quantity): its reservation map if it has
     * one (read from the database), otherwise (orders from before the reservation) computed from its items. Items
     * with unmanaged stock, a deleted product or a quantity <= 0 are skipped.
     *
     * @return array<int, int>
     */
    public function getOrderStockQuantities(Order $order): array
    {
        $orderId = (int) $order->id;
        $map = $orderId > 0 ? $this->fetchReservationMap($orderId) : null;

        if (null !== $map) {
            return $this->unserializeReservationMap($map);
        }

        return $this->getRequiredReservation($order);
    }

    /**
     * Atomically books the order's stock quantities (see getOrderStockQuantities()) back, e.g. when the order is
     * cancelled. Unmanaged stock rows are not touched.
     */
    public function restockOrder(Order $order): void
    {
        $this->bookBack($this->getOrderStockQuantities($order));
    }

    /**
     * Atomically decreases the stock by the order's stock quantities again (e.g. a cancelled order is reactivated).
     *
     * Each decrease is conditional (enough stock). Rows of deleted products and rows whose stock is unmanaged are
     * skipped. If a row can't be decreased, an error message is reported (with the current stock), everything already
     * decreased is booked back and false is returned.
     */
    public function unrestockOrder(Order $order): bool
    {
        /** @var array<int, int> $decreased */
        $decreased = [];

        foreach ($this->getOrderStockQuantities($order) as $targetId => $quantity) {
            if ($quantity <= 0 || null === ($product = Product::findByPk($targetId))) {
                continue;
            }

            // the row decrease() changes (normally $targetId itself)
            $rowId = $this->getTargetId($product);
            $this->refreshStock($product, $rowId);

            if (!$this->stockAttribute->isUsed($product)) {
                // unmanaged: nothing to decrease
                continue;
            }

            if ($this->decrease($product, $quantity)) {
                $decreased[$rowId] = ($decreased[$rowId] ?? 0) + $quantity;

                continue;
            }

            if (!$this->stockAttribute->isUsed($product)) {
                // the stock became unmanaged (or the row vanished) meanwhile: nothing to decrease
                continue;
            }

            // report it with the stock decrease() just re-read
            if ($this->stockAttribute->validateQuantity($product, $quantity)) {
                // the stock rose again meanwhile: don't leave the user without a message
                $this->addReservationFailedError();
            }

            $this->bookBack($decreased);

            return false;
        }

        return true;
    }

    /**
     * Validates the quantity with the active attributes (stock, max order size); the attributes report the error
     * message themselves.
     */
    private function validateQuantity(IsotopeProduct $product, int $quantity): bool
    {
        if ($this->stockAttribute->isActive($product) && !$this->stockAttribute->validateQuantity($product, $quantity)) {
            return false;
        }

        if ($this->maxOrderSizeAttribute->isActive($product) && !$this->maxOrderSizeAttribute->validateQuantity($product, $quantity)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the reservation (id of the stock row => quantity) the order's items currently need, without changing
     * any stock: managed stock, existing product, quantity > 0.
     *
     * @return array<int, int>
     */
    private function getRequiredReservation(Order $order): array
    {
        $required = [];

        foreach ($order->getItems() as $item) {
            $quantity = (int) $item->quantity;
            $product = $item->getProduct();

            if ($quantity <= 0 || null === $product) {
                continue;
            }

            $targetId = $this->getTargetId($product);
            $this->refreshStock($product, $targetId);

            if (!$this->stockAttribute->isUsed($product)) {
                continue;
            }

            $required[$targetId] = ($required[$targetId] ?? 0) + $quantity;
        }

        ksort($required);

        return $required;
    }

    /**
     * Reads the current stock of the given row into $product->stock: as string (StockAttribute::validateQuantity()
     * expects the model's string representation), NULL if the stock is unmanaged or the row is gone (so
     * StockAttribute::isUsed() returns false).
     * This marks the product model as modified: don't save() it afterwards (it would write the read value back).
     */
    private function refreshStock(IsotopeProduct $product, int $targetId): void
    {
        $stock = $this->connection->fetchOne(
            'SELECT stock FROM tl_iso_product WHERE id = :id',
            [
                'id' => $targetId,
            ],
            [
                'id' => ParameterType::INTEGER,
            ],
        );

        $stock = false === $stock || null === $stock ? null : (string) $stock;
        $product->stock = $stock;
    }

    /**
     * Books the given quantities (id of the stock row => quantity) back.
     *
     * @param array<int, int> $reserved
     */
    private function bookBack(array $reserved): void
    {
        foreach ($reserved as $targetId => $quantity) {
            $this->increaseById((int) $targetId, (int) $quantity);
        }
    }

    /**
     * Reads the order's serialized reservation map from the database (not from the model).
     */
    private function fetchReservationMap(int $orderId): ?string
    {
        $map = $this->connection->fetchOne(
            'SELECT stock_reservation FROM tl_iso_product_collection WHERE id = :id',
            [
                'id' => $orderId,
            ],
            [
                'id' => ParameterType::INTEGER,
            ],
        );

        return false === $map || null === $map ? null : (string) $map;
    }

    /**
     * @return array<int, int>
     */
    private function unserializeReservationMap(string $map): array
    {
        $data = @unserialize($map, [
            'allowed_classes' => false,
        ]);

        if (!\is_array($data)) {
            return [];
        }

        $reserved = [];

        foreach ($data as $targetId => $quantity) {
            if ((int) $targetId > 0 && (int) $quantity > 0) {
                $reserved[(int) $targetId] = (int) $quantity;
            }
        }

        ksort($reserved);

        return $reserved;
    }

    /**
     * Updates the order model's stock_reservation to the value now in the database, so it can't diverge from it.
     * If the field isn't modified in the model, it stays unmodified, so a later save() of the order doesn't write it
     * at all (and can't overwrite a reservation made concurrently).
     */
    private function syncReservationField(Order $order, ?string $map): void
    {
        $order->mergeRow([
            'stock_reservation' => $map,
        ]);

        // mergeRow() skips modified fields; the field is not declared on Order, hence row()/__set()
        if (($order->row()['stock_reservation'] ?? null) !== $map) {
            // the field was modified (but not saved) in the model: override that pending value
            $order->__set('stock_reservation', $map);
        }
    }

    private function addReservationFailedError(): void
    {
        $message = $this->translator->trans('MSC.stockReservationFailed', [], 'contao_default');

        if ($this->utils->container()->isFrontend()) {
            $_SESSION['ISO_ERROR'][] = $message;
        } else {
            Message::addError($message);
        }
    }
}
