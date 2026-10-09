<?php

namespace App\Models\Concerns;

/**
 * Client PDF commute/debris fold is versioned so saved quotes keep their
 * lines, HST and total.
 * 1 = legacy (extras may land on the first taxed line).
 * 2 = #60 fold (live since ~01:20; frozen byte-for-byte).
 * 3 = current (labour-category / calculator HST / override footer).
 */
trait HasPdfFoldVersion
{
    public const PDF_FOLD_VERSION_LEGACY = 1;

    public const PDF_FOLD_VERSION_V2 = 2;

    public const PDF_FOLD_VERSION_CURRENT = 3;

    public function initializeHasPdfFoldVersion(): void
    {
        $this->mergeFillable(['pdf_fold_version']);
        $this->mergeCasts(['pdf_fold_version' => 'integer']);
    }

    public static function bootHasPdfFoldVersion(): void
    {
        static::creating(function ($model): void {
            if ($model->getAttribute('pdf_fold_version') !== null) {
                return;
            }

            // ClientProjectQuotation resolves from the calculator source in
            // saving, before grandTotal(), so a first link can stay on v1.
            if ($model instanceof \App\Models\ClientProjectQuotation) {
                return;
            }

            $model->setAttribute('pdf_fold_version', self::PDF_FOLD_VERSION_CURRENT);
        });
    }

    /**
     * Same resolution preview and save use. An explicit version wins; otherwise
     * a linked calculator quote's version is copied; otherwise persisted rows
     * stay on v1 and new unsaved rows preview as v3.
     */
    public function resolvedPdfFoldVersion(): int
    {
        $version = $this->getAttribute('pdf_fold_version');
        if ($version !== null) {
            return (int) $version;
        }

        $fromSource = $this->sourcePdfFoldVersion();
        if ($fromSource !== null) {
            return $fromSource;
        }

        return $this->exists ? self::PDF_FOLD_VERSION_LEGACY : self::PDF_FOLD_VERSION_CURRENT;
    }

    public function usesCurrentPdfFold(): bool
    {
        return $this->resolvedPdfFoldVersion() === self::PDF_FOLD_VERSION_CURRENT;
    }

    public function usesPdfFoldV2(): bool
    {
        return $this->resolvedPdfFoldVersion() === self::PDF_FOLD_VERSION_V2;
    }

    public function usesLegacyPdfFold(): bool
    {
        return $this->resolvedPdfFoldVersion() === self::PDF_FOLD_VERSION_LEGACY;
    }

    protected function sourcePdfFoldVersion(): ?int
    {
        return null;
    }
}
