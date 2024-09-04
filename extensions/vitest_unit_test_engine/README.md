# Arcanist Vitest Unit Test Engine

Arcanist/Phabricator unit test engine for Vitest, supports both the V8 and Istanbul coverage reports that Vitest outputs.

## Installation

You'll need to make sure that you have Vitest installed and configured for your tests. Make sure that the `coverage.provider` config in your `.arcconfig` file matches the provider defined in your Vitest config.

## Config options

| Property                    | Description                                     | Default                             |
| --------------------------- | ----------------------------------------------- | ----------------------------------- |
| `include`                   | Paths that should be used for tests detection   | `""`                                |
| `bin`                       | Binary that should be used to run tests         | `node_modules/.bin/vitest --silent` |
| `forceAll`                  | Force all tests to run on each diff             | `false`                             |
| `coverage.provider`         | Provider used for coverage ('v8' or 'istanbul') | `v8`                                |
| `coverage.reportsDirectory` | Location coverage JSON is written to            | `coverage`                          |

### Sample .arcconfig

```json
{
  "project_id": "YourProjectName",
  "load": [
    "vendor/diablomedia/arcanist-extensions/extensions/vitest_unit_test_engine"
  ],
  "unit.engine": "VitestUnitTestEngine",
  "vitest": {
    "forceAll": true,
    "include": "/src/app/{components,containers,utils}",
    "coverage.provider": "istanbul"
  }
}
```

## Acknowledgements

This is based on the Jest library from this repository, which is forked from https://github.com/VISIT-X/arc-jest
