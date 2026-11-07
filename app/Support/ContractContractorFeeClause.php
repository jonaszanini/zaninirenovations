<?php

namespace App\Support;

/**
 * Deterministic contractor-fee / change-order sentence for newly generated
 * contracts. Old agreed contracts keep their stored body and do not gain this
 * overlay when the per-row flag is off.
 */
class ContractContractorFeeClause
{
    public const SENTENCE = 'A contractor fee applies to materials and subcontracted services, including BCIN drawings and professional engineer signing when required. It does not apply to Zanini hourly labour or the municipal building-permit fee. The same fee also applies to written change orders.';

    public static function isPresent(string $content): bool
    {
        $hay = mb_strtolower(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return str_contains($hay, 'contractor fee')
            && (str_contains($hay, 'written change orders') || str_contains($hay, 'written change order'));
    }

    public static function ensureInMarkdown(string $markdown): string
    {
        if (self::isPresent($markdown)) {
            return $markdown;
        }

        $trimmed = rtrim($markdown);

        return ($trimmed === '' ? '' : $trimmed."\n\n").self::SENTENCE;
    }

    public static function ensureInHtml(string $html): string
    {
        if (self::isPresent($html)) {
            return $html;
        }

        $trimmed = rtrim($html);
        $paragraph = '<p>'.e(self::SENTENCE).'</p>';

        return ($trimmed === '' ? '' : $trimmed."\n").$paragraph;
    }

    public static function shouldRenderOverlay(object $contract): bool
    {
        if (! ($contract->include_contractor_fee_change_order_clause ?? false)) {
            return false;
        }

        return ! self::isPresent((string) ($contract->html_content ?? ''));
    }
}
