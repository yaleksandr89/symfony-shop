<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211004063505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added \'isDeleted\' field to the Category entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD is_deleted BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP is_deleted');
    }
}
