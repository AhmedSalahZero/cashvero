<?php 
namespace App\Models\Traits\Controllers;

use App\Models\CleanOverdraft;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FullySecuredOverdraft;
use App\Models\FullySecuredOverdraftRate;
use Illuminate\Http\Request;

trait HasOverdraftRate 
{
	public function applyRate(Request $request , Company $company , FinancialInstitution $financialInstitution ,  $overdraftId )
	{
		$modelFullName = (self::getModelName()); // App\Models\CleanOverdraft for example
		$overdraftModel = $modelFullName::find($overdraftId);
		$date = $request->get('date_create') ;
		$marginRate = $request->get('margin_rate_create') ;
		$minInterestRate = $request->get('min_interest_rate_create') ;
		$borrowingRate = $request->get('borrowing_rate_create') ;
		$interestRate = $marginRate  + $borrowingRate  ;
		$data = [
			'date'=>$date,
			'margin_rate'=>$marginRate,
			'borrowing_rate'=>$borrowingRate,
			'interest_rate'=>$interestRate,
			'min_interest_rate'=>$minInterestRate,
			'company_id'=>$request->get('company_id'),
			'updated_at'=>now()
		] ;
		if($overdraftModel instanceof FullySecuredOverdraft){
			unset($data['min_interest_rate']);
		}
		$overdraftModel->rates()->create($data);
		$overdraftModel->updateBankStatementsFromDate($date);
		return redirect()->back()->with('success',__('Done'));
	
	}
	public function editRate(Request $request , Company $company , FinancialInstitution $financialInstitution ,  $rateId)
	{
		$modelFullName = (self::getModelName()); // App\Models\CleanOverdraft for example
		/**
		 * @var CleanOverdraft $modelFullName
		 */
		$rate = ($modelFullName::rateFullClassName())::find($rateId);
		$date = $request->get('date_edit') ;
		$marginRate = $request->get('margin_rate_edit') ;
		$minInterestRate = $request->get('min_interest_rate_edit') ;
		$borrowingRate = $request->get('borrowing_rate_edit') ;
		$interestRate = $marginRate  + $borrowingRate  ;
		$data = [
			'date'=>$date,
			'margin_rate'=>$marginRate,
			'borrowing_rate'=>$borrowingRate,
			'interest_rate'=> $interestRate ,
			'min_interest_rate'=>$minInterestRate,
			'updated_at'=>now()
		] ;
		if($rate instanceof FullySecuredOverdraftRate){
			unset($data['min_interest_rate']);
		}
		$rate->update($data);
		$rate->overdraftModal->updateBankStatementsFromDate($date);
		return response()->json([
			'status'=>true ,
			'reloadCurrentPage'=>true 
		]);
	}
	
	public function deleteRate(Request $request , Company $company , FinancialInstitution $financialInstitution ,  $rateId)
	{
		$modelFullName = (self::getModelName()); // App\Models\CleanOverdraft for example
		/**
		 * @var CleanOverdraft $modelFullName
		 */
		$rate = ($modelFullName::rateFullClassName())::find($rateId);
		$overdraftModel = $rate->overdraftModal;
		$date = $rate->getDate();
		$rate->delete();
		$overdraftModel->updateBankStatementsFromDate($date);
		return redirect()->back()->with('success',__('Done'));
	}
}
