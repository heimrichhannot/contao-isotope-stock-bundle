<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_product'];

$dca['fields']['stock']        = [
    'inputType'  => 'text',
    'eval'       => ['mandatory' => true, 'tl_class' => 'w50', 'rgxp' => 'digit'],
    'attributes' => ['legend' => 'inventory_legend', 'fe_sorting' => true],
    'sql'        => "int(10) unsigned NULL",
];
$dca['fields']['initialStock'] = [
    'inputType'  => 'text',
    'eval'       => ['mandatory' => true, 'tl_class' => 'w50', 'rgxp' => 'digit'],
    'attributes' => ['legend' => 'inventory_legend'],
    'sql'        => "int(10) unsigned NULL"
];
$dca['fields']['maxOrderSize'] = [
    'inputType'  => 'text',
    'eval'       => ['tl_class' => 'w50', 'rgxp' => 'digit'],
    'attributes' => ['legend' => 'inventory_legend'],
    'sql'        => "int(10) unsigned NULL",
];
