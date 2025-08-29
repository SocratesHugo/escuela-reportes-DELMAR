<?php

declare(strict_types=1);

/**
 * Deployer configuration for Escuela Reportes (Laravel, Deployer v7)
 */

require 'recipe/laravel.php';

use function Deployer\{set, host, after, before};

// --- Project settings ---
set('application', 'escuela-reportes-DELMAR');
set('repository', 'git@github.com:SocratesHugo/escuela-reportes-DELMAR.git');
set('branch', 'main');
set('keep_releases', 5);

// Shared files/dirs between deploys
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);

// Writable directories for web server
set('writable_dirs', ['storage', 'bootstrap/cache']);
set('writable_mode', 'chmod');

// Composer options (avoid interaction & optimize autoload)
set('composer_options', '--no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader');

// PHP binary on the server
set('bin/php', '/usr/bin/php');

// Host definition
host('prod')
    ->setHostname('45.90.223.223')
    ->set('remote_user', 'root')
    ->set('deploy_path', '/var/www/apps/escuela');

// --- Hooks ---
// Ensure Laravel caches/links are created on each release
after('deploy:vendors', 'artisan:storage:link');
after('deploy:vendors', 'artisan:config:cache');

// Run database migrations just before switching the symlink
before('deploy:symlink', 'artisan:migrate');

// If deploy fails automatically unlock
after('deploy:failed', 'deploy:unlock');

// The default `deploy` task pipeline is provided by the Laravel recipe.
