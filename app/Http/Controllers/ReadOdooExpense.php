<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashExpense;
use App\Models\Company;
use App\Models\FinancialInstitutionAccount;
use App\Models\OdooExpense;
use App\Models\Partner;
use App\Services\Api\ExpensePayment;
use Illuminate\Http\Request;


class ReadOdooExpense extends Controller
{
	public function handle(Request $request,  Company $company)
	{
		$startDate = $request->get('odoo_start_date');
		$endDate = $request->get('odoo_end_date');
		$odooExpensePayment = new ExpensePayment($company);
		$fields = ['id','write_date','currency_id','expense_line_ids', 'name', 'state', 'payment_state', 'employee_id', 'total_amount', 'account_move_ids', 'journal_id','payment_method_line_id', 'payment_mode'];
		
		$filters = [[['state','=','approve'],['payment_state','=','not_paid'],
			['write_date', '<=', $endDate],['write_date', '>=', $startDate]
		]];
		
		$odooExpenses =$odooExpensePayment->fetchData('hr.expense.sheet',$fields,$filters);
		
		$oldIds = OdooExpense::whereNotNull('odoo_id')->where('company_id',$company->id)->pluck('odoo_id')->toArray();
		$newIds = array_column($odooExpenses,'id');
		$idsToRemove = array_diff($oldIds,$newIds);
		foreach($idsToRemove as $odooId){
			$cashExpense = CashExpense::where('company_id',$company->id)->where('odoo_id',$odooId)->first();
			if($cashExpense){
				(new CashExpenseController)->destroy($company,$cashExpense);	
			}
			OdooExpense::where('company_id',$company->id)->where('odoo_id',$odooId)->delete();
		}
		foreach($odooExpenses as $odooExpense){
			$odooId = $odooExpense['id'];
			$odooPartnerId = $odooExpense['employee_id'][0] ;
			$odooPartnerName = $odooExpense['employee_id'][1];
			Partner::handlePartnerForOdoo($odooPartnerId ,$odooPartnerName,false ,false,true,false,$company->id );
			$journalId = $odooExpense['journal_id'][0] ;
	//		$journalName = $odooExpense['journal_id'][1] ;
			$accountJournal = $odooExpensePayment->fetchData('account.journal',[],[[['id','=',$journalId]]])[0];
			$additionalData = [
				'account_number'=>null ,
				'bank_name'=>null 
			];
			if($accountJournal['type'] == 'bank'){
			$odooId = $accountJournal['id'];
			$financialInstitutionAccount = FinancialInstitutionAccount::where('company_id',$company->id)->where('odoo_id',$odooId)->first();
			$deliveryBankName = $financialInstitutionAccount->getFinancialInstitutionName();
			$accountNumber = $financialInstitutionAccount->getAccountNumber();
			$additionalData['account_number']= $accountNumber ;
			$additionalData['bank_name']= $deliveryBankName ;
			}elseif($accountJournal['type'] == 'cash'){
				$deliveryBranchName = Branch::getNameFromOdooId($company->id,$accountJournal['id']);
				$additionalData['bank_name']= $deliveryBranchName ;
				$additionalData['account_number']= $accountJournal['default_account_id'][1] ;
			}
			$data = array_merge($additionalData, [
				'odoo_id'=>$odooId,
				'company_id'=>$company->id ,
				'name'=>$odooExpense['name'],
				'odoo_currency_id'=>$odooExpense['currency_id'][0],
				'state'=>$odooExpense['state'],
				'payment_state'=>$odooExpense['payment_state'],
				'odoo_employee_id'=>$odooPartnerId,
				'total_amount'=>$odooExpense['total_amount'],
				'account_move_ids'=>$odooExpense['account_move_ids'][0],
				'journal_id'=>$journalId,
				'payment_method_line_id'=>$odooExpense['payment_method_line_id'][0],
				'payment_mode'=>$odooExpense['payment_mode'][1]
			]);
			$odooExpense = OdooExpense::where('company_id',$company->id)->where('odoo_id',$odooId)->first();
			if($odooExpense){
				$odooExpense->update($data);
			}else{
				OdooExpense::create($data);
			}
		}
		return redirect()->back()->with('success',__('Read Approved Expenses Has Been Completed'));
		
	}
}
