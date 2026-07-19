<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cascade commerce-line deletion when their aggregate root is deleted';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_product DROP CONSTRAINT FK_2530ADE6851F0D95');
        $this->addSql('ALTER TABLE order_product ADD CONSTRAINT FK_2530ADE6851F0D95 FOREIGN KEY (app_order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart_product DROP CONSTRAINT FK_2890CCAA1AD5CDBF');
        $this->addSql('ALTER TABLE cart_product ADD CONSTRAINT FK_2890CCAA1AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_product DROP CONSTRAINT FK_2530ADE6851F0D95');
        $this->addSql('ALTER TABLE order_product ADD CONSTRAINT FK_2530ADE6851F0D95 FOREIGN KEY (app_order_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart_product DROP CONSTRAINT FK_2890CCAA1AD5CDBF');
        $this->addSql('ALTER TABLE cart_product ADD CONSTRAINT FK_2890CCAA1AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
