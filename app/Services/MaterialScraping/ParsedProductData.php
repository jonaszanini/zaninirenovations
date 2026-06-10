<?php

namespace App\Services\MaterialScraping;

/**
 * Normalized product fields extracted from a retailer page.
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
    ) {
    }

    public function isComplete(): bool
    {
        return filled($this->name) && $this->priceCad !== null && $this->priceCad >= 0;
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
            'source' => $this->source,
        ];
    }
}
