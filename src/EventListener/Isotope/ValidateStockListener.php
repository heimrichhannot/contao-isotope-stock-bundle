<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\Isotope;

use Isotope\Interfaces\IsotopeProduct;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\Checkout;
use Isotope\ServiceAnnotation\IsotopeHook;

class ValidateStockListener
{
    /**
     * @IsotopeHook("preCheckout")
     */
    public function onPreCheckout(?Order $order, Checkout $module): bool
    {
        if (!$order) {
            return true;
        }

        return $this->validateStockCheckout($order);
    }

    public function validateStockCheckout(Order $order, bool $isPostCheckout = false): bool
    {
        $items = $order->getItems();
        $orders = [];

        foreach ($items as $item) {
            // @todo Check if product has stock attributes
            $product = $item->getProduct();
            if (!$product) {
                continue;
            }



            if ('' != $product->stock && null !== $product->stock) {
                // override the quantity!
                if (!$this->validateQuantity($product, $item->quantity)) {
                    return false;
                }

                if ($isPostCheckout) {
                    $orders[] = $item;
                }
            }
        }

        // save new stock
        if ($isPostCheckout) {
            foreach ($orders as $item) {
                $product = $item->getProduct();

                if ($this->getOverridableStockProperty('skipStockEdit', $product)) {
                    continue;
                }

                $intQuantity = $this->getTotalStockQuantity($item->quantity, $product);

                $data = [
                    'stock' => $product->stock - $intQuantity,
                ];

                if ($data['stock'] <= 0 && !$this->getOverridableStockProperty('skipExemptionFromShippingWhenStockEmpty', $product)) {
                    $data['shipping_exempt'] = true;
                }

                $this->databaseUtil->update('tl_iso_product', $data, 'tl_iso_product.id=?', [$product->id]);
            }
        }

        return true;
    }

    /**
     * @param                       $quantity
     * @param ProductCollectionItem $cartItem
     * @param int                   $setQuantity
     *
     * @return array|bool
     */
    public function validateQuantity(IsotopeProduct $product, $quantity, ProductCollectionItem $cartItem = null, bool $includeError = false, int $setQuantity = null)
    {
        // no quantity at all
        if (null === $quantity) {
            return true;
        } elseif (empty($quantity)) {
            $quantity = 1;
        }

        $quantityTotal = $this->getTotalCartQuantity($quantity, $product, $cartItem, $setQuantity);

        // stock
        if (!$this->getOverridableStockProperty('skipStockValidation', $product)) {
            $validateStock = $this->stockAttribute->validate($product, $quantityTotal, $includeError);

            if (true !== $validateStock[0]) {
                return $this->validateQuantityErrorResult($validateStock[1], $includeError);
            }
        }

        // maxOrderSize
        $validateMaxOrderSize = $this->maxOrderSizeAttribute->validate($product, $quantityTotal);

        if (true !== $validateMaxOrderSize[0]) {
            return $this->validateQuantityErrorResult($validateMaxOrderSize[1], $includeError);
        }

        if ($includeError) {
            return [true, null];
        }

        return true;
    }
}