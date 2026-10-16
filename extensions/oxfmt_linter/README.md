# Oxfmt Linter

Runs [Oxfmt](https://oxc.rs/docs/guide/usage/formatter.html) on Arcanist's in-memory file contents and offers whole-file formatting patches. It does not write files directly. Unchanged files produce no messages; command failures stop linting without proposing a replacement.

Install Oxfmt with `npm install --save-dev oxfmt`, `pnpm add --save-dev oxfmt`, or your package manager's equivalent. The default binary is `<project-root>/node_modules/.bin/oxfmt`.

Load this extension in `.arcconfig` (or load all extensions as described in the root README):

```json
{
  "load": ["vendor/diablomedia/arcanist-extensions/extensions/oxfmt_linter"]
}
```

Configure `.arclint`:

```json
{
  "linters": {
    "oxfmt": {
      "type": "oxfmt",
      "include": "(\\.(?:[cm]?[jt]sx?|json|css|md|yaml|yml)$)",
      "exclude": "(^node_modules/)",
      "flags": ["--config", ".oxfmtrc.json"]
    }
  }
}
```

`flags` is optional; without it, Oxfmt discovers configuration normally. Standard Arcanist `bin` and `flags` options are supported. For example, set `bin` to `apps/admin/node_modules/.bin/oxfmt` in a monorepo, or `oxfmt` for an executable on PATH. Commands run from the project root. Each file is passed using `--stdin-filepath` so Oxfmt can select its parser and configuration.

Use `.arclint` include/exclude patterns to select files: stdin mode formats explicitly supplied contents, including files that directory traversal would normally ignore. Only include file types supported by your Oxfmt version. Avoid output/mode flags such as `--check`, `--list-different`, `--write`, or `--init`; the extension requires formatted source on stdout. Run `arc lint` and accept its patches to apply formatting.

Verified with Oxfmt 0.73.0. See the [CLI reference](https://oxc.rs/docs/guide/usage/formatter/cli.html) for configuration flags.
