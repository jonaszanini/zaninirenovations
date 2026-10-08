<?php

namespace Tests\Feature;

use App\Support\PublicSitemap;
use Tests\IntegrationTestCase;

class PublicPageBannedWordsTest extends IntegrationTestCase
{
    /** @var list<string> */
    private const BANNED = [
        'licensed',
        'wsib',
        'one-person',
        'one person',
    ];

    public function test_licensed_wsib_and_one_person_appear_nowhere_in_public_page_output(): void
    {
        $paths = [];
        foreach (PublicSitemap::urls() as $row) {
            $loc = (string) ($row['loc'] ?? '');
            $path = parse_url($loc, PHP_URL_PATH) ?: '/';
            $paths[$path] = true;
        }
        $paths['/llms.txt'] = true;
        $paths['/robots.txt'] = true;

        $this->assertNotEmpty($paths);

        foreach (array_keys($paths) as $path) {
            $html = strtolower($this->get($path)->assertOk()->getContent());
            foreach (self::BANNED as $word) {
                $this->assertStringNotContainsString(
                    $word,
                    $html,
                    $path.' contains banned “'.$word.'”'
                );
            }
        }

        $notFound = strtolower($this->get('/about-nonexistent')->assertStatus(404)->getContent());
        foreach (self::BANNED as $word) {
            $this->assertStringNotContainsString($word, $notFound, '404 contains banned “'.$word.'”');
        }
    }
}
