<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220326151023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added githubId';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD github_id VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP github_id');
    }
}
