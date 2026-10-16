# Oxlint Linter

Runs [Oxlint](https://oxc.rs/docs/guide/usage/linter.html) once per Arcanist batch, so JS plugins initialize once for the batch. JSON diagnostics retain their file, rule, severity and first label's line/column. Parser errors without a rule use `OXLINT`; file-wide diagnostics without labels use line/column 1. Invalid reports and failed commands stop linting.

Install with `npm install --save-dev oxlint`, `pnpm add --save-dev oxlint`, or your package manager's equivalent. The default binary is `<project-root>/node_modules/.bin/oxlint`.

Load this extension in `.arcconfig` (or load all extensions as described in the root README):

```json
{
  "load": ["vendor/diablomedia/arcanist-extensions/extensions/oxlint_linter"]
}
```

Configure `.arclint`:

```json
{
  "linters": {
    "oxlint": {
      "type": "oxlint",
      "include": "(\\.[cm]?[jt]sx?$)",
      "exclude": "(^node_modules/)",
      "flags": ["--config", ".oxlintrc.json"]
    }
  }
}
```

`flags` is optional; without it, Oxlint discovers configuration normally. Standard Arcanist `bin` and `flags` options are supported. For example, set `bin` to `apps/admin/node_modules/.bin/oxlint` in a monorepo, or `oxlint` for an executable on PATH. Commands run from the project root, with paths escaped individually after `--`.

The extension requires `--format=json` and supplies `--no-error-on-unmatched-pattern` for ignored or unsupported paths. Exit 0 may include warnings; exit 1 is accepted only with valid, nonempty diagnostics (errors or a warning limit). Other exits and malformed reports fail linting. Do not override the output format or use `--silent`, `--quiet`, or `--fix*` flags: Arcanist needs the diagnostics, and this extension does not offer Oxlint autofixes. Oxlint reads files on disk; run again after accepting formatting patches if needed.

Verified with Oxlint 1.88.0; its captured output is included in the tests. See the [CLI reference](https://oxc.rs/docs/guide/usage/linter/cli.html) for configuration flags.
