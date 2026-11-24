<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\ErrorHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

set_exception_handler([new ErrorHandler(), 'handleException']);

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');
}

// RU: Panther запускает приложение в отдельном процессе с тестовой конфигурацией.
// EN: Panther starts the application in a separate process with the test configuration.
$_SERVER['PANTHER_APP_ENV'] = 'panther';
foreach (['PANTHER_EXTERNAL_BASE_URI', 'SYMFONY_PROJECT_DEFAULT_ROUTE_URL', 'PANTHER_WEB_SERVER_ROUTER'] as $variable) {
    if (!empty($_SERVER[$variable]) || !empty($_ENV[$variable]) || getenv($variable)) {
        throw new RuntimeException('Refusing to override the local Panther server.');
    }
}
