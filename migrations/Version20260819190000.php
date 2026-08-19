<?php

declare(strict_types=1);

namespace Libok\Migrations;

final class Version20260819190000 extends AbstractLibokMigration
{
    public function getDescription(): string
    {
        return 'Create audit_logs for mutating API requests.';
    }

    public function up(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        if ($this->isSqlite()) {
            $this->addSql('CREATE TABLE audit_logs (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) DEFAULT NULL, action VARCHAR(120) NOT NULL, object_type VARCHAR(80) NOT NULL, object_id VARCHAR(36) DEFAULT NULL, payload CLOB NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent CLOB DEFAULT NULL, request_id VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX idx_audit_logs_user_id ON audit_logs (user_id)');
            $this->addSql('CREATE INDEX idx_audit_logs_action ON audit_logs (action)');
            $this->addSql('CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at)');

            return;
        }

        $this->addSql('CREATE TABLE audit_logs (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) DEFAULT NULL, action VARCHAR(120) NOT NULL, object_type VARCHAR(80) NOT NULL, object_id VARCHAR(36) DEFAULT NULL, payload TEXT NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent TEXT DEFAULT NULL, request_id VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_audit_logs_user_id ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX idx_audit_logs_action ON audit_logs (action)');
        $this->addSql('CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at)');
    }

    public function down(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
    }
}
