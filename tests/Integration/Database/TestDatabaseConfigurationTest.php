<?php

declare(strict_types=1);

namespace App\Tests\Integration\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[Group('integration')]
final class TestDatabaseConfigurationTest extends TestCase
{
    private const string RESET_DSN = 'postgresql://reset_password_test:disposable_reset_password_test@reset-password-postgres:5432/reset_password_concurrency?serverVersion=18.4';
    private const string OAUTH_DSN = 'postgresql://oauth_identity_test:disposable_oauth_identity_test@oauth-postgres:5432/oauth_identity_concurrency?serverVersion=18.4';

    #[DataProvider('allowedTargets')]
    #[TestDox('Doctrine разрешает точную тестовую конфигурацию без подключения')]
    public function testResolvesOnlyTheExpectedTarget(
        string $environment,
        string $mode,
        string $url,
        string $target,
    ): void {
        $process = $this->probe($environment, $mode, $url, '');
        self::assertSame(0, $process->run(), $process->getErrorOutput());
        self::assertSame($target, trim($process->getOutput()));
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function allowedTargets(): iterable
    {
        yield 'core' => ['test', 'sqlite', '', 'pdo_sqlite:' . dirname(__DIR__, 3) . '/var/db_for_test.db'];
        yield 'browser' => ['panther', 'sqlite', '', 'pdo_sqlite:' . dirname(__DIR__, 3) . '/var/db_for_test.db'];
        yield 'reset password' => ['test', 'reset-password-postgresql', self::RESET_DSN, 'pdo_pgsql:reset_password_concurrency_test'];
        yield 'OAuth' => ['test', 'oauth-postgresql', self::OAUTH_DSN, 'pdo_pgsql:oauth_identity_concurrency_test'];
    }

    #[DataProvider('rejectedTargets')]
    #[TestDox('Doctrine отклоняет неподходящую цель до обращения к драйверу')]
    public function testRejectsUnsafeTargetBeforeDriverAccess(
        string $environment,
        string|false $mode,
        string $url,
        string $token = '',
    ): void {
        $process = $this->probe($environment, $mode, $url, $token);
        self::assertSame(23, $process->run(), $process->getErrorOutput());
        self::assertSame('guard refusal', trim($process->getOutput()));
    }

    /** @return iterable<string, array{string, string|false, string, 3?: string}> */
    public static function rejectedTargets(): iterable
    {
        yield 'no guard' => ['test', false, ''];
        yield 'unknown mode' => ['test', 'other', ''];
        yield 'ordinary PostgreSQL' => ['test', 'sqlite', 'postgresql://fake@postgres:5432/development'];
        yield 'alternate SQLite path' => ['test', 'sqlite', 'sqlite:////var/www/html/var/alternate.db'];
        yield 'memory' => ['test', 'sqlite', 'sqlite:///:memory:'];
        yield 'malformed URL' => ['test', 'sqlite', 'invalid://['];
        yield 'Doctrine option override' => ['test', 'sqlite', 'sqlite:////var/www/html/var/db_for_test.db?wrapperClass=Other'];
        yield 'browser DSN' => ['panther', 'sqlite', self::RESET_DSN];
        yield 'browser PostgreSQL mode' => ['panther', 'oauth-postgresql', self::OAUTH_DSN];
        yield 'mismatched mode' => ['test', 'oauth-postgresql', self::RESET_DSN];
        yield 'different endpoint' => ['test', 'reset-password-postgresql', str_replace('reset-password-postgres:', 'unreachable.invalid:', self::RESET_DSN)];
        yield 'different database' => ['test', 'reset-password-postgresql', str_replace('/reset_password_concurrency?', '/other?', self::RESET_DSN)];
        yield 'extra options' => ['test', 'oauth-postgresql', self::OAUTH_DSN . '&dbname_suffix=_wrong'];
        yield 'token changes suffix' => ['test', 'oauth-postgresql', self::OAUTH_DSN, '0'];
    }

    private function probe(string $environment, string|false $mode, string $url, string $token): Process
    {
        return new Process([PHP_BINARY, '-r', <<<'PHP_CODE'
            require 'vendor/autoload.php';
            (new Symfony\Component\Dotenv\Dotenv())->bootEnv('.env');
            $kernel = new App\Kernel(getenv('APP_ENV'), true);
            $kernel->boot();
            try {
                $connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');
            } catch (RuntimeException $error) {
                if ($error->getMessage() !== 'Refusing an unsafe Symfony Shop test database configuration.') {
                    throw $error;
                }
                echo 'guard refusal';
                exit(23);
            }
            if (!$connection instanceof App\Tests\TestUtils\Database\GuardedConnection || $connection->isConnected()) {
                exit(24);
            }
            $params = $connection->getParams();
            echo $params['driver'], ':', $params['path'] ?? $params['dbname'];
            PHP_CODE], dirname(__DIR__, 3), [
            'APP_ENV' => $environment,
            'APP_DEBUG' => '1',
            'SYMFONY_SHOP_TEST_DB_GUARD' => $mode,
            'TEST_DATABASE_URL' => $url,
            'TEST_TOKEN' => $token,
        ]);
    }
}
