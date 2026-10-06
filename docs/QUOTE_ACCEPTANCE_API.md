# Quote acceptance API

Automation endpoint so a Gmail bot (or similar) can mark a quotation accepted without logging into admin. It does **not** email or SMS the client.

## URL

`POST https://zaninirenovations.ca/api/quotations/accept`

Local: `POST /api/quotations/accept` (same path; host follows `APP_URL`).

## Auth

Header:

```
Authorization: Bearer <QUOTE_ACCEPT_API_TOKEN>
```

Set `QUOTE_ACCEPT_API_TOKEN` in `.env` to a long random string. Requests with a missing, blank, or wrong token receive `401` and `{ "status": "unauthorized" }`. Comparison uses `hash_equals`. The route is rate-limited (30 requests / minute / IP, plus the default API limiter).

## Request

JSON body:

```json
{
  "project_number": "PRJ00001",
  "quote_number": "SQ-2026-0001",
  "source_note": "gmail-message-id-optional"
}
```

| Field | Required | Notes |
| --- | --- | --- |
| `project_number` | yes | Project reference such as `PRJ00001` |
| `quote_number` | yes | Calculator number (`SQ-2026-0001`) or document number (`QUO-2026-0150`) |
| `source_note` | no | Optional audit text (Gmail message id, etc.), max 255 characters |

## Response

JSON `{ "status": "..." }`:

| status | HTTP | Meaning |
| --- | --- | --- |
| `accepted` | 200 | Quote marked accepted; project moved to Proposal Accepted when it was still in a visit/quote stage |
| `already_accepted` | 200 | Idempotent: this quote was already accepted |
| `not_found` | 404 | Unknown project number, quote number, or the quote is not on that project |
| `invalid_state` | 409 | Quote is superseded or rejected, or the project is closed (cancelled/rejected) |
| `unauthorized` | 401 | Missing/wrong bearer token |

Example success:

```json
{ "status": "accepted" }
```

Acceptance is logged (via, IP, source note) and shown on the admin quotation card and project history. The public signed “Accept this quote” link in the client email/PDF does the same work and does not use this token.
