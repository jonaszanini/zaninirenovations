# CI: doc-only pull requests

Pull requests that change only Markdown under the repo root (`*.md`), `docs/`, or `lang/` skip the MySQL CI job. Any other path in the diff runs the money-path MySQL core suite (and path-filtered supplements when applicable).
