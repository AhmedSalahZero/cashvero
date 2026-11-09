<?php

namespace Tests\Feature\BankStatements;

use App\Models\CurrentAccountBankStatement;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesBanks;
use Tests\TestCase;

/**
 * A current account's statement is a chain: every row starts where the one before it ended,
 * and ends at start + money in − money out. The MySQL triggers keep the chain; this checks they
 * do whatever order the movements are typed in — including one dated before rows already there.
 *
 * Run: php artisan test --filter=StatementChainTest
 */
class StatementChainTest extends TestCase
{
    use MakesBanks;

    private function movement(int $accountId, int $companyId, string $at, float $in, float $out): void
    {
        CurrentAccountBankStatement::create([
            'financial_institution_account_id' => $accountId,
            'company_id' => $companyId,
            'money_received_id' => 0,
            'money_payment_id' => 0,
            'date' => substr($at, 0, 10),
            'full_date' => $at,
            'debit' => $in,
            'credit' => $out,
            'is_active' => 1,
        ]);
    }

    /** @return list<object> the account's rows in statement order */
    private function chain(int $accountId): array
    {
        return DB::table('current_account_bank_statements')
            ->where('financial_institution_account_id', $accountId)
            ->orderBy('full_date')->orderBy('id')
            ->get(['id', 'full_date', 'beginning_balance', 'debit', 'credit', 'end_balance'])->all();
    }

    private function assertChainHolds(array $rows, float $expectedClosing): void
    {
        $previousEnd = null;

        foreach ($rows as $row) {
            if ($previousEnd !== null) {
                $this->assertEqualsWithDelta($previousEnd, (float) $row->beginning_balance, 0.001, "row {$row->id} ({$row->full_date}) does not start where the previous one ended");
            }
            $this->assertEqualsWithDelta((float) $row->beginning_balance + (float) $row->debit - (float) $row->credit, (float) $row->end_balance, 0.001, "row {$row->id} ({$row->full_date}) does not add up");
            $previousEnd = (float) $row->end_balance;
        }

        $this->assertEqualsWithDelta($expectedClosing, $previousEnd, 0.001, 'closing balance');
    }

    public function test_movements_typed_in_date_order_form_an_unbroken_chain(): void
    {
        $company = $this->makeCompany();
        $account = $this->currentAccount($this->bank($company));

        $this->movement($account->id, $company->id, '2020-01-05 09:00:00', 1000, 0);
        $this->movement($account->id, $company->id, '2020-01-10 09:00:00', 0, 250);
        $this->movement($account->id, $company->id, '2020-01-20 09:00:00', 500, 0);

        // 1000 − 250 + 500 = 1250, worked out by hand
        $this->assertChainHolds($this->chain($account->id), 1250);
    }

    public function test_a_movement_dated_before_the_others_is_slotted_in_and_the_rest_follow(): void
    {
        $company = $this->makeCompany();
        $account = $this->currentAccount($this->bank($company));

        $this->movement($account->id, $company->id, '2020-01-10 09:00:00', 1000, 0);
        $this->movement($account->id, $company->id, '2020-01-20 09:00:00', 0, 300);
        // typed last, happened first
        $this->movement($account->id, $company->id, '2020-01-05 09:00:00', 200, 0);

        $rows = $this->chain($account->id);
        // The model gives a movement its own time of day; what matters is that it comes first.
        $this->assertSame('2020-01-05', substr($rows[0]->full_date, 0, 10));
        // 200 + 1000 − 300 = 900
        $this->assertChainHolds($rows, 900);
    }

    public function test_changing_an_old_movement_moves_every_later_balance(): void
    {
        $company = $this->makeCompany();
        $account = $this->currentAccount($this->bank($company));

        $this->movement($account->id, $company->id, '2020-01-05 09:00:00', 1000, 0);
        $this->movement($account->id, $company->id, '2020-01-10 09:00:00', 0, 250);
        $this->movement($account->id, $company->id, '2020-01-20 09:00:00', 500, 0);

        $first = $this->chain($account->id)[0];
        CurrentAccountBankStatement::findOrFail($first->id)->update(['debit' => 1100]);

        // 1100 − 250 + 500 = 1350
        $this->assertChainHolds($this->chain($account->id), 1350);
    }

    public function test_two_accounts_never_share_a_chain(): void
    {
        $company = $this->makeCompany();
        $bank = $this->bank($company);
        $first = $this->currentAccount($bank);
        $second = $this->currentAccount($bank);

        $this->movement($first->id, $company->id, '2020-01-05 09:00:00', 1000, 0);
        $this->movement($second->id, $company->id, '2020-01-06 09:00:00', 40, 0);

        $this->assertChainHolds($this->chain($first->id), 1000);
        $this->assertChainHolds($this->chain($second->id), 40);
    }
}
