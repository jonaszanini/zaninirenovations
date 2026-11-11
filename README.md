# Zanini Renovations

Professional Renovation and Carpentry Services Website

## About

Zanini Renovations is a Laravel-based website for advertising renovation and carpentry work. The site will include authentication for admin, helpers, and clients.

## Quick Start

### Prerequisites
- PHP 8.1 or higher
- Composer

### Installation Steps

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Set up environment file:**
   ```bash
   copy .env.example .env
   ```
   (On Windows PowerShell, use `copy` instead of `cp`)

3. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

4. **Start the development server:**
   ```bash
   php artisan serve
   ```

5. **Open your browser:**
   Navigate to `http://localhost:8000` to see the welcome page.

### Optional: Database Setup

If you plan to use a database later, configure it in `.env`:
- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=zanini_renovations`
- `DB_USERNAME=root`
- `DB_PASSWORD=`

Then run migrations when ready:
```bash
php artisan migrate
```

## Development

The application is currently showing a "Coming Soon" welcome page. Future features will include:
- Authentication system for admin, helpers, and clients
- Project management
- Client portal

## Color Palette

- Main Color: `#024768` (Dark Teal/Blue)
- Dark Color/Font: `#373435` (Dark Gray)
- Secondary Color: `#3a4856` (Slate Gray)
- Accent Color: `#9dadbc` (Light Blue Gray)

## Quote acceptance API

A Gmail automation can accept a sent quotation without logging in:

- **URL:** `POST /api/quotations/accept`
- **Auth:** `Authorization: Bearer <QUOTE_ACCEPT_API_TOKEN>` (set in `.env`)
- **Body:** `{ "project_number": "PRJ00001", "quote_number": "SQ-2026-0001", "source_note": "optional" }`
- **JSON status:** `accepted`, `already_accepted`, `not_found`, `invalid_state` (or `unauthorized` if the token is missing/wrong)

No client email or SMS is sent. Full request/response examples: [docs/QUOTE_ACCEPTANCE_API.md](docs/QUOTE_ACCEPTANCE_API.md).

## Incoming e-transfer API

A Gmail inbox watcher can capture Interac notifications without logging in. Notices stay **pending** until Jonas records the payment in admin.

- **URL:** `POST /api/etransfers`
- **Auth:** `Authorization: Bearer <ETRANSFER_API_TOKEN>` (falls back to `QUOTE_ACCEPT_API_TOKEN` if unset)
- **Body:** structured fields and/or `{ "raw_text": "<email body>" }`
- **JSON status:** `created` (201), `duplicate` (200), `invalid` (422), `unauthorized` (401)

The API never marks an invoice paid. Full contract: [docs/ETRANSFER_API.md](docs/ETRANSFER_API.md).

## CI

Pull request pushes run a **path-filtered** Tests workflow (PHP unit, MySQL feature lists, JS, and browser screenshot jobs only when those areas changed). Docs/`lang`/Markdown-only PRs skip the heavy jobs.

Before merge, run the **full** suite once on the PR head so every `tests/ci` list runs (including main-only MySQL tests such as `RoomAreaOpenPlan3DQuotationTest`):

1. Add the `full-ci` label to the PR, or
2. Run **Actions → Full CI → Run workflow** and pass the PR number.

That posts a `full-ci` check on the exact head SHA (re-run after further pushes). Details: [docs/ci.md](docs/ci.md).

## License

MIT

