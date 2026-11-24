<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211125173610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Changed realised cart: PHPSESSID to TOKEN(cookie)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart ADD token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cart DROP session_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart DROP token');
        $this->addSql('ALTER TABLE cart ADD session_id VARCHAR(255) NOT NULL');
    }
}
