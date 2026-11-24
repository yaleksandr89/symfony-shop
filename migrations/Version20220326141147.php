<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220326141147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added vkontakteId';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD vkontakte_id VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP vkontakte_id');
    }
}
