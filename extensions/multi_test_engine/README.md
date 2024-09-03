# Arcanist Multi Test Engine Extension

This extension allows you to run tests with multiple test engines. It is usefull when your project has code writen in more than one programming language, or when your project uses two different testing frameworks.

Below is an example of an `.arcconfig` that runs both Ruby tests - with the [`RSpecTestEngine`](https://github.com/tagview/arcanist-extensions#rspec_test_engine) - and Python tests - with Arcanist's native `PytestTestEngine`:

```json
{
  "project_id": "my-awesome-project",
  "conduit_uri": "https://example.org",

  "load": [
    "path/to/tagview/arcanist-extensions/rspec_test_engine",
    "/vendor/diablomedia/arcanist-extensions/extensions/multi_test_engine"
  ],

  "unit.engine": "MultiTestEngine",
  "unit.engine.multi-test.engines": ["RSpecTestEngine", "PytestTestEngine"]
}
```

You can also define some specific configuration for each engine. Below is an example that uses two [`TAPTestEngines`](https://github.com/tagview/arcanist-extensions#tap_test_engines) with different commands:

```json
{
  "project_id": "my-awesome-project",
  "conduit_uri": "https://example.org",

  "load": [
    "path/to/tagview/arcanist-extensions/rspec_test_engine",
    "path/to/tagview/arcanist-extensions/tap_test_engine",
    "vendor/diablomedia/arcanist-extensions/extensions/multi_test_engine"
  ],

  "unit.engine": "MultiTestEngine",
  "unit.engine.multi-test.engines": [
    "RSpecTestEngine",
    {
      "engine": "TAPTestEngine",
      "unit.engine.tap.command": "bundle exec teaspoon -f tap"
    },
    {
      "engine": "TAPTestEngine",
      "unit.engine.tap.command": "karma run spec/js/karma.conf"
    }
  ]
}
```

## Acknowledgements

Original repository for this extension: https://github.com/tagview/arcanist-extensions - brought into this repo to make installation via composer easier and for future maintenance.
