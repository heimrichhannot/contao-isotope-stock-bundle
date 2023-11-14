<?php

use HeimrichHannot\IsotopeStockBundle\Controller\FrontendModule\IsoStockReportModuleController;

$dca = &$GLOBALS['TL_DCA']['tl_module'];

$dca['palettes'][IsoStockReportModuleController::TYPE] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';