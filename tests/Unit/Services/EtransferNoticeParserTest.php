<?php

namespace Tests\Unit\Services;

use App\Services\Etransfer\EtransferNoticeParser;
use Tests\TestCase;

class EtransferNoticeParserTest extends TestCase
{
    private EtransferNoticeParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new EtransferNoticeParser;
    }

    public function test_english_sent_you_money(): void
    {
        $text = <<<'TXT'
INTERAC e-Transfer

Jane Citizen sent you $1,130.00 CAD

The money is now in your account.

Message: INV-2026-0001 kitchen deposit

Reference Number: CA1B2C3D4E5F6

Sent: October 6, 2026 at 4:32 PM ET

Sender's email: jane.citizen@example.com
TXT;

        $parsed = $this->parser->parse($text);

        $this->assertSame('Jane Citizen', $parsed['sender_name']);
        $this->assertSame('jane.citizen@example.com', $parsed['sender_email']);
        $this->assertSame(1130.00, $parsed['amount']);
        $this->assertSame('CAD', $parsed['currency']);
        $this->assertSame('CA1B2C3D4E5F6', $parsed['reference_number']);
        $this->assertSame('INV-2026-0001 kitchen deposit', $parsed['message']);
        $this->assertNotNull($parsed['received_at']);
        $this->assertSame('2026-10-06', $parsed['received_at']->toDateString());
    }

    public function test_english_auto_deposit(): void
    {
        $text = <<<'TXT'
Your funds have been deposited

$2,500.00 CAD from Alex Martin has been automatically deposited into your account.

Message: For the fence deposit

Reference Number: CA9Z8Y7X6W5V4

October 5, 2026
TXT;

        $parsed = $this->parser->parse($text);

        $this->assertSame('Alex Martin', $parsed['sender_name']);
        $this->assertSame(2500.00, $parsed['amount']);
        $this->assertSame('CA9Z8Y7X6W5V4', $parsed['reference_number']);
        $this->assertSame('For the fence deposit', $parsed['message']);
        $this->assertSame('2026-10-05', $parsed['received_at']->toDateString());
    }

    public function test_french_sent_you_money(): void
    {
        $text = <<<'TXT'
INTERAC e-Transfer

Marie Dupont vous a envoyé 850,00 $ CA

L'argent est maintenant dans votre compte.

Message : Acompte patio

Numéro de référence : CAFR123456789

Envoyé : 4 octobre 2026 à 10 h 15 HE
TXT;

        $parsed = $this->parser->parse($text);

        $this->assertSame('Marie Dupont', $parsed['sender_name']);
        $this->assertSame(850.00, $parsed['amount']);
        $this->assertSame('CAFR123456789', $parsed['reference_number']);
        $this->assertSame('Acompte patio', $parsed['message']);
        $this->assertSame('2026-10-04', $parsed['received_at']->toDateString());
    }

    public function test_french_auto_deposit(): void
    {
        $text = <<<'TXT'
Vos fonds ont été déposés

1 130,50 $ CA de Jean Tremblay ont été déposés automatiquement dans votre compte.

Message : INV-2026-0042

Numéro de référence : CA000111222333
TXT;

        $parsed = $this->parser->parse($text);

        $this->assertSame('Jean Tremblay', $parsed['sender_name']);
        $this->assertSame(1130.50, $parsed['amount']);
        $this->assertSame('CA000111222333', $parsed['reference_number']);
        $this->assertSame('INV-2026-0042', $parsed['message']);
    }

    public function test_ignores_interac_notify_email_as_sender(): void
    {
        $text = <<<'TXT'
From: notify@payments.interac.ca
Jane Citizen sent you $50.00 CAD
Reference Number: CAIGNOREEMAIL1
TXT;

        $parsed = $this->parser->parse($text);

        $this->assertSame('Jane Citizen', $parsed['sender_name']);
        $this->assertNull($parsed['sender_email']);
    }

    public function test_blank_message_is_null(): void
    {
        $text = <<<'TXT'
Pat Lee sent you $100.00
Message: n/a
Reference Number: CABLANKMSG0001
TXT;

        $parsed = $this->parser->parse($text);

        $this->assertNull($parsed['message']);
        $this->assertSame(100.00, $parsed['amount']);
    }
}
