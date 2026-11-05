# CI timing baseline (GitHub Actions, successful runs on 2026-11-05)

Measured step durations from `gh run view <id> --json jobs` (UTC).

| Run ID | Branch / context | Job | Step | Seconds |
|--------|------------------|-----|------|---------|
| 42108922889 | PR #121 admin hotkey only | unit_js | JavaScript tests (browser) | **159** |
| 42108922889 | PR #121 admin hotkey only | unit_js | Unit tests (no database) | 28 |
| 42108922889 | PR #121 admin hotkey only | unit_js | JavaScript tests (no browser) | 1 |
| 42108922889 | PR #121 admin hotkey only | mysql | Database tests (MySQL) | 115 |
| 42108199873 | PR #120 OpenPlan3D / room area | unit_js | JavaScript tests (browser) | **166** |
| 42108199873 | PR #120 OpenPlan3D / room area | mysql | Database tests (MySQL) | 183 |
| 42108199873 | PR #120 OpenPlan3D / room area | mysql | Room Area live layout geometry | 14 |

## Before (main @ pre-optimization behavior)

Every PR with any code change set `run_browser=true` whenever `run_database=true` (see inline flags in `tests.yml` before this PR). That forced the full **~159–166s** browser suite on admin-only PRs such as #121.

## After (this PR) — selection matrix

| Changed paths (PR) | `run_browser` domains | MySQL supplemental `filter_room_area` | Expected savings vs before |
|--------------------|----------------------|---------------------------------------|----------------------------|
| `public/js/admin-hotkey-save.js` | **admin only** | false | **~159s** browser on `unit_js` (run 42108922889) |
| `scripts/openplan3d-h1.json` or `public/vendor/openplan3d-h1/**` | **openplan3d** | **true** (+ `RoomAreaEngineFeatureFlagTest.php`) | Same heavy browser as #120 class; **closes PR gap** where asset edits skipped manifest PHP test |
| `scripts/patch-openplan3d.mjs` | openplan3d + live geometry | true | Unchanged vs #120; mysql Node only when `run_mysql_node` |
| `tests/ci/**` or `.github/workflows/**` | **all** (`global_test_deps`) | all supplemental filters | No reduction (intentional full run) |
| `main` push | **all** | full `database-extended.txt` | No reduction (deploy gate unchanged) |

Regression cases: `bash tests/ci/ci-filter-matrix.test.sh`
