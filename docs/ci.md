# CI

GitHub Actions minutes are billed per job. This repo splits **cheap PR-push coverage** from a **single full suite before merge**, then keeps a full Tests run on `main` so deploy can pin that SHA.

## PR pushes (Tests workflow)

Each `synchronize` / open of a non-draft PR:

1. The `changes` job classifies the diff (`tests/ci/classify-ci-changes.sh` + `dorny/paths-filter` + `tests/ci/compute-ci-flags.sh`).
2. Only the matching subsets run:
   - PHP unit (`tests/ci/unit-paths.txt`) when PHP/app/views/tests change
   - JS unit (`tests/ci/js-unit-paths.txt`) when JS/CSS/scripts change (or any browser domain matches)
   - MySQL core plus the matching `tests/ci/database-filter-*.txt` lists
   - Browser screenshot jobs (`tests/ci/js-browser-*.txt`) per domain
3. Markdown / `docs/` / `lang/` only: `changes` runs, `unit_js` and `mysql` are skipped.
4. Concurrency group `tests-<workflow>-<ref>-scoped` **cancels in-progress** runs for the same PR when a newer push lands (`main` is not cancelled).
5. Composer (setup-php cache), npm (`actions/setup-node` cache), and Puppeteer (`actions/cache` on `~/.cache/puppeteer`) are enabled on the jobs that install those tools.

Unknown paths fail **closed** (run the heavy jobs) rather than skipping tests.

## Full suite before merge (Full CI workflow)

Main-only MySQL lists (`tests/ci/database-extended.txt`) are **not** run on every PR push. They run:

- once on the PR **head SHA** via **Full CI**, and
- again on `main` after merge (deploy gate; see below).

Every path listed in any `tests/ci/database-*.txt`, `unit-paths.txt`, `js-unit-paths.txt`, or `js-browser-*.txt` file is included in the full suite (`CI_MYSQL_SUITE=full` unions core + extended + every filter list).

### How to trigger

Pick **one** after the last push you intend to merge:

1. **Label** the PR `full-ci` (remove and re-add the label to re-run on a new SHA), or
2. **Actions → Full CI → Run workflow**, set **pr_number** to this PR. Use a ref that contains `.github/workflows/full-ci.yml` (the PR branch before it lands, or `main` after).

Full CI:

- Checks out the PR **head SHA** (not the merge commit).
- Runs Tests as a reusable workflow with `full_suite: true`.
- Publishes a check named **`full-ci`** on that exact SHA (`in_progress`, then `success` / `failure`).

Require **`full-ci`** in branch protection if merges must wait for the full suite. A green scoped **Tests** run is not a substitute.

If you push after Full CI, the new head has no `full-ci` check until you trigger it again.

Optional: **Actions → Tests → Run workflow** with `full: true` on a branch also runs the full lists, but that reports as **Tests**, not the `full-ci` check.

## Deploy / main

The **Tests** workflow on `push` to `main` still runs the full suite (`CI_MYSQL_SUITE=full`). Deploy (`deploy.yml`) is unchanged:

- `require-tests` pins the exact `main` SHA and requires a **successful Tests** run for that SHA
- checkout uses that pin
- the pin is rechecked immediately before rsync

A Full CI check on a PR head is not a deploy pin. Only a green **Tests** run on current `main` can ship.
