<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211001104011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added ProductImage';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE product_image_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE product_image (id INT NOT NULL, product_id INT NOT NULL, filename_big VARCHAR(255) NOT NULL, filename_middle VARCHAR(255) NOT NULL, filename_small VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_64617F034584665A ON product_image (product_id)');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ALTER zip_code TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE "user" ALTER zip_code DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE product_image_id_seq CASCADE');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('ALTER TABLE "user" ALTER zip_code TYPE INT');
        $this->addSql('ALTER TABLE "user" ALTER zip_code DROP DEFAULT');
    }
}
