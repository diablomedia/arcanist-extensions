# Arcanist Jest Unit Test Engine

Arcanist/Phabricator unit test engine for Jest.

## Installation

You'll need to make sure that you have Jest installed and configured for your tests.

## Config options

| Property   | Description                                   | Default                   | Since version |
| ---------- | --------------------------------------------- | ------------------------- | ------------- |
| `include`  | Paths that should be used for tests detection | `""`                      | `0.0.1`       |
| `bin`      | Binary that should be used to run tests       | `/node_modules/.bin/jest` | `0.0.2`       |
| `forceAll` | Force all tests to run on each diff           | `false`                   | `0.0.5`       |

### Sample .arcconfig

```json
{
  "project_id": "YourProjectName",
  "load": [
    "vendor/diablomedia/arcanist-extensions/extensions/jest_unit_test_engine"
  ],
  "unit.engine": "JestUnitTestEngine",
  "jest": {
    "forceAll": true,
    "bin": "npm run jest",
    "include": "/src/app/{components,containers,utils}"
  }
}
```

## Acknowledgements

Original repository: https://github.com/VISIT-X/arc-jest - brought into this repository to make it easier to install via composer and to continue maintenance.
