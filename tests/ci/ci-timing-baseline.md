# CI timing baseline (GitHub Actions, successful runs on 2026-11-05)

Measured **step** durations from `gh run view <id> --json jobs` (UTC). These are historical CI measurements, not re-measured E2E after this PR.

| Run ID | Branch / context | Job | Step | Seconds |
|--------|------------------|-----|------|---------|
| 42108922889 | PR #121 admin hotkey only | unit_js | JavaScript tests (browser) | **159** |
| 42108922889 | PR #121 admin hotkey only | unit_js | Unit tests (no database) | 28 |
| 42108922889 | PR #121 admin hotkey only | unit_js | JavaScript tests (no browser) | 1 |
| 42108922889 | PR #121 admin hotkey only | mysql | Database tests (MySQL) | 115 |
| 42108199873 | PR #120 OpenPlan3D / room area | unit_js | JavaScript tests (browser) | **166** |
| 42108199873 | PR #120 OpenPlan3D / room area | mysql | Database tests (MySQL) | 183 |
| 42108199873 | PR #120 OpenPlan3D / room area | mysql | Room Area live layout geometry | 14 |

## Potential PR savings (not achieved until this workflow ships)

**Before:** every code PR set `run_browser=true` whenever `run_database=true`, so admin-only diffs still ran the full browser step (~**159–166s** on runs above).

**After (expected):** admin-only diffs run `js-browser-admin.txt` + `js-browser-quotation.txt` (shared hotkey/redraft deps), skipping openplan3d Puppeteer specs.

| Changed paths (PR) | Browser domains | MySQL `filter_room_area` | Potential savings |
|--------------------|-----------------|--------------------------|-------------------|
| `public/js/admin-hotkey-save.js` | admin + quotation | false | Up to **~159s** browser step (42108922889) — **potential**, not verified E2E here |
| `scripts/openplan3d-h1.json` / vendor tree | openplan3d | **true** (+ `RoomAreaEngineFeatureFlagTest`) | Same heavy browser as #120; closes manifest PHP gap on PR |
| Unknown path (no workflow filter) | **all** via `broad_run` | all supplemental | No reduction (fail-open) |
| **main** push | **all** | full extended suite | Unchanged (deploy gate) |

Regression: `node tests/ci/matrix-sim.js` (path → filter → flags → browser list).
