<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_product_collection'];

// serialized map: id of the product row holding the stock => reserved quantity (see StockReservation)
$dca['fields']['stock_reservation'] = [
    'sql' => 'blob NULL',
];
