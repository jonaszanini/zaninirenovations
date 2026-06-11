<?php

namespace Tests\Unit\Services;

use App\Models\Material;
use App\Services\MaterialMetadataEnricher;
use PHPUnit\Framework\TestCase;

class MaterialMetadataEnricherTest extends TestCase
{
    private MaterialMetadataEnricher $enricher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enricher = new MaterialMetadataEnricher;
    }

    /**
     * @dataProvider shortNameProvider
     */
    public function test_suggests_short_names_from_store_titles(string $name, string $expectedShort): void
    {
        $material = new Material(['name' => $name]);

        $suggestion = $this->enricher->suggest($material);

        $this->assertSame($expectedShort, $suggestion['short_name']);
    }

    public static function shortNameProvider(): array
    {
        return [
            'fence board' => [
                "1 x 6 x 8' Premium Wood Fence Board (Above Ground Use Only)",
                "8' Fence Board",
            ],
            'wood post' => [
                "4 x 4 x 10' Premium Wood Post (Suitable for Ground Contact)",
                '4x4x10 Post',
            ],
            'pressure treated lumber' => [
                "2 x 4 x 8' Premium Wood (Above Ground Use Only)",
                '2x4x8 PT',
            ],
            'concrete bag' => [
                'Rapid Post, 30 Kg, Multipurpose Easy to Use Concrete Mix, Cement for Fence Posts and Poles',
                'Cement bag 30kg',
            ],
            'fence bracket' => [
                'FB ZMAX Galvanized Fence Rail Bracket for 2x4',
                '2x4 Fence Bracket',
            ],
            'post cap' => [
                '4-inch x 4-inch Metal Black Fence Post Cap with Screws',
                'Post Cap',
            ],
        ];
    }

    public function test_suggests_concrete_category(): void
    {
        $material = new Material([
            'name' => 'Rapid Post, 30 Kg, Multipurpose Easy to Use Concrete Mix',
        ]);

        $suggestion = $this->enricher->suggest($material);

        $this->assertSame(6, $suggestion['category_id']);
        $this->assertSame('bag', $suggestion['unit']);
    }
}
