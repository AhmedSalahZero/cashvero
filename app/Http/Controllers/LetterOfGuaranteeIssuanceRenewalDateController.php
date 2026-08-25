<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreLgRenewalDateRequest;
use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitutionAccount;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LgRenewalDateHistory;
use App\Support\LetterOfGuarantee\LgRenewalTerms;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LetterOfGuaranteeIssuanceRenewalDateController
 * ------------------------------------------------------------------
 * Manages the renewal-date history of a single LG Issuance — each
 * time an LG is renewed (a new expiry date is set and renewal fees
 * are charged), an LgRenewalDateHistory row is kept so the full
 * renewal trail is never lost. Only the LAST history row is
 * editable/deletable (matches the original Blade behavior exactly —
 * see $loop->last in the old view). Same shape as
 * TimeOfDepositRenewalDateController — this was the last piece of
 * Section 3 (Letters of Credit & Guarantee) left un-migrated.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / edit() → MIGRATED to Vue + Inertia. Both render the
 *      same shared page,
 *      resources/js/Pages/LetterOfGuaranteeIssuance/RenewalHistory.vue,
 *      distinguished by a `mode: 'create' | 'edit'` prop (via the
 *      shared renderPage() helper below).
 *   ✅ store() / update() / destroy() → the Vue migration left all
 *      three untouched. They were changed later, for a different
 *      reason (below): the renewal-fees posting, Odoo sync and
 *      commission recalculation they already did are still exactly
 *      as they were.
 *
 * ── Renewal re-pricing ─────────────────────────────────────────────
 * A renewal is no longer just "a new expiry date + a renewal fee".
 * The bank re-prices the guarantee when it renews it: it can ask for
 * a different cash cover and charge a different commission, and both
 * apply to the NEW term only. store()/update()/destroy() delegate all
 * of that to LgRenewalTerms — see that class for how the difference
 * is computed and why only the difference is posted.
 *
 * The one ordering rule that matters here: the new terms must reach
 * the issuance BEFORE the new term's commission rows are posted,
 * because storeCommissionToCreditCurrentAccountBankStatement() reads
 * the commission off the issuance.
 *
 * ⚠️ IMPORTANT — the same date-format trap as TimeOfDeposit's
 * renewal history: store() and update() manually parse the incoming
 * `renewal_date` field with explode('/', ...), assuming MM/DD/YYYY
 * (what the old jQuery datepicker sent). This is UNCHANGED,
 * deliberately — RenewalHistory.vue must submit `renewal_date` as a
 * MM/DD/YYYY string via the same toSlashDate() helper used on the TD
 * page, not the ISO format a native <input type="date"> produces.
 *
 * ⚠️ A second, LG-specific quirk preserved exactly from the original
 * Blade: the "Expiry Date" shown/submitted is NOT simply "the
 * previous history row's date". In create mode it's the LG's current
 * renewal_date column; in edit mode it's
 * $letterOfGuaranteeIssuance->getRenewalDateBefore($currentRenewalDate)
 * — the most recent history row strictly before the LG's current
 * renewal_date. Both are computed server-side in renderPage() below,
 * exactly matching the original view's inline PHP.
 */
class LetterOfGuaranteeIssuanceRenewalDateController
{
    use GeneralFunctions;

	/**
	 * Shared by index() (create mode) and edit() (edit mode) — builds
	 * every prop RenewalHistory.vue needs: the LG's summary info, the
	 * full renewal history flattened into plain arrays (with a
	 * days-count computed the same way the old Blade view did,
	 * between each entry and the one before it), and the form
	 * defaults (see the class docblock's Expiry Date note above).
	 */
	protected function renderPage(Company $company, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, ?LgRenewalDateHistory $model)
	{
		$renewalDateHistories = $letterOfGuaranteeIssuance->renewalDateHistories;
		$count = $renewalDateHistories->count();
		$previousDate = null;

		$rows = $renewalDateHistories->values()->map(function (LgRenewalDateHistory $history, int $index) use (&$previousDate, $count, $company, $letterOfGuaranteeIssuance) {
			$currentRenewalDate = $history->getRenewalDate();
			$daysCount = $previousDate
				? getDiffBetweenTwoDatesInDays(Carbon::make($previousDate), Carbon::make($currentRenewalDate))
				: null;
			$isOriginal = is_null($previousDate);
			$previousDate = $currentRenewalDate;

			return [
				'id' => $history->id,
				'renewal_date_formatted' => $history->getRenewalDateFormatted(),
				'is_original' => $isOriginal,
				'days_count' => $daysCount,
				'fees_amount_formatted' => $history->getFeesAmountFormatted(),
				// NULL on all three = this renewal changed no terms,
				// which is every renewal recorded before the bank's
				// re-pricing was supported. The table shows a dash.
				'cash_cover_amount_formatted' => $history->getCashCoverAmountFormatted(),
				'cash_cover_difference_formatted' => $history->getCashCoverDifferenceFormatted(),
				'lg_commission_amount_formatted' => $history->getLgCommissionAmountFormatted(),
				'is_last' => $index === $count - 1,
				'edit_url' => route('edit.letter.of.issuance.renewal.date', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $letterOfGuaranteeIssuance->id, 'LgRenewalDateHistory' => $history->id]),
				'delete_url' => route('delete.letter.of.issuance.renewal.date', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $letterOfGuaranteeIssuance->id, 'LgRenewalDateHistory' => $history->id]),
			];
		})->values();

		$currentRenewalDate = $letterOfGuaranteeIssuance->getRenewalDate();

		return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/RenewalHistory', [
			'company' => ['id' => $company->id],
			'letterOfGuaranteeIssuance' => [
				'id' => $letterOfGuaranteeIssuance->id,
				'transaction_name' => $letterOfGuaranteeIssuance->getTransactionName(),
				'source_formatted' => $letterOfGuaranteeIssuance->getSourceFormatted(),
				'lg_code' => $letterOfGuaranteeIssuance->getLgCode(),
				'issuance_date_formatted' => $letterOfGuaranteeIssuance->getIssuanceDateFormatted(),
				'is_expired' => $letterOfGuaranteeIssuance->isExpired(),
			],
			'rows' => $rows,
			'mode' => $model ? 'edit' : 'create',
			// The original only shows the add/edit form at all when
			// either editing an existing row, or the LG has expired
			// (you can't renew an LG that hasn't reached its current
			// expiry yet). Same rule as TD's renewal page.
			'canShowForm' => (bool) $model || $letterOfGuaranteeIssuance->isExpired(),
			'formDefaults' => [
				// See class docblock — this is NOT simply "the
				// previous row's date" in edit mode.
				'expiry_date' => $model
					? $letterOfGuaranteeIssuance->getRenewalDateBefore($currentRenewalDate)
					: $currentRenewalDate,
				'renewal_date' => $model ? $model->getRenewalDate() : null,
				'fees_amount' => $model ? $model->getFeesAmount() : 0,
				/**
				 * The bank re-prices the LG at renewal, so the three
				 * term fields open pre-filled with what is in force
				 * right now — the user overwrites only what the bank
				 * actually changed.
				 *
				 * In edit mode the issuance is ALREADY carrying this
				 * renewal's terms (they were applied when it was
				 * saved), so the issuance value is the right default
				 * either way; the row's own value is preferred only
				 * so an unchanged term stays visibly unchanged.
				 */
				'cash_cover_amount' => $model && ! is_null($model->getCashCoverAmount())
					? $model->getCashCoverAmount()
					: $letterOfGuaranteeIssuance->getCashCoverAmount(),
				'lg_commission_amount' => $model && ! is_null($model->getLgCommissionAmount())
					? $model->getLgCommissionAmount()
					: $letterOfGuaranteeIssuance->getLgCommissionAmount(),
				'min_lg_commission_fees' => $model && ! is_null($model->getMinLgCommissionFees())
					? $model->getMinLgCommissionFees()
					: $letterOfGuaranteeIssuance->getMinLgCommissionFees(),
			],
			/**
			 * What is in force BEFORE the renewal being written — shown
			 * read-only next to each input so "10 became 20" is
			 * visible without digging through the history table.
			 */
			'currentTerms' => [
				'cash_cover_amount' => $model && ! is_null($model->getPreviousCashCoverAmount())
					? $model->getPreviousCashCoverAmount()
					: $letterOfGuaranteeIssuance->getCashCoverAmount(),
				'lg_commission_amount' => $model && ! is_null($model->lg_commission_amount)
					? $model->previous_lg_commission_amount
					: $letterOfGuaranteeIssuance->getLgCommissionAmount(),
				'min_lg_commission_fees' => $model && ! is_null($model->min_lg_commission_fees)
					? $model->previous_min_lg_commission_fees
					: $letterOfGuaranteeIssuance->getMinLgCommissionFees(),
				'currency' => $letterOfGuaranteeIssuance->getLgCurrency(),
				// Already translated here, same as every other LG form
				// gets it — the raw code ('quarterly') is not a
				// translation key anywhere.
				'commission_interval' => getCommissionInterval()[$letterOfGuaranteeIssuance->getLgCommissionInterval()] ?? null,
			],
			'storeUrl' => route('store.letter.of.issuance.renewal.date', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $letterOfGuaranteeIssuance->id]),
			'updateUrl' => $model ? route('update.letter.of.issuance.renewal.date', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $letterOfGuaranteeIssuance->id, 'LgRenewalDateHistory' => $model->id]) : null,
			'indexUrl' => route('letter.of.issuance.renewal.date', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $letterOfGuaranteeIssuance->id]),
			'backUrl' => route('view.letter.of.guarantee.issuance', ['company' => $company->id]),
			'navUrls' => [
				'home' => route('home', ['company' => $company->id]),
				'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
				'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
				'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
				'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
			],
		]);
	}

	/**
	 * ✅ MIGRATED to Vue + Inertia. Renders RenewalHistory.vue in
	 * `mode: 'create'`.
	 */
	public function index(Company $company,Request $request,LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance)
	{
		return $this->renderPage($company, $letterOfGuaranteeIssuance, null);
    }
	public function store(StoreLgRenewalDateRequest $request, Company $company, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance){

		$date = $request->get('renewal_date') ;
		$renewalFeesAmount = number_unformat($request->get('fees_amount',0));
		$expiryDate = $letterOfGuaranteeIssuance->getRenewalDate();
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$renewalDate = $year.'-'.$month.'-'.$day ;
		$financialInstitution = $letterOfGuaranteeIssuance->financialInstitutionBank;
		$lgType = $letterOfGuaranteeIssuance->getLgType();
		$transactionName = $letterOfGuaranteeIssuance->getTransactionName();
		$financialInstitutionAccount = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->lg_fees_and_commission_account_id);
	
		if(!$letterOfGuaranteeIssuance->renewalDateHistories->count()){
			/**
			 * * في حالة اول مرة هنضيف تاريخ التجديد الاصلي اكنة تاريخ علشان نحتفظ بيه علشان ما يضيعش
			 */
			LgRenewalDateHistory::create([
				'company_id'=>$company->id ,
				'fees_amount'=>0,
				'renewal_date'=>$letterOfGuaranteeIssuance->getRenewalDate(),
				'letter_of_guarantee_issuance_id'=>$letterOfGuaranteeIssuance->id,
			]);
		}
		$lgRenewalDateHistory = LgRenewalDateHistory::create([
			'company_id'=>$company->id ,
			'fees_amount'=>$renewalFeesAmount,
			'renewal_date'=>$renewalDate,
			'letter_of_guarantee_issuance_id'=>$letterOfGuaranteeIssuance->id
		]);

		/**
		 * The bank can re-price the LG at renewal — a different cash
		 * cover, a different commission. This has to run BEFORE the
		 * commission rows below are posted: they read the commission
		 * straight off the issuance, so the issuance must already be
		 * carrying the NEW one. $expiryDate (the old expiry) is the
		 * start of the new term, which is the same date the renewal
		 * fee and the new term's first commission land on.
		 *
		 * @see \App\Support\LetterOfGuarantee\LgRenewalTerms
		 */
		LgRenewalTerms::apply($lgRenewalDateHistory, $letterOfGuaranteeIssuance, LgRenewalTerms::fromInput($request->all()), $expiryDate);

		$lgRenewalDateHistory->handleRenewalFeesForOdoo($renewalFeesAmount,$expiryDate);
		
		$this->storeCommissionToCreditCurrentAccountBankStatement($lgRenewalDateHistory,$letterOfGuaranteeIssuance,$company,$expiryDate,$renewalDate,$transactionName,$lgType);
		$financialInstitutionAccountOpeningBalance = $financialInstitutionAccount->getOpeningBalanceDate();
		if(Carbon::make($expiryDate)->greaterThanOrEqualTo(Carbon::make($financialInstitutionAccountOpeningBalance))){
			$letterOfGuaranteeIssuance->storeCurrentAccountCreditBankStatement($expiryDate,$renewalFeesAmount , $financialInstitutionAccount->id,0,1,__('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'en'),'transactionName'=>$transactionName],'en') , __('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'ar'),'transactionName'=>$transactionName],'ar'),true);
		}
		
		$letterOfGuaranteeIssuance->update([
			'renewal_date'=>$renewalDate
		]);
		
		
		return redirect()->route('letter.of.issuance.renewal.date',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance->id]);
	}
	protected function storeCommissionToCreditCurrentAccountBankStatement(LgRenewalDateHistory $lgRenewalDateHistory , LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance,Company $company,string $expiryDate , string $renewalDate,string $transactionName, string $lgType )
	{
		$lgRenewalDateHistoryId = $lgRenewalDateHistory->id;
		$lgCommissionInterval = $letterOfGuaranteeIssuance->getLgCommissionInterval();
		/**
		 * ⚠️ REAL BUG FIXED HERE (same Carbon 3 sign-bug class already
		 * found and fixed on TimeOfDeposit::calculateInterestAmount(),
		 * Cheque::calculateChequeExpectedCollectionDate(), and
		 * FactoringTransaction's interest calculation).
		 *
		 * $expiryDate (the OLD expiry, almost always the EARLIER date)
		 * was the base, $renewalDate (the new, later expiry) was the
		 * argument. Under Carbon 2 diffInMonths() always returned a
		 * positive count regardless of argument order; Carbon 3
		 * (shipped with this project's Laravel 12) made it signed by
		 * default, so this returned a NEGATIVE month-count in the
		 * normal renewal case, not an edge case. That negative count
		 * fed directly into $numberOfIterationsForQuarter below
		 * (ceil($lgDurationMonths / 3)), which controls how many real
		 * quarterly LG commission-fee entries get posted to the bank
		 * statement ledger on renewal — a negative/zero iteration
		 * count meant real commission charges could be silently
		 * under-posted or skipped entirely. Fixed by forcing
		 * $absolute = true, restoring the always-positive guarantee
		 * this code was written expecting, regardless of Carbon
		 * version.
		 */
		$lgDurationMonths = Carbon::make($expiryDate)->diffInMonths(Carbon::make($renewalDate), true);
	
		$numberOfIterationsForQuarter = ceil($lgDurationMonths / 3); 
		$issuanceDate = $expiryDate;
		$minLgCommissionAmount = $letterOfGuaranteeIssuance->getMinLgCommissionFees();
		$lgCommissionAmount = $letterOfGuaranteeIssuance->getLgCommissionAmount();
		$maxLgCommissionAmount = max($minLgCommissionAmount ,$lgCommissionAmount );
		$financialInstitutionId = $letterOfGuaranteeIssuance->getFinancialInstitutionBankId();
		$financialInstitutionAccountForFeesAndCommission = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->getFeesAndCommissionAccountId());
		$financialInstitutionAccountIdForFeesAndCommission = $financialInstitutionAccountForFeesAndCommission->id;
		$openingBalanceDateOfCurrentAccount = $financialInstitutionAccountForFeesAndCommission->getOpeningBalanceDate();
		$isOpeningBalance = $letterOfGuaranteeIssuance->isOpeningBalance();
		$letterOfGuaranteeIssuance->storeCommissionAmountCreditBankStatement( $lgCommissionInterval ,  $numberOfIterationsForQuarter ,  $issuanceDate, $openingBalanceDateOfCurrentAccount,$maxLgCommissionAmount, $financialInstitutionAccountIdForFeesAndCommission, $transactionName, $lgType, $isOpeningBalance,$lgRenewalDateHistoryId);
		
	}
	/**
	 * ✅ MIGRATED to Vue + Inertia. Renders RenewalHistory.vue in
	 * `mode: 'edit'`, pre-filled with $LgRenewalDateHistory's values.
	 */
	public function edit(Request $request , Company $company ,  LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance , LgRenewalDateHistory $LgRenewalDateHistory){
		return $this->renderPage($company, $letterOfGuaranteeIssuance, $LgRenewalDateHistory);
	}
	public function update(StoreLgRenewalDateRequest $request , Company $company ,  LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance  , LgRenewalDateHistory $LgRenewalDateHistory){
		$date = $request->get('renewal_date') ;
		$renewalFeesAmount = number_unformat($request->get('fees_amount',0));
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$renewalDate = $year.'-'.$month.'-'.$day ;
		$expiryDate = $request->get('expiry_date');

		$renewalFeesCurrentAccountBankStatement = $letterOfGuaranteeIssuance->renewalFeesCurrentAccountBankStatement($expiryDate) ;
		$financialInstitution = $letterOfGuaranteeIssuance->financialInstitutionBank;
		/**
		 * Put the issuance back on the terms that were in force BEFORE
		 * this renewal, and drop the cash cover difference it posted,
		 * before re-applying below. Without this the difference would
		 * be measured against the terms this very row already set —
		 * saving the same 20,000 twice would post another 10,000.
		 *
		 * Runs before the commission rows are dropped because it also
		 * removes this renewal's cash-cover row from
		 * current_account_bank_statements, which shares the same
		 * lg_renewal_date_history_id.
		 */
		LgRenewalTerms::revert($LgRenewalDateHistory, $letterOfGuaranteeIssuance);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($LgRenewalDateHistory->commissionCurrentBankStatements()->withoutGlobalScope('only_active')->get());
		$transactionName = $letterOfGuaranteeIssuance->getTransactionName();
		$lgType = $letterOfGuaranteeIssuance->getLgType();
		$financialInstitutionAccount = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->lg_fees_and_commission_account_id);
		$financialInstitutionAccountOpeningBalance = $financialInstitutionAccount->getOpeningBalanceDate();
		/**
		 * Same ordering rule as store(): the new terms have to be on
		 * the issuance before the commission is re-posted.
		 */
		LgRenewalTerms::apply($LgRenewalDateHistory, $letterOfGuaranteeIssuance, LgRenewalTerms::fromInput($request->all()), $expiryDate);
		$this->storeCommissionToCreditCurrentAccountBankStatement($LgRenewalDateHistory,$letterOfGuaranteeIssuance,$company,$expiryDate,$renewalDate,$transactionName,$lgType);
		if($renewalFeesCurrentAccountBankStatement){
			$renewalFeesCurrentAccountBankStatement->handleFullDateAfterDateEdit($expiryDate,0,$renewalFeesAmount);
		}
		else{
			if(Carbon::make($expiryDate)->greaterThanOrEqualTo(Carbon::make($financialInstitutionAccountOpeningBalance))){
				$letterOfGuaranteeIssuance->storeCurrentAccountCreditBankStatement($expiryDate,$renewalFeesAmount , $financialInstitutionAccount->id,0,1,__('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'en'),'transactionName'=>$transactionName],'en') , __('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'ar'),'transactionName'=>$transactionName],'ar'),true);
			}
		}
		$LgRenewalDateHistory->update([
			'renewal_date'=>$renewalDate ,
			'fees_amount'=>$renewalFeesAmount
		]);
		$LgRenewalDateHistory->unlinkRenewalFeesForOddo();
		$LgRenewalDateHistory->handleRenewalFeesForOdoo($renewalFeesAmount,$expiryDate);
		$letterOfGuaranteeIssuance->update([
			'renewal_date'=>$renewalDate
		]);
		

		return redirect()->route('letter.of.issuance.renewal.date',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance->id]);
		
	}
	public function destroy( Company $company ,  LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance , LgRenewalDateHistory $LgRenewalDateHistory)
	{
		
		/**
		 * Deleting a renewal un-does its re-pricing too: the issuance
		 * goes back to the cash cover and commission that were in
		 * force before it, and the cash cover difference it posted is
		 * removed. Only rows carrying THIS renewal's id are touched —
		 * the original issuance's cash cover stays put.
		 */
		LgRenewalTerms::revert($LgRenewalDateHistory, $letterOfGuaranteeIssuance);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($LgRenewalDateHistory->commissionCurrentBankStatements()->withoutGlobalScope('only_active')->get());
		$oldRenewalDate = $letterOfGuaranteeIssuance->getRenewalDate();
		$expiryDate = $letterOfGuaranteeIssuance->getRenewalDateBefore($oldRenewalDate);
		$renewalFeesCurrentAccountBankStatement = $letterOfGuaranteeIssuance->renewalFeesCurrentAccountBankStatement($expiryDate) ;
		if($renewalFeesCurrentAccountBankStatement){
			$renewalFeesCurrentAccountBankStatement->delete();
		}
		$LgRenewalDateHistory->unlinkRenewalFeesForOddo();
		$LgRenewalDateHistory->delete();
		$letterOfGuaranteeIssuance = $letterOfGuaranteeIssuance->refresh();
		$lastHistory = $letterOfGuaranteeIssuance->renewalDateHistories->last();
		$letterOfGuaranteeIssuance->update([
			'renewal_date'=>$lastHistory->renewal_date 
			]) ; 
			/**
			 * * لو معدش فاضل غيرها دا معناه انه حذف تاني عنصر وبالتالي العنصر الاول اللي معتش فاضل غيره هو الديو ديت الاصلي ففي الحاله
			 * * دي هنحذفه معتش ليه لزمة
			 */
			if($letterOfGuaranteeIssuance->renewalDateHistories->count() == 1){
				$lastHistory->delete();
			}
		return redirect()->route('letter.of.issuance.renewal.date',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance->id]);
	}
	
}
