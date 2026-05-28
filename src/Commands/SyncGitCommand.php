<?php

declare(strict_types=1);

namespace GitAdmin\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class SyncGitCommand extends Command
{
    protected $signature = 'app:sync-git
                            {branch=desarrollo : Base branch used for rebase (without "origin/")}
                            {--yes : Skip confirmation for push --force-with-lease}';

    protected $description = 'Sync current branch with remote and rebase against target base branch.';

    public function handle(): int
    {
        try {
            $currentBranch = trim($this->runGitCommand('git symbolic-ref --short HEAD')) ?: 'unknown';

            if (!$this->confirm("Current branch is '{$currentBranch}'. Continue?")) {
                $this->info('Process aborted by user.');
                return self::SUCCESS;
            }

            if ($this->hasChanges()) {
                $this->error('You have uncommitted changes. Commit or stash before continuing.');
                return self::FAILURE;
            }

            $this->info('Running git fetch...');
            $this->runGitCommand('git fetch');

            [$behind, $ahead] = $this->getAheadBehind();
            $this->info("Branch is {$behind} behind and {$ahead} ahead of remote upstream.");

            if ($behind > 0) {
                $this->info('Integrating missing commits from your own branch (pull --rebase)...');
                $this->runGitCommand('git pull --rebase');
            }

            $base = (string) $this->argument('branch');
            $remoteBase = 'origin/' . $base;
            $this->info("Rebasing against {$remoteBase}...");
            $this->runGitCommand('git rebase ' . escapeshellarg($remoteBase));

            [, $aheadAfter] = $this->getAheadBehind();
            $forcePushNeeded = $aheadAfter > 0;

            $pushCmd = $forcePushNeeded ? 'git push --force-with-lease' : 'git push';

            if ($forcePushNeeded && !$this->option('yes')) {
                if (!$this->confirm('Remote history will be rewritten. Continue with --force-with-lease?')) {
                    $this->warn('Push aborted by user.');
                    return self::FAILURE;
                }
            }

            $this->info('Pushing changes to remote...');
            $this->runGitCommand($pushCmd);

            $this->info("Completed.\nRemote commits integrated: {$behind}\nLocal commits pushed: {$aheadAfter}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function hasChanges(): bool
    {
        $output = $this->runGitCommand('git status --porcelain');
        return (bool) trim($output);
    }

    private function getAheadBehind(): array
    {
        $output = $this->runGitCommand('git rev-list --left-right --count HEAD...@{u}');
        $parts = preg_split('/\s+/', trim($output));

        if (!is_array($parts) || count($parts) < 2) {
            throw new RuntimeException('Unable to determine ahead/behind state. Ensure branch has an upstream.');
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    private function runGitCommand(string $command, bool $abortOnError = true): string
    {
        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);

        $outputText = implode(PHP_EOL, $output);

        if ($code !== 0 && $abortOnError) {
            throw new RuntimeException("Failed running '{$command}':\n{$outputText}");
        }

        return $outputText;
    }
}
