<?php

declare(strict_types=1);

use Contao\Rector\Set\ContaoLevelSetList;
use Contao\Rector\Set\ContaoSetList;
use Rector\Config\RectorConfig;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/contao',
        __DIR__ . '/src',
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
        ExplicitNullableParamTypeRector::class
    ])

    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_81,
        SymfonySetList::SYMFONY_54,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        # Erst mit Symfony 6 (Contao 5) nutzen:
        //SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        ContaoSetList::CONTAO_413,
        ContaoSetList::FQCN,
        ContaoLevelSetList::UP_TO_CONTAO_413,
        ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);