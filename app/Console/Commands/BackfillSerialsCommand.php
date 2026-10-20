<?php

namespace App\Console\Commands;

use App\Support\Activity\ActivityRegistry;
use App\Support\Serials\SerialBackfiller;
use App\Support\Serials\SerialRegistry;
use Illuminate\Console\Command;

/**
 * * بيدّي أرقام مسلسلة للحركات اللي لسه ما عندهاش — شوف SerialBackfiller .
 * * الهجرة بتشغّله مرة وقت الديبلوي ؛ تشغيله تاني آمن .
 */
class BackfillSerialsCommand extends Command
{
    protected $signature = 'serials:backfill
        {--company= : Restrict to one company id}
        {--model= : Restrict to one model (short class name, e.g. MoneyReceived)}
        {--dry-run : Report how many rows would be numbered without writing}';

    protected $description = 'Assign serial numbers (e.g. MR/CIS/2026/10/00001) to treasury transactions that have none';

    public function handle(SerialBackfiller $backfiller): int
    {
        $class = null;

        if ($model = $this->option('model')) {
            $class = collect(array_keys(SerialRegistry::MODELS))->first(fn ($c) => class_basename($c) === $model);

            if (! $class) {
                $this->error("Unknown model [{$model}]. Expected one of: ".collect(array_keys(SerialRegistry::MODELS))->map(fn ($c) => class_basename($c))->implode(', '));

                return self::FAILURE;
            }
        }

        $company = $this->option('company') ? (int) $this->option('company') : null;
        $report = $backfiller->run($company, $class, (bool) $this->option('dry-run'));

        $this->table(
            ['Model', $this->option('dry-run') ? 'Would assign' : 'Assigned', 'Skipped (no company / unknown type)'],
            collect($report)->map(fn ($r, $c) => [ActivityRegistry::labelFor($c) ?: class_basename($c), $r['assigned'], $r['skipped']])->values()->all()
        );

        return self::SUCCESS;
    }
}
