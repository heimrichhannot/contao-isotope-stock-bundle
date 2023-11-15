<?php

use HeimrichHannot\IsotopeStockBundle\Controller\FrontendModule\StockReportModuleController;

$dca = &$GLOBALS['TL_DCA']['tl_module'];

$dca['palettes'][StockReportModuleController::TYPE] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';