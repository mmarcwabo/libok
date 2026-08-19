<?php

declare(strict_types=1);

namespace Libok\Migrations;

final class Version20260819210000 extends AbstractLibokMigration
{
    public function getDescription(): string
    {
        return 'Add outbox_events and idempotency_keys.';
    }

    public function up(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        if ($this->isSqlite()) {
            $this->addSql('CREATE TABLE outbox_events (id VARCHAR(36) NOT NULL, type VARCHAR(100) NOT NULL, payload CLOB NOT NULL, aggregate_id VARCHAR(36) DEFAULT NULL, organization_id VARCHAR(36) DEFAULT NULL, status VARCHAR(20) NOT NULL, attempts INTEGER NOT NULL, max_attempts INTEGER NOT NULL, available_at DATETIME NOT NULL, created_at DATETIME NOT NULL, published_at DATETIME DEFAULT NULL, last_error CLOB DEFAULT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX idx_outbox_claim ON outbox_events (status, available_at, created_at)');
            $this->addSql('CREATE TABLE idempotency_keys (id VARCHAR(36) NOT NULL, idempotency_key VARCHAR(255) NOT NULL, organization_id VARCHAR(120) NOT NULL, actor_id VARCHAR(36) NOT NULL, request_hash VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, response_code INTEGER DEFAULT NULL, response_body CLOB DEFAULT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_idempotency_keys_key_org_actor ON idempotency_keys (idempotency_key, organization_id, actor_id)');
            $this->addSql('CREATE INDEX idx_idempotency_keys_expires_at ON idempotency_keys (expires_at)');

            return;
        }

        $this->addSql('CREATE TABLE outbox_events (id VARCHAR(36) NOT NULL, type VARCHAR(100) NOT NULL, payload TEXT NOT NULL, aggregate_id VARCHAR(36) DEFAULT NULL, organization_id VARCHAR(36) DEFAULT NULL, status VARCHAR(20) NOT NULL, attempts INT NOT NULL, max_attempts INT NOT NULL, available_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, last_error TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_outbox_claim ON outbox_events (status, available_at, created_at)');
        $this->addSql('CREATE TABLE idempotency_keys (id VARCHAR(36) NOT NULL, idempotency_key VARCHAR(255) NOT NULL, organization_id VARCHAR(120) NOT NULL, actor_id VARCHAR(36) NOT NULL, request_hash VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, response_code INT DEFAULT NULL, response_body TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_idempotency_keys_key_org_actor ON idempotency_keys (idempotency_key, organization_id, actor_id)');
        $this->addSql('CREATE INDEX idx_idempotency_keys_expires_at ON idempotency_keys (expires_at)');
    }

    public function down(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        $this->addSql('DROP TABLE idempotency_keys');
        $this->addSql('DROP TABLE outbox_events');
    }
}
