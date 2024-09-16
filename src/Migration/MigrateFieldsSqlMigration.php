<?php

namespace HeimrichHannot\IsotopeStockBundle\Migration;

use Contao\CoreBundle\Migration\MigrationInterface;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\Database;
use Doctrine\DBAL\Connection;

class MigrateFieldsSqlMigration implements MigrationInterface
{
    public function __construct(
        private Connection $connection,
    )
    {
    }

    public function getName(): string
    {
        return 'Isotope Stock migrate fields sql migration';
    }

    public function shouldRun(): bool
    {
        if (!Database::getInstance()->fieldExists('stock', 'tl_iso_product')
            || !Database::getInstance()->fieldExists('initialStock', 'tl_iso_product')
            || !Database::getInstance()->fieldExists('maxOrderSize', 'tl_iso_product')
        ) {
            return false;
        }

        return $this->migrateFields(false);
    }

    public function run(): MigrationResult
    {
        $result = $this->migrateFields(true);
        return new MigrationResult($result, 'Migrated fields stock and initialStock.');
    }

    private function migrateFields(bool $execute = false): bool
    {
        $fields = ['stock', 'initialStock', 'maxOrderSize'];

        $schemaManager = $this->connection->createSchemaManager();
        $schema        = $schemaManager->introspectSchema();

        foreach ($fields as $fieldName) {
            $column = $schema->getTable('tl_iso_product')->getColumn($fieldName);
            if ($column->getNotnull()) {
                if (!$execute) {
                    return true;
                }
                $column->setNotnull(false);
            }
            if ($column->getDefault() !== null) {
                if (!$execute) {
                    return true;
                }
                $column->setDefault(null);
            }
        }

        if ($execute) {
            $comparator = $schemaManager->createComparator();
            $schemaDiff = $comparator->compareSchemas($schemaManager->introspectSchema(), $schema);

            $queries = $this->connection->getDatabasePlatform()->getAlterSchemaSQL($schemaDiff);

            foreach ($queries as $query) {
                $this->connection->executeQuery($query);
            }
        }

        foreach ($fields as $fieldName) {
            $result = $this->connection->executeQuery("SELECT id FROM `tl_iso_product` WHERE ".$fieldName."='' AND ".$fieldName." != '0'");
            if ($result->rowCount() < 1) {
                continue;
            }
            if ($execute) {
                $this->connection->executeQuery("UPDATE `tl_iso_product` SET ".$fieldName."=NULL WHERE ".$fieldName."='' AND ".$fieldName." != '0'");
            } else {
                return true;
            }
        }

        return $execute ? true : false;
    }
}