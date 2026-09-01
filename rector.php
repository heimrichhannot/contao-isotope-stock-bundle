<?php

declare(strict_types=1);

use Contao\Rector\Set\ContaoSetList;
use Rector\Config\RectorConfig;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

/*
 * Note on the missing Contao level set: every ContaoLevelSetList entry from UP_TO_CONTAO_53 upwards
 * pulls in versioned Symfony sets (SymfonySetList::SYMFONY_64, ::SYMFONY_70, …) that rector-symfony 2
 * no longer defines, so contao/contao-rector dev-main cannot be loaded through them. The concrete
 * Contao sets below work, and the Symfony side is covered by the code-quality and attribute sets.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/contao',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
        ExplicitNullableParamTypeRector::class,
    ])

    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        ContaoSetList::CONTAO_50,
        ContaoSetList::CONTAO_51,
        ContaoSetList::CONTAO_53,
        ContaoSetList::FQCN,
        ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);
