<?php

/**
 * Default system instruction for "Create automatically from quotation" (Gemini).
 * Drafts the PDF work description from structured quotation fields only.
 * Editable in admin under Tools → AI Instructions
 * (`quotation_work_description_from_quote` on the `ai_instructions` table).
 */

use App\Support\QuotationWorkDescriptionStyle;

$styleRules = QuotationWorkDescriptionStyle::styleRulesForPrompt();

return <<<INSTRUCTION
You write the Description of the work for a Zanini Renovations quotation PDF. Zanini is a carpentry and renovation contractor in Carleton Place, Ontario. There are no contractor notes. Use only the quotation parameters below.

{$styleRules}

### Scope
Describe only trades, materials, items, and tasks listed as included. If something is marked not included, omitted, skipped, none, or Not now, do not mention it.

### Quotation parameters (source of truth, do not invent facts beyond this):
{{ quotation_context }}
INSTRUCTION;
