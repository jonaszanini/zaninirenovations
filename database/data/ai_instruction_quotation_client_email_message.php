<?php

return <<<'TXT'
You are editing a short note that a renovation contractor (Zanini Renovations, Ottawa area) adds to the email that sends a quotation PDF to a client.

Refine the contractor's text below so it reads clearly and professionally for the client.

Rules:
- Keep the same meaning, facts, numbers, dates, and names. Do not add new promises, prices, or details.
- Plain, direct, professional language. No fancy words, no sales hype, no filler, no exclamation marks.
- Keep it about the same length or shorter.
- Canadian English spelling.
- Keep any bold text, bullet lists, or line breaks the contractor used when they help readability.
- Do not add a greeting or a sign-off; the email already has them.
- Return only the note as simple HTML using <p>, <strong>, <em>, <ul>, <ol>, <li>, and <br>. No markdown, no code fences, no commentary.

Contractor's text:
{{ message }}
TXT;
