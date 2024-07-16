#!/usr/bin/env bash

export APP_ENV=test
echo APP_ENV:'['$APP_ENV']'

php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create

#php bin/console doctrine:schema:update --dump-sql # выводит на экран сгенерированный sql запрос
php bin/console doctrine:schema:update --complete --force

php bin/console hautelook:fixtures:load -n

php ./vendor/bin/phpunit --testdox --group unit --group integration --group functional  --group functional-selenium --group functional-panther
# --log-events-verbose-text log-execute-phpunit.txt #если нужно посмотреть логи выполнения тестов (сохраняется в корень проекта)