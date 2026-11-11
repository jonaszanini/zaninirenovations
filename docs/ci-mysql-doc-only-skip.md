# CI: doc-only pull requests

Pull requests that change only Markdown under the repo root (`*.md`), `docs/`, or `lang/` skip the heavy Tests jobs (`unit_js` and `mysql`). Any other path in the diff runs the matching subsets (PHP unit, money-path MySQL core, JS, browser) described in [docs/ci.md](ci.md).

The full suite (every `tests/ci` list, including extended MySQL such as `RoomAreaOpenPlan3DQuotationTest`) runs once before merge — see [docs/ci.md](ci.md#full-suite-before-merge-full-ci-workflow).
