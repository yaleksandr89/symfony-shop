<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__.'/var/php-cs-fixer.cache')
    ->setRules([
        '@Symfony' => true,
        'no_superfluous_phpdoc_tags' => true,
        'global_namespace_import' => false,
        'phpdoc_separation' => false,
    ])
    ->setFinder($finder);
