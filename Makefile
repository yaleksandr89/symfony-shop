NODE_MODULES = ./node_modules
VENDOR = ./vendor

##
## UTILS
## ----------
del-log:
	rm -rf ./var/log

del-cache:
	rm -rf ./var/cache

watch:
	npm run watch

##
## REFACTORING
## -----------

check:
	make refactoring --keep-going

refactoring: eslint php-cs-fixer

eslint:
	${NODE_MODULES}/.bin/eslint assets/js/ --ext .js,.vue --fix

php-cs-fixer:
	${VENDOR}/bin/php-cs-fixer fix src/  --verbose

phpstan:
	${VENDOR}/bin/phpstan analyse src --level 8

##
## TESTING
## -----------

run-test:
	sh ./bin/run-tests.sh