<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Moves the stock-increasing order states off the shop configuration and onto the statuses.
 *
 * The predecessor extensions kept a tl_iso_config.stockIncreaseOrderStates list; this bundle marks
 * the statuses themselves. Purely a one-shot for installations coming from those extensions, kept
 * only so they can still upgrade.
 *
 * Deliberately plain SQL: Isotope\Isotope::getConfig() no longer exists in Isotope 3, and neither
 * column is part of any current schema, so no model can describe them.
 */
class IncreaseStockMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function getName(): string
    {
        return 'Isotope Stock: move stock-increasing order states to tl_iso_orderstatus';
    }

    public function shouldRun(): bool
    {
        return [] !== $this->getStatusIds();
    }

    public function run(): MigrationResult
    {
        $ids = $this->getStatusIds();

        $this->connection->executeStatement(
            'UPDATE tl_iso_orderstatus SET stock_increaseStock = 1 WHERE id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        $this->connection->executeStatement("UPDATE tl_iso_config SET stockIncreaseOrderStates = ''");

        return $this->createResult(true, \sprintf(
            'Marked %d order status(es) as stock-increasing.',
            \count($ids),
        ));
    }

    /**
     * The order status ids still listed in any shop configuration.
     *
     * @return list<int>
     */
    private function getStatusIds(): array
    {
        if (
            !$this->hasColumn('tl_iso_orderstatus', 'stock_increaseStock')
            || !$this->hasColumn('tl_iso_config', 'stockIncreaseOrderStates')
        ) {
            return [];
        }

        $ids = [];

        foreach ($this->connection->fetchFirstColumn('SELECT stockIncreaseOrderStates FROM tl_iso_config') as $value) {
            foreach (array_keys(StringUtil::deserialize($value, true)) as $id) {
                $ids[] = (int) $id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ([] === $ids) {
            return [];
        }

        // Nothing to do if every one of them is already marked
        return $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_iso_orderstatus WHERE id IN (?) AND stock_increaseStock != 1',
            [$ids],
            [ArrayParameterType::INTEGER],
        );
    }

    private function hasColumn(string $table, string $column): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$table])) {
            return false;
        }

        return $schemaManager->introspectTableByUnquotedName($table)->hasColumn($column);
    }
}
