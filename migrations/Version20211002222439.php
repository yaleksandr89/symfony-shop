<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211002222439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added fields: \'uuid\' to the Product entity';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product ADD uuid UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN product.uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('UPDATE product SET uuid=uuid_generate_v4() WHERE uuid IS NULL'); // ONLY postgresql
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP uuid');
    }
}
