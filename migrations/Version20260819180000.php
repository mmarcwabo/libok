<?php

declare(strict_types=1);

namespace Libok\Migrations;

final class Version20260819180000 extends AbstractLibokMigration
{
    public function getDescription(): string
    {
        return 'Add user status/roles and refresh_tokens for cookie JWT auth.';
    }

    public function up(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        if ($this->isSqlite()) {
            $this->addSql('ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT \'active\'');
            $this->addSql('ALTER TABLE users ADD COLUMN roles CLOB NOT NULL DEFAULT \'["member"]\'');
            $this->addSql('CREATE INDEX idx_users_status ON users (status)');
            $this->addSql('CREATE TABLE refresh_tokens (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, token_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent CLOB DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_refresh_tokens_hash ON refresh_tokens (token_hash)');
            $this->addSql('CREATE INDEX idx_refresh_tokens_user ON refresh_tokens (user_id)');
            $this->addSql('CREATE INDEX idx_refresh_tokens_expires_at ON refresh_tokens (expires_at)');

            return;
        }

        $this->addSql('ALTER TABLE users ADD status VARCHAR(20) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE users ADD roles TEXT DEFAULT \'["member"]\' NOT NULL');
        $this->addSql('CREATE INDEX idx_users_status ON users (status)');
        $this->addSql('CREATE TABLE refresh_tokens (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, token_hash VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_refresh_tokens_hash ON refresh_tokens (token_hash)');
        $this->addSql('CREATE INDEX idx_refresh_tokens_user ON refresh_tokens (user_id)');
        $this->addSql('CREATE INDEX idx_refresh_tokens_expires_at ON refresh_tokens (expires_at)');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_tokens');
    }
}
