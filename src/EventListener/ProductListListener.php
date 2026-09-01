<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\CoreBundle\Model\ProductTypeModel;

/**
 * Shows stock as a sortable column in the backend product list, if any product type uses it.
 */
class ProductListListener
{
    #[AsCallback(table: 'tl_iso_product', target: 'config.onload')]
    public function onLoadCallback(DataContainer $dc): void
    {
        $enabled = $this->getEnabledAttributes();

        foreach ([StockAttribute::getName(), InitialStockAttribute::getName()] as $attribute) {
            if (!\in_array($attribute, $enabled, true)) {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_iso_product']['list']['label']['fields'][] = $attribute;
            $GLOBALS['TL_DCA']['tl_iso_product']['fields'][$attribute]['sorting'] = true;
        }
    }

    /**
     * The attribute names enabled by at least one product type.
     *
     * Isotope 3 stores tl_iso_producttype.attributes as a rowWizard blob of name/enabled pairs, so a
     * LIKE over the serialized value would match disabled attributes too. There are only ever a
     * handful of product types, so asking the model is both correct and cheap.
     *
     * @return list<string>
     */
    private function getEnabledAttributes(): array
    {
        $enabled = [];

        foreach (ProductTypeModel::findAll() ?? [] as $type) {
            $enabled[] = $type->getAttributes();
        }

        return [] === $enabled ? [] : array_values(array_unique(array_merge(...$enabled)));
    }
}
