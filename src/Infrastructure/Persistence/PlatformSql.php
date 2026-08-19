<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * Rewrites PostgreSQL-oriented DDL/DML fragments for MySQL/MariaDB where needed.
 */
final class PlatformSql
{
    public static function isPostgreSQL(AbstractPlatform $platform): bool
    {
        return $platform instanceof PostgreSQLPlatform
            || str_starts_with($platform->getName(), 'postgresql');
    }

    public static function isMySQL(AbstractPlatform $platform): bool
    {
        return $platform instanceof MySQLPlatform
            || in_array($platform->getName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @return string|null Rewritten SQL, or null when the statement should be skipped
     */
    public static function rewrite(string $sql, AbstractPlatform $platform): ?string
    {
        if (!self::isMySQL($platform)) {
            return $sql;
        }

        $trimmed = ltrim($sql);

        if (preg_match('/^COMMENT\s+ON\s+/i', $trimmed) === 1) {
            return null;
        }

        if (preg_match('/^SET\s+lock_timeout\b/i', $trimmed) === 1) {
            return null;
        }

        if (preg_match('/^ALTER\s+TABLE\s+\S+\s+VALIDATE\s+CONSTRAINT\b/i', $trimmed) === 1) {
            return null;
        }

        if (preg_match('/^DROP\s+INDEX\s+IF\s+EXISTS\s+\w+\s*$/i', $trimmed) === 1) {
            return null;
        }

        $sql = preg_replace('/\bCONCURRENTLY\b/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\s+NOT\s+VALID\b/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\s+NOT\s+DEFERRABLE(?:\s+INITIALLY\s+(?:IMMEDIATE|DEFERRED))?\b/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\s+DEFERRABLE(?:\s+INITIALLY\s+(?:IMMEDIATE|DEFERRED))?\b/i', '', $sql) ?? $sql;

        $sql = preg_replace(
            '/\b(CREATE\s+UNIQUE\s+INDEX\s+\S+\s+ON\s+\S+\s*\([^)]+\))\s+WHERE\s+.+$/i',
            '$1',
            $sql,
        ) ?? $sql;

        $sql = preg_replace(
            '/\bTIMESTAMP(?:\(\d+\))?\s+(?:WITH|WITHOUT)\s+TIME\s+ZONE\b/i',
            'DATETIME',
            $sql,
        ) ?? $sql;
        $sql = str_ireplace('TIMESTAMPTZ', 'DATETIME', $sql);
        $sql = str_ireplace('DOUBLE PRECISION', 'DOUBLE', $sql);
        $sql = preg_replace('/\bBOOLEAN\b/i', 'TINYINT(1)', $sql) ?? $sql;

        $sql = preg_replace('/\bDEFAULT\s+TRUE\b/i', 'DEFAULT 1', $sql) ?? $sql;
        $sql = preg_replace('/\bDEFAULT\s+FALSE\b/i', 'DEFAULT 0', $sql) ?? $sql;
        $sql = preg_replace('/\bDEFAULT\s+true\b/', 'DEFAULT 1', $sql) ?? $sql;
        $sql = preg_replace('/\bDEFAULT\s+false\b/', 'DEFAULT 0', $sql) ?? $sql;

        $sql = preg_replace('/(?<![.\w])TRUE(?![\w])/i', '1', $sql) ?? $sql;
        $sql = preg_replace('/(?<![.\w])FALSE(?![\w])/i', '0', $sql) ?? $sql;

        $sql = preg_replace('/\bDROP\s+TABLE\s+IF\s+EXISTS\s+(\S+)\s+CASCADE\b/i', 'DROP TABLE IF EXISTS $1', $sql) ?? $sql;
        $sql = preg_replace('/\bDROP\s+TABLE\s+(\S+)\s+CASCADE\b/i', 'DROP TABLE $1', $sql) ?? $sql;

        $sql = preg_replace(
            "/'(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})(?:\.\d+)?(?:[Zz]|[+-]\d{2}:?\d{2})'/",
            "'$1'",
            $sql,
        ) ?? $sql;

        return $sql;
    }

    public static function boolLiteral(AbstractPlatform $platform, bool $value): string
    {
        if (self::isMySQL($platform)) {
            return $value ? '1' : '0';
        }

        return $value ? 'TRUE' : 'FALSE';
    }

    public static function castToTimestamp(AbstractPlatform $platform, string $expression): string
    {
        if (self::isMySQL($platform)) {
            return 'CAST(' . $expression . ' AS DATETIME)';
        }

        return $expression . '::timestamp';
    }

    public static function addMinutes(
        AbstractPlatform $platform,
        string $timestampExpression,
        string $minutesExpression,
    ): string {
        if (self::isMySQL($platform)) {
            return sprintf(
                'DATE_ADD(%s, INTERVAL %s MINUTE)',
                $timestampExpression,
                $minutesExpression,
            );
        }

        return sprintf(
            '%s + (%s * INTERVAL \'1 minute\')',
            $timestampExpression,
            $minutesExpression,
        );
    }

    public static function orderNullsLast(AbstractPlatform $platform, string $columnExpression): string
    {
        if (self::isMySQL($platform)) {
            return '(' . $columnExpression . ' IS NULL) ASC';
        }

        return $columnExpression . ' NULLS LAST';
    }
}
