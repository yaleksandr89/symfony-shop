<?php

declare(strict_types=1);

namespace App\Tests\TestUtils\Database;

use Closure;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

final readonly class TestDatabaseGuard implements EnvVarProcessorInterface
{
    public const string MARKER = 'SYMFONY_SHOP_TEST_DB_GUARD';
    public const string SQLITE = 'sqlite';

    private const array POSTGRESQL = [
        'reset-password-postgresql' => [
            'host' => 'reset-password-postgres',
            'database' => 'reset_password_concurrency',
            'user' => 'reset_password_test',
            'password' => 'disposable_reset_password_test',
        ],
        'oauth-postgresql' => [
            'host' => 'oauth-postgres',
            'database' => 'oauth_identity_concurrency',
            'user' => 'oauth_identity_test',
            'password' => 'disposable_oauth_identity_test',
        ],
    ];

    public function __construct(private string $environment, private string $projectDir) {}

    public static function getProvidedTypes(): array
    {
        return ['shop_test_db' => 'string'];
    }

    public function getEnv(string $prefix, string $name, Closure $getEnv): string
    {
        $url = $getEnv($name);
        $processUrl = getenv('TEST_DATABASE_URL');
        $mode = getenv(self::MARKER);

        if ($mode === self::SQLITE) {
            if (($url !== null && $url !== '') || ($processUrl !== false && $processUrl !== '')) {
                throw self::refusal();
            }

            return 'sqlite:///' . self::sqlitePath($this->projectDir);
        }

        $target = self::POSTGRESQL[$mode] ?? null;
        if ($this->environment !== 'test' || $target === null) {
            throw self::refusal();
        }

        $expected = sprintf(
            'postgresql://%s:%s@%s:5432/%s?serverVersion=18.4',
            $target['user'],
            $target['password'],
            $target['host'],
            $target['database'],
        );
        if ($url !== $expected || $processUrl !== $expected) {
            throw self::refusal();
        }

        // RU: Doctrine разбирает параметры запроса DSN как параметры подключения.
        // EN: Doctrine parses DSN query parameters as connection options.
        return $expected;
    }

    /** @param array<string, mixed> $params */
    public static function verify(#[SensitiveParameter] array $params): void
    {
        $mode = getenv(self::MARKER);
        if (($params['dbname_suffix'] ?? null) !== '_test'
            || !in_array(getenv('TEST_TOKEN'), [false, ''], true)
            || ($params['driverOptions'] ?? []) !== []
            || array_intersect_key($params, array_flip(['pdo', 'primary', 'replica', 'driverClass', 'persistent', 'memory'])) !== []
        ) {
            throw self::refusal();
        }

        if ($mode === self::SQLITE) {
            $root = dirname(__DIR__, 3);
            $path = self::sqlitePath($root);
            if (($params['driver'] ?? null) !== 'pdo_sqlite' || ($params['path'] ?? null) !== $path) {
                throw self::refusal();
            }

            self::verifySqliteFiles($root, $path);

            return;
        }

        $target = self::POSTGRESQL[$mode] ?? null;
        if ($target === null
            || ($params['driver'] ?? null) !== 'pdo_pgsql'
            || ($params['host'] ?? null) !== $target['host']
            || ($params['dbname'] ?? null) !== $target['database'] . '_test'
            || ($params['user'] ?? null) !== $target['user']
            || ($params['password'] ?? null) !== $target['password']
            || ($params['port'] ?? null) !== 5432
            || ($params['serverVersion'] ?? null) !== '18.4'
        ) {
            throw self::refusal();
        }
    }

    private static function sqlitePath(string $projectDir): string
    {
        return $projectDir . '/var/db_for_test.db';
    }

    private static function verifySqliteFiles(string $root, string $path): void
    {
        clearstatcache(true);
        $directory = $root . '/var';
        if (realpath($directory) !== $directory || !is_dir($directory) || !is_readable($directory) || !is_executable($directory)) {
            throw self::refusal();
        }

        foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
            $file = $path . $suffix;
            $stat = @lstat($file);
            if ($stat !== false && (($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1)) {
                throw self::refusal();
            }
        }
    }

    private static function refusal(): RuntimeException
    {
        return new RuntimeException('Refusing an unsafe Symfony Shop test database configuration.');
    }
}
