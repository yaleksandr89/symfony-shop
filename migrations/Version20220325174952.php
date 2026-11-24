<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220325174952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deleted facebookId';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP facebook_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD facebook_id VARCHAR(50) DEFAULT NULL');
    }
}
