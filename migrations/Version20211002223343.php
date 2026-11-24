<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211002223343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The field: \'uuid\' option: \'nullable=true\' is removed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER uuid SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER uuid DROP NOT NULL');
    }
}
