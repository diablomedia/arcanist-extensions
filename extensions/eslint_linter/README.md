# ESLint Batch Arcanist Linter

This library integrates [ESLint](https://eslint.org) as lint engine to `arcanist` and provides batch processing of files in the changeset.

## Installation

- Make sure `eslint` is installed in the project (or available globally).
- Make sure `.arcconfig` file contains following configurable default entries:
  - `"load": ["vendor/diablomedia/arcanist-extensions/extensions/eslint_linter/"]`
- Add the linter to your `.arclint` file with the following config (change values as appropriate for your project):

```json
"eslint": {
    "type": "eslint-batch",
    "include": [
        "(\\.(j|t)s(x)?$)"
    ],
    "eslint.config": "./eslint.config.mjs",
    "flags": [
        "--concurrency=auto",
        "--cache"
    ],
    "bin": "./node_modules/.bin/esling"
},
```

## Acknowledgements

This is based on the Pinterest ESLint Arcanist Linter from their [arcanist-linters](https://github.com/pinterest/arcanist-linters) project. It's been slightly modified to support calling ESLint in batches instead of one call per file. Coupled with ESLint 9.34.0's concurrency feature, this still has the benefit of processing multiple files at once but doesn't consume as many resources as calling ESLint once for every file in the changeset.