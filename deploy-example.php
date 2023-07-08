<?php

namespace Deployer;

use Deployer\Exception\Exception as DeployerException;

require 'recipe/symfony.php';
require 'contrib/npm.php'; // если фронт собирается на сервере, в противном случае закомментировать или удалить

try {
    // Project name
    set('application', 'Pet project - Symfony shop');
    // Project repository
    set('repository', 'git@github.com:yaleksandr89/symfony-shop.git');

    // >>> Добавлены из-за возникающей ошибки "Can't set writable dirs with ACL."
    set('writable_mode', 'chmod');
    set('writable_recursive', true);
    // <<<

    set('ssh_type', 'native');
    set('ssh_multiplexing', true);
    set('allow_anonymous_stats', false);
    set('shared_dirs', []);
    set('shared_files', []);
    set('writable_dirs', []);
    set('bin_dir', 'bin');
    set('var_dir', 'var');
    set('env', []);

    // >>> Если npm установлен через nvm, в противном случае закомментировать или удалить
    // если опция удаляется, необходимо удалить её из tasks: 'deploy:npm:install', 'deploy:npm:build'
    set('nvm', 'source $HOME/.nvm/nvm.sh &&');
    // <<<

    set('keep_releases', 3);
} catch (DeployerException $e) {
    exit($e->getMessage());
}

// Файлы, которы будут перекидываться между релизами (т.юе. будут создаваться симлинки)
add('shared_files', [
    '.env',
]);
// Директории, которы будут перекидываться между релизами (т.юе. будут создаваться симлинки)
add('shared_dirs', [
    'var/log',
    'public/uploads',
]);

// Директории доступные для записи
add('writable_dirs', [
    'var',
    'public/uploads',
]);

// Предварительно сгенерировать файл: "composer dump-env prod"
// Предварительно создать '.env.prod' с настройками, которые будут использовать на проде
task('upload:env', function () {
    upload('.env.prod', '{{deploy_path}}/shared/.env');
})->desc('Environment setup');

// Hosts
host('...')
    ->setHostname('...')
    ->setPort('...')
    ->setRemoteUser('...')
    ->setIdentityFile('~/.ssh/....pub')
    ->set('labels', ['stage' => 'prod'])
    ->set('branch', '...')
    ->set('deploy_path', '...');

// Tasks
task('deploy:assets:install', function () {
    run('{{bin/console}} assets:install {{console_options}} {{release_path}}/public');
})->desc('Install bundle assets');

// >>> Если оперативная память на сервере позволяет - используем эти таски (выполняет npm i и билд), если
// такой возможности нет - делаем все локально, и переносим уже на сервер (в таком случае эти таски не нужны)
task('deploy:npm:install', function () {
    run('cd {{release_path}} && {{nvm}} npm install');
});
task('deploy:npm:build', function () {
    run('cd {{release_path}} && {{nvm}} npm run build');
});
// <<<
// >>> См коммент выше
// task('deploy:build_local_assets', function () {
//    upload('./public/build', '{{release_path}}/public/.');
//    upload('./public/bundles', '{{release_path}}/public/.');
//    upload('./public/uploads', '{{release_path}}/public/.');
//    upload('./node_modules', '{{release_path}}/.');
// });
// after('deploy:update_code', 'deploy:build_local_assets');
// <<<

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

/* All tasks */
task('deploy', [
    'deploy:prepare',
    'upload:env',
    'deploy:vendors',
    'database:migrate',
    'deploy:cache:clear',
    'deploy:clear_paths',
    'deploy:assets:install',
    'deploy:npm:install',
    'deploy:npm:build',
    'deploy:publish',
])->desc('Fine! Deploy completed.');
