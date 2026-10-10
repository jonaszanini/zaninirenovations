<?php

namespace Tests\Unit\Support;

use App\Support\QuotationDraftFlash;
use Tests\TestCase;

class QuotationDraftFlashTest extends TestCase
{
    public function test_draft_update_says_saved_as_a_draft(): void
    {
        $quote = (object) [
            'is_draft' => true,
            'quote_number' => 'BR-1',
            'version' => 4,
        ];

        $this->assertSame(
            'Basement quotation saved as a draft.',
            QuotationDraftFlash::updated('Basement', $quote, true, true)
        );

        $paused = (object) [
            'is_draft' => true,
            'client_share_paused' => true,
        ];
        $this->assertSame(
            'Basement quotation saved as a draft. The client\'s link is paused until you publish again.',
            QuotationDraftFlash::updated('Basement', $paused, false, false)
        );
        $this->assertSame(
            'Basement quotation saved as a draft.',
            QuotationDraftFlash::updated('Basement', $paused, false, true)
        );
    }

    public function test_publish_update_keeps_version_or_linked_copy(): void
    {
        $quote = (object) [
            'is_draft' => false,
            'quote_number' => 'FQ-9',
            'version' => 3,
        ];

        $this->assertSame(
            'Fence quotation FQ-9 updated (v3).',
            QuotationDraftFlash::updated('Fence', $quote, false, false)
        );
        $this->assertSame(
            'Fence quotation FQ-9 updated and linked.',
            QuotationDraftFlash::updated('Fence', $quote, true, false)
        );
    }

    public function test_was_draft_before_save_has_no_default(): void
    {
        $param = (new \ReflectionMethod(QuotationDraftFlash::class, 'updated'))
            ->getParameters()[3];

        $this->assertSame('wasDraftBeforeSave', $param->getName());
        $this->assertFalse($param->isDefaultValueAvailable());
    }
}
