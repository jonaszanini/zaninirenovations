# Dark-mode audit

Authenticated **admin** UI must work in light and dark. Public site, login, PDFs, emails, and print/purchase lists stay **light only**.

## Scripts

| File | Purpose |
|------|---------|
| `dark-mode-audit.js` | Puppeteer: every admin HTML page at 1280 and 390 in `html.admin-dark`. Flags light backgrounds (luminance &gt; 0.6), WCAG contrast &lt; 4.5:1 (3:1 large text/icons), and invisible text (contrast &lt; 1.5). |
| `screenshot-all.js` | Existing screenshot + overflow checker. |
| `php artisan dark-mode:prepare` | Seeds realistic demo records and writes `storage/app/dark-mode-audit-pages.json`. |

## Local run

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --force
php artisan dark-mode:prepare
php artisan serve --host=127.0.0.1 --port=8000

npm install
node dark-mode-audit.js            # report only
node dark-mode-audit.js --ci       # exit 1 if any offender remains
node screenshot-all.js             # overflow checker + screenshots
```

Login for the seeded admin: `admin@zanini.test` / `password`.

`--ci` is **not** wired into GitHub Actions: it needs a long-lived app server, Chrome, and a seeded database. CI instead runs `tests/Unit/Views/AdminDarkThemeCoverageTest.php` (token + selector coverage) plus existing PHPUnit jobs.

## Latest results (this PR)

122 admin pages × 2 viewports = 244 combos.

| Run | Light backgrounds | Low contrast | Invisible text | Total |
| --- | ---: | ---: | ---: | ---: |
| First full audit | 125 | 556 | 143 | 824 |
| After this PR | 0 | 0 | 0 | 0 |

The existing overflow checker (`screenshot-all.js`) still reports 16 pre-existing offenders: 15 `wider_than_card` hits on the projects-list table (already inside `.admin-table-scroll`) and 1 `text_overflow` on a mobile expense description cell. Those are layout, not dark-mode.

## Allowlisted / out of scope

- Public pages, `/admin/login`, password reset, PDFs, signed guest downloads
- Print HTML (`purchase-list`, `cut-list`, `layout-diagrams`)
- Images, SVGs, canvases, videos, iframes
- TinyMCE oxide chrome (`.tox` / `.tox-tinymce`) — vendor skin; toolbar/statusbar are themed in `admin-dark.css`
- FullCalendar event chips (intentional status colours) and generated overlay nodes (`#fc-dom-*`)
- Decorative Font Awesome icons inside `.cp-badge` (the badge label is still contrast-checked)
- Switch knobs (`.cp-visit-toggle__control::after`) stay white by design
- Print HTML, PDFs, receipts, and purchase/cut lists stay light-only
- `admin.client-projects.quotation.edit` is a redirect hub to the specialised calculator (or a 404 for unlinked rows). The 12 calculator editors are audited on their own routes.
