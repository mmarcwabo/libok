<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Pagination;

use Libok\Application\Pagination\PageQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PageQueryTest extends TestCase
{
    public function testAppliesDefaultsAndCapsPerPage(): void
    {
        $default = PageQuery::fromRequest(Request::create('/api/v1/users'));
        self::assertSame(1, $default->page);
        self::assertSame(20, $default->perPage);
        self::assertSame('created_at', $default->sortField);
        self::assertSame('desc', $default->sortDir);

        $capped = PageQuery::fromRequest(
            Request::create('/api/v1/users?page=2&per_page=500&sort=email:asc'),
            ['created_at', 'email', 'name'],
        );
        self::assertSame(2, $capped->page);
        self::assertSame(100, $capped->perPage);
        self::assertSame('email', $capped->sortField);
        self::assertSame('asc', $capped->sortDir);

        $unknown = PageQuery::fromRequest(
            Request::create('/api/v1/users?sort=password:desc'),
            ['created_at', 'email', 'name'],
        );
        self::assertSame('created_at', $unknown->sortField);
    }
}
