<?php

namespace App\Services;

use App\Models\CleanOverdraftBankStatement;
use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use App\Models\FullySecuredOverdraftBankStatement;
use App\Models\LcOverdraftBankStatement;
use App\Models\OverdraftAgainstAssignmentOfContractBankStatement;
use App\Models\OverdraftAgainstCommercialPaperBankStatement;
use App\Models\User;
use App\Services\Api\CashExpenseOdooService;
use App\Services\Api\LetterOfGuaranteeService;
use App\Support\BankStatements\GeneratedMonthEndInterestRows;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpeningBalanceDateCleanupService
 * ------------------------------------------------------------------
 * When the super admin moves a company's Opening Balance Date FORWARD
 * (e.g. 1 Aug 2026 -> 1 Oct 2026), every entry the app calculated by
 * itself for that company and dated BEFORE the new date is removed:
 *
 *   - LG commission fees, LG issuance fees, LG renewal fees
 *   - LC interest posted to the current account
 *   - Certificate / Time Deposit periodic interest and break interest
 *   - "End Of Month Interest" rows (current accounts + every overdraft)
 *
 * NEVER touched: anything a user typed in (payments, receipts,
 * transfers, cheques, invoices, expenses), beginning-balance rows, and
 * any entry dated on or after the new date.
 *
 * Balances are recalculated by the app's own existing mechanism
 * (HasDeleteButTriggerChangeOnLastElement): all rows of one account are
 * removed, and the OLDEST one goes through the normal model delete so
 * the balance cascade runs once, from the oldest affected date onward.
 *
 * Odoo: rows already posted to Odoo are unlinked there first. A row
 * whose Odoo entry cannot be removed is KEPT (not deleted locally) and
 * reported back, so the two systems never silently disagree.
 */
class OpeningBalanceDateCleanupService
{
    /**
     * Overdraft-type statement tables that hold generated
     * "End Of Month Interest" rows: [model class, facility key column].
     */
    private const OVERDRAFT_STATEMENTS = [
        [CleanOverdraftBankStatement::class, 'clean_overdraft_id'],
        [FullySecuredOverdraftBankStatement::class, 'fully_secured_overdraft_id'],
        [OverdraftAgainstAssignmentOfContractBankStatement::class, 'overdraft_against_assignment_of_contract_id'],
        [OverdraftAgainstCommercialPaperBankStatement::class, 'overdraft_against_commercial_paper_id'],
        [LcOverdraftBankStatement::class, 'lc_facility_id'],
    ];

    private string $newDate;

    public function __construct(private Company $company, string $newDate)
    {
        $this->newDate = Carbon::parse($newDate)->format('Y-m-d');
    }

    /**
     * True only when the date really moved to a LATER day.
     * Same date, earlier date, or no old date => false (nothing deleted).
     */
    public static function movedForward(?string $oldDate, string $newDate): bool
    {
        if (! $oldDate) {
            return false;
        }

        return Carbon::parse($newDate)->startOfDay()->greaterThan(Carbon::parse($oldDate)->startOfDay());
    }

    /**
     * How many entries would be deleted. Used for the confirmation message.
     */
    public function countEntries(): int
    {
        $total = $this->currentAccountQuery()->count();

        foreach (self::OVERDRAFT_STATEMENTS as [$modelClass]) {
            $total += $this->overdraftQuery($modelClass)->count();
        }

        return $total;
    }

    /**
     * Deletes the entries.
     *
     * @return array{deleted:int, kept_odoo:int}
     */
    public function run(): array
    {
        $keptIds = $this->unlinkFromOdoo();
        $deleted = 0;

        /*
         * ---- Current accounts (commission / fees / interest) ----
         */
        $rows = $this->currentAccountQuery()
            ->when($keptIds !== [], fn (Builder $q) => $q->whereNotIn('id', $keptIds))
            ->orderByDesc('full_date')
            ->orderByDesc('id')
            ->get();

        $deleted += $this->deleteGroupedByKey($rows, 'financial_institution_account_id', CurrentAccountBankStatement::class);

        /*
         * ---- Overdraft facilities (end-of-month interest) ----
         */
        foreach (self::OVERDRAFT_STATEMENTS as [$modelClass, $keyColumn]) {
            $rows = $this->overdraftQuery($modelClass)
                ->orderByDesc('full_date')
                ->orderByDesc('id')
                ->get();

            $deleted += $this->deleteGroupedByKey($rows, $keyColumn, $modelClass);
        }

        return [
            'deleted' => $deleted,
            'kept_odoo' => count($keptIds),
        ];
    }

    /**
     * One account/facility at a time, newest -> oldest, so the oldest row
     * is the one that triggers the balance recalculation.
     */
    private function deleteGroupedByKey(Collection $rows, string $keyColumn, string $modelClass): int
    {
        $count = 0;

        foreach ($rows->groupBy($keyColumn) as $group) {
            // the delete helper requires a real Eloquent collection, newest row first
            $group = new Collection($group->values()->all());

            DB::transaction(function () use ($modelClass, $group) {
                $modelClass::deleteButTriggerChangeOnLastElement($group);
            });

            $count += $group->count();
        }

        return $count;
    }

    /**
     * Current-account rows that the app generated by itself.
     */
    private function currentAccountQuery(): Builder
    {
        return CurrentAccountBankStatement::withoutGlobalScope('only_active')
            ->where('company_id', $this->company->id)
            ->where('date', '<', $this->newDate)
            ->where('is_beginning_balance', 0)
            ->where(function (Builder $q) {
                $q->where('is_commission_fees', 1)
                    ->orWhere('is_issuance_fees', 1)
                    ->orWhere('is_renewal_fees', 1)
                    ->orWhere('type', 'lc_interest')
                    ->orWhere('is_period_cd_or_td_interest', '>', 0)
                    ->orWhere('is_break_interest', 1)
                    ->orWhereIn('interest_type', GeneratedMonthEndInterestRows::INTEREST_TYPES);
            });
    }

    /**
     * Generated "End Of Month Interest" rows of an overdraft facility.
     */
    private function overdraftQuery(string $modelClass): Builder
    {
        return $modelClass::query()
            ->where('company_id', $this->company->id)
            ->where('date', '<', $this->newDate)
            ->whereIn('interest_type', GeneratedMonthEndInterestRows::INTEREST_TYPES);
    }

    /**
     * Removes the matching Odoo journal entries first.
     *
     * @return int[] ids of local rows that must be KEPT because their
     *               Odoo entry could not be removed.
     */
    private function unlinkFromOdoo(): array
    {
        $linkedRows = $this->currentAccountQuery()
            ->where(function (Builder $q) {
                $q->whereNotNull('lg_commission_fees_journal_entry_id')
                    ->orWhereNotNull('interest_journal_entry_id');
            })
            ->get();

        if ($linkedRows->isEmpty()) {
            return [];
        }

        $user = $this->resolveOdooUser();
        if (! $this->company->hasOdooCredentials() || ! $user) {
            Log::warning('Opening balance date cleanup: rows linked to Odoo kept (no usable Odoo credentials)', [
                'company_id' => $this->company->id,
                'rows' => $linkedRows->count(),
            ]);

            return $linkedRows->pluck('id')->all();
        }

        $kept = [];
        $lgService = null;
        $cashExpenseService = null;

        foreach ($linkedRows as $row) {
            try {
                if ($row->lg_commission_fees_journal_entry_id) {
                    $lgService ??= new LetterOfGuaranteeService($this->company, $user);
                    $lgService->unlink((int) $row->lg_commission_fees_journal_entry_id);
                }
                if ($row->interest_journal_entry_id) {
                    $cashExpenseService ??= new CashExpenseOdooService($this->company, $user);
                    $cashExpenseService->unlink((int) $row->interest_journal_entry_id);
                }
            } catch (Throwable $e) {
                $kept[] = $row->id;
                Log::warning('Opening balance date cleanup: Odoo entry could not be removed, row kept', [
                    'company_id' => $this->company->id,
                    'statement_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $kept;
    }

    /**
     * Odoo calls need a user who has Odoo login details. The logged-in
     * super admin is used if they have them, otherwise the first user of
     * the company who does.
     */
    private function resolveOdooUser(): ?User
    {
        $current = auth()->user();
        if ($current instanceof User && $current->getOdooDBUserName() && $current->getOdooDBPassword()) {
            return $current;
        }

        return $this->company->users()
            ->whereNotNull('users.odoo_username')
            ->whereNotNull('users.odoo_db_password')
            ->first();
    }
}
