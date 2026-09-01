<?php

declare(strict_types=1);

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeStockBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Doctrine\DBAL\Connection;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Isotope\CoreBundle\Model\ProductModel;
use Isotope\CoreBundle\Model\ProductTypeModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lists the remaining stock of every published product, grouped by product type.
 *
 * Isotope 2 shipped this as a frontend module; Isotope 3 has content elements only.
 *
 * The report needs nothing but a name and two numbers per product, so it reads tl_iso_product
 * directly instead of going through the product factory — that also keeps it out of the way of
 * variant resolution and price calculation, neither of which it has any use for.
 */
#[AsContentElement(StockReportController::TYPE, category: 'isotope')]
class StockReportController extends AbstractContentElementController
{
    public const TYPE = 'iso_stockreport';

    public function __construct(
        private readonly Connection $connection,
    ) {}

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $template->items = $this->getCategories();

        return $template->getResponse();
    }

    /**
     * @return list<array{title: string, products: list<array{name: string, stock: int, initialStock: int|null, stockPercent: int|null}>}>
     */
    private function getCategories(): array
    {
        $categories = [];

        foreach ($this->getStockManagedTypes() as $type) {
            $products = $this->getProducts($type);

            if ([] === $products) {
                continue;
            }

            $categories[] = [
                'title' => $type->name,
                'products' => $products,
            ];
        }

        return $categories;
    }

    /**
     * @return list<ProductTypeModel>
     */
    private function getStockManagedTypes(): array
    {
        if (!$typeModels = ProductTypeModel::findAll(['order' => 'name ASC'])) {
            return [];
        }

        if (!\is_iterable($typeModels)) {
            return [];
        }

        $types = [];

        foreach ($typeModels as $type) {
            if (\in_array(StockAttribute::getName(), $type->getAttributes(), true)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @return list<array{name: string, stock: int, initialStock: int|null, stockPercent: int|null}>
     */
    private function getProducts(ProductTypeModel $type): array
    {
        $hasInitialStock = \in_array(InitialStockAttribute::getName(), $type->getAttributes(), true);

        $qTable = $this->connection->quoteIdentifier(ProductModel::getTable());

        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
               SELECT `name`, `stock`, `initialStock`
                 FROM $qTable
                WHERE `type` = ?
                  AND `published` = '1'
                  AND `stock` IS NOT NULL
                ORDER BY `name` ASC
            SQL,
            [$type->id],
        );

        $products = [];

        foreach ($rows as $row) {
            $stock = (int) $row['stock'];
            $initialStock = $hasInitialStock && null !== $row['initialStock'] ? (int) $row['initialStock'] : null;

            $products[] = [
                'name' => (string) $row['name'],
                'stock' => $stock,
                'initialStock' => $initialStock,
                'stockPercent' => $initialStock > 0 ? (int) floor($stock * 100 / $initialStock) : null,
            ];
        }

        return $products;
    }
}
