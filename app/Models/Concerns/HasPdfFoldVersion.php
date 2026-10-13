<?php

namespace App\Models\Concerns;

use App\Support\Quotations\PdfFoldVersion;

/**
 * Client PDF commute/debris fold is versioned so saved quotes keep their
 * lines, HST and total. Version numbers live on {@see PdfFoldVersion}.
 */
trait HasPdfFoldVersion
{
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

            $model->setAttribute('pdf_fold_version', PdfFoldVersion::CURRENT);
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

        return $this->exists ? PdfFoldVersion::LEGACY : PdfFoldVersion::CURRENT;
    }

    public function usesCurrentPdfFold(): bool
    {
        return $this->resolvedPdfFoldVersion() === PdfFoldVersion::CURRENT;
    }

    public function usesPdfFoldV2(): bool
    {
        return $this->resolvedPdfFoldVersion() === PdfFoldVersion::V2;
    }

    /**
     * v2 and v3 share leftover hst_amount emission. 0 / unknown versions
     * follow #60 and stay on legacy.
     */
    public function usesNonLegacyPdfFold(): bool
    {
        return $this->usesPdfFoldV2() || $this->usesCurrentPdfFold();
    }

    public function usesLegacyPdfFold(): bool
    {
        return ! $this->usesNonLegacyPdfFold();
    }

    protected function sourcePdfFoldVersion(): ?int
    {
        return null;
    }
}
