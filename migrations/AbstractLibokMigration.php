<?php

declare(strict_types=1);

namespace Libok\Migrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\Migrations\AbstractMigration;
use Libok\Infrastructure\Persistence\PlatformSql;

abstract class AbstractLibokMigration extends AbstractMigration
{
    public function addSql(string $sql, array $params = [], array $types = []): void
    {
        $rewritten = PlatformSql::rewrite($sql, $this->connection->getDatabasePlatform());
        if ($rewritten === null) {
            return;
        }

        parent::addSql($rewritten, $params, $types);
    }

    protected function isMySQL(): bool
    {
        return PlatformSql::isMySQL($this->connection->getDatabasePlatform());
    }

    protected function isPostgreSQL(): bool
    {
        return PlatformSql::isPostgreSQL($this->connection->getDatabasePlatform());
    }

    protected function isSqlite(): bool
    {
        $platform = $this->connection->getDatabasePlatform();

        return $platform instanceof SqlitePlatform
            || str_starts_with($platform->getName(), 'sqlite');
    }
}
