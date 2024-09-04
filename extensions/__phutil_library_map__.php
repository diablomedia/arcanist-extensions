<?php

/**
 * This file is automatically generated. Use 'arc liberate' to rebuild it.
 *
 * @generated
 * @phutil-library-version 2
 */
phutil_register_library_map(array(
  '__library_version__' => 2,
  'class' => array(
    'ComposerValidateLinter' => 'composer_validate_linter/src/ComposerLinter.php',
    'JestUnitTestEngine' => 'jest_unit_test_engine/src/JestUnitTestEngine.php',
    'LintMessageBuilder' => 'php_cs_fixer_linter/src/Linter/LintMessageBuilder.php',
    'MultiTestEngine' => 'multi_test_engine/src/MultiTestEngine.php',
    'PhpCsFixerLinter' => 'php_cs_fixer_linter/src/Linter/PhpCsFixerLinter.php',
    'PhpstanLinter' => 'phpstan_linter/src/PhpstanLinter.php',
    'VitestUnitTestEngine' => 'vitest_unit_test_engine/src/VitestUnitTestEngine.php',
  ),
  'function' => array(),
  'xmap' => array(
    'ComposerValidateLinter' => 'ArcanistExternalLinter',
    'JestUnitTestEngine' => 'ArcanistUnitTestEngine',
    'MultiTestEngine' => 'ArcanistUnitTestEngine',
    'PhpCsFixerLinter' => 'ArcanistExternalLinter',
    'PhpstanLinter' => 'ArcanistLinter',
    'VitestUnitTestEngine' => 'ArcanistUnitTestEngine',
  ),
));
