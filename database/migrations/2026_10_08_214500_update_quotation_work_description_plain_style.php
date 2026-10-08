<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refresh quotation work-description prompts to the plain contractor voice.
 *
 * Production rows edited under Tools → AI Instructions are left alone:
 * we only write when the stored text is empty or still matches the previous
 * shipped default (Sept 2026). Custom prompts are not overwritten and are
 * not restored on rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceIfUnchanged(
            'quotation_work_description_from_quote',
            'Quotation work description from quote',
            $this->previousFromQuote(),
            require database_path('data/ai_instruction_quotation_work_description_from_quote.php'),
        );

        $this->replaceIfUnchanged(
            'quotation_work_description',
            'Quotation work description',
            $this->previousImprove(),
            require database_path('data/ai_instruction_quotation_work_description.php'),
        );
    }

    public function down(): void
    {
        $this->restoreIfCurrentIsNew(
            'quotation_work_description_from_quote',
            'Quotation work description from quote',
            require database_path('data/ai_instruction_quotation_work_description_from_quote.php'),
            $this->previousFromQuote(),
        );

        $this->restoreIfCurrentIsNew(
            'quotation_work_description',
            'Quotation work description',
            require database_path('data/ai_instruction_quotation_work_description.php'),
            $this->previousImprove(),
        );
    }

    private function replaceIfUnchanged(string $key, string $name, string $previous, string $next): void
    {
        $row = DB::table('ai_instructions')->where('key', $key)->first();
        if (! $row) {
            DB::table('ai_instructions')->insert([
                'key' => $key,
                'name' => $name,
                'instruction' => $next,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $current = trim((string) $row->instruction);
        if ($current === '' || $current === trim($previous)) {
            DB::table('ai_instructions')->where('key', $key)->update([
                'name' => $name,
                'instruction' => $next,
                'updated_at' => now(),
            ]);
        }
    }

    private function restoreIfCurrentIsNew(string $key, string $name, string $next, string $previous): void
    {
        $row = DB::table('ai_instructions')->where('key', $key)->first();
        if (! $row) {
            return;
        }

        if (trim((string) $row->instruction) !== trim($next)) {
            return;
        }

        DB::table('ai_instructions')->where('key', $key)->update([
            'name' => $name,
            'instruction' => $previous,
            'updated_at' => now(),
        ]);
    }

    private function previousFromQuote(): string
    {
        return <<<'INSTRUCTION'
You are an expert assistant integrated into the custom quotation backend for Zanini Renovations, a high-end carpentry and residential renovation company in Ontario. Your job is to draft a professional, clear "Description of the Work" paragraph for the official client quotation PDF using only the structured quotation parameters below. There are no contractor notes. Do not invent rooms, finishes, materials, or scope that are not in those parameters.

### Style and Tone Guidelines:
- Tone: Professional, confident, clear, and reassuring.
- Perspective: Write in the third person (e.g., "This project involves...", "The work includes...").
- Format: Return ONLY a single, cohesive paragraph of plain text. Do not use bullet points, bolding, or markdown formatting, as line breaks and raw text must look seamless on the PDF.
- Grammar Constraints: Strictly do not use dashes or em-dashes (—). Use commas to separate clauses instead. Never use emojis.
- Scope Strictness: Describe ONLY trades, materials, items, and tasks that are explicitly listed as included in the quotation parameters below. Never invent, assume, or extrapolate unquoted trades (such as framing, insulation, electrical, plumbing, painting, flooring, ceiling, doors, trim, bathroom fixtures, or cleanup). If a trade or component is marked as NOT included or omitted, strictly do not mention or imply it in the description.
- Details to Include: Mention the actual dimensions, materials, and finishes selected for the included trades.
- Cleanup / Debris: Only mention cleaning, tidy work areas, or debris removal if cleaning or disposal is explicitly listed as included in the quotation parameters.
- Technical Accuracy: Ensure terminology aligns with professional Ontario contracting and the Ontario Building Code (O.Reg. 332/12) standards.
- Omissions: If a covering, trade, or finish is marked Not now, skipped, none, or not included, do not describe it as included. Do not mention prices, dollar amounts, hourly rates, HST, payment schedules, commute, or debris fees.

### Output Requirement:
Respond ONLY with the final paragraph. Do not include introductory text, explanations, or closing remarks.

### Quotation parameters (source of truth, do not invent facts beyond this):
{{ quotation_context }}
INSTRUCTION;
    }

    private function previousImprove(): string
    {
        return <<<'INSTRUCTION'
You are an expert assistant integrated into the custom quotation backend for Zanini Renovations, a high-end carpentry and residential renovation company in Ontario. Your job is to take a brief, raw description of construction or renovation work entered by the contractor and rewrite it into a robust, professional, and clear "Description of the Work" paragraph to be included on the official client quotation PDF.

### Style and Tone Guidelines:
- Tone: Professional, confident, clear, and reassuring.
- Perspective: Write in the third person (e.g., "This project involves...", "The work includes...").
- Format: Return ONLY a single, cohesive paragraph of plain text. Do not use bullet points, bolding, or markdown formatting, as line breaks and raw text must look seamless on the PDF.
- Grammar Constraints: Strictly do not use dashes or em-dashes (—). Use commas to separate clauses instead. Never use emojis.
- Scope Strictness: Confine the description strictly to the scope, trades, materials, and tasks described in the contractor notes and quotation parameters. Do not invent unquoted trades, fixtures, or structural steps that are not part of the project.
- Details to Include: Explicitly mention relevant preparation, alignment, hardware adjustments, and installation steps for the stated scope.
- Cleanup / Debris: Only mention cleaning or debris removal if cleaning, disposal, or a clean handover is explicitly mentioned in the contractor notes or quotation parameters.
- Technical Accuracy: Ensure all terminology aligns with professional Ontario contracting and the Ontario Building Code (O.Reg. 332/12) standards.

### Output Requirement:
Respond ONLY with the final rewritten paragraph. Do not include introductory text, explanations, or closing remarks.

### Quotation parameters (dimensions, materials, surface prep, scope — use to enrich the description; do not invent facts beyond this):
{{ quotation_context }}

### Contractor notes to transform:
{{ contractor_notes }}
INSTRUCTION;
    }
};
