<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260808141851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add LinkedIn OIDC external identity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $this->addSql('ALTER TABLE "user" ADD linkedin_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64999ABDB52 ON "user" (linkedin_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $this->addSql('DROP INDEX UNIQ_8D93D64999ABDB52');
        $this->addSql('ALTER TABLE "user" DROP linkedin_id');
    }
}
