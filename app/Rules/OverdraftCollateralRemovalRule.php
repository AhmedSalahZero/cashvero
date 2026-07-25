<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Support\Facades\DB;

/**
 * Blocks a VOLUNTARY removal of overdraft collateral — a cheque leaving "under collection"
 * status (returned to the safe, or moved to a different account while still under collection),
 * or a contract losing its collateral status / being deleted — whenever doing so would leave the
 * facility's available room below zero. A negative room after removal means real transactions
 * have already drawn on the room that specific piece of collateral provided; removing it would
 * silently put the facility over its practical limit.
 *
 * Confirmed business rule (project owner, 2026-07-24): this rule is for VOLUNTARY removals only.
 * A cheque being REJECTED is a real bank event and must always be allowed to record, never
 * blocked here — the resulting over-limit facility is instead caught downstream by the existing
 * `AmountCanNotBeGreaterThanEndBalanceAtPaymentDate` rule, which already refuses any new Money
 * Payment drawn from an account whose room is insufficient. Do not use this rule on a rejection
 * action.
 */
class OverdraftCollateralRemovalRule implements ImplicitRule
{
	protected string $bankStatementTable;
	protected string $facilityForeignKey;
	protected int $facilityId;
	protected int $companyId;
	protected float $contributionBeingRemoved;
	protected string $failMessage;

	public function __construct(string $bankStatementTable, string $facilityForeignKey, int $facilityId, int $companyId, float $contributionBeingRemoved)
	{
		$this->bankStatementTable = $bankStatementTable;
		$this->facilityForeignKey = $facilityForeignKey;
		$this->facilityId = $facilityId;
		$this->companyId = $companyId;
		$this->contributionBeingRemoved = $contributionBeingRemoved;
		$this->failMessage = __('Error');
	}

	/**
	 * Determine if the validation rule passes.
	 *
	 * @param  string  $attribute
	 * @param  mixed  $value
	 * @return bool
	 */
	public function passes($attribute, $value)
	{
		if ($this->contributionBeingRemoved <= 0) {
			return true;
		}

		$currentRoom = DB::table($this->bankStatementTable)
			->where($this->facilityForeignKey, $this->facilityId)
			->where('company_id', $this->companyId)
			->orderByDesc('date')
			->orderByDesc('id')
			->value('room');

		$currentRoom = $currentRoom ?? 0;

		if ($currentRoom < $this->contributionBeingRemoved) {
			$this->failMessage = __('This cannot be removed right now — the facility\'s available room is less than the limit this provided, which means real transactions already rely on this room. Settle or reduce those transactions first.');
			return false;
		}

		return true;
	}

	/**
	 * Get the validation error message.
	 *
	 * @return string
	 */
	public function message()
	{
		return $this->failMessage;
	}
}
