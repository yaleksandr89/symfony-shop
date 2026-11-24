<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = new Finder()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/migrations',
        __DIR__ . '/tools/demo',
        __DIR__ . '/fixtures/demo',
    ])
    ->append([
        __DIR__ . '/config/bundles.php',
        __DIR__ . '/config/preload.php',
        __DIR__ . '/public/index.php',
        __DIR__ . '/bin/console',
        __FILE__,
    ]);

return new Config()
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => null,
            'import_functions' => null,
        ],
        'new_expression_parentheses' => ['use_parentheses' => false],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_line_empty_body' => true,
    ])
    ->setCacheFile(__DIR__ . '/var/php-cs-fixer.cache')
    ->setFinder($finder);
