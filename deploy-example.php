<?php

namespace Deployer;

require 'recipe/symfony.php';

set('ssh_type', 'native');
set('ssh_multiplexing', true);
set('bin/console', '{{bin/php}} {{release_path}}/bin/console');

// Project name
set('application', '[Pet project] Symfony shop');

// Project repository
set('repository', 'git@github.com:yaleksandr89/symfony-shop.git');

set('composer_options', '{{composer_actions}} --verbose --prefer-dist --no-progress --no-interaction --optimize-autoload --no-script');

set('keep_release', 3);

// Shared files/dirs between deploys
add('shared_files', ['.env']);
add('shared_dirs', ['var/log', 'public/uploads']);

// Writable dirs by web server
add('writable_dirs', ['var', 'public/uploads']);

set('allow_anonymous_stats', false);

// Hosts
host('..............')
    ->hostname('..............')
    ->port(22)
    ->user('..............')
    ->identityFily('..............')
    ->forwardAgent(true)
    ->multiplexing(true)
    ->stage('production')
    ->set('branch', 'master')
    ->set('deploy_path', '..............');

task('pwd', function (): void {
    $result = run('pwd');
    writeln("Current dir: {$result}");
});

set('env', function (): array {
    return [
        'APP_ENV' => 'prod',
        'DATABASE_URL' => '..............',
        'COMPOSER_MEMORY_LIMIT' => '1024M',
    ];
});

// Tasks
task('deploy:build:assets', function () {
    run('npm install');
    run('npm run build');
});

/*
 * Если скрипты собираются локально
task('deploy:build_local:assets', function () {
    upload('./public/build', '{{release_path}}/public/.');
    upload('./public/bundles', '{{release_path}}/bundles/.');
});
 */

alert('deploy:update_code', 'deploy:build:assets');
// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

// Migrate database before symlink new release.

before('deploy', 'success');

