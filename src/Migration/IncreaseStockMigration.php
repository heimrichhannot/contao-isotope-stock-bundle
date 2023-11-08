<?php

namespace HeimrichHannot\IsotopeStockBundle\Migration;

use Contao\CoreBundle\Migration\MigrationInterface;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\Database;
use Contao\StringUtil;
use Isotope\Isotope;
use Isotope\Model\OrderStatus;

class IncreaseStockMigration implements MigrationInterface
{

    public function getName(): string
    {
        return 'Isotope Stock increase stock migration';
    }

    public function shouldRun(): bool
    {
        if (!Database::getInstance()->fieldExists('stock_increaseStock', 'tl_iso_orderstatus')) {
            return false;
        }

        if (!Database::getInstance()->fieldExists('stockIncreaseOrderStates', 'tl_iso_config')) {
            return false;
        }

        $config = Isotope::getConfig();

        return !empty(StringUtil::deserialize($config->stockIncreaseOrderStates, true));
    }

    public function run(): MigrationResult
    {
        $config = Isotope::getConfig();
        $states = StringUtil::deserialize($config->stockIncreaseOrderStates, true);

        foreach ($states as $id => $name) {
            $objOrderStatus = OrderStatus::findByPk($id);

            if ($objOrderStatus === null) {
                continue;
            }

            $objOrderStatus->stock_increaseStock = true;
            $objOrderStatus->save();
        }

        $config->stockIncreaseOrderStates = '';
        $config->save();

        return new MigrationResult(
            true,
            'Migrated stock increase order states to stock_increaseStock field in tl_iso_orderstatus.'
        );
    }
}