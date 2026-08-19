<?php

declare(strict_types=1);

namespace Libok\Migrations;

final class Version20260819120000 extends AbstractLibokMigration
{
    public function getDescription(): string
    {
        return 'Create users table.';
    }

    public function up(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        if ($this->isSqlite()) {
            $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
            $this->addSql('CREATE INDEX idx_users_created_at ON users (created_at)');

            return;
        }

        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
        $this->addSql('CREATE INDEX idx_users_created_at ON users (created_at)');
    }

    public function down(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
