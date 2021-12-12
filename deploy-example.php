<?php

// Testing - Symfony 5.3

namespace Deployer;

require 'recipe/symfony.php';

set('ssh_type', 'native');
set('ssh_multiplexing', true);
set('allow_anonymous_stats', false);
set('env', []); // Run command environment (for example, SYMFONY_ENV=prod)
set('shared_dirs', []);
set('shared_files', []);
set('writable_dirs', []);
set('bin_dir', 'bin');
set('var_dir', 'var');

// Project name
set('application', 'Pet project - Symfony shop');

// Project repository
set('repository', '......');

set(
    'composer_options',
    '{{composer_action}}  --verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader --no-scripts'
);

set('keep_releases', 3);

// Shared files/dirs between deploys
add('shared_files', [
    '.env',
]);

add('shared_dirs', [
    'var/log',
    'var/cache',
    'public/uploads',
]);

// Writable dirs by web server
add('writable_dirs', [
    'var',
    'public/uploads',
]);

// Предварительно сгенерировать файл: "composer dump-env prod"
task('upload:env', function () {
    upload('.env.prod', '{{deploy_path}}/shared/.env');
})->desc('Environment setup');

// Hosts
host('......')
    ->stage('production')
    ->user('......')
    ->set('deploy_path', '......');

// Tasks
task('deploy:assets:install', function () {
    run('{{bin/console}} assets:install {{console_options}} {{release_path}}/public');
})->desc('Install bundle assets');

task('deploy:npm:install', function () {
    run('cd {{release_path}} && npm install');
});

task('deploy:npm:build', function () {
    run('cd {{release_path}} && npm run build');
});

task('deploy:database:migrate', function () {
    run('{{bin/console}} doctrine:migrations:migrate');
});

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

// Migrate database before symlink new release.
before('deploy:symlink', 'database:migrate');

/*
 * Main task
 */
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'upload:env',
    'deploy:update_code',
    'deploy:clear_paths',
    'deploy:create_cache_dir',
    'deploy:shared',
    'deploy:assets',
    'deploy:vendors',
    'deploy:assets:install',
    'deploy:npm:install',
    'deploy:npm:build',
    'deploy:assetic:dump',
    'deploy:cache:clear',
    'deploy:cache:warmup',
    'deploy:database:migrate',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy your project');
