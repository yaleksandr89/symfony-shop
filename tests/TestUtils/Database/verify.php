<?php

declare(strict_types=1);

use App\Kernel;
use App\Tests\TestUtils\Database\GuardedConnection;
use App\Tests\TestUtils\Database\TestDatabaseGuard;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

try {
    $environment = getenv('APP_ENV');
    if (!in_array($environment, ['test', 'panther'], true) || getenv(TestDatabaseGuard::MARKER) !== TestDatabaseGuard::SQLITE) {
        throw new RuntimeException();
    }

    new Dotenv()->bootEnv(dirname(__DIR__, 3) . '/.env');
    $kernel = new Kernel($environment, ($_SERVER['APP_DEBUG'] ?? '1') === '1');
    $kernel->boot();
    $connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');
    if (!$connection instanceof GuardedConnection || $connection->isConnected()) {
        throw new RuntimeException();
    }
    TestDatabaseGuard::verify($connection->getParams());
    $kernel->shutdown();
} catch (Throwable) {
    fwrite(STDERR, "Refusing to proceed without a validated local test database.\n");
    exit(1);
}
