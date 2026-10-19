<?php

namespace Tests\Feature\Leasing;

use App\Models\Company;
use App\Models\ContractLoanSchedule;
use App\Models\ContractLoanScheduleSettlement;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LeasingCompany;
use App\Models\LeasingContract;
use App\Models\User;
use App\Support\Permissions\PermissionResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Leasing cheques follow the supplier payable-cheque lifecycle: the
 * cheque is in the drawee account's statement from the moment the
 * installment exists (at its due date), Mark As Paid MOVES that row to
 * the actual payment date, Unmark moves it back. Every test checks the
 * statement itself — which row, which date, which amount — and that the
 * account's running balance still chains correctly.
 *
 * Runs against SMOKE_DB (default: cashvero) inside a rolled-back
 * transaction. Statement balances are computed by MySQL triggers, so the
 * class skips itself on a database where `php artisan run:sql` has never
 * installed them.
 */
class LeasingChequeStatementTest extends TestCase
{
    use DatabaseTransactions;

    private const FUNDING = 1000000.0;

    private Company $company;

    private User $user;

    private LeasingContract $contract;

    private FinancialInstitution $bank;

    private FinancialInstitutionAccount $account;

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        $hasTriggers = DB::table('information_schema.triggers')
            ->where('trigger_schema', DB::getDatabaseName())
            ->where('event_object_table', 'current_account_bank_statements')
            ->exists();
        if (! $hasTriggers) {
            $this->markTestSkipped('Statement balance triggers are not installed on '.DB::getDatabaseName().' (php artisan run:sql).');
        }

        $company = Company::first();
        if (! $company) {
            $this->markTestSkipped('Development database has no company.');
        }
        $this->company = $company;

        $this->bank = FinancialInstitution::create([
            'company_id' => $this->company->id,
            'type' => 'bank',
            'name' => 'TEST Cheque Bank '.uniqid(),
            'branch_name' => 'TEST Branch',
        ]);
        $this->account = $this->makeAccount(now()->subYear()->format('Y-m-d'), self::FUNDING);

        $leasingCompany = LeasingCompany::create([
            'company_id' => $this->company->id,
            'name' => 'TEST Leasing Co '.uniqid(),
        ]);
        $this->contract = LeasingContract::create([
            'company_id' => $this->company->id,
            'leasing_company_id' => $leasingCompany->id,
            'status' => LeasingContract::RUNNING,
            'name' => 'TEST-LC-'.uniqid(),
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'end_date' => now()->addYears(3)->format('Y-m-d'),
            'currency' => 'egp',
            'limit' => 1000000,
            'borrowing_rate' => 10,
            'margin_rate' => 2,
            'duration' => 36,
            'installment_payment_interval' => 'monthly',
        ]);

        $this->user = User::create([
            'name' => 'Leasing Cheque Test '.uniqid(),
            'email' => 'leasing-cheque-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $this->user->givePermissionTo([
            'contract_loan_schedule.view',
            'contract_loan_schedule.update',
            'contract_loan_schedule.delete',
            'leasing_contract.view',
            'loan_schedule_data.delete',
        ]);
        $this->user->companies()->attach($this->company->id);
        $this->user->load('companies');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
    }

    /* ── posting ───────────────────────────────────────────────────── */

    public function test_a_new_installment_posts_its_cheque_at_the_due_date(): void
    {
        $due = now()->addDays(20)->format('Y-m-d');
        $schedule = $this->makeInstallment(100000, $due);

        $row = $this->chequeRow($schedule);
        $this->assertNotNull($row, 'The cheque must be in the statement as soon as the installment exists.');
        $this->assertSame($due, $this->day($row->date));
        $this->assertEquals(100000.0, (float) $row->credit);
        $this->assertEquals(0.0, (float) $row->debit);
        $this->assertEquals(self::FUNDING - 100000, (float) $row->end_balance);
        $this->assertStringContainsString($schedule->getChequeNumber(), $row->comment_en);

        $this->assertEquals(self::FUNDING, $this->balanceOn(now()->addDays(19)), 'Before the due date the cheque has not left yet.');
        $this->assertEquals(self::FUNDING - 100000, $this->balanceOn(now()->addDays(20)));
        $this->assertChainIsConsistent();
    }

    public function test_a_cheque_due_before_the_account_opened_is_posted_on_the_opening_date(): void
    {
        $opening = now()->subDays(10)->format('Y-m-d');
        $lateAccount = $this->makeAccount($opening, 50000, asOpeningBalance: true);

        $schedule = $this->makeInstallment(20000, now()->subDays(15)->format('Y-m-d'), $lateAccount);

        $row = $this->chequeRow($schedule);
        $this->assertSame($opening, $this->day($row->date));
        $this->assertEquals(30000.0, (float) $row->end_balance, 'Opening 50,000 first, then the cheque.');
        $this->assertChainIsConsistent($lateAccount);
    }

    /* ── mark as paid / unmark ─────────────────────────────────────── */

    public function test_mark_as_paid_moves_the_same_row_to_the_payment_date(): void
    {
        $due = now()->subDays(10)->format('Y-m-d');
        $paid = now()->subDays(4)->format('Y-m-d');
        $schedule = $this->makeInstallment(100000, $due, interest: 30000, principle: 70000);
        $rowId = $this->chequeRow($schedule)->id;

        $this->markAsPaid($schedule, $paid)->assertSessionHasNoErrors()->assertSessionMissing('fail');

        $schedule->refresh();
        $this->assertTrue($schedule->isChequePaid());
        $this->assertSame($paid, $this->day($schedule->actual_payment_date));
        $this->assertEquals(0.0, (float) $schedule->remaining);
        $this->assertSame('paid', $schedule->status);

        $this->assertSame(1, $this->chequeRowCount(), 'Paying must move the cheque, never add a second movement.');
        $row = $this->chequeRow($schedule);
        $this->assertSame($rowId, $row->id);
        $this->assertSame($paid, $this->day($row->date));
        $this->assertEquals(100000.0, (float) $row->credit);

        $this->assertEquals(self::FUNDING, $this->balanceOn(now()->subDays(5)), 'Between due and payment date the money is still there.');
        $this->assertEquals(self::FUNDING - 100000, $this->balanceOn(now()));
        $this->assertChainIsConsistent();

        $settlement = $schedule->settlements()->sole();
        $this->assertTrue($settlement->isChequePayment());
        $this->assertSame(0, CurrentAccountBankStatement::where('contract_loan_schedule_settlement_id', $settlement->id)->count(),
            'The Mark As Paid settlement must not post a bank row of its own.');
    }

    public function test_unmark_moves_the_row_back_to_the_due_date(): void
    {
        $due = now()->subDays(10)->format('Y-m-d');
        $schedule = $this->makeInstallment(100000, $due);
        $this->markAsPaid($schedule, now()->subDays(2)->format('Y-m-d'))->assertSessionMissing('fail');

        $this->actingAs($this->user)
            ->post(route('contract.loan.schedule.unmark.as.paid', ['company' => $this->company->id, 'contractLoanSchedule' => $schedule->id]))
            ->assertSessionMissing('fail');

        $schedule->refresh();
        $this->assertFalse($schedule->isChequePaid());
        $this->assertEquals(100000.0, (float) $schedule->remaining);
        $this->assertSame(0, $schedule->settlements()->count());
        $this->assertSame(0, DB::table('loan_statements')->whereIn('contract_loan_schedule_settlement_id', ContractLoanScheduleSettlement::where('contract_loan_schedule_id', $schedule->id)->pluck('id'))->count());

        $this->assertSame(1, $this->chequeRowCount());
        $row = $this->chequeRow($schedule);
        $this->assertSame($due, $this->day($row->date));
        $this->assertEquals(self::FUNDING - 100000, $this->balanceOn(now()->subDays(10)));
        $this->assertChainIsConsistent();
    }

    public function test_mark_as_paid_refuses_a_date_before_the_due_date_or_after_today(): void
    {
        $schedule = $this->makeInstallment(100000, now()->subDays(5)->format('Y-m-d'));

        $this->markAsPaid($schedule, now()->subDays(6)->format('Y-m-d'))->assertSessionHasErrors('actual_payment_date');
        $this->markAsPaid($schedule, now()->addDay()->format('Y-m-d'))->assertSessionHasErrors('actual_payment_date');

        $this->assertFalse($schedule->fresh()->isChequePaid());
        $this->assertSame($this->day($schedule->date), $this->day($this->chequeRow($schedule)->date));
    }

    public function test_mark_as_paid_refuses_when_the_account_cannot_cover_the_cheque(): void
    {
        $poorAccount = $this->makeAccount(now()->subYear()->format('Y-m-d'), 1000);
        $schedule = $this->makeInstallment(100000, now()->subDays(3)->format('Y-m-d'), $poorAccount);

        $this->markAsPaid($schedule, now()->format('Y-m-d'))->assertSessionHas('fail');

        $this->assertFalse($schedule->fresh()->isChequePaid());
        $this->assertSame(0, $schedule->settlements()->count());
    }

    public function test_a_paid_cheque_cannot_be_paid_twice(): void
    {
        $schedule = $this->makeInstallment(100000, now()->subDays(3)->format('Y-m-d'));
        $this->markAsPaid($schedule, now()->format('Y-m-d'))->assertSessionMissing('fail');

        $this->markAsPaid($schedule->fresh(), now()->format('Y-m-d'))->assertSessionHas('fail');

        $this->assertSame(1, $schedule->settlements()->count());
        $this->assertSame(1, $this->chequeRowCount());
    }

    /* ── editing / deleting the installment ────────────────────────── */

    public function test_editing_an_unpaid_installment_moves_and_resizes_its_cheque(): void
    {
        $schedule = $this->makeInstallment(100000, now()->addDays(5)->format('Y-m-d'));
        $newDue = now()->addDays(40)->format('Y-m-d');

        $schedule->update(['date' => $newDue, 'cheque_amount' => 120000, 'remaining' => 120000]);

        $this->assertSame(1, $this->chequeRowCount());
        $row = $this->chequeRow($schedule);
        $this->assertSame($newDue, $this->day($row->date));
        $this->assertEquals(120000.0, (float) $row->credit);
        $this->assertEquals(self::FUNDING, $this->balanceOn(now()->addDays(39)));
        $this->assertEquals(self::FUNDING - 120000, $this->balanceOn(now()->addDays(40)));
        $this->assertChainIsConsistent();
    }

    public function test_moving_an_installment_to_another_account_moves_its_cheque(): void
    {
        $other = $this->makeAccount(now()->subYear()->format('Y-m-d'), 500000);
        $schedule = $this->makeInstallment(100000, now()->addDays(5)->format('Y-m-d'));

        $schedule->update(['financial_institution_account_id' => $other->id, 'account_number' => $other->account_number]);

        $row = $this->chequeRow($schedule);
        $this->assertSame($other->id, (int) $row->financial_institution_account_id);
        $this->assertEquals(self::FUNDING, $this->balanceOn(now()->addYear()), 'The old account gets its money back.');
        $this->assertEquals(400000.0, $this->balanceOn(now()->addYear(), $other));
        $this->assertChainIsConsistent();
        $this->assertChainIsConsistent($other);
    }

    public function test_deleting_an_unpaid_installment_removes_its_cheque(): void
    {
        $schedule = $this->makeInstallment(100000, now()->addDays(5)->format('Y-m-d'));

        // The schedule table's own Delete button.
        $this->actingAs($this->user)
            ->delete(route('salesGathering.destroy', ['company' => $this->company->id, 'salesGathering' => $schedule->id]).'?modelType=ContractLoanSchedule')
            ->assertSessionMissing('fail');

        $this->assertNull(ContractLoanSchedule::find($schedule->id));

        $this->assertSame(0, $this->chequeRowCount());
        $this->assertEquals(self::FUNDING, $this->balanceOn(now()->addYear()));
        $this->assertChainIsConsistent();
    }

    /* ── settlements recorded the old way ──────────────────────────── */

    public function test_an_older_partial_settlement_reduces_what_the_cheque_still_carries(): void
    {
        $due = now()->subDays(10)->format('Y-m-d');
        $schedule = $this->makeInstallment(100000, $due);

        // How a partial settlement used to be stored: its own bank row.
        $legacy = $schedule->settlements()->create([
            'current_account_number' => $this->account->account_number,
            'amount' => 40000,
            'date' => now()->subDays(8)->format('Y-m-d'),
            'company_id' => $this->company->id,
        ]);
        $legacy->handleCreditStatement($this->company->id, $this->bank->id, \App\Models\AccountType::onlyCurrentAccount()->first(), $this->account->account_number, null, $legacy->date, 40000);

        $this->assertEquals(60000.0, (float) $this->chequeRow($schedule)->credit);
        $this->assertEquals(self::FUNDING - 100000, $this->balanceOn(now()), 'Still exactly one cheque out — 40,000 + 60,000.');

        $this->markAsPaid($schedule->fresh(), now()->format('Y-m-d'))->assertSessionMissing('fail');

        $schedule->refresh();
        $this->assertEquals(0.0, (float) $schedule->remaining);
        $this->assertEquals(60000.0, (float) $schedule->settlements()->where('is_cheque_payment', 1)->sum('amount'));
        $this->assertEquals(self::FUNDING - 100000, $this->balanceOn(now()));

        // The old settlement can't be pulled out from under a paid cheque.
        $this->actingAs($this->user)
            ->delete(route('delete.contract.loan.schedule.settlements', ['company' => $this->company->id, 'contractLoanScheduleSettlement' => $legacy->id]))
            ->assertSessionHas('fail');
        $this->assertNotNull(ContractLoanScheduleSettlement::find($legacy->id));
        $this->assertChainIsConsistent();
    }

    /* ── the screen ────────────────────────────────────────────────── */

    public function test_the_cheque_screen_offers_mark_as_paid_then_unmark(): void
    {
        $schedule = $this->makeInstallment(100000, now()->subDays(3)->format('Y-m-d'));
        $url = route('view.contract.loan.schedule.settlements', ['company' => $this->company->id, 'contractLoanSchedule' => $schedule->id]);

        $props = $this->actingAs($this->user)->get($url)->assertOk()->viewData('page')['props'];
        $this->assertFalse($props['contractLoanSchedule']['is_paid']);
        $this->assertStringContainsString('mark-as-paid', $props['markAsPaidUrl']);
        $this->assertSame('100,000', $props['contractLoanSchedule']['amount_to_pay_formatted']);

        $this->markAsPaid($schedule, now()->format('Y-m-d'))->assertSessionMissing('fail');

        $props = $this->actingAs($this->user)->get($url)->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['contractLoanSchedule']['is_paid']);
        $this->assertSame(now()->format('d-m-Y'), $props['contractLoanSchedule']['actual_payment_date_formatted']);
    }

    /* ── helpers ───────────────────────────────────────────────────── */

    private function makeAccount(string $openingDate, float $funding, bool $asOpeningBalance = false): FinancialInstitutionAccount
    {
        $account = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => 'TEST-CHQ-'.uniqid(),
            'currency' => 'egp',
            'balance_amount' => $funding,
            'balance_date' => $openingDate,
            'exchange_rate' => 1,
            'is_active' => 1,
        ]);

        // On a current account debit is money IN.
        CurrentAccountBankStatement::create([
            'financial_institution_account_id' => $account->id,
            'company_id' => $this->company->id,
            'is_beginning_balance' => $asOpeningBalance ? 1 : 0,
            'date' => $openingDate,
            'debit' => $funding,
            'credit' => 0,
            'beginning_balance' => 0,
        ]);

        return $account;
    }

    private function makeInstallment(float $amount, string $due, ?FinancialInstitutionAccount $account = null, float $interest = 0, ?float $principle = null): ContractLoanSchedule
    {
        $account ??= $this->account;

        return ContractLoanSchedule::create([
            'leasing_contract_id' => $this->contract->id,
            'company_id' => $this->company->id,
            'date' => $due,
            'beginning_balance' => 1000000,
            'cheque_amount' => $amount,
            'interest_amount' => $interest,
            'principle_amount' => $principle ?? $amount,
            'end_balance' => 1000000 - ($principle ?? $amount),
            'remaining' => $amount,
            'status' => 'not_paid',
            'cheque_number' => 'CHQ-'.random_int(100000, 999999),
            'drawee_bank_id' => $this->bank->id,
            'financial_institution_account_id' => $account->id,
            'account_number' => $account->account_number,
        ]);
    }

    private function markAsPaid(ContractLoanSchedule $schedule, string $date)
    {
        return $this->actingAs($this->user)->post(
            route('contract.loan.schedule.mark.as.paid', ['company' => $this->company->id, 'contractLoanSchedule' => $schedule->id]),
            ['actual_payment_date' => $date]
        );
    }

    private function chequeRow(ContractLoanSchedule $schedule): ?CurrentAccountBankStatement
    {
        return CurrentAccountBankStatement::where('contract_loan_schedule_id', $schedule->id)->first();
    }

    private function chequeRowCount(): int
    {
        return CurrentAccountBankStatement::whereIn('contract_loan_schedule_id', ContractLoanSchedule::where('leasing_contract_id', $this->contract->id)->pluck('id'))->count();
    }

    private function balanceOn($date, ?FinancialInstitutionAccount $account = null): float
    {
        return (float) DB::table('current_account_bank_statements')
            ->where('financial_institution_account_id', ($account ?? $this->account)->id)
            ->where('is_active', 1)
            ->where('date', '<=', Carbon::make($date)->format('Y-m-d'))
            ->orderByDesc('full_date')->orderByDesc('id')
            ->value('end_balance');
    }

    /**
     * Every row starts where the previous one ended, and ends at
     * beginning + debit − credit.
     */
    private function assertChainIsConsistent(?FinancialInstitutionAccount $account = null): void
    {
        $rows = DB::table('current_account_bank_statements')
            ->where('financial_institution_account_id', ($account ?? $this->account)->id)
            ->where('is_active', 1)
            ->orderBy('full_date')->orderBy('id')
            ->get();

        $previousEnd = null;
        foreach ($rows as $row) {
            if ($previousEnd !== null) {
                $this->assertEqualsWithDelta($previousEnd, (float) $row->beginning_balance, 0.01, "Row {$row->id} does not start where the previous row ended.");
            }
            $this->assertEqualsWithDelta((float) $row->beginning_balance + (float) $row->debit - (float) $row->credit, (float) $row->end_balance, 0.01, "Row {$row->id} end balance is wrong.");
            $previousEnd = (float) $row->end_balance;
        }
    }

    private function day($date): string
    {
        return Carbon::make($date)->format('Y-m-d');
    }
}
