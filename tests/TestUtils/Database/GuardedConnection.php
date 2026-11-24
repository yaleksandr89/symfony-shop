<?php

declare(strict_types=1);

namespace App\Tests\TestUtils\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use SensitiveParameter;

final class GuardedConnection extends Connection
{
    /**
     * RU: Команды Doctrine могут менять файлы SQLite с параметрами подключения без соединения.
     * EN: Doctrine commands can use connection parameters to modify SQLite files without connecting.
     *
     * @param array<string, mixed> $params
     */
    public function __construct(#[SensitiveParameter] array $params, Driver $driver, ?Configuration $config = null)
    {
        TestDatabaseGuard::verify($params);
        parent::__construct($params, $driver, $config);
    }

    protected function connect(): DriverConnection
    {
        if (!$this->isConnected()) {
            TestDatabaseGuard::verify($this->getParams());
        }

        return parent::connect();
    }
}
