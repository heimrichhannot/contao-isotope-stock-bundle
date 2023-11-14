<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Model\Collection;
use Contao\System;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Isotope\Backend\Product\Label;
use Isotope\Model\ProductType;

class ProductListListener
{
    public function __construct(
        protected Utils $utils,
    )
    {
    }

    #[AsCallback(table: 'tl_iso_product', target: 'config.onload')]
    public function onLoadCallback(DataContainer $dc): void
    {
        $where = $this->utils->database()->createWhereForSerializedBlob('attributes', ['stock']);

        /** @var Collection|ProductType[] $types */
        $types = ProductType::findBy([$where->createOrWhere()], $where->values);
        if (!$types) {
            return;
        }

        $stockActive = false;
        foreach ($types as $type) {
            if (in_array(StockAttribute::getName(), $type->getAttributes())) {
                $stockActive = true;
                break;
            }
        }

        if (!$stockActive) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_iso_product']['list']['label']['fields'][] = 'stock';
        $GLOBALS['TL_DCA']['tl_iso_product']['fields']['stock']['sorting'] = true;

    }

//    #[AsCallback(table: 'tl_iso_product', target: 'list.label.label')]
//    public function onListLabelLabelCallback(array $row, string $label, DataContainer $dc, array $labels): array
//    {
//        $isotopeCallback = System::importStatic(Label::class);
//        $labels = $isotopeCallback->generate($row, $label, $dc, $labels);
//
//        if (($key = array_search('stock', $GLOBALS['TL_DCA']['tl_iso_product']['list']['label']['fields'])) === false) {
//            return $labels;
//        }
//
//        return $labels;
//    }
}