<?php

namespace App\Console\Commands;

use App\Models\Cheque;
use App\Models\OverdraftAgainstCommercialPaperLimit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verifies Cheque::updateLimitUpdateRowFromStatement() cascades neighbor
 * days_count / interest when the limit_update marker moves later.
 *
 * Production order (Cheque::updated):
 *   1. Limit Eloquent update(full_date)
 *   2. updateLimitUpdateRowFromStatement
 *
 * Always rolls back — never persists.
 *
 * Usage:
 *   php artisan audit:prove-limit-update-cascade
 *   php artisan audit:prove-limit-update-cascade --facility=10 --limit=45
 */
class ProveLimitUpdateCascadeCommand extends Command
{
    protected $signature = 'audit:prove-limit-update-cascade
                            {--facility= : Overdraft-against-CP facility id}
                            {--limit= : overdraft_against_commercial_paper_limits.id to move later}
                            {--days=10 : How many days later to move the limit}';

    protected $description = 'Prove/verify limit_update marker cascade after date move (rolled back)';

    public function handle(): int
    {
        $picked = $this->pickScenario();
        if (! $picked) {
            $this->error('No usable scenario: need a limit_update with a later non-limit_update row within the move window.');

            return self::FAILURE;
        }

        [$facilityId, $limit, $marker, $probe, $oldFull, $newFull, $oldDate, $newDate] = $picked;

        $this->info("Facility: {$facilityId}");
        $this->info("Limit L#{$limit->id}  {$oldFull}  →  {$newFull} (later)");
        $this->info("Marker statement #{$marker->id} (limit_update @ {$marker->date})");
        $this->info("Probe statement #{$probe->id} ({$probe->type} @ {$probe->date}) — sits between old and new date");
        $this->newLine();

        $probeId = (int) $probe->id;

        DB::beginTransaction();
        try {
            $before = $this->snap($probeId);

            $limitModel = OverdraftAgainstCommercialPaperLimit::find($limit->id);
            $limitModel->update(['full_date' => $newFull, 'updated_at' => now()]);
            $afterLimit = $this->snap($probeId);

            // Production step 2 (fixed path)
            Cheque::updateLimitUpdateRowFromStatement($limitModel, $newFull);
            $afterFixed = $this->snap($probeId);

            // Independent expected cascade from min(old, new) — should match fixed path
            $table = 'overdraft_against_commercial_paper_bank_statements';
            DB::table($table)
                ->where('overdraft_against_commercial_paper_id', $facilityId)
                ->where('date', '>=', $oldDate)
                ->orderByRaw('date asc, priority asc, id asc')
                ->each(function ($r) use ($table) {
                    DB::table($table)->where('id', $r->id)->update(['updated_at' => now()]);
                });
            $afterMin = $this->snap($probeId);

            $this->info("=== Probe #{$probeId} ===");
            $this->line('  before:      '.$this->fmt($before));
            $this->line('  after Limit: '.$this->fmt($afterLimit));
            $this->line('  after fix:   '.$this->fmt($afterFixed));
            $this->line('  after min(): '.$this->fmt($afterMin));
            $this->newLine();

            $this->info('=== Verdict ===');
            if ($afterFixed['days'] === $afterMin['days'] && $afterFixed['int'] === $afterMin['int']) {
                if ($afterFixed['days'] !== $before['days'] || $afterFixed['int'] !== $before['int']) {
                    $this->info('FIXED: production path matches min(old,new) cascade; probe days/interest recalculated.');
                } else {
                    $this->warn('INCONCLUSIVE: fixed path matched min-cascade but probe days/interest did not change on this dataset.');
                }
            } else {
                $this->error('STILL BROKEN: fixed path diverges from min-cascade.');
                $this->error("  fix days={$afterFixed['days']} int={$afterFixed['int']}");
                $this->error("  min days={$afterMin['days']} int={$afterMin['int']}");
            }
        } finally {
            DB::rollBack();
            $this->info('Transaction rolled back — no data persisted.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:object,2:object,3:object,4:string,5:string,6:string,7:string}|null
     */
    protected function pickScenario(): ?array
    {
        $facilityOpt = $this->option('facility') ? (int) $this->option('facility') : null;
        $limitOpt = $this->option('limit') ? (int) $this->option('limit') : null;
        $daysLater = max(1, (int) $this->option('days'));

        $limitsQuery = DB::table('overdraft_against_commercial_paper_limits as lim')
            ->join('overdraft_against_commercial_paper_bank_statements as lu', function ($j) {
                $j->on('lu.overdraft_against_commercial_paper_limit_id', '=', 'lim.id')
                    ->where('lu.type', '=', 'limit_update');
            })
            ->when($facilityOpt, fn ($q) => $q->where('lim.overdraft_against_commercial_paper_id', $facilityOpt))
            ->when($limitOpt, fn ($q) => $q->where('lim.id', $limitOpt))
            ->orderBy('lim.full_date')
            ->select([
                'lim.id as limit_id',
                'lim.overdraft_against_commercial_paper_id as facility_id',
                'lim.full_date as limit_full_date',
                'lu.id as marker_id',
                'lu.date as marker_date',
            ]);

        foreach ($limitsQuery->get() as $cand) {
            $oldDate = Carbon::make($cand->marker_date)->format('Y-m-d');
            $newDate = Carbon::make($oldDate)->addDays($daysLater)->format('Y-m-d');
            $oldFull = $cand->limit_full_date;
            $newFull = $newDate.' '.Carbon::make($oldFull)->format('H:i:s');

            $probe = DB::table('overdraft_against_commercial_paper_bank_statements')
                ->where('overdraft_against_commercial_paper_id', $cand->facility_id)
                ->where('id', '!=', (int) $cand->marker_id)
                ->where('type', '!=', 'limit_update')
                ->where('date', '>', $oldDate)
                ->where('date', '<', $newDate)
                ->orderByRaw('date asc, priority asc, id asc')
                ->first();

            if (! $probe) {
                continue;
            }

            $limit = DB::table('overdraft_against_commercial_paper_limits')->where('id', $cand->limit_id)->first();
            $marker = DB::table('overdraft_against_commercial_paper_bank_statements')->where('id', $cand->marker_id)->first();

            return [(int) $cand->facility_id, $limit, $marker, $probe, $oldFull, $newFull, $oldDate, $newDate];
        }

        return null;
    }

    protected function snap(int $id): array
    {
        $r = DB::table('overdraft_against_commercial_paper_bank_statements')->where('id', $id)->first();

        return [
            'date' => $r->date,
            'limit' => (string) $r->limit,
            'days' => (string) $r->days_count,
            'int' => (string) $r->interest_amount,
            'end' => (string) $r->end_balance,
            'room' => (string) $r->room,
        ];
    }

    protected function fmt(array $t): string
    {
        return "date={$t['date']} lim={$t['limit']} days={$t['days']} int={$t['int']} end={$t['end']} room={$t['room']}";
    }
}
