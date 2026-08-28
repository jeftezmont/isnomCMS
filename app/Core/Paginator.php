<?php

namespace App\Core;

final class Paginator
{
    public static function page(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    }

    public static function result(int $requestedPage, int $perPage, int $total): array
    {
        $perPage = max(1, min(100, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $requestedPage), $pages);
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages,
            'offset' => ($page - 1) * $perPage,
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
        ];
    }
}
