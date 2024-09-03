# PHPStan Arcanist Extension

Use [phpstan](https://github.com/phpstan/phpstan) to lint your PHP source code with [Phabricator](http://phabricator.org)'s `arc` command line tool.

## Features

phpstan generates warning messages.

Example output:

```
>>> Lint for src/AppBundle/Foo.php:


Error  () phpstan violation
Error: AppBundle\Foo::__construct() does not
call parent constructor from AppBundle\Bar.

          33      * constructor
          34      */
>>>       35     public function __construct()
          36     {
          37         Bar::__construct();
          38         $this->property = 0;
```

## Installation

phpstan is required. You can follow the [official instructions](https://github.com/phpstan/phpstan#installation) to install and put it on your $PATH, or you can run composer `install` and point the `bin` option to `vendor/bin/phpstan`, as in the example below.

### Project-specific installation

If you've installed the `diablomedia/arcanist-extensions` package with composer, can just add a line to your `.arcconfig` to load the extension.

```json
{
  "load": ["vendor/diablomedia/arcanist-extensions/extensions/phpstan_linter"]
}
```

Otherwise, just change the path to where you cloned this repository (or location of the sub-module, etc...)

## Setup

To use the linter you must register it in your `.arclint` file, as in this example

```json
{
  "linters": {
    "phpstan": {
      "type": "phpstan",
      "include": "(\\.php$)" /* optional, if arc chooses to lint any files, phpstan run will be triggered */,
      "config": "var/build/phpstan.neon" /* optional */,
      "bin": "vendor/bin/phpstan" /* optional */,
      "level": "0" /* optional */,
      "paths": "./" /* optional */
    }
  }
}
```

## Acknowledgements

Based on [material-foundation/arc-tslint](https://github.com/material-foundation/arc-tslint) and some improvements made in [sascha-egerer/arc-phpstan](https://github.com/sascha-egerer/arc-phpstan). Provides basic support for `arc lint` to execute `phpstan`. [Forked](https://github.com/diablomedia/arc-phpstan/tree/run-once) from [appsinet/arc-phpstan](https://github.com/appsinet/arc-phpstan) to change the execution method to run phpstan against all source files, otherwise dependencies could be missed if it just runs on the modified files.
