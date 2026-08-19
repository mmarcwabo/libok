<?php

declare(strict_types=1);

namespace Libok\Migrations;

final class Version20260819200000 extends AbstractLibokMigration
{
    public function getDescription(): string
    {
        return 'Add organizations, memberships, and tenant-owned items.';
    }

    public function up(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        if ($this->isSqlite()) {
            $this->addSql('CREATE TABLE organizations (id VARCHAR(36) NOT NULL, slug VARCHAR(120) NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, host VARCHAR(253) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_organizations_slug ON organizations (slug)');
            $this->addSql('CREATE UNIQUE INDEX uniq_organizations_host ON organizations (host)');
            $this->addSql('CREATE INDEX idx_organizations_status ON organizations (status)');
            $this->addSql('CREATE TABLE organization_memberships (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, status VARCHAR(20) NOT NULL, role VARCHAR(20) NOT NULL, is_default BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_org_memberships_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE, CONSTRAINT FK_org_memberships_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_organization_membership ON organization_memberships (organization_id, user_id)');
            $this->addSql('CREATE INDEX idx_membership_user_active ON organization_memberships (user_id, status, is_default)');
            $this->addSql('CREATE TABLE items (id VARCHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, organization_id VARCHAR(36) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX idx_items_organization_created_at ON items (organization_id, created_at)');

            return;
        }

        $this->addSql('CREATE TABLE organizations (id VARCHAR(36) NOT NULL, slug VARCHAR(120) NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, host VARCHAR(253) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_organizations_slug ON organizations (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_organizations_host ON organizations (host)');
        $this->addSql('CREATE INDEX idx_organizations_status ON organizations (status)');
        $this->addSql('CREATE TABLE organization_memberships (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, status VARCHAR(20) NOT NULL, role VARCHAR(20) NOT NULL, is_default BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_membership ON organization_memberships (organization_id, user_id)');
        $this->addSql('CREATE INDEX idx_membership_user_active ON organization_memberships (user_id, status, is_default)');
        $this->addSql('ALTER TABLE organization_memberships ADD CONSTRAINT FK_org_memberships_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_memberships ADD CONSTRAINT FK_org_memberships_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE items (id VARCHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, organization_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_items_organization_created_at ON items (organization_id, created_at)');
    }

    public function down(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        $this->addSql('DROP TABLE items');
        $this->addSql('DROP TABLE organization_memberships');
        $this->addSql('DROP TABLE organizations');
    }
}
