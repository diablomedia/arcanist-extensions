# PHPCS Fixer Arcanist Extension

This library integrates [PHP CS Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) as lint engine to `arcanist`.
It allows developer to automatically run `php-cs-fixer` on `arc diff`.

## Installation

- Make sure `.php-cs-fixer.php` file is in project directory.
- Make sure `.arcconfig` file contains following configurable default entries:
  - `"load": ["vendor/diablomedia/arcanist-extensions/extensions/php_cs_fixer_linter/"]`
- Add the linter to your `.arclint` file with the following config (change values as appropriate for your project):

```json
"php-cs-fixer": {
    "type": "php-cs-fixer",
    "include": [
        "(\\.php$)",
    ],
    "config": ".php-cs-fixer.php",
    "flags": [
        "--path-mode=intersection"
    ],
    "bin": "vendor/bin/php-cs-fixer"
},
```

## Example output

In case `php-cs-fixer` found no problems:

```
$ arc lint
 OKAY  No lint warnings.
```

If `php-cs-fixer` reports errors, arcanist `diff` will be displayed:

````
$ arc lint

>>> Lint for src/Acme/Bundle/AcmeBundle/Controller/DefaultController.php:


   Warning  (PHP_CS_FIXER) pre_increment, phpdoc_separation, phpdoc_align
    Please consider applying these changes:
    ```
    - * @param array $fixData
    + * @param array  $fixData
    + *
    ```

               4 {
               5     /**
               6      * @param string $path
    >>>        7      * @param array $fixData
               8      * @return \ArcanistLintMessage[]
               9      */
              10     public function buildLintMessages($path, array $fixData)

   Warning  (PHP_CS_FIXER) pre_increment, phpdoc_separation, phpdoc_align
    Please consider applying these changes:
    ```
    - for ($i = 0; $i < count($rows); $i++) {
    + for ($i = 0; $i < count($rows); ++$i) {
    ```

              13         $rows = array_map('trim', file($path));
              14
              15         $messages = [];
    >>>       16         for ($i = 0; $i < count($rows); $i++) {
              17             foreach ($diffParts as $diffPart) {
              18                 if (isset($diffPart['informational'])) {
              19                     $matchedInformational = 0;

````

If `Excuse` message will be provided, these messages will be sent to `Phabricator`.

## Acknowledgements

Original repository for this extension: https://github.com/paysera/lib-arcanist-php-cs-extension - Originally [forked](https://github.com/diablomedia/lib-arcanist-php-cs-extension/tree/multi-lint) to add support to work with Arcanist's `.arclint` files and to be sure it works with more recent versions of PHPCS Fixer.
