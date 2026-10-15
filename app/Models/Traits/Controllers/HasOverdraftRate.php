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
		/**
		 * * لازم يتم اختيار التاريخ و الاسعار ارقام صحيحة قبل اي حفظ
		 * * (من غير كده الحفظ بدون تاريخ كان بيطلع خطأ قاعدة بيانات)
		 */
		$request->validate([
			'date_create' => ['required', 'date'],
			'margin_rate_create' => ['nullable', 'numeric'],
			'borrowing_rate_create' => ['nullable', 'numeric'],
			'min_interest_rate_create' => ['nullable', 'numeric'],
		], [
			'date_create.required' => __('Please select the rate date'),
			'date_create.date' => __('Please select the rate date'),
			'margin_rate_create.numeric' => __('Rates must be valid numbers'),
			'borrowing_rate_create.numeric' => __('Rates must be valid numbers'),
			'min_interest_rate_create.numeric' => __('Rates must be valid numbers'),
		]);
		$modelFullName = (self::getModelName()); // App\Models\CleanOverdraft for example
		$overdraftModel = $modelFullName::find($overdraftId);
		$date = $request->get('date_create') ;
		$marginRate = $request->get('margin_rate_create') ?? 0 ;
		$minInterestRate = $request->get('min_interest_rate_create') ?? 0 ;
		$borrowingRate = $request->get('borrowing_rate_create') ?? 0 ;
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
		$request->validate([
			'date_edit' => ['required', 'date'],
			'margin_rate_edit' => ['nullable', 'numeric'],
			'borrowing_rate_edit' => ['nullable', 'numeric'],
			'min_interest_rate_edit' => ['nullable', 'numeric'],
		], [
			'date_edit.required' => __('Please select the rate date'),
			'date_edit.date' => __('Please select the rate date'),
			'margin_rate_edit.numeric' => __('Rates must be valid numbers'),
			'borrowing_rate_edit.numeric' => __('Rates must be valid numbers'),
			'min_interest_rate_edit.numeric' => __('Rates must be valid numbers'),
		]);
		$modelFullName = (self::getModelName()); // App\Models\CleanOverdraft for example
		/**
		 * @var CleanOverdraft $modelFullName
		 */
		$rate = ($modelFullName::rateFullClassName())::find($rateId);
		$date = $request->get('date_edit') ;
		$marginRate = $request->get('margin_rate_edit') ?? 0 ;
		$minInterestRate = $request->get('min_interest_rate_edit') ?? 0 ;
		$borrowingRate = $request->get('borrowing_rate_edit') ?? 0 ;
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
		return redirect()->back()->with('success',__('Done'));
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
