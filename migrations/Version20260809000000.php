<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260809000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Product merchandising state and update timestamp';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $this->addSql('ALTER TABLE product ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE product SET updated_at = created_at');
        $this->addSql('ALTER TABLE product ALTER updated_at SET NOT NULL');
        $this->addSql('ALTER TABLE product ADD is_new BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE product ADD is_on_sale BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $this->addSql('ALTER TABLE product DROP is_on_sale');
        $this->addSql('ALTER TABLE product DROP is_new');
        $this->addSql('ALTER TABLE product DROP updated_at');
    }
}
