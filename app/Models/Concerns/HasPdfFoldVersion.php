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
     * Null means v1 on a persisted row (pre-column quotes and any save
     * that never stored a version). Null on an unsaved model means v2 —
     * a new quote that has not been assigned a version yet.
     */
    public function usesLegacyPdfFold(): bool
    {
        $version = $this->getAttribute('pdf_fold_version');
        if ($version === null) {
            return (bool) $this->exists;
        }

        return (int) $version < self::PDF_FOLD_VERSION_CURRENT;
    }
}
