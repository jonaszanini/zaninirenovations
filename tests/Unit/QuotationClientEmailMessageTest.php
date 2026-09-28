<?php

namespace Tests\Unit;

use App\Support\QuotationClientEmailMessage;
use Tests\TestCase;

class QuotationClientEmailMessageTest extends TestCase
{
    public function test_empty_input_normalizes_to_null(): void
    {
        $this->assertNull(QuotationClientEmailMessage::normalize(''));
        $this->assertNull(QuotationClientEmailMessage::normalize('   '));
        $this->assertNull(QuotationClientEmailMessage::forEmail(null));
    }

    public function test_plain_text_becomes_paragraphs(): void
    {
        $html = QuotationClientEmailMessage::normalize("First line\n\nSecond & third");

        $this->assertStringContainsString('<p>First line</p>', $html);
        $this->assertStringContainsString('Second &amp; third', $html);
    }

    public function test_scripts_are_stripped_and_formatting_kept(): void
    {
        $html = QuotationClientEmailMessage::normalize('<p><strong>Hi</strong></p><script>alert(1)</script>');

        $this->assertStringContainsString('<strong>Hi</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_for_email_adds_inline_styles(): void
    {
        $html = QuotationClientEmailMessage::forEmail('<p>One</p><ul><li>A</li></ul>');

        $this->assertStringContainsString('<p style="margin:0 0 12px 0;">', $html);
        $this->assertStringContainsString('<li style="margin:4px 0;">', $html);
    }
}
