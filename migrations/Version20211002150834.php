<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211002150834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added field \'slug\' to Product entity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD slug VARCHAR(128) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD989D9B62 ON product (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_D34A04AD989D9B62');
        $this->addSql('ALTER TABLE product DROP slug');
    }
}
