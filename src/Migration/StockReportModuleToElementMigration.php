<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Migration;

use HeimrichHannot\IsotopeStockBundle\Controller\ContentElement\StockReportController;
use Isotope\CoreBundle\Migration\AbstractModuleToElementMigration;

/**
 * Turns existing iso_stockreport frontend modules into content elements.
 *
 * Isotope 3 has no frontend modules, so the report moved to tl_content. Isotope's base class does
 * the work: it copies the row, pushes unknown columns into jsonData, rewrites tl_content rows of
 * type "module" that pointed at the module into aliases, fixes up tl_layout.modules and drops the
 * tl_module row.
 *
 * Tagged with an explicit priority in config/services.yaml so it runs after Isotope's own
 * AbstractModuleToElementMigration, see the comment there.
 */
class StockReportModuleToElementMigration extends AbstractModuleToElementMigration
{
    public function getName(): string
    {
        return 'Isotope Stock: stock report frontend module to content element';
    }

    /**
     * @return array<string, string>
     */
    protected function getMapping(): array
    {
        return [
            StockReportController::TYPE => StockReportController::TYPE,
        ];
    }
}
