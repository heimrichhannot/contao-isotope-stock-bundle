<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_product'];

// These are plain DCA fields on purpose: Isotope 3 turns every tl_iso_product field that is not a
// system column into an attribute of its own (Isotope\CoreBundle\Attribute\AttributeRegistry), and
// the 'attributes' key is what offers them in the product type's attribute wizard. The explicit
// 'sql' definitions survive AbstractAttribute::updateDcaField(), which only fills in a missing one.
//
// They must stay nullable: an empty stock means "not managed", which is different from a stock of
// zero. MigrateFieldsSqlMigration drops any NOT NULL and default left over from older versions.
$dca['fields']['stock'] = [
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50', 'rgxp' => 'digit'],
    'attributes' => ['legend' => 'inventory_legend', 'fe_sorting' => true],
    'sql' => 'int(10) unsigned NULL',
];

$dca['fields']['initialStock'] = [
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50', 'rgxp' => 'digit'],
    'attributes' => ['legend' => 'inventory_legend'],
    'sql' => 'int(10) unsigned NULL',
];

$dca['fields']['maxOrderSize'] = [
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50', 'rgxp' => 'digit'],
    'attributes' => ['legend' => 'inventory_legend'],
    'sql' => 'int(10) unsigned NULL',
];
