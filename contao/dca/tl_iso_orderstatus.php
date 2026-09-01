<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Doctrine\DBAL\Types\Types;

$dca = &$GLOBALS['TL_DCA']['tl_iso_orderstatus'];

// Isotope 3 splits tl_iso_orderstatus by type (order/transaction/delivery); only order statuses
// can affect stock
PaletteManipulator::create()
    ->addField('stock_increaseStock', 'name_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('order', 'tl_iso_orderstatus');

$dca['fields']['stock_increaseStock'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => Types::BOOLEAN, 'default' => false],
];
