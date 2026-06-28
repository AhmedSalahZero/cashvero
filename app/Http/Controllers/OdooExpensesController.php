<?php
namespace App\Http\Controllers;
use App\Models\CashExpenseCategoryName;
use App\Models\Company;
use App\Models\OdooExpense;
use App\Services\Api\ExpensePayment;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OdooExpensesController
{
    use GeneralFunctions;
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName =  'created_at' ; // change it 
		$from = $request->get('from');
		$to = $request->get('to');
		$value = $request->query('value');
		$collection = $collection
		->when($request->has('value'),function($collection) use ($value,$searchFieldName){
			return $collection->filter(function($model) use ($value,$searchFieldName){
				$currentValue = $model->{$searchFieldName} ;
				// if($searchFieldName == 'bank_id'){
				// 	$currentValue = $model->getBankName() ;  
				// }
				return false !== stristr($currentValue , $value);
			});
		})
		->when($request->get('from') , function($collection) use($dateFieldName,$from){
			return $collection->where($dateFieldName,'>=',$from);
		})
		->when($request->get('to') , function($collection) use($dateFieldName,$to){
			return $collection->where($dateFieldName,'<=',$to);
		})
		->sortByDesc('id')->values();
		return $collection;
	}
	public function index(Company $company,Request $request)
	{
		
		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$currentType = $request->get('active',OdooExpense::APPROVED);
		
		$filterDates = [];
		foreach(OdooExpense::getAllTypes() as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of bank to safe internal money transfer 
		 */
		
		$runningStartDate = $filterDates[OdooExpense::APPROVED]['startDate'] ?? null ;
		$runningEndDate = $filterDates[OdooExpense::APPROVED]['endDate'] ?? null ;
		$rows = $company->odooApprovedExpenses ;
		$rows =  $rows->filterByCreatedAt($runningStartDate,$runningEndDate) ;
		$rows =  $currentType == OdooExpense::APPROVED ? $this->applyFilter($request,$rows):$rows ;

		/**
		 * * end of bank to safe internal money transfer 
		 */
		 
		
		 $searchFields = [
			OdooExpense::APPROVED=>[
				'name'=>__('Name'),
				'created_at'=>__('Created Date'),
				// 'end_Date'=>__('End Date'),
			],
		];
	
		$models = [
			OdooExpense::APPROVED =>$rows ,
		];

        return view('odoo-expenses.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates
		]);
    }
	public function markAsPaid(Request $request,Company $company){
		$id = $request->get('id');
		$paymentDate = Carbon::make($request->get('payment_date'))->format('Y-m-d');
		$odooExpense = OdooExpense::find($id);
		/**
		 * @var OdooExpense $odooExpense
		 */
		$journalId = $odooExpense->getJournalId();
		 $paymentMethodId = $odooExpense->getPaymentMethodId();
		 $odooExpenseSheetId =$odooExpense->getOdooId(); 
        $expensePaymentService = new ExpensePayment($company);
		$settlementResult = $expensePaymentService->settleApprovedExpenses($journalId,$paymentMethodId,$paymentDate,$odooExpenseSheetId);
	
		// if(isset($settlementResult['success']) && $settlementResult['success'] == false ){
		// 	return redirect()->back()->with('fail',$settlementResult['message']);
		// }
		$code = $settlementResult['account_result']['account_code'];
		$expenseCategorySub = CashExpenseCategoryName::findByOdooChatOfAccountNumber($company->id ,$code );
		// if(!$expenseCategorySub){
		// 	$expenseCategorySub = CashExpenseCategoryName::create([
		// 		'name'=>$settlementResult['account_result']['account_name'],
		// 		'odoo_chart_of_account_number'=>$settlementResult['account_result']['account_id'],
		// 		'company_id'=>$company->id,
		// 	]);	
		// }
		// $expenseCategoryParentId = $expenseCategorySub->cashExpenseCategory->id;
		$expenseCategorySubId = $expenseCategorySub ? $expenseCategorySub->id : null  ;
		$odooExpense->generateCashExpenseData($paymentDate,$expenseCategorySubId);
		$odooExpense->update([
			'state'=>'done',
			'payment_state'=>'paid'
		]);
		return redirect()->back()->with('success',__('Done'));
		
	}

	

}
