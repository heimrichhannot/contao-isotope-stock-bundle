<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeStockBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\Template;
use Doctrine\DBAL\Connection;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Isotope\Model\Product;
use Isotope\Model\ProductType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(StockReportModuleController::TYPE, category: 'isotope')]
class StockReportModuleController extends AbstractFrontendModuleController
{
    public const TYPE = 'iso_stockreport';

    public function __construct(
        protected ContaoFramework $framework,
        protected Utils $utils,
        protected Connection $connection,
        protected InitialStockAttribute $initialStockAttribute,
        protected StockAttribute $stockAttribute,
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $where = $this->utils->database()->createWhereForSerializedBlob('attributes', [StockAttribute::getName()]);
        /** @var Collection|ProductType[] $types */
        $types = ProductType::findBy([$where->createOrWhere()], $where->values, [
            'order' => 'name ASC',
        ]);

        $products = [];
        if ($types) {
            $products = $this->generateProductList($types);
        }

        $template->items = $products;
        $template->id = 'stockReport';

        return $template->getResponse();
    }

    private function generateProductList(Collection $types): array
    {
        Controller::loadDataContainer('tl_iso_product');
        $products = [];

        /** @var ProductType $type */
        foreach ($types as $type) {
            if (!in_array(StockAttribute::getName(), $type->getAttributes())) {
                continue;
            }

            $result = $this->connection->executeQuery(
                "SELECT id FROM tl_iso_product WHERE type=? AND published='1' ORDER BY name ASC",
                [$type->id]
            );

            if ($result->rowCount() < 1) {
                continue;
            }

            $category = 'category_' . $type->id;
            $products[$category]['title'] = $type->name;
            $products[$category]['type'] = 'category';

            while ($id = $result->fetchOne()) {
                /** @var Product $product */
                $product = $this->utils->model()->findModelInstanceByIdOrAlias('tl_iso_product', $id);
                if (!$product || !$this->stockAttribute->isUsed($product)) {
                    continue;
                }

                //                $productData = $product->row();

                if ($this->initialStockAttribute->isUsed($product) && $product->initialStock > 0) {
                    $product->stockPercent = floor($product->stock * 100 / $product->initialStock);

                    //                    $productData['stockPercent'] = floor($product->stock * 100 / $product->initialStock);
                }

                $products[$category]['products'][$product->id] = $product;
            }
        }

        return $products;
    }
}
