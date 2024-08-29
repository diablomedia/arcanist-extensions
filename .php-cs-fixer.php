<?php

use Ergebnis\PhpCsFixer\Config;
use DiabloMedia\PhpCsFixer\Config\RuleSet\Php74;

$config = Config\Factory::fromRuleSet(
    new Php74(),
    [
        'visibility_required' => ['elements' => ['method', 'property']],  // `const` is omitted
        'list_syntax' => ['syntax' => 'long'],
        'use_arrow_functions' => false,
        'phpdoc_to_property_type' => false,
    ]
);

$config->setCacheFile(__DIR__ . '/.php_cs.cache');
$config->getFinder()
    ->exclude('vendor')
    ->files()
    ->name('*.php')
    ->notName('__phutil_library_init__.php')
    ->notName('__phutil_library_map__.php')
    ->in(__DIR__)
;

return $config;
