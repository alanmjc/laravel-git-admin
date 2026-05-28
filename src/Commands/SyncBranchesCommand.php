<?php

declare(strict_types=1);

namespace GitAdmin\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SyncBranchesCommand extends Command
{
    protected $signature = 'app:sync-branches';

    protected $description = 'Sync development branches into the main branch.';

    public function handle(): int
    {
        $branches = array_values(array_filter((array) config('git-admin.sync_branches.branches', []), static fn ($branch) => is_string($branch) && trim($branch) !== ''));
        $mainBranch = (string) config('git-admin.sync_branches.main_branch', 'desarrollo');

        if ($branches === []) {
            $this->error('No branches configured in git-admin.sync_branches.branches.');
            return self::FAILURE;
        }

        if ($this->hasChanges()) {
            $this->error('You have uncommitted changes. Aborting process.');
            return self::FAILURE;
        }

        $originalBranch = trim((string) shell_exec('git rev-parse --abbrev-ref HEAD'));

        foreach ($branches as $branch) {
            $this->info("Switching to branch: {$branch}");
            if (!$this->runGitCommand(['git', 'checkout', $branch])) {
                return self::FAILURE;
            }

            $this->info("Running pull on {$branch}");
            if (!$this->runGitCommand(['git', 'pull', 'origin', $branch])) {
                return self::FAILURE;
            }

            if ($this->hasConflicts()) {
                $this->error("Conflicts found on {$branch}. Resolve them and run again.");
                return self::FAILURE;
            }

            $this->info("Switching to branch: {$mainBranch}");
            if (!$this->runGitCommand(['git', 'checkout', $mainBranch])) {
                return self::FAILURE;
            }

            $this->info("Merging {$branch} into {$mainBranch}");
            if (!$this->runGitCommand(['git', 'merge', $branch])) {
                return self::FAILURE;
            }

            if ($this->hasConflicts()) {
                $this->error('Conflicts found during merge. Resolve them and run again.');
                return self::FAILURE;
            }

            $this->info("Pushing {$mainBranch} to remote");
            if (!$this->runGitCommand(['git', 'push', 'origin', $mainBranch])) {
                return self::FAILURE;
            }

            $this->info("Merge completed: {$branch} into {$mainBranch}");
            $this->newLine();
        }

        if ($originalBranch !== '') {
            $this->info("Returning to original branch: {$originalBranch}");
            $this->runGitCommand(['git', 'checkout', $originalBranch]);
        }

        $this->info('Branch synchronization completed.');

        return self::SUCCESS;
    }

    private function runGitCommand(array $command): bool
    {
        $process = new Process($command);
        $process->run();

        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());

        if ($output !== '') {
            $this->line($output);
        }

        if ($errorOutput !== '') {
            $this->line($errorOutput);
        }

        return $process->isSuccessful();
    }

    private function hasConflicts(): bool
    {
        $process = new Process(['git', 'diff', '--check']);
        $process->run();

        return trim($process->getOutput()) !== '';
    }

    private function hasChanges(): bool
    {
        $output = [];
        exec('git status --porcelain', $output);

        return !empty($output);
    }
}
