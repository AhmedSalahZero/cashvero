<?php
namespace App\Http\Controllers;

use App\Http\Requests\DeleteCurrentAccountRequest;
use App\Http\Requests\UpdateCurrentAccountRequest;
use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FinancialInstitutionAccountController
 * ------------------------------------------------------------------
 * Manages a single bank account record that belongs to a Financial
 * Institution — editing its details, its interest rate schedule
 * (multiple time-bound rate periods), deleting it, and locking/
 * unlocking it.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   - edit()             → ALREADY migrated. Returns Inertia::render(),
 *                          served by resources/js/Pages/FinancialInstitutions/EditAccount.vue
 *   - update()           → UNCHANGED, deliberately. This method recalculates
 *                          bank statement history when the balance date
 *                          shifts, diffs the interest-rate schedule
 *                          (create/update/delete), and syncs to Odoo if
 *                          configured. This is sensitive financial logic —
 *                          only the presentation layer was touched.
 *   - destroy() / lockOrUnlock() → NOT YET migrated (still return redirects
 *                          to Blade-rendered pages; low priority, simple actions)
 */
class FinancialInstitutionAccountController
{
    use GeneralFunctions;
  
	/**
	 * Show the "edit financial institution account" form — includes the
	 * account's core fields plus its interest rate schedule (multiple
	 * time-bound interest rate periods, managed as a repeater).
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/FinancialInstitutions/EditAccount.vue.
	 *
	 * ⚠️ update() below is UNCHANGED — this is deliberate. It recalculates
	 * bank statement history when the balance date shifts, diffs the
	 * interest-rate schedule (create/update/delete), and syncs to Odoo
	 * if configured. This is real, sensitive financial logic — only the
	 * *presentation* layer (edit()) was touched, not the calculation logic.
	 */
	public function edit(Company $company , Request $request , FinancialInstitutionAccount $financialInstitutionAccount){

		return \Inertia\Inertia::render('FinancialInstitutions/EditAccount', [
			'company' => ['id' => $company->id],
			'financialInstitution' => [
				'id' => $financialInstitutionAccount->financialInstitution->id,
				'name' => $financialInstitutionAccount->financialInstitution->getName(),
			],
			'model' => [
				'id' => $financialInstitutionAccount->id,
				'account_number' => $financialInstitutionAccount->getAccountNumber(),
				'iban' => $financialInstitutionAccount->getIban(),
				'odoo_code' => $financialInstitutionAccount->getOdooCode(),
				'balance_amount' => $financialInstitutionAccount->getBalanceAmount(),
				'balance_date' => $financialInstitutionAccount->getBalanceDateForSelect(),
				'currency' => $financialInstitutionAccount->getCurrency(),
				'exchange_rate' => $financialInstitutionAccount->getExchangeRate(),
			],
			'accountInterests' => $financialInstitutionAccount->accountInterests->map(function ($ai) {
				return [
					'id' => $ai->getId(),
					'start_date' => $ai->getStartDateForSelect(),
					'interest_rate' => $ai->getInterestRate(),
					'min_balance' => $ai->getMinBalance(),
				];
			})->values(),
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'backUrl' => route('view.all.bank.accounts', ['company' => $company->id, 'financialInstitution' => $financialInstitutionAccount->financialInstitution->id]),
			'submitUrl' => route('update.financial.institutions.account', ['company' => $company->id, 'financialInstitution' => $financialInstitutionAccount->financialInstitution->id, 'financialInstitutionAccount' => $financialInstitutionAccount->id]),
			'navUrls' => [
				'home' => route('home', ['company' => $company->id]),
				'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
				'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
				'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
				'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
			],
		]);
	}
	public function update(Company $company , UpdateCurrentAccountRequest $request ,FinancialInstitution $financialInstitution , FinancialInstitutionAccount $financialInstitutionAccount){
		$currency = $request->get('currency',$financialInstitutionAccount->getCurrency());
		$balanceDate = Carbon::make($request->get('balance_date'))->format('Y-m-d');
		$financialInstitutionAccount->update([
			'account_number'=>$request->get('account_number'),
			'odoo_code'=>$request->get('odoo_code'),
			'currency'=>$currency ,
			'balance_amount'=>$request->get('balance_amount'),
			'balance_date'=>$balanceDate,
			'iban'=>$request->get('iban'),
			'exchange_rate'=>$request->get('exchange_rate')
		]);
			
		
		$currentAccountBeginningBalance = $financialInstitutionAccount->getOpeningBalanceFromCurrentAccountBankStatement() ;
		
	
		if($currentAccountBeginningBalance){
			$currentDate =$currentAccountBeginningBalance->date ; 
			$currentFullDate =$currentAccountBeginningBalance->full_date ; 
			$time  = Carbon::make($currentFullDate)->format('H:i:s');
			$newFullDateTime = date('Y-m-d H:i:s', strtotime("$balanceDate $time")) ;
			// $minDateTime = min($currentFullDate ,$newFullDateTime );
			DB::table('current_account_bank_statements')->where('id',$currentAccountBeginningBalance->id)->update([
				'date'=>$balanceDate,
				'full_date'=>$newFullDateTime ,
				'debit'=>$request->get('balance_amount'),
				'comment_en'=>__('Beginning Balance',[],'en'),
				'comment_ar'=>__('Beginning Balance',[],'ar'),
			]);
			CurrentAccountBankStatement::where('date','>=',$currentDate)
			->where('financial_institution_account_id',$currentAccountBeginningBalance->financial_institution_account_id)
			->orderByRaw('date asc , id asc')
			->first()
			->update([
				'updated_at'=>now()
			]);
		}else{

			$time  = Carbon::make(now())->format('H:i:s');
			$newFullDateTime = date('Y-m-d H:i:s', strtotime("$balanceDate $time")) ;
			DB::table('current_account_bank_statements')->insert([
				'financial_institution_account_id'=>$financialInstitutionAccount->id,
				'company_id'=>$company->id,
				'date'=>$balanceDate,
				'beginning_balance'=>0,
				'is_beginning_balance'=>1 ,
				'full_date'=>$newFullDateTime ,
				'debit'=>$request->get('balance_amount'),
				'comment_en'=>__('Beginning Balance',[],'en'),
				'comment_ar'=>__('Beginning Balance',[],'ar'),
			]);
			
			$currentStatement = CurrentAccountBankStatement::where('date','>=',$balanceDate)
			->where('financial_institution_account_id',$financialInstitutionAccount->id)
			->orderByRaw('date asc , id asc')
			->first();
			if($currentStatement){
				$currentStatement->update([
				'updated_at'=>now()
			]);
			}
			
			
		}
		
	//	$endDate = Carbon::make($balanceDate)->addYear(FinancialInstitutionAccount::NUMBER_OF_YEARS_FOR_INTEREST_IN_CURRENT_STATEMENT)->format('Y-m-d');
			//$financialInstitutionAccount->handleEndOfMonthInterest($balanceDate,$endDate,$company->id);
		if($company->hasOdooIntegrationCredentials()){
			$odoo = new OdooService($company);
			$odoo->syncFinancialInstitutions($financialInstitutionAccount);
		}
		
	
		$oldAccountInterestsIds = $financialInstitutionAccount->accountInterests->pluck('id')->toArray();
		$AccountInterestsIdsFromRequest =array_column($request->get('account_interests',[]),'id') ;
		$elementsToDelete = array_diff($oldAccountInterestsIds,$AccountInterestsIdsFromRequest);
		$elementsToUpdate = array_intersect($AccountInterestsIdsFromRequest,$oldAccountInterestsIds);
		$financialInstitutionAccount->accountInterests()->whereIn('account_interests.id',$elementsToDelete)->delete();
		foreach($elementsToUpdate as $id){
			$dataToUpdate = findByKey($request->get('account_interests'),'id',$id);
			unset($dataToUpdate['id']);
			$dataToUpdate['start_date'] = isset($dataToUpdate['start_date']) ? Carbon::make($dataToUpdate['start_date'])->format('Y-m-d') : null;
			$currentAccountRate = $financialInstitutionAccount->accountInterests()->where('account_interests.id',$id) ;
			$currentAccountRate->update($dataToUpdate);
			
		}
		foreach($request->get('account_interests') as $accountInterestArr){
			if(!isset($accountInterestArr['id'])){
				unset($accountInterestArr['id']);
				$accountInterestArr['start_date'] = isset($accountInterestArr['start_date']) ? Carbon::make($accountInterestArr['start_date'])->format('Y-m-d') : null;
				$currentAccountRate = $financialInstitutionAccount->accountInterests()->create($accountInterestArr);
			}
		}
		/**
		 * * هنجيب اول قيمة في البانك 
		 * * current account bank statement 
		 * * لهذا الحساب ونبدا نحدث من عندها لاننا لما حذفنا
		 * * $financialInstitutionAccount->accountInterests()->whereIn('account_interests.id',$elementsToDelete)->delete();
		 * * فا احنا مش عارفين ي
		 */
			
				
		$minDateInCurrentAccountStatement = DB::table('current_account_bank_statements')
											->where('financial_institution_account_id', $financialInstitutionAccount->id)
											->min('date');
		if($minDateInCurrentAccountStatement){
			$financialInstitutionAccount->updateBankStatementsFromDate($minDateInCurrentAccountStatement);
		}
		return redirect()->route('view.all.bank.accounts',['company'=>$company->id ,'financialInstitution'=>$financialInstitution->id])->with('success',__('Item Has Been Updated Successfully'));
		
	}
	
	public function destroy(Company $company , FinancialInstitutionAccount $financialInstitutionAccount,DeleteCurrentAccountRequest $request)
	{
		$financialInstitutionAccount->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	public function lockOrUnlock(Company $company , FinancialInstitutionAccount $financialInstitutionAccount)
	{
		$financialInstitutionAccount->is_active = (int) (! $financialInstitutionAccount->isActive());
		$financialInstitutionAccount->save();
		return redirect()->back()->with('success',__('Item Has Been Updated Successfully'));
	}
	

	
	
}