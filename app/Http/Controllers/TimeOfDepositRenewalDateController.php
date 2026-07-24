<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreTdRenewalDateRequest;
use App\Models\Company;
use App\Models\TdRenewalDateHistory;
use App\Models\TimeOfDeposit;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;

/**
 * TimeOfDepositRenewalDateController
 * ------------------------------------------------------------------
 * Manages the renewal-date history of a single Time Of Deposit — each
 * time a TD's term is renewed (a new expiry date + interest rate is
 * set), a TdRenewalDateHistory row is kept so the full renewal trail
 * is never lost. Only the LAST history row is editable/deletable
 * (matches the original Blade behavior exactly — see $loop->last in
 * the old view).
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / edit() → MIGRATED to Vue + Inertia. Both render the
 *      same shared page, resources/js/Pages/TimeOfDeposits/RenewalHistory.vue,
 *      distinguished by a `mode: 'create' | 'edit'` prop (via the
 *      shared renderPage() helper below).
 *   ✅ store() / update() / destroy() → presentation-only change (both
 *      already redirect back to the migrated index() page). The
 *      financial logic — interest recalculation, bank statement
 *      posting, TD state updates — is UNCHANGED, deliberately.
 *
 * ⚠️ IMPORTANT — a date-format trap that is easy to reintroduce:
 * store() and update() manually parse the incoming `renewal_date`
 * field with explode('/', ...), assuming MM/DD/YYYY (exactly what the
 * old jQuery datepicker sent). This is UNCHANGED, deliberately — but
 * it means RenewalHistory.vue must submit `renewal_date` as a
 * MM/DD/YYYY string, NOT the ISO format a native <input type="date">
 * produces by default, or this will silently corrupt the date. See
 * the `toSlashDate()` helper in RenewalHistory.vue. `expiry_date` has
 * no such trap — TdRenewalDateHistory::setExpiryDateAttribute()
 * already tolerates both formats, so it's sent as plain ISO.
 */
class TimeOfDepositRenewalDateController
{
    use GeneralFunctions;

	/**
	 * Shared by index() (create mode) and edit() (edit mode) — builds
	 * every prop RenewalHistory.vue needs: the TD's summary info, the
	 * full renewal history flattened into plain arrays (with a
	 * days-count computed the same way the old Blade view did, between
	 * each entry and the one before it), and the form defaults, which
	 * faithfully replicate the original's slightly unusual rule: the
	 * "Expiry Date" field is always driven by the TD's *current* state
	 * (not by the specific history row being edited), while "Interest
	 * Rate" and "New Expiry Date" (submitted as `renewal_date`) do come
	 * from the row being edited when one is selected.
	 */
	protected function renderPage(Company $company, TimeOfDeposit $timeOfDeposit, ?TdRenewalDateHistory $model)
	{
		$renewalDateHistories = $timeOfDeposit->renewalDateHistories;
		$count = $renewalDateHistories->count();
		$previousDate = null;

		$rows = $renewalDateHistories->values()->map(function (TdRenewalDateHistory $history, int $index) use (&$previousDate, $count, $company, $timeOfDeposit) {
			$currentRenewalDate = $history->getRenewalDate();
			$daysCount = $previousDate
				? getDiffBetweenTwoDatesInDays(\Carbon\Carbon::make($previousDate), \Carbon\Carbon::make($currentRenewalDate))
				: null;
			$isOriginal = is_null($previousDate);
			$previousDate = $currentRenewalDate;

			return [
				'id' => $history->id,
				'renewal_date_formatted' => $history->getRenewalDateFormatted(),
				'is_original' => $isOriginal,
				'days_count' => $daysCount,
				'is_last' => $index === $count - 1,
				'edit_url' => route('edit.time.of.deposit.renewal.date', ['company' => $company->id, 'timeOfDeposit' => $timeOfDeposit->id, 'TdRenewalDateHistory' => $history->id]),
				'delete_url' => route('delete.time.of.deposit.renewal.date', ['company' => $company->id, 'timeOfDeposit' => $timeOfDeposit->id, 'TdRenewalDateHistory' => $history->id]),
			];
		})->values();

		return \Inertia\Inertia::render('TimeOfDeposits/RenewalHistory', [
			'company' => ['id' => $company->id],
			'timeOfDeposit' => [
				'id' => $timeOfDeposit->id,
				'financial_institution_name' => $timeOfDeposit->getFinancialInstitutionName(),
				'account_number' => $timeOfDeposit->getAccountNumber(),
				'currency' => $timeOfDeposit->getCurrency(),
				'interest_rate_formatted' => $timeOfDeposit->getInterestRateFormatted(),
				'is_expired' => $timeOfDeposit->isExpired(),
			],
			'rows' => $rows,
			'mode' => $model ? 'edit' : 'create',
			// The original only shows the add/edit form at all when
			// either editing an existing row, or the TD has expired
			// (you can't add a renewal to a TD that hasn't matured
			// yet). Same rule here.
			'canShowForm' => (bool) $model || $timeOfDeposit->isExpired(),
			'formDefaults' => [
				'expiry_date' => $model ? $timeOfDeposit->getExpiryDate() : $timeOfDeposit->getRenewalDate(),
				'renewal_date' => $model ? $model->getRenewalDate() : null,
				'interest_rate' => $model ? $model->getInterestRate() : $timeOfDeposit->getInterestRate(),
				'interest_amount' => $timeOfDeposit->getInterestAmountFormatted(),
			],
			'storeUrl' => route('store.time.of.deposit.renewal.date', ['company' => $company->id, 'timeOfDeposit' => $timeOfDeposit->id]),
			'updateUrl' => $model ? route('update.time.of.deposit.renewal.date', ['company' => $company->id, 'timeOfDeposit' => $timeOfDeposit->id, 'TdRenewalDateHistory' => $model->id]) : null,
			'indexUrl' => route('time.of.deposit.renewal.date', ['company' => $company->id, 'timeOfDeposit' => $timeOfDeposit->id]),
			'backUrl' => route('view.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $timeOfDeposit->financial_institution_id]),
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
	public function index(Company $company,Request $request,TimeOfDeposit $timeOfDeposit)
	{
		return $this->renderPage($company, $timeOfDeposit, null);
    }

	/**
	 * Creates a new renewal-date history row and rolls the TD forward
	 * to the new end date / interest rate. UNCHANGED, deliberately —
	 * see the class docblock's date-format warning before touching
	 * the `renewal_date` parsing below.
	 */
	public function store(StoreTdRenewalDateRequest $request, Company $company, TimeOfDeposit $timeOfDeposit){
	
		$date = $request->get('renewal_date') ;
		$newInterestRate = $request->get('interest_rate');
		// $expiryDate = $timeOfDeposit->getRenewalDate();
		
		$expiryDate = $request->get('expiry_date');
	
		
		
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$renewalDate = $year.'-'.$month.'-'.$day ;
		// $financialInstitution = $timeOfDeposit->financialInstitution;
		// $lgType = $timeOfDeposit->getLgType();
		// $transactionName = $timeOfDeposit->getTransactionName();
		// $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber,$company->id , $financialInstitution->id);
		
		if(!$timeOfDeposit->renewalDateHistories->count()){
			/**
			 * * في حالة اول مرة هنضيف تاريخ التجديد الاصلي اكنة تاريخ علشان نحتفظ بيه علشان ما يضيعش
			 */
			TdRenewalDateHistory::create([
				'company_id'=>$company->id ,
				// 'fees_amount'=>0,
				'renewal_date'=>$expiryDate,
				'interest_rate'=>$timeOfDeposit->getInterestRate(),
				'expiry_date'=>$timeOfDeposit->getStartDate(),
				'time_of_deposit_id'=>$timeOfDeposit->id,
			]);
		}
		$tdRenewalDateHistory = TdRenewalDateHistory::create([
			'company_id'=>$company->id ,
			// 'fees_amount'=>$renewalFeesAmount,
			'renewal_date'=>$renewalDate,
			'interest_rate'=>$newInterestRate,
			'expiry_date'=>$expiryDate,
			'time_of_deposit_id'=>$timeOfDeposit->id
		]);
		
		// $this->storeCommissionToCreditCurrentAccountBankStatement($tdRenewalDateHistory,$timeOfDeposit,$company,$expiryDate,$renewalDate,$transactionName,$lgType);
		// $financialInstitutionAccountOpeningBalance = $financialInstitutionAccount->getOpeningBalanceDate();
		// if(Carbon::make($expiryDate)->greaterThanOrEqualTo(Carbon::make($financialInstitutionAccountOpeningBalance))){
		// 	$timeOfDeposit->storeCurrentAccountCreditBankStatement($expiryDate,$renewalFeesAmount , $financialInstitutionAccount->id,0,1,__('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'en'),'transactionName'=>$transactionName],'en') , __('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'ar'),'transactionName'=>$transactionName],'ar'),true);
		// }
		$commentEn = __('Renewal For Time Deposit',[],'en');
		$commentAr = __('Renewal For Time Deposit',[],'ar');
		$interestAmount = $timeOfDeposit->storeRenewalDebitCurrentAccount($expiryDate,$renewalDate,$newInterestRate,$commentEn,$commentAr);
		$timeOfDeposit->storeRenewal($expiryDate,$newInterestRate);
		$timeOfDeposit->update([
			'end_date'=>$renewalDate,
			'start_date'=>$expiryDate,
			'interest_rate'=>$newInterestRate,
			'interest_amount'=>$interestAmount,
			'actual_interest_amount'=>$interestAmount
		]);
		
		
		return redirect()->route('time.of.deposit.renewal.date',['company'=>$company->id,'timeOfDeposit'=>$timeOfDeposit->id]);
	}
	
	/**
	 * ✅ MIGRATED to Vue + Inertia. Renders RenewalHistory.vue in
	 * `mode: 'edit'`, pre-filled with $TdRenewalDateHistory's values.
	 */
	public function edit(Request $request , Company $company ,  TimeOfDeposit $timeOfDeposit , TdRenewalDateHistory $TdRenewalDateHistory){
		return $this->renderPage($company, $timeOfDeposit, $TdRenewalDateHistory);
	}

	/**
	 * Updates the last renewal-date history row and re-syncs the TD's
	 * current end date / interest rate to match. UNCHANGED,
	 * deliberately — see the class docblock's date-format warning.
	 */
	public function update(StoreTdRenewalDateRequest $request , Company $company ,  TimeOfDeposit $timeOfDeposit  , TdRenewalDateHistory $TdRenewalDateHistory){
		$date = $request->get('renewal_date') ;
		$newInterestRate  = $request->get('interest_rate');
		// $renewalFeesAmount = $request->get('fees_amount');
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$renewalDate = $year.'-'.$month.'-'.$day ;
		$expiryDate = $request->get('expiry_date');
		$interestAmount = number_unformat($request->get('interest_amount'));

		$renewalFeesCurrentAccountBankStatement = $timeOfDeposit->renewalDebitCurrentAccount($expiryDate) ;
		// $interestAmount = $interestAmount ? $interestAmount 
		// : $timeOfDeposit->calculateInterestAmount($expiryDate,$renewalDate,$newInterestRate)
		// ;
		$renewalFeesCurrentAccountBankStatement->handleFullDateAfterDateEdit($expiryDate,$interestAmount,0);
	
		
		$TdRenewalDateHistory->update([
			'renewal_date'=>$renewalDate ,
			'expiry_date'=>$expiryDate,
			'interest_rate'=>$newInterestRate,
			'interest_amount'=>$interestAmount
		]);
		$timeOfDeposit->update([
			'end_date'=>$renewalDate,
			'start_date'=>$expiryDate,
			'interest_rate'=>$newInterestRate,
			'interest_amount'=>$interestAmount,
			'actual_interest_amount'=>$interestAmount
		]);
		

		return redirect()->route('time.of.deposit.renewal.date',['company'=>$company->id,'timeOfDeposit'=>$timeOfDeposit->id]);
		
	}

	/**
	 * Deletes a renewal-date history row and rolls the TD's current
	 * end date / interest rate back to whatever the new last row is.
	 * UNCHANGED, deliberately.
	 */
	public function destroy(Request $request , Company $company ,  TimeOfDeposit $timeOfDeposit , TdRenewalDateHistory $TdRenewalDateHistory)
	{
		
		$TdRenewalDateHistory->delete();
		$timeOfDeposit = $timeOfDeposit->refresh();
		$lastHistory = $timeOfDeposit->renewalDateHistories->last();
		$expiryDate = $lastHistory->expiry_date ;
		$renewalDate = $lastHistory->renewal_date ;
		$interestRate = $lastHistory->interest_rate ;
		$interestAmount = $lastHistory->interest_amount ;
		
		// $interestAmount = $timeOfDeposit->calculateInterestAmount($expiryDate,$renewalDate,$interestRate);
		$timeOfDeposit->update([
			'end_date'=>$renewalDate ,
			'start_date'=>$expiryDate,
			'interest_rate'=>$interestRate,
			'interest_amount'=>$interestAmount,
			'actual_interest_amount'=>$interestAmount
			]) ; 
			/**
			 * * لو معدش فاضل غيرها دا معناه انه حذف تاني عنصر وبالتالي العنصر الاول اللي معتش فاضل غيره هو الديو ديت الاصلي ففي الحاله
			 * * دي هنحذفه معتش ليه لزمة
			 */
			if($timeOfDeposit->renewalDateHistories->count() == 1){
				$lastHistory->delete();
			}
		return redirect()->route('time.of.deposit.renewal.date',['company'=>$company->id,'timeOfDeposit'=>$timeOfDeposit->id]);
	}
	
}