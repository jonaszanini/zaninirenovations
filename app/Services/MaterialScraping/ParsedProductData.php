<?php

namespace App\Services\MaterialScraping;

/**
 * Normalized product fields extracted from a retailer page or store API.
 */
class ParsedProductData
{
    public function __construct(
        public ?string $name = null,
        public ?string $sku = null,
        public ?float $priceCad = null,
        public ?string $imageUrl = null,
        public ?string $model = null,
        public string $source = 'unknown',
        public ?string $productUrl = null,
        public ?string $vendor = null,
        public ?string $unit = null,
        public ?int $quantityPerPackage = null,
    ) {
    }

    public function isComplete(): bool
    {
        return filled($this->name) && $this->priceCad !== null && $this->priceCad >= 0;
    }

    public function withTitlePackagingHints(): self
    {
        $hints = HomeDepotProductTitleParser::parse($this->name);

        if ($this->unit === null && filled($hints['unit'])) {
            $this->unit = $hints['unit'];
        }

        if ($this->quantityPerPackage === null && $hints['quantity_per_package'] !== null) {
            $this->quantityPerPackage = $hints['quantity_per_package'];
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'price_cad' => $this->priceCad,
            'image_url' => $this->imageUrl,
            'model' => $this->model,
            'product_url' => $this->productUrl,
            'vendor' => $this->vendor,
            'unit' => $this->unit,
            'quantity_per_package' => $this->quantityPerPackage,
            'source' => $this->source,
        ];
    }
}
