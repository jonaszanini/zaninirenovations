<?php

namespace Tests\Unit\Models;

use App\Models\ClientProject;
use PHPUnit\Framework\TestCase;

class ClientProjectShortClientNameTest extends TestCase
{
    public function test_shortens_three_part_names_to_first_two(): void
    {
        $this->assertSame(
            'Patricia Oliveira',
            ClientProject::shortClientNameForList('Patricia Oliveira Garcia'),
        );
    }

    public function test_keeps_two_part_names_unchanged(): void
    {
        $this->assertSame(
            'Michael Barak',
            ClientProject::shortClientNameForList('Michael Barak'),
        );
    }

    public function test_keeps_single_name_unchanged(): void
    {
        $this->assertSame('Madonna', ClientProject::shortClientNameForList('Madonna'));
    }

    public function test_handles_empty_name(): void
    {
        $this->assertSame('', ClientProject::shortClientNameForList(null));
        $this->assertSame('', ClientProject::shortClientNameForList('   '));
    }
}
