<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store order totals as exact decimal values';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $invalidCount = (int) $this->connection->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM "order"
WHERE CASE
    WHEN total_price::text IN ('NaN', 'Infinity', '-Infinity') THEN TRUE
    ELSE ABS(ROUND(total_price::numeric, 2)) >= 100000000000000000::numeric
END
SQL
        );
        $this->abortIf(0 < $invalidCount, 'Order total_price contains non-finite or NUMERIC(19,2)-overflow values.');

        $this->addSql('ALTER TABLE "order" ALTER COLUMN total_price TYPE NUMERIC(19, 2) USING ROUND(total_price::numeric, 2)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        // Reverting this migration returns the values to binary floating-point representation.
        $this->addSql('ALTER TABLE "order" ALTER COLUMN total_price TYPE DOUBLE PRECISION USING total_price::double precision');
    }
}
