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

    public function test_scotiabank_deposit_own_address_is_not_sender_email(): void
    {
        $text = <<<'TXT'
Your funds have been deposited

$2,260.00 CAD from Zohar Barak has been automatically deposited into your account at Scotiabank at zaninirenovations@gmail.com.

Message: invoice #INV-2026-0257

Reference Number: CAZOHAROWN001

October 8, 2026
TXT;

        $parsed = $this->parser->parse($text);

        $this->assertSame('Zohar Barak', $parsed['sender_name']);
        $this->assertNull($parsed['sender_email']);
        $this->assertSame(2260.00, $parsed['amount']);
        $this->assertSame('invoice #INV-2026-0257', $parsed['message']);
    }

    public function test_payer_email_is_kept_when_own_deposit_address_is_also_present(): void
    {
        $text = <<<'TXT'
Malcolm Travis sent you $379.61 CAD

The money has been automatically deposited into your account at Scotiabank at zaninirenovations@gmail.com.

Sender's email: malcolm.travis@example.com

Message: kitchen balance

Reference Number: CAMALCOLM001
TXT;

        $parsed = $this->parser->parse($text);

        $this->assertSame('Malcolm Travis', $parsed['sender_name']);
        $this->assertSame('malcolm.travis@example.com', $parsed['sender_email']);
        $this->assertSame(379.61, $parsed['amount']);
    }

    public function test_normalize_sender_email_rejects_business_inbox(): void
    {
        $this->assertNull($this->parser->normalizeSenderEmail('zaninirenovations@gmail.com'));
        $this->assertNull($this->parser->normalizeSenderEmail(' notify@payments.interac.ca '));
        $this->assertSame('payer@example.com', $this->parser->normalizeSenderEmail('Payer@example.com'));
    }

    public function test_destination_pattern_filter_skips_bank_at_email(): void
    {
        $line = '$379.61 CAD from Malcolm Travis has been automatically deposited into your account at Scotiabank at dest-only@example.net.';

        $this->assertSame(['dest-only@example.net'], $this->parser->destinationPatternEmails($line));

        $text = <<<TXT
Malcolm Travis sent you \$379.61 CAD
{$line}
Reference Number: CADESTONLY001
TXT;

        $parsed = $this->parser->parse($text);
        $this->assertSame('Malcolm Travis', $parsed['sender_name']);
        $this->assertNull($parsed['sender_email']);
    }

    public function test_deposit_address_strip_removes_destination_email(): void
    {
        $text = <<<'TXT'
Pat Lee sent you $50.00 CAD
Credit posted to your account at Scotiabank at strip-only@example.net.
Reference Number: CASTRIPONLY001
TXT;

        $this->assertSame([], $this->parser->destinationPatternEmails($text));
        $this->assertSame([], $this->parser->depositLineEmails($text));
        $this->assertStringNotContainsString(
            'strip-only@example.net',
            $this->parser->stripDepositDestinationEmails($text)
        );

        $parsed = $this->parser->parse($text);
        $this->assertSame('Pat Lee', $parsed['sender_name']);
        $this->assertNull($parsed['sender_email']);
    }

    public function test_deposit_line_filter_catches_email_destination_regex_misses(): void
    {
        $line = 'Funds have been deposited into your account at Scotiabank. Write line-only@example.net';

        $this->assertSame([], $this->parser->destinationPatternEmails($line));
        $this->assertSame(['line-only@example.net'], $this->parser->depositLineEmails($line));

        $text = <<<TXT
Pat Lee sent you \$50.00 CAD
{$line}
Reference Number: CALINEONLY001
TXT;

        $parsed = $this->parser->parse($text);
        $this->assertSame('Pat Lee', $parsed['sender_name']);
        $this->assertNull($parsed['sender_email']);
    }

    public function test_forwarded_unconfigured_alias_is_not_sender_email(): void
    {
        $text = <<<'TXT'
From: Forward Desk <alias@forwarded.example>
To: inbox@forwarded.example
Jane Citizen sent you $50.00 CAD
Reference Number: CAFWDALIAS001
TXT;

        $this->assertStringNotContainsString(
            'alias@forwarded.example',
            $this->parser->stripForwardedHeaderLines($text)
        );
        $this->assertStringNotContainsString(
            'inbox@forwarded.example',
            $this->parser->stripForwardedHeaderLines($text)
        );

        $parsed = $this->parser->parse($text);
        $this->assertSame('Jane Citizen', $parsed['sender_name']);
        $this->assertNull($parsed['sender_email']);
    }

    public function test_payer_email_is_kept_when_forwarded_headers_are_present(): void
    {
        $text = <<<'TXT'
From: alias@forwarded.example
To: inbox@forwarded.example
Malcolm Travis sent you $379.61 CAD
Sender's email: malcolm.travis@example.com
Reference Number: CAFWDPAYER001
TXT;

        $parsed = $this->parser->parse($text);
        $this->assertSame('Malcolm Travis', $parsed['sender_name']);
        $this->assertSame('malcolm.travis@example.com', $parsed['sender_email']);
    }

    public function test_french_quoted_and_outlook_forwarded_headers_are_stripped(): void
    {
        $text = <<<'TXT'
De : Alias Desk <alias@forwarded.example>
À : inbox@forwarded.example
> From: quoted@forwarded.example
*From:* outlook@forwarded.example
Jane Citizen sent you $50.00 CAD
Reference Number: CAFWDVAR001
TXT;

        $stripped = $this->parser->stripForwardedHeaderLines($text);
        $this->assertStringNotContainsString('alias@forwarded.example', $stripped);
        $this->assertStringNotContainsString('inbox@forwarded.example', $stripped);
        $this->assertStringNotContainsString('quoted@forwarded.example', $stripped);
        $this->assertStringNotContainsString('outlook@forwarded.example', $stripped);

        $parsed = $this->parser->parse($text);
        $this->assertSame('Jane Citizen', $parsed['sender_name']);
        $this->assertNull($parsed['sender_email']);
    }

    public function test_reply_to_payer_email_is_used_when_it_is_the_only_address(): void
    {
        $text = <<<'TXT'
From: Zanini Renovations <zaninirenovations@gmail.com>
To: zaninirenovations@gmail.com
Reply-To: malcolm.travis@example.com
Malcolm Travis sent you $379.61 CAD
Reference Number: CAREPLYTO001
TXT;

        $this->assertSame(['malcolm.travis@example.com'], $this->parser->replyToEmails($text));

        $parsed = $this->parser->parse($text);
        $this->assertSame('Malcolm Travis', $parsed['sender_name']);
        $this->assertSame('malcolm.travis@example.com', $parsed['sender_email']);
    }

    public function test_own_reply_to_is_not_stored_as_sender_email(): void
    {
        $text = <<<'TXT'
From: notify@payments.interac.ca
Reply-To: zaninirenovations@gmail.com
Jane Citizen sent you $50.00 CAD
Reference Number: CAREPLYOWN001
TXT;

        $parsed = $this->parser->parse($text);
        $this->assertSame('Jane Citizen', $parsed['sender_name']);
        $this->assertNull($parsed['sender_email']);
    }
}
