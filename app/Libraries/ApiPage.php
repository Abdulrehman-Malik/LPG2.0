<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Pagination for /api/v1 list endpoints (BL-019): `page` (1…) and `per_page` (1…100, default 50)
 * in the query string; responses carry meta {page, per_page, total, has_more}.
 */
final class ApiPage
{
    public const MAX_PER_PAGE = 100;
    public const DEFAULT_PER_PAGE = 50;

    /** @return array{0:int,1:int} page, per_page */
    public static function params(RequestInterface $request): array
    {
        $page = max(1, (int) ($request->getGet('page') ?? 1));
        $perPage = (int) ($request->getGet('per_page') ?? self::DEFAULT_PER_PAGE);
        return [$page, max(1, min(self::MAX_PER_PAGE, $perPage ?: self::DEFAULT_PER_PAGE))];
    }

    /**
     * One page of a builder (which must not have limit/offset yet).
     *
     * @return array{0:list<array<string,mixed>>,1:array<string,int|bool>}
     */
    public static function builder(BaseBuilder $builder, RequestInterface $request): array
    {
        [$page, $perPage] = self::params($request);
        $total = (int) (clone $builder)->countAllResults(false);
        $rows = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return [$rows, self::meta($page, $perPage, $total)];
    }

    /**
     * One page of an already loaded list.
     *
     * @return array{0:list<mixed>,1:array<string,int|bool>}
     */
    public static function rows(array $rows, RequestInterface $request): array
    {
        [$page, $perPage] = self::params($request);
        return [array_values(array_slice($rows, ($page - 1) * $perPage, $perPage)), self::meta($page, $perPage, count($rows))];
    }

    private static function meta(int $page, int $perPage, int $total): array
    {
        return ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'has_more' => $page * $perPage < $total];
    }
}
