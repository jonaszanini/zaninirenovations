<?php

namespace Tests\Unit\Services;

use App\Services\ClientProjectDuplicateMatcher;
use PHPUnit\Framework\TestCase;

class ClientProjectDuplicateMatcherTest extends TestCase
{
    private ClientProjectDuplicateMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new ClientProjectDuplicateMatcher;
    }

    public function test_exact_names_are_identical(): void
    {
        $this->assertSame(1.0, $this->matcher->nameSimilarity('Front Deck', 'front deck'));
    }

    public function test_subset_names_match_a_repair_suffix(): void
    {
        $score = $this->matcher->nameSimilarity('Front deck', 'Front Deck Repair');
        $this->assertGreaterThanOrEqual(ClientProjectDuplicateMatcher::NAME_SIMILARITY_THRESHOLD, $score);
    }

    public function test_plural_and_stem_variants_match(): void
    {
        $this->assertGreaterThanOrEqual(
            ClientProjectDuplicateMatcher::NAME_SIMILARITY_THRESHOLD,
            $this->matcher->nameSimilarity('Deck', 'Decks')
        );
        $this->assertGreaterThanOrEqual(
            ClientProjectDuplicateMatcher::NAME_SIMILARITY_THRESHOLD,
            $this->matcher->nameSimilarity('Fence', 'Fencing')
        );
    }

    public function test_unrelated_job_types_do_not_match(): void
    {
        $this->assertLessThan(
            ClientProjectDuplicateMatcher::NAME_SIMILARITY_THRESHOLD,
            $this->matcher->nameSimilarity('Deck', 'Kitchen')
        );
        $this->assertLessThan(
            ClientProjectDuplicateMatcher::NAME_SIMILARITY_THRESHOLD,
            $this->matcher->nameSimilarity('New fence', 'New deck')
        );
    }

    public function test_shared_weak_words_alone_do_not_match(): void
    {
        $this->assertLessThan(
            ClientProjectDuplicateMatcher::NAME_SIMILARITY_THRESHOLD,
            $this->matcher->nameSimilarity('Front patio', 'Front deck')
        );
    }

    public function test_addresses_match_despite_unit_and_province_noise(): void
    {
        $this->assertTrue($this->matcher->addressesLikelySame(
            '209 Bert Hall St, Arnprior, ON K7S 3G3, Canada',
            '209 Bert Hall Street, Arnprior ON',
        ));
        $this->assertFalse($this->matcher->addressesLikelySame(
            '209 Bert Hall St, Arnprior, ON',
            '12 Main Street, Ottawa, ON',
        ));
    }
}
