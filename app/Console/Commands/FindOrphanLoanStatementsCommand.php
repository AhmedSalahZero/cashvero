<?php

namespace App\Console\Commands;

use App\Models\LoanStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * FindOrphanLoanStatementsCommand
 * ------------------------------------------------------------------
 * Finds — and optionally cleans up — loan_statements rows left behind
 * when a leasing contract was deleted before the 2026-08-15 fix to
 * LeasingContract::deleteRelations().
 *
 * WHY THESE ROWS EXIST
 * A loan_statements row is created from a contract_loan_schedule_settlement
 * (a payment against one installment of a contract's loan schedule).
 * Deleting a whole leasing contract used to just delete each installment
 * row (contract_loan_schedules) without first deleting that installment's
 * settlements — so the settlement, and the loan_statements row it created,
 * were left behind pointing at an installment/contract that no longer
 * existed. This command finds those leftovers along the whole chain:
 *
 *   loan_statements -> contract_loan_schedule_settlements
 *                    -> contract_loan_schedules
 *                    -> leasing_contracts
 *
 * A row is an orphan if it references a settlement that's gone, or a
 * settlement whose own schedule is gone, or a settlement whose schedule's
 * leasing contract is gone. Same three checks are also run for the other
 * kind of loan statement (loan_schedule_settlement_id -> loan_schedules
 * -> medium_term_loans), since that path shares the same underlying bug
 * shape even though it wasn't the one reported.
 *
 * WHY A COMMAND AND NOT A RAW SQL DELETE
 * LoanStatement::deleting() zeroes debit/credit (rather than removing the
 * row) and re-triggers balance recalculation for every later row on that
 * account, via the same mechanism the app uses for a normal, intentional
 * delete. A raw SQL DELETE would skip that and leave every later row's
 * stored balance wrong. So cleanup goes through the model, one row at a
 * time, and is safe to re-run.
 *
 * USAGE
 *   php artisan loan-statements:orphan-rows                # report only (safe, default)
 *   php artisan loan-statements:orphan-rows --company=92    # limit to one company
 *   php artisan loan-statements:orphan-rows --fix           # actually clean them up
 */
class FindOrphanLoanStatementsCommand extends Command
{
    protected $signature = 'loan-statements:orphan-rows
        {--fix : Clean up the orphan rows instead of only reporting them}
        {--company= : Restrict to a single company id}
        {--samples=5 : How many sample rows to print}';

    protected $description = 'Report (and optionally clean up) loan_statements rows whose contract/schedule/settlement no longer exists';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $companyId = $this->option('company');
        $samples = (int) $this->option('samples');

        $this->line('');
        $this->info($fix ? 'MODE: FIX (orphan rows will be cleaned up)' : 'MODE: REPORT ONLY (nothing will be changed)');
        if ($companyId) {
            $this->line("Company filter: {$companyId}");
        }
        $this->line('');

        $ids = $this->orphanIds($companyId);

        if ($ids === []) {
            $this->info('No orphan loan_statements rows found.');

            return self::SUCCESS;
        }

        $this->warn('loan_statements (contract/settlement chain broken): '.count($ids).' row(s)');
        $this->showSamples($ids, $samples);

        if ($fix) {
            $this->cleanUp($ids);
        } else {
            $this->line('');
            $this->warn('Nothing was changed. Re-run with --fix to clean these rows up.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function orphanIds(?string $companyId): array
    {
        $query = DB::table('loan_statements')
            ->where(function ($any) {
                $any->whereNotNull('contract_loan_schedule_settlement_id')
                    ->orWhereNotNull('loan_schedule_settlement_id');
            })
            ->where(function ($broken) {
                // contract-loan side: settlement gone, or its schedule gone,
                // or its schedule's leasing contract gone
                $broken->where(function ($q) {
                    $q->whereNotNull('loan_statements.contract_loan_schedule_settlement_id')
                        ->where(function ($dead) {
                            $dead->whereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('contract_loan_schedule_settlements')
                                    ->whereColumn('contract_loan_schedule_settlements.id', 'loan_statements.contract_loan_schedule_settlement_id');
                            })->orWhereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('contract_loan_schedule_settlements')
                                    ->join('contract_loan_schedules', 'contract_loan_schedules.id', '=', 'contract_loan_schedule_settlements.contract_loan_schedule_id')
                                    ->whereColumn('contract_loan_schedule_settlements.id', 'loan_statements.contract_loan_schedule_settlement_id');
                            })->orWhereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('contract_loan_schedule_settlements')
                                    ->join('contract_loan_schedules', 'contract_loan_schedules.id', '=', 'contract_loan_schedule_settlements.contract_loan_schedule_id')
                                    ->join('leasing_contracts', 'leasing_contracts.id', '=', 'contract_loan_schedules.leasing_contract_id')
                                    ->whereColumn('contract_loan_schedule_settlements.id', 'loan_statements.contract_loan_schedule_settlement_id');
                            });
                        });
                })
                // medium-term-loan side: same three-link check
                ->orWhere(function ($q) {
                    $q->whereNotNull('loan_statements.loan_schedule_settlement_id')
                        ->where(function ($dead) {
                            $dead->whereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('loan_schedule_settlements')
                                    ->whereColumn('loan_schedule_settlements.id', 'loan_statements.loan_schedule_settlement_id');
                            })->orWhereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('loan_schedule_settlements')
                                    ->join('loan_schedules', 'loan_schedules.id', '=', 'loan_schedule_settlements.loan_schedule_id')
                                    ->whereColumn('loan_schedule_settlements.id', 'loan_statements.loan_schedule_settlement_id');
                            })->orWhereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('loan_schedule_settlements')
                                    ->join('loan_schedules', 'loan_schedules.id', '=', 'loan_schedule_settlements.loan_schedule_id')
                                    ->join('medium_term_loans', 'medium_term_loans.id', '=', 'loan_schedules.medium_term_loan_id')
                                    ->whereColumn('loan_schedule_settlements.id', 'loan_statements.loan_schedule_settlement_id');
                            });
                        });
                });
            });

        if ($companyId) {
            $query->where('loan_statements.company_id', $companyId);
        }

        return $query->orderBy('loan_statements.id')->pluck('loan_statements.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $ids
     */
    private function showSamples(array $ids, int $samples): void
    {
        if ($samples <= 0) {
            return;
        }

        $preview = array_slice($ids, 0, $samples);

        foreach (DB::table('loan_statements')->whereIn('id', $preview)->get([
            'id', 'company_id', 'financial_institution_account_id', 'date',
            'debit', 'credit', 'contract_loan_schedule_settlement_id',
            'loan_schedule_settlement_id', 'comment_en',
        ]) as $row) {
            $this->line('    '.json_encode($row, JSON_UNESCAPED_UNICODE));
        }

        if (count($ids) > $samples) {
            $this->line('    ... +'.(count($ids) - $samples).' more');
        }
    }

    /**
     * Cleans up through the Eloquent model (never a mass SQL delete) so
     * LoanStatement::deleting() and the balance-recalculation it triggers
     * run for every row, keeping later balances on the same account correct.
     *
     * @param  list<int>  $ids
     */
    private function cleanUp(array $ids): void
    {
        $cleaned = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $row = LoanStatement::withoutGlobalScopes()->find($id);
                if (! $row) {
                    continue;
                }
                $row->delete();
                $cleaned++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("    failed to clean up loan_statements#{$id}: ".$e->getMessage());
            }
        }

        $this->line('');
        $this->info("Cleaned up {$cleaned} row(s)".($failed ? ", {$failed} failed" : ''));
    }
}
