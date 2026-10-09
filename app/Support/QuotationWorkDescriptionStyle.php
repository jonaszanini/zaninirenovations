<?php

namespace App\Support;

/**
 * Shared voice rules and a light cleanup pass for quotation PDF
 * "Description of the work" text (all quotation types).
 */
final class QuotationWorkDescriptionStyle
{
    /**
     * Marketing / AI-flourish phrases the model must not use.
     *
     * @var list<string>
     */
    public const BANNED_PHRASES = [
        'encompasses',
        'precision installation',
        'showcasing',
        'tailored',
        'meticulously',
        'robust',
        'premium',
        'seamless',
        'elevate',
        'enhance',
        'high-value investment',
        'industry best practices',
        'optimal',
        'architectural grade',
        'engineered for',
        'delve',
        'licensed',
        'WSIB',
        'one-person',
        '$3M',
    ];

    /**
     * Longest-first replacements for leftover flourish after generation.
     * Single adjectives that often appear in real product names (premium)
     * are only stripped in a clear marketing phrase, not as a bare word.
     *
     * @var array<string, string>
     */
    private const REPLACEMENTS = [
        'precision installation' => 'installation',
        'precision integration' => 'integration',
        'high-value investment' => 'job',
        'high value investment' => 'job',
        'industry best practices' => 'usual practice',
        'architectural grade' => '',
        'engineered for' => 'for',
        'one-person' => '',
        '$3 million' => '',
        '$3M' => '',
        'encompasses' => 'includes',
        'showcasing' => 'with',
        'meticulously' => '',
        'tailored' => '',
        'seamless' => '',
        'optimal' => '',
        'robust' => '',
        'licensed' => '',
        'WSIB' => '',
        'delving' => '',
        'delves' => '',
        'delve' => '',
        'elevating' => '',
        'elevates' => '',
        'elevate' => '',
    ];

    public static function styleRulesForPrompt(): string
    {
        $banned = implode(', ', self::BANNED_PHRASES);

        return <<<TXT
### Style and tone
Write like a contractor explaining the job to a homeowner. Professional, simple, and natural. Not marketing copy and not AI flourish.

- Third person is fine ("This quote is for...", "The work includes...").
- Short sentences. One or two facts per sentence.
- Plain digits and units: 24 linear feet, 8 ft high, 6x6 posts. Do not spell numbers out.
- Canadian English spelling.
- No dashes or em-dashes. Use commas or a new sentence. No emojis, markdown, or bullet points.
- No sales pitch or closing line. Stop after the last fact.
- Do not invent rooms, finishes, materials, quantities, or work that is not in the quotation parameters.
- If a count is stated (sections, runs, sides, gates, rooms), use that exact number. Do not invent extra sections by dividing length by post spacing, and do not treat rails, pickets, or other material quantities as a section count.
- Only mention cleaning or debris removal if it is listed as included.
- Do not mention prices, dollar amounts, hourly rates, HST, payment schedules, or commute.
- Never say licensed, WSIB, one-person, or any insurance dollar figure (including \$3M). If insurance is mentioned at all, say only "fully insured".

Never use these words or close cousins: {$banned}.
Say "installation" not "precision installation". Say "includes" not "encompasses".

### Example (follow this voice, not these facts; do not copy these numbers onto another job)
This quote is for installing about 24 linear feet of 8 ft high overlapping fence. The fence has 2 sections. Posts are 6x6, set in concrete. Work includes overlapping boards, pressure-treated rails, and post caps.

### Output
Return only the description as plain sentences. One short paragraph is enough. No title, preamble, or closing remark.
TXT;
    }

    public static function apply(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        foreach (self::REPLACEMENTS as $from => $to) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($from, '/').'(?![\p{L}\p{N}])/iu';
            $text = preg_replace($pattern, $to, $text) ?? $text;
        }

        // Drop leftover sales-close clauses without touching earlier facts.
        $text = preg_replace('/\bthat enhances?\b[^.]*\.?/iu', '', $text) ?? $text;
        $text = preg_replace('/\bthat both\b[^.]*\.?/iu', '', $text) ?? $text;
        $text = preg_replace('/\bto deliver a\s*(?:job\s*)?\.?/iu', '.', $text) ?? $text;
        $text = preg_replace('/\bpremium\s+(?=fence|materials|components|pickets|boards)/iu', '', $text) ?? $text;

        return self::strip($text);
    }

    /**
     * Grammar / leftover cleanup after phrase replacements. Safe on already
     * plain contractor wording (no-op besides whitespace).
     */
    public static function strip(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/^```[a-zA-Z0-9_-]*\s*/u', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/u', '', $text) ?? $text;

        $text = preg_replace('/\b(a|an|the)\s+\1\b/iu', '$1', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+,/u', ',', $text) ?? $text;
        $text = preg_replace('/\s+;/u', ';', $text) ?? $text;
        $text = preg_replace('/,\s*,/u', ',', $text) ?? $text;
        $text = preg_replace('/\s+\./u', '.', $text) ?? $text;
        $text = preg_replace('/\.\s*\./u', '.', $text) ?? $text;
        $text = preg_replace('/(?:^|\s)\.(?:\s|$)/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace_callback(
            '/(?:^|[.!?]\s+)(\p{Ll})/u',
            static function (array $m): string {
                return substr($m[0], 0, -strlen($m[1])).mb_strtoupper($m[1]);
            },
            $text
        ) ?? $text;

        return trim($text);
    }
}
