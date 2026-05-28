# Laravel Git Admin

Composer package that provides Artisan commands for common Git synchronization workflows.

## Installation

```bash
composer require alanmjc/laravel-git-admin:^0.1.0
```

Compatibility:

- Laravel 8.83+
- Laravel 9, 10, 11, and 12
- PHP 8.0+

Laravel package auto-discovery registers the provider automatically.

## Commands

### app:sync-git

Synchronizes the current branch with remote and rebases on a base branch.

```bash
php artisan app:sync-git
php artisan app:sync-git desarrollo
php artisan app:sync-git desarrollo --yes
```

Arguments and options:

- `branch` (default: `desarrollo`): base branch used for rebase.
- `--yes`: skips confirmation for force push.

### app:sync-branches

Iterates predefined development branches, merges each into `desarrollo`, and pushes.

```bash
php artisan app:sync-branches
```

Publish configuration to customize branches and main target branch:

```bash
php artisan vendor:publish --tag=git-admin-config
```

Generated config file:

```php
<?php

return [
  'sync_branches' => [
    'main_branch' => 'desarrollo',
    'branches' => ['alan', 'luis', 'mauricio', 'vasni'],
  ],
];
```

## Local development

Run this in the package root:

```bash
composer dump-autoload
```

To test in a Laravel app using path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../git-admin"
    }
  ]
}
```

Then require it:

```bash
composer require alanmjc/laravel-git-admin:dev-main
```
