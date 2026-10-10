# Incoming e-transfer API

Automation endpoint so a Gmail inbox watcher can capture Interac e-Transfer
notifications. It stores a **pending** notice and returns suggested invoices.
It does **not** mark any invoice paid.

Jonas records the payment from **Financials → Incoming e-transfers**.

## URL

`POST https://zaninirenovations.ca/api/etransfers`

Local: `POST /api/etransfers` (same path; host follows `APP_URL`).

## Auth

Header:

```
Authorization: Bearer <ETRANSFER_API_TOKEN>
```

Set `ETRANSFER_API_TOKEN` in `.env` to a long random string. If it is unset or
blank, `QUOTE_ACCEPT_API_TOKEN` is used instead so one secret can drive both
automations. Requests with a missing, blank, or wrong token receive `401` and
`{ "status": "unauthorized" }`. Comparison uses `hash_equals`. The route is
rate-limited (30 requests / minute / IP, plus the default API limiter).

## Request

JSON body. Send **either** the raw email text, **or** structured fields, or both.
When both are present, structured fields override whatever the parser extracted.

```json
{
  "raw_text": "Jane Citizen sent you $1,130.00 CAD\nMessage: INV-2026-0001 kitchen deposit\nReference Number: CA1B2C3D4E5F6\nSent: October 6, 2026 at 4:32 PM ET",
  "sender_name": "Jane Citizen",
  "sender_email": "jane@example.com",
  "amount": 1130.00,
  "currency": "CAD",
  "reference": "CA1B2C3D4E5F6",
  "message": "INV-2026-0001 kitchen deposit",
  "received_at": "2026-10-06T16:32:00-04:00"
}
```

Minimum accepted payloads:

1. `{ "raw_text": "<full Interac email body>" }`
2. `{ "sender_name": "Jane Citizen", "amount": 1130.00 }`

| Field | Required | Notes |
| --- | --- | --- |
| `raw_text` | if structured amount+name omitted | English or French Interac body, max 20 000 characters |
| `sender_name` | if no `raw_text` | Display name from the email |
| `amount` | if no parseable amount in `raw_text` | Number or CAD string (`1130`, `1,130.00`) |
| `currency` | no | Defaults to `CAD` |
| `sender_email` | no | Ignored if it is an `@interac.ca` address or one of our own deposit/business addresses |
| `reference` | no | Interac confirmation / reference number. Alias: `reference_number` |
| `message` | no | Memo the sender typed |
| `received_at` | no | ISO-8601 or any Carbon-parseable datetime. Defaults to now (or the date parsed from the email) |

## Response

JSON:

```json
{
  "status": "created",
  "notice": {
    "id": 42,
    "received_at": "2026-10-06T16:32:00-04:00",
    "sender_name": "Jane Citizen",
    "sender_email": "jane@example.com",
    "amount": 1130.00,
    "currency": "CAD",
    "reference": "CA1B2C3D4E5F6",
    "message": "INV-2026-0001 kitchen deposit",
    "source": "api",
    "status": "pending",
    "invoice_id": null,
    "payment_id": null,
    "match_score": 140
  },
  "candidates": [
    {
      "invoice_id": 15,
      "invoice_number": "INV-2026-0001",
      "project_id": 3,
      "project_name": "Kitchen Reno",
      "client_name": "Jane Citizen",
      "amount": 1130.00,
      "outstanding": 1130.00,
      "status": "sent",
      "score": 140,
      "reasons": ["Invoice number", "Amount matches", "Name similar"]
    }
  ]
}
```

| status | HTTP | Meaning |
| --- | --- | --- |
| `created` | 201 | Notice stored as pending. Use `notice.id` plus `candidates` in logs if you want. |
| `duplicate` | 200 | Same Interac `reference` already exists. `notice` is the existing row; no second insert. |
| `invalid` | 422 | Missing both `raw_text` and `sender_name`+`amount` |
| `unauthorized` | 401 | Missing/wrong bearer token |

A 422 with Laravel validation errors is also returned when the parser cannot
find an amount (and none was sent). Example: `{ "message": "...", "errors": { "amount": ["..."] } }`.

## Behaviour

- **Never auto-records a payment.** Matching is a suggestion only.
- Unique on `reference` when present, so retries of the same Interac email are idempotent.
- Candidates are ranked by: a full `INV-YYYY-NNNN` anywhere in the memo (strongest); a short number only directly after `inv` / `invoice` / `inv#` / `invoice #` (and only when the client or amount also matches — a short hit never outranks an amount match); amount equal to the unpaid invoice total or an unpaid deposit/instalment; client name/email vs sender; then recency. A bare `#NNN`, a 4-digit token, or digits inside a phone number is not a citation.
- If the memo cites an invoice that is already paid, that invoice is still returned and flagged `Already paid` instead of dropping it for an unrelated open invoice. An open invoice that is also cited and matches the amount ranks above that paid invoice.
- Recency alone (score 10) is strictly below `ETRANSFER_MIN_MATCH_SCORE` (default 15) and is not a confident suggestion. A score equal to the minimum is a match. The minimum is not silently raised.
- Two or more open invoices cited in the memo, two or more open invoices that match the amount, or a cited invoice that conflicts with a different-client amount/name match, are an ambiguous match — nothing is preselected.
- Configured own inboxes and any address on the auto-deposit line (`…deposited into your account at Scotiabank at …`) are never stored as `sender_email`. Forwarded From:/To: headers are ignored entirely when picking the payer address. When no payer address remains, it stays null.
- Admin: **Financials → Incoming e-transfers** → Record payment (method e-Transfer) or Ignore.
