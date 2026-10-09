<?php

namespace Tests\Unit\Support;

use App\Support\QuotationWorkDescriptionStyle;
use PHPUnit\Framework\TestCase;

class QuotationWorkDescriptionStyleTest extends TestCase
{
    public function test_replaces_worst_flourish_without_dropping_facts(): void
    {
        $input = 'This project encompasses the precision installation of an approximately fifty-eight linear foot perimeter enclosure, standing six feet in height and showcasing a classic board style. This tailored renovation will feature structural 4x4 posts, securely anchored with robust concrete footings, engineered for enduring stability and compliance with industry best practices. The design incorporates eight distinct fence sections, alongside the creation and precision integration of one four-foot wide gate assembly, complete with essential anti-sagging hardware for optimal functionality. All components, including premium fence board pickets, durable pressure treated fence rails, and architectural grade post caps, will be meticulously installed to deliver a high value investment that enhances both security and aesthetic appeal.';

        $out = QuotationWorkDescriptionStyle::apply($input);

        $this->assertStringContainsString('includes the installation', $out);
        $this->assertStringContainsString('4x4 posts', $out);
        $this->assertStringContainsString('concrete footings', $out);
        $this->assertStringContainsString('anti-sagging hardware', $out);
        $this->assertStringContainsString('fence board pickets', $out);
        $this->assertStringContainsString('pressure treated fence rails', $out);
        $this->assertStringContainsString('post caps', $out);

        $this->assertStringNotContainsStringIgnoringCase('precision installation', $out);
        $this->assertStringNotContainsStringIgnoringCase('encompasses', $out);
        $this->assertStringNotContainsStringIgnoringCase('showcasing', $out);
        $this->assertStringNotContainsStringIgnoringCase('meticulously', $out);
        $this->assertStringNotContainsStringIgnoringCase('architectural grade', $out);
        $this->assertStringNotContainsStringIgnoringCase('industry best practices', $out);
        $this->assertStringNotContainsStringIgnoringCase('high value investment', $out);
        $this->assertStringNotContainsStringIgnoringCase('enhances both', $out);
        $this->assertStringNotContainsStringIgnoringCase('that both', $out);
        $this->assertStringNotContainsStringIgnoringCase('to deliver a', $out);
        $this->assertStringNotContainsStringIgnoringCase('tailored', $out);
        $this->assertStringNotContainsStringIgnoringCase('robust', $out);
        $this->assertStringNotContainsStringIgnoringCase('premium', $out);
        $this->assertDoesNotMatchRegularExpression('/\s{2,}/', $out);
        $this->assertDoesNotMatchRegularExpression('/\s[.,]/', $out);
        $this->assertStringContainsString('will be installed.', $out);
        $this->assertMatchesRegularExpression('/^[A-Z]/', $out);
    }

    public function test_keeps_fully_insured_and_strips_banned_claims(): void
    {
        $out = QuotationWorkDescriptionStyle::apply(
            'Zanini Renovations is licensed, WSIB registered, a one-person shop with $3M insurance, and fully insured.'
        );

        $this->assertStringContainsString('fully insured', $out);
        $this->assertStringNotContainsStringIgnoringCase('licensed', $out);
        $this->assertStringNotContainsStringIgnoringCase('WSIB', $out);
        $this->assertStringNotContainsStringIgnoringCase('one-person', $out);
        $this->assertStringNotContainsStringIgnoringCase('$3M', $out);
    }

    public function test_does_not_mangle_plain_contractor_wording(): void
    {
        $plain = 'This quote is for installing about 58 linear feet of 6 ft high board-style fence. The fence has 3 sections. Posts are 4x4, set in concrete.';

        $this->assertSame($plain, QuotationWorkDescriptionStyle::apply($plain));
    }

    public function test_strip_cleans_leftover_sales_fragments_and_empty_input(): void
    {
        $this->assertSame('', QuotationWorkDescriptionStyle::strip('   '));
        $this->assertSame('', QuotationWorkDescriptionStyle::strip('```'));
        $this->assertSame(
            'Posts are 4x4, set in concrete.',
            QuotationWorkDescriptionStyle::strip('Posts are 4x4, set in concrete.')
        );
        $this->assertSame(
            'The backyard will get a new fence.',
            QuotationWorkDescriptionStyle::apply('Elevate the backyard will get a new fence.')
        );
        $this->assertStringContainsString(
            'Behr Premium Plus',
            QuotationWorkDescriptionStyle::apply('Paint is Behr Premium Plus on the walls.')
        );
        $this->assertSame(
            'The fence has two gates that both swing inward. Posts are 6x6, set in concrete.',
            QuotationWorkDescriptionStyle::apply('The fence has two gates that both swing inward. Posts are 6x6, set in concrete.')
        );
        $this->assertSame(
            'About 120 sq. ft. with 16 in. on centre, e.g. around the island.',
            QuotationWorkDescriptionStyle::apply('About 120 sq. ft. with 16 in. on centre, e.g. around the island.')
        );
        $this->assertSame(
            'Build a A-frame shed. Approx. 8 ft. high.',
            QuotationWorkDescriptionStyle::apply('Build a A-frame shed. approx. 8 ft. high.')
        );
    }
}
