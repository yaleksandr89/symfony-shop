<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce uniqueness of OAuth provider external identities';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64976F5C865 ON "user" (google_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64988FDD79D ON "user" (yandex_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64989588C72 ON "user" (vkontakte_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649D4327649 ON "user" (github_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('DROP INDEX UNIQ_8D93D649D4327649');
        $this->addSql('DROP INDEX UNIQ_8D93D64989588C72');
        $this->addSql('DROP INDEX UNIQ_8D93D64988FDD79D');
        $this->addSql('DROP INDEX UNIQ_8D93D64976F5C865');
    }
}
