<?php

use HeimrichHannot\IsotopeStockBundle\Controller\ContentElement\StockReportController;

/*
 * Content element (Isotope 3 has no frontend modules, so this is TL_LANG.CTE, not TL_LANG.FMD)
 */
$GLOBALS['TL_LANG']['CTE'][StockReportController::TYPE] = [
    'Bericht - Bestandsauswertung',
    'Zeigt noch vorhandene Bestände an',
];

/*
 * Messages
 */
$lang = &$GLOBALS['TL_LANG']['MSC'];

$lang['stockEmpty'] = 'Der Artikel "%s" ist nicht mehr verfügbar.';
$lang['stockExceeded'] = 'Vom Artikel "%s" sind nur noch %d Stück verfügbar. Bitte bedenken Sie, dass es manche Artikel nur im Set gibt.';
$lang['maxOrderSizeExceeded'] = 'Sie können den Artikel "%s" pro Bestellung nur maximal %d-mal anfordern.';
