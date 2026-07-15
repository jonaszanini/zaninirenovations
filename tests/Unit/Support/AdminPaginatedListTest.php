<?php

namespace Tests\Unit\Support;

use App\Support\AdminPaginatedList;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class AdminPaginatedListTest extends TestCase
{
    public function test_per_page_from_allows_custom_options(): void
    {
        $request = Request::create('/', 'GET', ['per_page' => '35']);

        $this->assertSame(
            35,
            AdminPaginatedList::perPageFrom($request, [25, 35, 50], 25),
        );
    }

    public function test_per_page_from_falls_back_to_default_for_invalid_value(): void
    {
        $request = Request::create('/', 'GET', ['per_page' => '99']);

        $this->assertSame(
            25,
            AdminPaginatedList::perPageFrom($request, [25, 35, 50], 25),
        );
    }
}
