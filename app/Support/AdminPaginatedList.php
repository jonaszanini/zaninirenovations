<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class AdminPaginatedList
{
    /** @var array<int|string> */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100, 'all'];

    public static function perPage(Request $request, int $default = 25): int|string
    {
        $value = (string) $request->query('per_page', (string) $default);

        if ($value === 'all') {
            return 'all';
        }

        $int = (int) $value;

        return in_array($int, [10, 25, 50, 100], true) ? $int : $default;
    }

    public static function paginateOrAll(Builder $query, int|string $perPage): LengthAwarePaginator
    {
        if ($perPage === 'all') {
            $items = $query->get();
            $total = $items->count();

            return new Paginator(
                $items,
                $total,
                max($total, 1),
                1,
                [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => 'page',
                    'query' => request()->query(),
                ]
            );
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
