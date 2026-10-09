<?php

namespace App\Models\Concerns;

/**
 * Client PDF commute/debris fold is versioned so saved quotes keep their
 * lines, HST and total. 1 = legacy (main: extras may land on the first
 * taxed line). 2 = Labour or combined materials+labour only.
 */
trait HasPdfFoldVersion
{
    public const PDF_FOLD_VERSION_LEGACY = 1;

    public const PDF_FOLD_VERSION_CURRENT = 2;

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
     * Only an explicit version 2 uses the new fold. Null, 1, and any other
     * value run main's LegacyPdfFoldV1 path.
     */
    public function usesCurrentPdfFold(): bool
    {
        return (int) $this->getAttribute('pdf_fold_version') === self::PDF_FOLD_VERSION_CURRENT;
    }

    public function usesLegacyPdfFold(): bool
    {
        return ! $this->usesCurrentPdfFold();
    }
}
