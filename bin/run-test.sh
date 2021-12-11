#!/usr/bin/env bash

export APP_ENV=test
echo APP_ENV:'['$APP_ENV']'

symfony console doctrine:database:drop --force
symfony console doctrine:database:create

symfony console doctrine:schema:update --dump-sql
symfony console doctrine:schema:update --force

php bin/console hautelook:fixtures:load -n

symfony php ./vendor/bin/phpunit --testdox --group unit,integration,functional