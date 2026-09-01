<?php

declare(strict_types=1);

namespace HeimrichHannot\IsotopeStockBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\InitialStockAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeStockBundle\ProductAttribute\StockAttribute;
use Isotope\CoreBundle\Model\ProductModel;

/**
 * Makes the three stock columns nullable and default-less.
 *
 * Versions before 0.1.2 declared them NOT NULL with a default, which cannot express "stock is not
 * managed for this product" — a distinction the whole bundle rests on.
 *
 * Tagged with an explicit priority in config/services.yaml, see the comment there.
 */
class MigrateFieldsSqlMigration extends AbstractMigration
{
    private const array FIELDS = [
        StockAttribute::NAME,
        InitialStockAttribute::NAME,
        MaxOrderSizeAttribute::NAME,
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function getName(): string
    {
        return 'Isotope Stock: make stock columns nullable';
    }

    public function shouldRun(): bool
    {
        return [] !== $this->getConstrainedFields() || [] !== $this->getFieldsWithEmptyValues();
    }

    public function run(): MigrationResult
    {
        $table = ProductModel::getTable();

        foreach ($this->getConstrainedFields() as $field) {
            $this->connection->executeStatement(
                \sprintf('ALTER TABLE %s MODIFY `%s` int(10) unsigned NULL DEFAULT NULL', $table, $field),
            );
        }

        foreach ($this->getFieldsWithEmptyValues() as $field) {
            $this->connection->executeStatement(
                \sprintf("UPDATE %s SET `%s` = NULL WHERE `%s` = '' AND `%s` != '0'", $table, $field, $field, $field),
            );
        }

        return $this->createResult(true, 'Made the stock, initialStock and maxOrderSize columns nullable.');
    }

    /**
     * Columns that are still NOT NULL or carry a default.
     *
     * @return list<string>
     *
     * @throws SchemaException
     */
    private function getConstrainedFields(): array
    {
        $table = $this->getTable();

        if (!$table) {
            return [];
        }

        $constrained = [];

        foreach (self::FIELDS as $field) {
            if (!$table->hasColumn($field)) {
                continue;
            }

            $column = $table->getColumn($field);

            if ($column->getNotnull() || null !== $column->getDefault()) {
                $constrained[] = $field;
            }
        }

        return $constrained;
    }

    /**
     * Columns holding an empty string where NULL is meant.
     *
     * @return list<string>
     *
     * @throws Exception
     */
    private function getFieldsWithEmptyValues(): array
    {
        $table = $this->getTable();

        if (!$table) {
            return [];
        }

        $tableName = ProductModel::getTable();
        $fields = [];

        foreach (self::FIELDS as $field) {
            if (!$table->hasColumn($field)) {
                continue;
            }

            $count = $this->connection->fetchOne(
                \sprintf("SELECT COUNT(*) FROM %s WHERE `%s` = '' AND `%s` != '0'", $tableName, $field, $field),
            );

            if ($count > 0) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function getTable(): ?Table
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([ProductModel::getTable()])) {
            return null;
        }

        return $schemaManager->introspectTableByUnquotedName(ProductModel::getTable());
    }
}
