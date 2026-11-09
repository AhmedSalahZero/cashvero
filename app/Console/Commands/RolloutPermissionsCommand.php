<?php

namespace App\Console\Commands;

use App\Support\Permissions\PermissionRollout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * `php artisan permissions:rollout`
 * ==================================================================
 * Hands each newly added permission key, ONCE, to the users who already
 * hold the screen it came from (its `legacy` alias). See
 * App\Support\Permissions\PermissionRollout for the rule and why.
 *
 * Dry run by default: prints who would get what. `--force` grants and
 * records every pending key, so the next run finds nothing to do and an
 * admin who later removes a key from someone is not overruled.
 *
 * Runs on every deploy (deploy.sh), after `permissions:sync`. It never
 * prompts, so it is safe there.
 */
class RolloutPermissionsCommand extends Command
{
    protected $signature = 'permissions:rollout
                            {--force : Grant the keys and record them (otherwise this is a dry run)}';

    protected $description = 'Give each newly added permission key, once, to the users who already hold the screen it came from';

    public function handle(): int
    {
        if (! Schema::hasTable(PermissionRollout::TABLE)) {
            $this->error('The '.PermissionRollout::TABLE.' table does not exist yet. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $plan = PermissionRollout::plan();

        if ($plan === []) {
            $this->info('Nothing pending — every registry key has already been rolled out.');

            return self::SUCCESS;
        }

        $write = (bool) $this->option('force');

        $this->info($write
            ? count($plan).' new permission key(s) to roll out.'
            : 'DRY RUN — '.count($plan).' new permission key(s) would be rolled out. Re-run with --force to apply.');
        $this->newLine();

        $rows = [];
        foreach ($plan as $key => $users) {
            $who = $users->isEmpty()
                ? 'nobody inherits it — a Super Admin grants it by hand'
                : Str::limit($users->map(fn ($user) => "#{$user->id} {$user->name}")->implode(', '), 90);

            $rows[] = [$key, $users->count(), $who];
        }

        $this->table(['Key', 'Users', 'Who'], $rows);
        $this->newLine();

        if (! $write) {
            $this->warn('Nothing was written. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        $granted = PermissionRollout::apply($plan);

        $this->info("Done. {$granted} grant(s) made across ".count($plan).' key(s); they are recorded and will not be handed out again.');

        return self::SUCCESS;
    }
}
