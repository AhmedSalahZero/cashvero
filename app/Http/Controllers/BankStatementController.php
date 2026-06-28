<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\CleanOverdraft;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\FullySecuredOverdraft;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Models\TimeOfDeposit;
use App\Services\Api\CashExpenseOdooService;
use App\Services\Api\LetterOfGuaranteeService;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Container\Container;


use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class BankStatementController
{
    use GeneralFunctions;

    public function index(Company $company, Request $request)
    {
        $selectedAccountTypeName = $request->get('accountType');
        $selectedCurrency  = $request->get('currency');
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $accountTypes = AccountType::onlyCashAccounts()->get();
        return view('bank_statement_form', [
            'company' => $company,
            'financialInstitutionBanks' => $financialInstitutionBanks,
            'accountTypes'=>$accountTypes,
            'selectedAccountTypeName'=>$selectedAccountTypeName,
            'selectedCurrency'=>$selectedCurrency
        ]);
    }

    public function result(Company $company, Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $financialInstitutionId = $request->get('financial_institution_id');
        $financialInstitution = FinancialInstitution::find($financialInstitutionId);
        $financialInstitutionName = $financialInstitution->getName();
        $accountTypeId = $request->get('account_type');
        $accountNumber = $request->get('account_number');
        $currencyName = $request->get('currency');
        $results = [];
        $accountType = AccountType::find($accountTypeId);
        
        /**
         * @var AccountType $accountType
         */
    
        $accountTypeName = $accountType->getName() ;
        $isCurrentAccount = $accountType->isCurrentAccount() ;
        $statementModelName = null;
        if ($isCurrentAccount) {
            $statementModelName = 'CurrentAccountBankStatement';
            $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            $results = DB::table('current_account_bank_statements')
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            /**
             * * is_active =1
             * * علشان الكوميشن ما تجيش في حاله ال
             * * lg issuance
             */
            ->where('current_account_bank_statements.is_active', 1)
            ->where('current_account_bank_statements.financial_institution_account_id', $financialInstitutionAccount->id)
            ->where('current_account_bank_statements.company_id', $company->id)
            ->join('financial_institution_accounts', 'financial_institution_account_id', '=', 'financial_institution_accounts.id')
            ->where('financial_institution_accounts.currency', $currencyName)
            ->where('current_account_bank_statements.date', '>=', $startDate)
            ->where('current_account_bank_statements.date', '<=', $endDate)
            ->leftJoin('money_received', 'current_account_bank_statements.money_received_id', '=', 'money_received.id')
            ->selectRaw('current_account_bank_statements.*,financial_institution_accounts.*,money_received.is_reviewed,money_received.reviewed_by,current_account_bank_statements.id as id,current_account_bank_statements.full_date as full_date,current_account_bank_statements.date as date')
            ->orderByRaw('date desc , current_account_bank_statements.id desc')
            ->get();
            
            
        } elseif ($accountType->isCleanOverdraftAccount()) {
            $statementModelName = 'CleanOverdraftBankStatement';
            $cleanOverdraft  = CleanOverdraft::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            
            $results = DB::table('clean_overdraft_bank_statements')
                 ->where('clean_overdraft_bank_statements.company_id', $company->id)
                 ->where('date', '>=', $startDate)
                 ->where('date', '<=', $endDate)
                 ->where('clean_overdraft_id', $cleanOverdraft->id)
                 ->join('clean_overdrafts', 'clean_overdraft_bank_statements.clean_overdraft_id', '=', 'clean_overdrafts.id')
                 ->where('clean_overdrafts.currency', '=', $currencyName)
                //  ->leftJoin('money_received','current_account_bank_statements.money_received_id','=','money_received.id')
                ->orderByRaw('clean_overdraft_bank_statements.date desc , clean_overdraft_bank_statements.id desc')
                ->selectRaw('*,clean_overdraft_bank_statements.id as id')
                 ->get();
            
        } elseif ($accountType->isFullySecuredOverdraftAccount()) {
            $fullySecuredOverdraft  = FullySecuredOverdraft::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            $statementModelName = 'FullySecuredOverdraftBankStatement';
            $results = DB::table('fully_secured_overdraft_bank_statements')
                 ->where('fully_secured_overdraft_bank_statements.company_id', $company->id)
                 ->where('date', '>=', $startDate)
                 ->where('date', '<=', $endDate)
                 ->where('fully_secured_overdraft_id', $fullySecuredOverdraft->id)
                 ->join('fully_secured_overdrafts', 'fully_secured_overdraft_bank_statements.fully_secured_overdraft_id', '=', 'fully_secured_overdrafts.id')
                 ->where('fully_secured_overdrafts.currency', '=', $currencyName)
                 ->selectRaw('*,fully_secured_overdraft_bank_statements.id as id')
                 ->orderByRaw('date desc, fully_secured_overdraft_bank_statements.id desc')
                 ->get();
        } elseif ($accountType->isOverdraftAgainstCommercialPaperAccount()) {
            $statementModelName = 'OverdraftAgainstCommercialPaperBankStatement';
            
            $overdraftAgainstCommercialPaper  = OverdraftAgainstCommercialPaper::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            $results = DB::table('overdraft_against_commercial_paper_bank_statements')
                 ->where('overdraft_against_commercial_paper_bank_statements.company_id', $company->id)
                 ->where('date', '>=', $startDate)
                 ->where('date', '<=', $endDate)
                 ->where('overdraft_against_commercial_paper_id', $overdraftAgainstCommercialPaper->id)
                 ->join('overdraft_against_commercial_papers', 'overdraft_against_commercial_paper_bank_statements.overdraft_against_commercial_paper_id', '=', 'overdraft_against_commercial_papers.id')
                 ->where('overdraft_against_commercial_papers.currency', '=', $currencyName)
                 ->orderByRaw('date desc, overdraft_against_commercial_paper_bank_statements.id desc')
                 ->selectRaw('* , overdraft_against_commercial_paper_bank_statements.limit as statement_limit,overdraft_against_commercial_paper_bank_statements.id as id')
                 ->get();
        } elseif ($accountType->isOverdraftAgainstAssignmentOfContractAccount()) {
            $statementModelName = 'OverdraftAgainstAssignmentOfContractBankStatement';
            $overdraftAgainstAgainstAssignmentOfContract  = OverdraftAgainstAssignmentOfContract::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            $odaId = $overdraftAgainstAgainstAssignmentOfContract ? $overdraftAgainstAgainstAssignmentOfContract->id:0;
            $results = DB::table('overdraft_against_assignment_of_contract_bank_statements')
                 ->where('overdraft_against_assignment_of_contract_bank_statements.company_id', $company->id)
                 ->where('date', '>=', $startDate)
                 ->where('date', '<=', $endDate)
                 ->where('overdraft_against_assignment_of_contract_id', $odaId)
                 ->join('overdraft_against_assignment_of_contracts', 'overdraft_against_assignment_of_contract_bank_statements.overdraft_against_assignment_of_contract_id', '=', 'overdraft_against_assignment_of_contracts.id')
                 ->where('overdraft_against_assignment_of_contracts.currency', '=', $currencyName)
                 ->orderByRaw('date desc, overdraft_against_assignment_of_contract_bank_statements.id desc')
                 ->selectRaw('* , overdraft_against_assignment_of_contract_bank_statements.limit as statement_limit,overdraft_against_assignment_of_contract_bank_statements.id as id')
                 ->get();
        }
        if (!count($results)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $results = $this->paginate($results, 50);
        return view('bank_statement_result', [
            'results' => $results,
            'currency' => $currencyName,
            'isCurrentAccount'=>$isCurrentAccount,
            'financialInstitutionName'=>$financialInstitutionName,
            'accountTypeName'=>$accountTypeName,
            'accountNumber'=>$accountNumber,
            'isAgainstCommercialPaper'=>$accountType->isOverdraftAgainstCommercialPaperAccount(),
            'isAgainstAssignmentOfContract'=>$accountType->isOverdraftAgainstAssignmentOfContractAccount(),
            'statementModelName'=>$statementModelName
        ]);
    }
    
    public function paginate(\Illuminate\Support\Collection $results, $pageSize)
    {
        $page = Paginator::resolveCurrentPage('page');
    
        $total = $results->count();
        return $this->paginator($results->forPage($page, $pageSize), $total, $pageSize, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);

    }

    public function paginator($items, $total, $perPage, $currentPage, $options)
    {
        return Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items',
            'total',
            'perPage',
            'currentPage',
            'options'
        ));
    }
    public function updateCommissionFees(Company $company, Request $request)
    {
        $statementModelName = $request->get('statement_model_name');
        $statementId = $request->get('statement_id');
        $credit = number_unformat($request->get('credit'));
        $date = Carbon::make($request->get('date'))->format('Y-m-d');
        $fullModelClass = 'App\Models\\'.$statementModelName;
        $bankStatementRecord = $fullModelClass::find($statementId) ;
        $letterOfGuaranteeIssuanceId = $bankStatementRecord->letter_of_guarantee_issuance_id;
        $letterOfGuaranteeIssuance  = LetterOfGuaranteeIssuance::find($letterOfGuaranteeIssuanceId);
		 $financialInstitutionAccountForCommissionAndFees = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->getCommissionFeesAccountId());
        /**
         * @var LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance
         */
        $lgType = $letterOfGuaranteeIssuance->getLgTypeFormatted();
        $bankStatementRecord->handleFullDateAfterDateEdit($date, 0, $credit);
		// $bankStatementRecord->lg_commission_fees_journal_entry_id=1;
		// $bankStatementRecord->odoo_lg_commission_fees_reference=2;
		// $bankStatementRecord->save();
       
        if ($company->hasOdooIntegrationCredentials()) {
            $odooLetterOfGuaranteeIssuanceService = new LetterOfGuaranteeService($company);
           
                $currentJournalEntryId = $bankStatementRecord->lg_commission_fees_journal_entry_id;
                if ($currentJournalEntryId) {
                    $odooLetterOfGuaranteeIssuanceService->unlink($currentJournalEntryId);
                }
                
  
            
            
            
           $commissionFees = $credit;
            $ref = $lgType . ' Commission Fees';
            $message = $ref;
            $odooSetting = $company->odooSetting;
            $debitOdooAccountId = $odooSetting->getLetterOfGuaranteeCommissionFeesId();

            $fromAccountNumber = $financialInstitutionAccountForCommissionAndFees->getAccountNumber();
            $journalId = $financialInstitutionAccountForCommissionAndFees->financialInstitution->getJournalIdForAccount(27, $fromAccountNumber);
            $accountOdooId = $financialInstitutionAccountForCommissionAndFees->financialInstitution->getOdooIdForAccount(27, $fromAccountNumber);
            $currency = $letterOfGuaranteeIssuance->getLgCurrency();
            $odooCurrencyId = Currency::getOdooId($currency);
            $analytic_distribution = $letterOfGuaranteeIssuance->formatAnalysisDistribution();
            $result = $odooLetterOfGuaranteeIssuanceService->createLgIssuanceCashCover($date, $commissionFees, $journalId, $odooCurrencyId, $debitOdooAccountId, $accountOdooId, $letterOfGuaranteeIssuance->getBeneficiaryOdooId(), $ref, $message, $analytic_distribution);
         //   $letterOfGuaranteeIssuance->commission_fees_account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
		      $bankStatementRecord->lg_commission_fees_journal_entry_id=$result['journal_entry_id'];
		     $bankStatementRecord->odoo_lg_commission_fees_reference=$result['reference'];
			 $bankStatementRecord->save();
        
            
            
            
            
            
        }
        
        
        
        return redirect()->back()->with('success', __('Data Updated Successfully'));
    }
    public function updateBankStatementRow(Company $company, Request $request)
    {

        $statementModelName = $request->get('statement_model_name');
        $statementId = $request->get('statement_id');
        $credit = number_unformat($request->get('credit', 0));
        $debit = number_unformat($request->get('debit', 0));
        $date = Carbon::make($request->get('date'))->format('Y-m-d');
        $fullModelClass = 'App\Models\\'.$statementModelName;
        $bankStatementRecord = $fullModelClass::find($statementId) ;
		$financialInstitutionAccountId = $bankStatementRecord->financial_institution_account_id;
		$financialInstitutionAccount = FinancialInstitutionAccount::find($financialInstitutionAccountId);
		$financialInstitution = $financialInstitutionAccount->financialInstitution;
		$financialInstitutionId= $financialInstitution->id;
		if($bankStatementRecord && $bankStatementRecord->interest_journal_entry_id){
			(new CashExpenseOdooService($company))->unlink($bankStatementRecord->interest_journal_entry_id);
		}
		(new TimeOfDeposit())->storePeriodInterestOdooRelations($bankStatementRecord,$date,$debit,$financialInstitutionId , $financialInstitutionAccountId,$company);
        $bankStatementRecord->handleFullDateAfterDateEdit($date, $debit, $credit);
        return redirect()->back()->with('success', __('Data Updated Successfully'));
    }
}
