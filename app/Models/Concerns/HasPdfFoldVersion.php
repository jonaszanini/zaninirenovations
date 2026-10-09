<?php

namespace App\Models\Concerns;

use App\Models\ClientProjectQuotation;

/**
 * Client PDF fold behaviour is versioned so saved quotes keep their
 * lines, HST and total. 1 = legacy (pre-tax extras land on the first
 * taxed line). 2 = current (fold into labour / combined summary, leftover
 * HST so the PDF grand total matches the editor).
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

            if ($model instanceof ClientProjectQuotation) {
                $source = $model->sourceQuotationModel();
                if ($source !== null && $source->getAttribute('pdf_fold_version') !== null) {
                    $model->setAttribute(
                        'pdf_fold_version',
                        (int) $source->getAttribute('pdf_fold_version')
                    );

                    return;
                }
            }

            $model->setAttribute('pdf_fold_version', self::PDF_FOLD_VERSION_CURRENT);
        });
    }

    public function usesLegacyPdfFold(): bool
    {
        $version = $this->getAttribute('pdf_fold_version');
        if ($version === null) {
            return (bool) $this->exists;
        }

        return (int) $version < self::PDF_FOLD_VERSION_CURRENT;
    }
}
