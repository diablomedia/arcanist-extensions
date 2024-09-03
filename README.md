# Arcanist Extensions

Extensions for [Arcanist](https://github.com/phacility/arcanist) (should also work with any forks of Arcanist, like the one bundled with [Phorge](https://github.com/phorgeit/arcanist))

## Installation

The recommended way to install these extensions is with composer. There are very few dependencies (only one, for the php-cs-fixer linter), so there shouldn't be many dependency conflicts.

`composer require diablomedia/arcanist-extensions --dev`

If composer installation is not possible, you can clone this repo somewhere in your system and point your `.arcconfig` to the path where this is installed. You can also install it in the same location as your `arcanist` and `libphutil` directories, and arcanist should find the extensions there.

## Configuration

To enable all of the extensions in this repository, you just need to add one line to your `.arcconfig` file's "load" section:

```json
{
  "load": ["vendor/diablomedia/arcanist-extensions/extensions/"]
}
```

If you don't want to enable all of the extensions in your config (technically they're not used until you configure them in your `.arclint` (for linters) or `.arcconfig` (for unit test engines)) you can load each extension individually, for example:

```json
{
  "load": [
    "vendor/diablomedia/arcanist-extensions/extensions/composer_validate_linter",
    "vendor/diablomedia/arcanist-extensions/extensions/phpstan_linter"
  ]
}
```

To configure the extension to run in your arcanist project, please reference each extension's README file (linked below).

## Extensions Included

- Linters
  - [Composer Validate](extensions/composer_validate_linter/README.md) - Validate composer.json and composer.lock files (using `composer --validate` command)
  - [PHP CS Fixer](extensions/php_cs_fixer_linter/README.md) - Runs [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
  - [PHPStan](extensions/phpstan_linter/README.md) - Runs [PHPStan](https://github.com/phpstan/phpstan)
- Unit Test Engines
  - [Jest](extensions/jest_unit_test_engine/README.md) - Runs [Jest](https://github.com/jestjs/jest) (for Javascript tests) and processes coverage report
  - [Multi Test Engine](extensions/multi_test_engine/README.md) - Allows configuration of multiple unit test engines (useful for repositories that contain tests for different languages, i.e. PHP and JS for a repo that contains server-side (PHP) and client side (Javascript) tests)

## Acknowledgements

Some of these linters come from forks of other linters found on github that are generally not maintained anymore (or we forked due to some difference in functionality that worked better for us). Check out the README for each linter, which will link back to the original project.
