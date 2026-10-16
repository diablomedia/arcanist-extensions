These reports are captured stdout from Oxlint 1.88.0, without rewriting the JSON.

Inputs (run from the directory containing these files):

- `first file.js`: `const unused = 1;\ndebugger;\n`
- `second.js`: `if (true) {}\n`
- `syntax.js`: `const = ;\n`

Commands (all include `--format=json --no-error-on-unmatched-pattern`):

- `diagnostics.json`: `-D no-debugger -W no-unused-vars -- "first file.js" second.js` (exit 1)
- `syntax.json`: `-- syntax.js` (exit 1, no rule code)
- `clean.json`: `-A all -- second.js` (exit 0)

The timing and thread-count fields are incidental. Tests do not depend on diagnostic ordering.
