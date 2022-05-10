#!/usr/bin/env bash

export APP_ENV=test
echo APP_ENV:'['$APP_ENV']'

php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create

php bin/console doctrine:schema:update --dump-sql
php bin/console doctrine:schema:update --force

php bin/console hautelook:fixtures:load -n

php ./vendor/bin/phpunit --testdox --group unit,integration,functional,functional-panther,functional-selenium
