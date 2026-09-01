<?php

use HeimrichHannot\IsotopeStockBundle\Controller\ContentElement\StockReportController;

$GLOBALS['TL_DCA']['tl_content']['palettes'][StockReportController::TYPE]
    = '{type_legend},title,headline,type;{template_legend:collapsed},customTpl;{protected_legend:collapsed},protected;{expert_legend:collapsed},cssID;{invisible_legend:collapsed},invisible,start,stop';
