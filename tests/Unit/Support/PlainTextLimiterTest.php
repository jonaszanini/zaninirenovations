<?php

namespace Tests\Unit\Support;

use App\Support\PlainTextLimiter;
use Tests\TestCase;

class PlainTextLimiterTest extends TestCase
{
    public function test_short_text_is_unchanged(): void
    {
        $this->assertSame('Revive your deck this weekend.', PlainTextLimiter::trimToLength('Revive your deck this weekend.', 155));
    }

    public function test_trims_at_sentence_boundary(): void
    {
        $text = 'Protect your deck from moisture and winter damage. Extra filler words are added here so the second sentence is well past the character budget for a meta description.';
        $out = PlainTextLimiter::trimToLength($text, 155);

        $this->assertSame('Protect your deck from moisture and winter damage.', $out);
        $this->assertLessThanOrEqual(155, mb_strlen($out));
    }

    public function test_does_not_cut_mid_word_or_leave_trailing_comma(): void
    {
        $text = 'Protect your deck from moisture UV and winter damage, plus extra words that make this longer than the limit for Ottawa homeowners';
        $out = PlainTextLimiter::trimToLength($text, 58);

        $this->assertLessThanOrEqual(58, mb_strlen($out));
        $this->assertStringEndsNotWith(',', $out);
        $this->assertMatchesRegularExpression('/[A-Za-z]$/u', $out);
        $this->assertStringNotContainsString('extra', $out);
    }

    public function test_uses_multibyte_length_for_accents(): void
    {
        $text = 'Rénovation complète de la terrasse pour les propriétaires d\'Ottawa et de Gatineau, avec plus de texte pour dépasser la limite.';
        $out = PlainTextLimiter::trimToLength($text, 40);

        $this->assertLessThanOrEqual(40, mb_strlen($out));
        $this->assertStringNotContainsString('�', $out);
        $this->assertStringEndsNotWith(',', $out);
    }

    public function test_filesystems_config_trims_trailing_slash_on_app_url(): void
    {
        $appUrl = 'https://zaninirenovations.ca/';
        $url = rtrim((string) $appUrl, '/').'/storage/blog-featured/1/x.webp';

        $this->assertSame('https://zaninirenovations.ca/storage/blog-featured/1/x.webp', $url);
        $this->assertStringNotContainsString('//storage', $url);

        $config = file_get_contents(config_path('filesystems.php'));
        $this->assertIsString($config);
        $this->assertStringContainsString("rtrim((string) env('APP_URL'", $config);
    }
}
