<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Model\Collection;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Util\Utils;
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
        $where = $this->utils->database()->createWhereForSerializedBlob('attributes', [StockAttribute::getName(), InitialStockAttribute::getName()]);

        /** @var Collection|ProductType[] $types */
        $types = ProductType::findBy([$where->createOrWhere()], $where->values);
        if (!$types) {
            return;
        }

        $stockActive = false;
        $initialStockActive = false;
        foreach ($types as $type) {
            if (in_array(StockAttribute::getName(), $type->getAttributes())) {
                $stockActive = true;
                if ($initialStockActive) {
                    break;
                }
            }
            if (in_array(InitialStockAttribute::getName(), $type->getAttributes())) {
                $initialStockActive = true;
                if ($stockActive) {
                    break;
                }
            }
        }

        if ($stockActive) {
            $GLOBALS['TL_DCA']['tl_iso_product']['list']['label']['fields'][] = 'stock';
            $GLOBALS['TL_DCA']['tl_iso_product']['fields']['stock']['sorting'] = true;
        }

        if ($initialStockActive) {
            $GLOBALS['TL_DCA']['tl_iso_product']['list']['label']['fields'][] = 'initialStock';
            $GLOBALS['TL_DCA']['tl_iso_product']['fields']['initialStock']['sorting'] = true;
        }
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