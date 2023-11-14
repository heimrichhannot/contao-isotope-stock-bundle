<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeStockBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Database;
use Contao\ModuleModel;
use Contao\System;
use Contao\Template;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule(IsoStockReportModuleController::TYPE, category="isotope")
 */
class IsoStockReportModuleController extends AbstractFrontendModuleController
{
    public const TYPE = 'iso_stockreport';

    public function __construct(
        protected ContaoFramework $framework,
        protected Utils           $utils
    ) {}

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $products = [];

        $query = 'SELECT p.*, t.name as type FROM tl_iso_product p INNER JOIN tl_iso_producttype t ON t.id = p.type WHERE p.published=1 AND p.shipping_exempt="" AND p.initialStock!="" AND stock IS NOT NULL';

        $result = Database::getInstance()->prepare($query)->execute();

        $this->framework->getAdapter(System::class)->loadLanguageFile('tl_reports');

        if ($result->numRows < 1) {
            return new Response('');
        }

        while ($result->next()) {
            $product = $this->utils->model()->findModelInstanceByIdOrAlias('tl_iso_product', $result->id);
            if (!$product || empty($product->initialStock)) {
                continue;
            }

            $category = 'category_' . $product->type;

            if (!isset($products[$category])) {
                $products[$category]['type'] = 'category';
                $products[$category]['title'] = $result->type;
            }

            $productData = $product->row();
            $productData['stock'] = $product->stock;
            $productData['initialStock'] = $product->initialStock;

            $percent = floor($product->stock * 100 / $product->initialStock);
            $productData['stockPercent'] = $percent;

            $products[$category]['products'][$product->id] = $productData;
        }

        $template->items = $products;
        $template->id = 'stockReport';

        return $template->getResponse();
    }
}
