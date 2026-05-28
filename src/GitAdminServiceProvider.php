<?php

declare(strict_types=1);

namespace GitAdmin;

use GitAdmin\Commands\SyncBranchesCommand;
use GitAdmin\Commands\SyncGitCommand;
use Illuminate\Support\ServiceProvider;

class GitAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/git-admin.php', 'git-admin');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/git-admin.php' => config_path('git-admin.php'),
        ], 'git-admin-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncGitCommand::class,
                SyncBranchesCommand::class,
            ]);
        }
    }
}
