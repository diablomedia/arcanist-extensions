<?php

use Ergebnis\PhpCsFixer\Config;
use DiabloMedia\PhpCsFixer\Config\RuleSet\Php80;

$config = Config\Factory::fromRuleSet(new Php80());

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
