<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashExpenseCategory;
use App\Models\CashExpenseCategoryName;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CashExpenseStatementController
{
    use GeneralFunctions;
    public function index(Company $company)
	{
		$cashExpenseCategories = CashExpenseCategory::where('company_id',$company->id)->orderBy('name','asc')->get()->formattedForSelect(true,'getId','getName');
        return view('cash_expense_statement_form', [
			'company'=>$company,
			'cashExpenseCategories'=>$cashExpenseCategories
		]);
    }
	public function result(Company $company , Request $request){
		$startDate = $request->get('start_date');
		$endDate = $request->get('end_date');
		$currency = $request->get('currency');
		// $expenseCategoryId = is_array($request->get('expense_category_id')) ? $request->input('expense_category_id.0') :  $request->get('expense_category_id');
		// $expenseCategory =count($request->get('expense_category_id'))  == 1 ? CashExpenseCategory::find($expenseCategoryId)->getName() : null;
		$cashExpenseCategoryIds = $request->get('cash_expense_category_name_id',[]) ; 
		// $expenseCategoryName =   CashExpenseCategoryName::find($cashExpenseCategoryId)->getName();

		$result = DB::table('cash_expenses')->where('cash_expenses.company_id',$company->id)->where('currency',$currency)
		->where('payment_date','>=',$startDate)
		->where('payment_date','<=',$endDate)
		->whereIn('cash_expense_category_name_id',$cashExpenseCategoryIds)
		->orderByRaw('payment_date asc')
		->join('cash_expense_category_names','cash_expense_category_names.id','=','cash_expenses.cash_expense_category_name_id')
		->join('cash_expense_categories','cash_expense_categories.id','=','cash_expense_category_names.cash_expense_category_id')
		->selectRaw('cash_expenses.*,cash_expense_category_names.name as sub_category_name , cash_expense_categories.name as main_category_name ' )
		->get();
		
			if(!count($result)){
				return redirect()
									->back()
									->with('fail',__('No Data Found'))	
									;
			}
		
		return view('cash_expense_statement_result',[
			'results'=>$result,
			'currency'=>$currency,
			// 'expenseCategory'=>$expenseCategory,
			// 'expenseCategoryName'=>$expenseCategoryName
		]);
	}




}
