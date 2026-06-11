# Zanini Renovations — Test Strategy & Coverage Report

Generated as part of an initial test harness build. Use this as the living map for expanding coverage.

## Executive summary

| Metric | Before | After this pass |
|--------|--------|-----------------|
| Test files | 1 (`ExampleTest`) | 18+ |
| Unit tests | ~0 | Core calculators, parsers, invoice model logic |
| Feature tests | 1 smoke test | Auth, security, invoice mark-paid, signed URLs |
| Estimated app coverage | &lt;1% | Foundation laid; still low overall (~5–10% of `app/`) |

**Highest risk:** Almost all admin business workflows (quotations, contracts, SMS, automations, material sync in production) had **zero** automated tests before this work.

---

## What existed before

- `tests/Feature/ExampleTest.php` — homepage 200 check only
- `tests/TestCase.php` — missing `CreatesApplication` (tests could not boot)
- No `tests/Unit/` directory
- No factories except `UserFactory`
- No CI test step documented in repo

---

## Coverage gaps by area

### Authentication (partially covered now)

| Component | Risk | Status |
|-----------|------|--------|
| `LoginController` login/logout | High | **Covered** — `AdminLoginTest`, `AdminLogoutTest` |
| Guest vs auth middleware | High | **Covered** — `AdminRouteProtectionTest` |
| Password reset / registration | N/A | **Not implemented** — single admin user model only |
| 2FA | N/A | **Not implemented** |
| Sanctum API tokens | Low | **Untested** — `HasApiTokens` on User, no API routes found in primary flows |

### Payments & invoicing (partially covered)

| Component | Risk | Status |
|-----------|------|--------|
| `ClientProjectInvoice` calculations | High | **Unit tests** — line items, discounts, payment labels |
| `markPaid()` flow | Critical | **Feature tests** — guards, validation, happy path + receipt PDF |
| Installment sync on mark-paid | High | **Untested** — needs installment factory + assertion |
| Invoice send / SMS / email | High | **Untested** — mock `Mail`, `ClientSmsService` |
| Helper payments | Medium | **Untested** |
| Stripe / card webhooks | N/A | **Not in codebase** — cash + e-Transfer only |

### Core business logic (partially covered)

| Component | Risk | Status |
|-----------|------|--------|
| `FenceQuotationCalculator` | Critical | **Unit tests** — shape, nails/box math, input normalization |
| `PavingQuotationCalculator` | High | **Untested** |
| `StairsQuotationCalculator` | High | **Untested** |
| `StandardQuotationCalculator` | High | **Untested** |
| `ClientProjectStatusService` | High | **Untested** |
| `MaterialPriceSyncService` | High | **Partial** — metadata hook + dry-run (mocked fetcher) |
| `MaterialMetadataEnricher` | Medium | **Unit tests** — pattern examples |
| `AdminDashboardService` | Medium | **Untested** |

### Security-sensitive (partially covered)

| Component | Risk | Status |
|-----------|------|--------|
| Signed public PDF URLs | High | **Covered** — `SignedPublicUrlsTest` |
| HTTP cron endpoint | High | **Partial** — 404 when token missing/invalid; valid token path not exercised (runs Artisan) |
| Blog comment captcha | Medium | **Untested** |
| Contact form throttle | Medium | **Untested** |
| Google OAuth / Photos | Medium | **Untested** |

### User / client data CRUD

| Component | Risk | Status |
|-----------|------|--------|
| `ClientController` | High | **Untested** |
| `ClientProjectController` | Critical | **Untested** |
| `MaterialController` | Medium | **Untested** |
| Privacy / PII in logs | Medium | **Untested** — review `RedactionProcessor` manually |

---

## Files added in this pass

### Infrastructure
- `tests/CreatesApplication.php`
- `tests/Support/ActsAsAdmin.php`
- `database/factories/ClientProjectFactory.php`
- `database/factories/ClientProjectInvoiceFactory.php`
- `HasFactory` on `ClientProject`, `ClientProjectInvoice`

### Unit tests
- `tests/Unit/Services/AmazonCaUrlsTest.php`
- `tests/Unit/Services/HomeDepotProductTitleParserTest.php`
- `tests/Unit/Services/MaterialMetadataEnricherTest.php`
- `tests/Unit/Services/MaterialMetadataPopulationServiceTest.php`
- `tests/Unit/Services/MaterialPriceSyncServiceTest.php`
- `tests/Unit/Services/FenceQuotationCalculatorTest.php`
- `tests/Unit/Models/FenceMaterialTest.php`
- `tests/Unit/Models/ClientProjectInvoiceTest.php`

### Smoke tests (500 / Blade regressions)
- `tests/Unit/Views/BladeSyntaxTest.php` — compiles all `admin/` and `site/` Blade files; fails on mismatched `@error("` quotes (no database)
- `tests/Feature/Smoke/PublicGetRoutesSmokeTest.php` — every public GET route must return HTTP &lt; 500
- `tests/Feature/Smoke/AdminGetRoutesSmokeTest.php` — every authenticated admin GET route must return HTTP &lt; 500
- `tests/Support/RouteSmokeHelper.php` + `SmokeTestFixtures.php` — resolve route parameters with minimal factory data

Run after view or route changes:

```bash
php artisan test tests/Unit/Views/BladeSyntaxTest.php   # fast, no MySQL
php artisan test tests/Feature/Smoke                    # needs MySQL test DB
```

Signed URLs, OAuth callbacks, and cron are excluded from the smoke sweep (covered elsewhere or need special tokens).

### Feature tests
- `tests/Feature/Auth/AdminLoginTest.php`
- `tests/Feature/Auth/AdminLogoutTest.php`
- `tests/Feature/Security/AdminRouteProtectionTest.php`
- `tests/Feature/Security/CronEndpointTest.php`
- `tests/Feature/Security/SignedPublicUrlsTest.php`
- `tests/Feature/Invoices/MarkPaidInvoiceTest.php`

### Templates
- `tests/Templates/NewServiceTest.template.php`
- `tests/Templates/NewFeatureFlowTest.template.php`

---

## Recommendations

### 1. CI integration (GitHub Actions example)

```yaml
name: Tests
on: [push, pull_request]
jobs:
  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: dom, mbstring, sqlite
      - run: composer install --no-interaction --prefer-dist
      - run: php artisan test
```

### 2. Coverage reporting

```bash
composer require --dev phpunit/php-code-coverage
php artisan test --coverage-html build/coverage
```

Add a minimum coverage gate once baseline exceeds ~40% on `app/Services` and critical controllers.

### 3. Testability improvements

| Issue | Suggestion |
|-------|------------|
| PDF generation in controllers | Extract `InvoicePdfRenderer` service; inject in tests with fake |
| `MaterialPriceSyncService` HTTP calls | Already mockable via constructor injection — continue pattern |
| Static `MaterialVendor::homeDepotId()` | Cache in instance or pass vendor resolver for easier fakes |
| Large controllers (`ClientProjectInvoiceController` 1500+ lines) | Extract payment + receipt workflows to dedicated action classes |
| Fence calculator loads DB catalogue | Tests already inject in-memory catalogue — use same pattern for paving/stairs |

### 4. Next tests to write (priority order)

1. `PavingQuotationCalculator` / `StairsQuotationCalculator` — same pattern as fence
2. `ClientProjectStatusService` — status transitions on first payment
3. `MaterialController::syncPrice` / `syncAllStep` — JSON progress API
4. `RunDailyAutomations` — smoke test with mocked notifications
5. `BlogCommentCaptcha` — math mode session validation
6. Installment sync when `markPaid` on installment-linked invoice

### 5. Running tests locally

```bash
php artisan test
php artisan test --filter=FenceQuotationCalculatorTest
php artisan test tests/Feature/Auth
```

### Local `.env` points at production (no local MySQL)

This project is often developed with `.env` aimed at the Hostinger database. **Tests do not use `.env` for DB credentials** — `phpunit.xml` overrides them to `127.0.0.1` / `zanini_renovations_test`.

`IntegrationTestCase` also **refuses** to run if `DB_HOST` looks like production (`hstgr.io`, etc.) or if the database name does not end in `_test`.

**Recommended local workflow (no local DB):**

```bash
composer test:blade
# or: php artisan test tests/Unit/Views/BladeSyntaxTest.php
```

That catches Blade typos like the `/admin/materials/{id}/edit` 500 in ~0.2s.

**Route smoke tests** (`tests/Feature/Smoke`) need MySQL. Without a local server they skip. They run automatically in GitHub Actions (`.github/workflows/tests.yml`) on every push/PR to `main`.

### Database for integration tests (optional local setup)

Feature tests extend `IntegrationTestCase` and require **MySQL** (migrations use MySQL-specific DDL; SQLite is not supported).

```sql
CREATE DATABASE zanini_renovations_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`phpunit.xml` points at `zanini_renovations_test` on `127.0.0.1` with user `root` and empty password (XAMPP defaults). Adjust credentials in `phpunit.xml` if needed.

```bash
# Fast, no database
composer test:blade
php artisan test --testsuite=Unit

# Route smoke (local MySQL only, or use CI)
composer test:smoke

# Full suite (requires local MySQL + migrated test DB)
php artisan test
```

If MySQL is not running locally, integration tests are **skipped** rather than failed.

---

## Notes on payment scope

This application does **not** integrate Stripe or card processors. “Payment processing” is implemented as:

- Manual **mark paid** on invoices (cash / e-Transfer)
- Receipt PDF generation + optional email/SMS
- Financials installment flags
- Helper labour payments (separate flow)

Tests reflect that model rather than external payment webhooks.
