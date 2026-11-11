# CI timing baseline (GitHub Actions)

Job wall times from `gh run view <id> --json jobs` (UTC). GitHub **bills each job rounded up to the next minute**, summed across jobs (parallel jobs both bill).

## Measured runs (2026-11-11)

| Run ID | Context | changes | unit_js | mysql | Billed (est.) |
|--------|---------|---------|---------|-------|----------------|
| 42933328067 | **main** Tests success (full MySQL) | 20s → 1m | 4m22s → 5m | 14m49s → 15m | **~21 min** |
| 42986972283 | **main** Tests fail (`RoomAreaOpenPlan3DQuotationTest`, extended-only) | 19s → 1m | 5m17s → 6m | 21m55s → 22m | **~29 min** |
| 42984307582 | PR #144 room-area (scoped + room-area/openplan3d) | 19s → 1m | 4m52s → 5m | 17m35s → 18m | **~24 min** |
| 42930541839 | PR #148 CI/deploy scripts (`global_test_deps` → all heavy) | 22s → 1m | 4m03s → 5m | 12m57s → 13m | **~19 min** |
| 42921169035 | PR #147 basement PHP | 21s → 1m | 3m53s → 4m | 17m45s → 18m | **~23 min** |

Step-level history (2026-11-05):

| Run ID | Branch / context | Job | Step | Seconds |
|--------|------------------|-----|------|---------|
| 42108922889 | PR #121 admin hotkey only | unit_js | JavaScript tests (browser) | **159** |
| 42108922889 | PR #121 admin hotkey only | unit_js | Unit tests (no database) | 28 |
| 42108922889 | PR #121 admin hotkey only | mysql | Database tests (MySQL) | 115 |
| 42108199873 | PR #120 OpenPlan3D / room area | unit_js | JavaScript tests (browser) | **166** |
| 42108199873 | PR #120 OpenPlan3D / room area | mysql | Database tests (MySQL) | 183 |

## Before vs after (per PR)

Assumes a typical **3-push** PR (superseded runs cancel; cancelled jobs still bill a partial minute) plus merge to main.

| Slice | Before (this baseline) | After |
|-------|------------------------|--------|
| **Each PR push** (code, path-matched) | ~**19–24 min** billed (`changes` + `unit_js` + `mysql`). Docs-only still paid ~5–6 min for `unit_js`. Unclassified PHP (`app/Models/…`) fail-opened **all** browser + filters (~19–24 min). | Docs/content-only: **~1 min** (`changes` only, no npm). PHP-only classified model: **~9–14 min** (PHP unit + MySQL core, no Puppeteer). JS hotkey: skip PHP unit (~1 min setup); browser stays domain-scoped. Same-PR `global_test_deps` still ~19–24 min (this PR). |
| **Merge-time full suite** | **0 min on the PR** — `database-extended.txt` (e.g. `RoomAreaOpenPlan3DQuotationTest`) ran only on `main` after merge (42986972283 failed in 29 min). | **Once** via Full CI on the head SHA: ~**21–25 min** (same jobs as a main Tests run, every `tests/ci` list). |
| **main Tests** (deploy pin) | ~**21 min** full suite | ~**21–25 min** full suite still required (`require-tests` pin unchanged). Slightly more MySQL if filter-only files were missing from extended (now unioned). |

**Per PR cycle (3 pushes + 1 merge), typical room-area/PHP PR:** before ~**3 × 22 + 21 = 87 min** (plus a full main *retry* when an extended-only test failed). After ~**3 × 22 + 23 + 23 ≈ 112 min** if every push still hits room-area/browser — **no win** on those diffs — but **docs PRs drop ~20 min/push**, **unclassified PHP drops ~10 min/push**, cancelled superseded pushes still stop, and extended failures are caught **once on the PR head** instead of after merge.

**Intended savings** are from (1) skipping `unit_js`/`mysql` on docs, (2) not fail-opening browser on generic `app/**` PHP, (3) skipping PHP install on JS-only pushes, (4) not repeating the extended MySQL list on every PR push (already true) while still running it **once before merge**.
