<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Facebook OAuth external identity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $this->addSql('ALTER TABLE "user" ADD facebook_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6499BE8FD98 ON "user" (facebook_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $this->addSql('DROP INDEX UNIQ_8D93D6499BE8FD98');
        $this->addSql('ALTER TABLE "user" DROP facebook_id');
    }
}
