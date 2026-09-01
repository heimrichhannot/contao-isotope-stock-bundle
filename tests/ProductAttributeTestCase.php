<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Tests;

use Isotope\CoreBundle\Model\ProductTypeModel;
use Isotope\CoreBundle\Product\CoreProduct;
use Isotope\CoreBundle\Product\Render\ProductRendererInterface;
use PHPUnit\Framework\TestCase;

abstract class ProductAttributeTestCase extends TestCase
{
    /**
     * A product whose type enables the given attributes.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $enabledAttributes
     */
    protected function createProduct(array $data, array $enabledAttributes): CoreProduct
    {
        $type = $this->createMock(ProductTypeModel::class);
        $type
            ->method('getAttributes')
            ->willReturn($enabledAttributes)
        ;

        return new CoreProduct(
            [
                'id' => 1,
                'name' => 'Test product',
                ...$data,
            ],
            $type,
            $this->createMock(ProductRendererInterface::class),
        );
    }
}
