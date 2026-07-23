<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce cart identity and cart product uniqueness';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $invalidTokenCount = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM cart
            WHERE token IS NULL
               OR token = ''
               OR token !~ '^[0-9a-f]{32}$'
            SQL
        );
        $this->abortIf(0 < $invalidTokenCount, 'Cannot enforce Cart token invariant: invalid Cart tokens exist.');

        $duplicateTokenGroupCount = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM (
                SELECT token
                FROM cart
                GROUP BY token
                HAVING COUNT(*) > 1
            ) AS duplicate_tokens
            SQL
        );
        $this->abortIf(0 < $duplicateTokenGroupCount, 'Cannot enforce Cart token uniqueness: duplicate Cart tokens exist.');

        $duplicateCartProductGroupCount = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM (
                SELECT cart_id, product_id
                FROM cart_product
                GROUP BY cart_id, product_id
                HAVING COUNT(*) > 1
            ) AS duplicate_cart_products
            SQL
        );
        $this->abortIf(0 < $duplicateCartProductGroupCount, 'Cannot enforce CartProduct uniqueness: duplicate cart/product pairs exist.');

        $this->addSql('ALTER TABLE cart ALTER COLUMN token TYPE VARCHAR(32)');
        $this->addSql('ALTER TABLE cart ALTER COLUMN token SET NOT NULL');
        $this->addSql("ALTER TABLE cart ADD CONSTRAINT chk_cart_token_format CHECK (token ~ '^[0-9a-f]{32}$')");
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_token ON cart (token)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_product_cart_id_product_id ON cart_product (cart_id, product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('DROP INDEX uniq_cart_product_cart_id_product_id');
        $this->addSql('DROP INDEX uniq_cart_token');
        $this->addSql('ALTER TABLE cart DROP CONSTRAINT chk_cart_token_format');
        $this->addSql('ALTER TABLE cart ALTER COLUMN token DROP NOT NULL');
        $this->addSql('ALTER TABLE cart ALTER COLUMN token TYPE VARCHAR(255)');
    }
}
