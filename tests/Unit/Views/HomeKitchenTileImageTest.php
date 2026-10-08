<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class HomeKitchenTileImageTest extends TestCase
{
    public function test_kitchen_remodel_tile_uses_dedicated_image_and_file_exists(): void
    {
        $kitchen = $this->serviceTileHtml('Kitchen Remodel');
        $this->assertNotNull($kitchen, 'Expected a Kitchen Remodel service tile on the home page.');

        $this->assertSame(1, preg_match('/<img src="([^"]+)" alt="([^"]+)"/', $kitchen, $img));
        $this->assertSame('images/services/kitchen-remodel.webp', $img[1]);
        $this->assertFileExists(public_path('images/services/kitchen-remodel.webp'));

        $this->assertMatchesRegularExpression('/kitchen remodel/i', $img[2]);
        foreach (['licensed', 'WSIB', '$3M', 'one-person'] as $banned) {
            $this->assertStringNotContainsStringIgnoringCase($banned, $img[2]);
        }
    }

    public function test_tile_tile_does_not_use_kitchen_remodel_image(): void
    {
        $tile = $this->serviceTileHtml('Tile');
        $this->assertNotNull($tile, 'Expected a Tile service tile on the home page.');

        $this->assertSame(1, preg_match('/<img src="([^"]+)"/', $tile, $img));
        $this->assertSame('images/services/tiles.webp', $img[1]);
        $this->assertStringNotContainsString('kitchen-remodel', $img[1]);
        $this->assertStringNotContainsString('images/services/kitchen-remodel.webp', $tile);
    }

    private function serviceTileHtml(string $title): ?string
    {
        $blade = file_get_contents(resource_path('views/site/home.blade.php'));
        $this->assertNotFalse($blade);

        $count = preg_match_all(
            '/<article class="service-block-2 text-center">(.*?)<\/article>/s',
            $blade,
            $articles
        );
        $this->assertGreaterThan(0, $count);

        foreach ($articles[1] as $html) {
            if (str_contains($html, '<h3>'.$title.'</h3>')) {
                return $html;
            }
        }

        return null;
    }
}
