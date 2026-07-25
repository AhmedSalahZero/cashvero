<?php

namespace App\Rules;

use App\Models\AccountType;
use App\Models\Company;
use App\Models\MoneyReceived;
use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Support\Facades\DB;

class MoneyReceivedCanBeDeletedRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected MoneyReceived $moneyReceived ;
	protected Company $company ; 
    public function __construct(MoneyReceived $moneyReceived , Company $company)
    {
        $this->moneyReceived = $moneyReceived;
		$this->company = $company;
    }

    /**
     * Determine if the validation rule passes.
     *
     * Business rule (confirmed with project owner, 2026-07-24): a Money Received can NOT be
     * deleted if doing so would leave the affected bank account / safe with a negative balance,
     * or push an overdraft account's utilization past its approved facility limit, ON ANY DATE
     * from the receipt's own date forward to today (not just "today's" resulting balance).
     *
     * Why we check every date, not just today: deleting a statement row here is implemented by
     * zeroing its debit/credit and re-saving it, which fires the `updated` model event and
     * cascades a full recalculation of every subsequent statement row's beginning/end balance
     * (see CurrentAccountBankStatement::updateNextRows() and its siblings). Removing an inflow
     * shifts every day's balance downward by the same fixed amount from that point forward, so a
     * check that only looked at today's final number could silently allow a deletion that would
     * have made some day in the past show an impossible negative (or over-limit) balance.
     *
     * Instead of replaying that whole recalculation to check this, we ask a single, cheap
     * question: what is the lowest end_balance (or, for an overdraft account, the lowest `room`
     * — the remaining space under the facility limit) already on record for this account, on any
     * date from the receipt's date onward? Since the shift is a fixed amount, checking that one
     * worst point tells us whether the deletion is safe everywhere in the timeline.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		$receivedAmount = $this->moneyReceived->getReceivedAmount();
		$receivedDate = $this->moneyReceived->getReceivingDate();

		if ($this->moneyReceived->isChequeInSafe()) {
			return true ;
		}

		if ($this->moneyReceived->isIncomingTransfer() || $this->moneyReceived->isCheque() || $this->moneyReceived->isCashInBank()) {
			$accountType = AccountType::find($this->moneyReceived->getAccountTypeId());
			if (!$accountType) {
				return true ;
			}

			$accountModelClass = '\App\Models\\'.$accountType->getModelName();
			$accountNumberModel = $accountModelClass::findByAccountNumber(
				$this->moneyReceived->getAccountNumber(),
				$this->company->id,
				$this->moneyReceived->getFinancialInstitutionId()
			);
			if (!$accountNumberModel) {
				return true ;
			}

			$column = $accountType->isOverdraftAccount() ? 'room' : 'end_balance';
			$statementTableName = $accountNumberModel->getStatementTableName();
			$foreignKeyName = $accountNumberModel->getForeignKeyInStatementTable();

			$query = DB::table($statementTableName)
				->where($foreignKeyName, $accountNumberModel->id)
				->where('company_id', $this->company->id)
				->where('date', '>=', $receivedDate);

			// Only current_account_bank_statements carries the `is_active` "not yet due" convention
			// (see the trigger audit); the overdraft/other statement tables don't have this column.
			if ($accountType->getSlug() === AccountType::CURRENT_ACCOUNT) {
				$query->where('is_active', 1);
			}

			$lowestRecordedBalance = $query->min($column);

			if (is_null($lowestRecordedBalance)) {
				// No statement history found from the receipt's date onward (should not normally
				// happen, since the receipt itself always creates a statement row) — nothing on
				// record to protect against, so don't block on data we don't have.
				return true ;
			}

			if (($lowestRecordedBalance - $receivedAmount) < 0) {
				return false ;
			}

			return true ;
		}

		if ($this->moneyReceived->isCashInSafe()) {
			$lowestRecordedBalance = DB::table('cash_in_safe_statements')
				->where('branch_id', $this->moneyReceived->getCashInSafeReceivingBranchId())
				->where('company_id', $this->company->id)
				->where('currency', $this->moneyReceived->getReceivingCurrency())
				->where('date', '>=', $receivedDate)
				->min('end_balance');

			if (is_null($lowestRecordedBalance)) {
				return true ;
			}

			if (($lowestRecordedBalance - $receivedAmount) < 0) {
				return false ;
			}

			return true ;
		}

		return true ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('This Money Received Can Not Be Deleted .. There Is No Enough Balance');
    }
}
