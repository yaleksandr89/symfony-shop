<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211003091352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increased precision price field \'price\' to Product entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER price TYPE NUMERIC(15, 2)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER price TYPE NUMERIC(6, 2)');
    }
}
