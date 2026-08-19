<?php

declare(strict_types=1);

namespace Libok\Application\Pagination;

use Symfony\Component\HttpFoundation\Request;

final class PageQuery
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;
    public const DEFAULT_SORT = 'created_at';
    public const DEFAULT_DIRECTION = 'desc';

    /**
     * @param 'asc'|'desc' $sortDir
     */
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly string $sortField,
        public readonly string $sortDir,
    ) {
    }

    /**
     * @param list<string> $allowedSorts
     */
    public static function fromRequest(Request $request, array $allowedSorts = [self::DEFAULT_SORT]): self
    {
        $page = (int) $request->query->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int) $request->query->get('per_page', self::DEFAULT_PER_PAGE);
        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $perPage = min($perPage, self::MAX_PER_PAGE);

        $rawSort = trim((string) $request->query->get('sort', self::DEFAULT_SORT . ':' . self::DEFAULT_DIRECTION));
        $parts = explode(':', $rawSort, 2);
        $field = strtolower($parts[0] !== '' ? $parts[0] : self::DEFAULT_SORT);
        $direction = strtolower($parts[1] ?? self::DEFAULT_DIRECTION);

        if (!in_array($field, $allowedSorts, true)) {
            $field = self::DEFAULT_SORT;
        }
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = self::DEFAULT_DIRECTION;
        }

        return new self($page, $perPage, $field, $direction);
    }
}
