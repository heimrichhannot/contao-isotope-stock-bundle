<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$dca = &$GLOBALS['TL_DCA']['tl_iso_orderstatus'];

PaletteManipulator::create()
    ->addField('stock_increaseStock', 'name_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_iso_orderstatus');

$dca['fields']['stock_increaseStock'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default ''",
];