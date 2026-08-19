<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Libok\Infrastructure\Persistence\PlatformSql;
use PHPUnit\Framework\TestCase;

final class PlatformSqlTest extends TestCase
{
    public function testRewritesMysqlTypesAndSkipsPgOnly(): void
    {
        $mysql = new MySQLPlatform();

        self::assertNull(PlatformSql::rewrite(
            "COMMENT ON COLUMN users.created_at IS '(DC2Type:datetimetz_immutable)'",
            $mysql,
        ));

        $ddl = PlatformSql::rewrite(
            'CREATE TABLE t (active BOOLEAN NOT NULL DEFAULT TRUE, weight DOUBLE PRECISION NOT NULL, created_at TIMESTAMPTZ NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL)',
            $mysql,
        );
        self::assertNotNull($ddl);
        self::assertStringContainsString('TINYINT(1)', $ddl);
        self::assertStringContainsString('DEFAULT 1', $ddl);
        self::assertStringContainsString('DOUBLE', $ddl);
        self::assertStringContainsString('DATETIME', $ddl);
        self::assertStringNotContainsString('TIMESTAMPTZ', $ddl);
        self::assertStringNotContainsString('TIME ZONE', $ddl);
        self::assertStringNotContainsString('DATETIME(6)', $ddl);

        $fk = PlatformSql::rewrite(
            'ALTER TABLE items ADD CONSTRAINT FK_1 FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
            $mysql,
        );
        self::assertNotNull($fk);
        self::assertStringNotContainsString('DEFERRABLE', $fk);
        self::assertStringContainsString('REFERENCES users (id)', $fk);

        $index = PlatformSql::rewrite(
            'CREATE UNIQUE INDEX uniq_x ON items (organization_id, source_type, source_id) WHERE source_id IS NOT NULL',
            $mysql,
        );
        self::assertSame(
            'CREATE UNIQUE INDEX uniq_x ON items (organization_id, source_type, source_id)',
            $index,
        );

        $seed = PlatformSql::rewrite(
            "INSERT INTO t (created_at) VALUES ('2026-08-03 13:38:09+01:00'), ('2026-08-03 12:00:00Z')",
            $mysql,
        );
        self::assertSame(
            "INSERT INTO t (created_at) VALUES ('2026-08-03 13:38:09'), ('2026-08-03 12:00:00')",
            $seed,
        );
    }

    public function testLeavesPostgresqlUntouched(): void
    {
        $pg = new PostgreSQLPlatform();
        $sql = 'CREATE TABLE t (active BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL)';
        self::assertSame($sql, PlatformSql::rewrite($sql, $pg));
    }

    public function testPortableHelpers(): void
    {
        $mysql = new MySQLPlatform();
        $pg = new PostgreSQLPlatform();

        self::assertSame('1', PlatformSql::boolLiteral($mysql, true));
        self::assertSame('TRUE', PlatformSql::boolLiteral($pg, true));
        self::assertSame('CAST(c.start_date AS DATETIME)', PlatformSql::castToTimestamp($mysql, 'c.start_date'));
        self::assertSame('c.start_date::timestamp', PlatformSql::castToTimestamp($pg, 'c.start_date'));
        self::assertStringContainsString('DATE_ADD', PlatformSql::addMinutes($mysql, 's.scheduled_at', '5'));
        self::assertStringContainsString("INTERVAL '1 minute'", PlatformSql::addMinutes($pg, 's.scheduled_at', '5'));
        self::assertSame('(organization_id IS NULL) ASC', PlatformSql::orderNullsLast($mysql, 'organization_id'));
        self::assertSame('organization_id NULLS LAST', PlatformSql::orderNullsLast($pg, 'organization_id'));
    }
}
