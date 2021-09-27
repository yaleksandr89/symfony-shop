<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210927185503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'set default value of the field \'is_deleted\'';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE "user" SET is_deleted=\'0\' WHERE is_deleted IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE "user" SET is_deleted=NULL WHERE is_deleted IS NOT NULL');
    }
}
