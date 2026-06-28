<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CashInSafeStatement;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\OpeningBalance;
use App\Models\Partner;
use App\Models\PayableCheque;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OpeningBalancesController
{
    use GeneralFunctions;

    public function index(Company $company, Request $request)
    {
        return view('opening-balance.form', $this->getViewVars($company,$request) );
    }
	protected function getViewVars(Company $company, Request $request):array 
	{
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $accountTypes = AccountType::onlyCashAccounts()->get();
        $selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id) ;
        $customers = Partner::where('company_id', $company->id)->where('is_customer', 1)->orderBy('name', 'asc')->get()->formattedForSelect(true, 'getId', 'getName');
        $suppliers = Partner::where('company_id', $company->id)->where('is_supplier', 1)->orderBy('name', 'asc')->get()->formattedForSelect(true, 'getId', 'getName');
        $selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        $branchCurrencies = Branch::where('company_id', $company->id)->pluck('currency', 'id');
        $banks = Bank::pluck('view_name', 'id');
		
		return [
            'company' => $company,
            'model' => $company->openingBalance,
            'selectedBanks' => $selectedBanks,
            'banks' => $banks,
            'customersFormatted' => $customers,
            'financialInstitutionBanks' => $financialInstitutionBanks,
            'accountTypes' => $accountTypes,
            'suppliersFormatted'=>$suppliers,
            'selectedBranches'=>$selectedBranches,
            'branchCurrencies'=>$branchCurrencies,
        ];
	}

    public function store(StoreOpeningBalanceRequest $request, Company $company)
    {
        $openingBalanceDate = $request->get('date');
        $openingBalanceDate = Carbon::make($openingBalanceDate)->format('Y-m-d');
        $openingBalance = OpeningBalance::create([
            'date' => $openingBalanceDate,
            'company_id' => $company->id
        ]);
        
        foreach ($request->get('cash-in-safe', []) as $index => $cashInSafeArr) {
            $amount = number_unformat($cashInSafeArr['received_amount'] ?: 0) ;
            $receivingBranchId = $cashInSafeArr['received_branch_id'] ?: null ;
            $exchangeRate = isset($cashInSafeArr['exchange_rate']) ? $cashInSafeArr['exchange_rate'] : 1  ;
        
            $openingBalance->cashInSafeStatements()->create([
                'type'=>OpeningBalance::OPEN_BALANCE,
                'branch_id' => $receivingBranchId,
                'currency' => $cashInSafeArr['currency'],
                'exchange_rate' => $exchangeRate,
                'company_id' => $company->id,
                'debit' =>$amount,
                'credit' => 0,
                'date' => $openingBalanceDate,
            ]);
            
        }
        foreach ($request->get(MoneyReceived::CHEQUE, []) as $index => $cheque) {
            $customer = Partner::find($cheque['customer_id'] ?: null);

            $currentAmount = isset($cheque['received_amount']) ? number_unformat($cheque['received_amount']) : 0 ;
            if ($currentAmount > 0) {
                $moneyReceived = $openingBalance->moneyReceived()->create([
                    'type' => MoneyReceived::CHEQUE,
                    'partner_id' => $customer ? $customer->id : null,
                    'received_amount' => $currentAmount,
                    'amount_in_invoice_currency' => $currentAmount,
                    'currency' => $cheque['currency'],
                    'receiving_currency' => $cheque['currency'],
                    'receiving_date' => $openingBalanceDate,
                    'company_id' => $company->id,
                    'user_id' => auth()->id(),
                    'exchange_rate' => isset($cheque['exchange_rate']) ? $cheque['exchange_rate'] : 1
                ]);
                $moneyReceived->cheque()->create([
                    'cheque_number' => $cheque['cheque_number'] ?: null,
                    'drawee_bank_id' => isset($cheque['drawee_bank_id']) ? $cheque['drawee_bank_id'] : null,
                    'due_date' => $cheque['due_date'] ?: null,
                    'company_id'=>$company->id
                ]);
            }
        }
        
        foreach ($request->get(MoneyReceived::CHEQUE_UNDER_COLLECTION, []) as $index => $chequeUnderCollection) {
            $customer = Partner::find($chequeUnderCollection['customer_id'] ?: null);
            $currentAmount = isset($chequeUnderCollection['received_amount']) ? number_unformat($chequeUnderCollection['received_amount']) :  0 ;
            if ($currentAmount > 0) {
                $moneyReceived = $openingBalance->moneyReceived()->create([
                    'type' => MoneyReceived::CHEQUE,
                    'partner_id' => $customer ? $customer->id : null,
                    'received_amount' => $currentAmount,
                    'amount_in_invoice_currency' => $currentAmount,
                    'currency' => $chequeUnderCollection['currency'],
                    'receiving_currency' => $chequeUnderCollection['currency'],
                    'receiving_date' => $openingBalanceDate,
                    'company_id' => $company->id,
                    'user_id' => auth()->id(),
                    'exchange_rate' => isset($chequeUnderCollection['exchange_rate']) ? $chequeUnderCollection['exchange_rate'] : 1
                ]);
                $dueDate = $chequeUnderCollection['due_date'] ?: null;
                $dueDate = $dueDate?  Carbon::make($dueDate)->format('Y-m-d'): null;
                $currentUnderCollectionCheque = $moneyReceived->cheque()->create([
                    'status' => Cheque::UNDER_COLLECTION,
                    'cheque_number' => $chequeUnderCollection['cheque_number'] ?: null,
                    'drawee_bank_id' => isset($chequeUnderCollection['drawee_bank_id']) ? $chequeUnderCollection['drawee_bank_id'] : null,
                    'due_date' =>$dueDate  ,
                    'expected_collection_date'=>$dueDate,
                    'deposit_date' => $chequeUnderCollection['deposit_date'] ?: null,
                    'drawl_bank_id' => $chequeUnderCollection['drawl_bank_id'] ?: null,
                    'account_type' => $chequeUnderCollection['account_type'] ?: null,
                    'account_number' => $chequeUnderCollection['account_number'] ?: null,
                    'clearance_days' => $chequeUnderCollection['clearance_days'] ?: 0,
                    'company_id'=>$company->id
                ]);
                $currentUnderCollectionCheque->update([
                    'updated_at'=>now()
                ]);
                
            }
        }
        
        
        
        
        foreach ($request->get(MoneyPayment::PAYABLE_CHEQUE, []) as $index => $payableChequeArr) {
            $supplier = Partner::find($payableChequeArr['supplier_id'] ?: null);
            $currentAmount = isset($payableChequeArr['paid_amount']) ? number_unformat($payableChequeArr['paid_amount']) : 0 ;
            if ($currentAmount > 0) {
                $paymentCurrency = $payableChequeArr['currency'] ;
                $moneyPayment = $openingBalance->moneyPayments()->create([
                    'type' => MoneyPayment::PAYABLE_CHEQUE,
                    'partner_id' => $supplier ? $supplier->id : null,
                    'paid_amount' => $currentAmount,
                    'amount_in_invoice_currency' => $currentAmount,
                    'currency' => $paymentCurrency,
                    'delivery_date' => $openingBalanceDate,
                    'company_id' => $company->id,
                    'user_id' => auth()->id(),
                    'exchange_rate' => isset($payableChequeArr['exchange_rate']) ? $payableChequeArr['exchange_rate'] : 1
                ]);
                $financialInstitutionId = isset($payableChequeArr['delivery_bank_id']) ? $payableChequeArr['delivery_bank_id'] : null;
                $accountType = $payableChequeArr['account_type'] ?: null ;
                $accountNumber = $payableChequeArr['account_number'] ?: null ;
                $dueDate = $payableChequeArr['due_date'] ?: null ;
                $dueDate = $dueDate ? Carbon::make($dueDate)->format('Y-m-d') : null  ;
                $statementDate = $dueDate ;
                $moneyType = MoneyPayment::PAYABLE_CHEQUE;
                $amountInPaymentCurrency = $currentAmount ;
                $deliveryBranchId = null;
                $currentPayableCheque = $moneyPayment->payableCheque()->create([
                    'status' => PayableCheque::PENDING,
                    'cheque_number' => $payableChequeArr['cheque_number'] ?: null,
                    'delivery_bank_id' => $financialInstitutionId,
                    'due_date' => $dueDate,
                    'delivery_date' => $openingBalanceDate ,
                    'company_id'=>$company->id,
                    'account_type' => $accountType,
                    'account_number' => $accountNumber ,
                ]);
                $accountType = AccountType::find($accountType);
                $moneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
                $currentPayableCheque->update([
                    'updated_at'=>now()
                ]);
                
            }
        }
        
        
        
        
        
        
        return response()->json([
            'redirectTo'=>route('opening-balance.index', ['company'=>$company->id])
        ]);
      
    }
	
	// public function editPayableChequesFromMoneyPaymentsPage(Company $company, StoreOpeningBalanceRequest $request, OpeningBalance $openingBalance)
	// {
	// 	return view('opening-balance.form', $this->getViewVars($company,$request) );
	// }

    public function update(Company $company, StoreOpeningBalanceRequest $request, OpeningBalance $openingBalance)
    {
        $openingBalanceDate = $request->get('date') ;
        $openingBalanceDate = Carbon::make($openingBalanceDate)->format('Y-m-d');
        
        $openingBalance->update([
            'date' => $openingBalanceDate,
        ]);

        /**
         * * هنا تحديث ال
         * * cash in safe
         */
        $oldIdsFromDatabase = $openingBalance->cashInSafeStatements->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input(MoneyReceived::CASH_IN_SAFE, []), 'id') ;

        $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
        // $elementsToUpdate = array_diff($idsFromRequest, $elementsToDelete); // test

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
        
        CashInSafeStatement::deleteButTriggerChangeOnLastElement($openingBalance->cashInSafeStatements->whereIn('id', $elementsToDelete));
    
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input(MoneyReceived::CASH_IN_SAFE), 'id', $id);
            $openingBalance->cashInSafeStatements()->where('cash_in_safe_statements.id', $id)->first()->update(array_merge($dataToUpdate, [
                'debit'=>number_unformat($dataToUpdate['received_amount']),
                'branch_id'=>$dataToUpdate['received_branch_id'],
                'date'=>$openingBalanceDate
            ]));
            
        }
        foreach ($request->get(MoneyReceived::CASH_IN_SAFE, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0')) {
                unset($data['id']);
                $openingBalance->cashInSafeStatements()->create(array_merge($data, [
                    'company_id' => $company->id,
                    'type' => OpeningBalance::OPEN_BALANCE,
                    'user_id' => auth()->id(),
                    'debit'=>number_unformat($data['received_amount']),
                    'branch_id'=>$data['received_branch_id'],
                    'date'=>$openingBalanceDate
                ]));
                
                
            }
        }
        /**
         * * هنا تحديث الشيكات في الخزنة
         * * ChequeInSafe
         */

        $oldIdsFromDatabase = $openingBalance->chequeInSafe->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input(MoneyReceived::CHEQUE, []), 'id') ;

        $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
        // $elementsToUpdate = array_diff($idsFromRequest, $elementsToDelete); // test

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
    
        $openingBalance->chequeInSafe()->whereIn('money_received.id', $elementsToDelete)->delete();
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input(MoneyReceived::CHEQUE), 'id', $id);
    
        
            unset($dataToUpdate['id']);
            $pivotData = [
                'due_date' => $dataToUpdate['due_date'],
                'drawee_bank_id' => isset($dataToUpdate['drawee_bank_id']) ? $dataToUpdate['drawee_bank_id'] : null,
                'cheque_number' => $dataToUpdate['cheque_number'],
                'company_id'=>$company->id
            ];
            unset($dataToUpdate['due_date'], $dataToUpdate['drawee_bank_id'], $dataToUpdate['cheque_number']);
            $dataToUpdate['received_amount'] = isset($dataToUpdate['received_amount']) ? number_unformat($dataToUpdate['received_amount']) : 0;
            $dataToUpdate['partner_id'] = is_numeric($dataToUpdate['customer_id']) ? Partner::find($dataToUpdate['customer_id'])->id : Partner::where('is_customer', 1)->where('name', $dataToUpdate['customer_id'])->first()->id ;
            $dataToUpdate['receiving_date'] =  $openingBalanceDate ;
            $dataToUpdate['company_id'] =  $company->id ;
            $dataToUpdate['receiving_currency'] = $dataToUpdate['currency'] ;
			/**
			 * @var MoneyReceived $currentChequeInSafe
			 */
            $currentChequeInSafe = $openingBalance->chequeInSafe()->where('money_received.id', $id)->first() ;
            $currentChequeInSafe->update($dataToUpdate);
            $currentChequeInSafe->cheque->update($pivotData);
        }
        foreach ($request->get(MoneyReceived::CHEQUE, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0')) {
                unset($data['id']);
                $pivotData = [
                    'due_date' => $data['due_date'],
                    'drawee_bank_id' => isset($data['drawee_bank_id']) ? $data['drawee_bank_id'] : null,
                    'cheque_number' => $data['cheque_number'],
                    'company_id'=>$company->id
                ];
                unset($data['due_date'], $data['drawee_bank_id'], $data['cheque_number']);
                $data['received_amount'] = isset($data['received_amount']) ? number_unformat($data['received_amount']) : 0;
                $data['partner_id'] = is_numeric($data['customer_id']) ? Partner::find($data['customer_id'])->id : Partner::where('is_customer', 1)->where('name', $data['customer_id'])->first()->id ;
                $data['receiving_date'] = $openingBalanceDate ;
                $data['receiving_currency'] = $data['currency'] ;
                $data['company_id'] = $company->id ;
				/**
				 * @var MoneyReceived $moneyReceived
				 */
                $moneyReceived = $openingBalance->chequeInSafe()->create(array_merge($data, [
                    'type' => MoneyReceived::CHEQUE,
                    'user_id' => auth()->id()
                ]));
                $moneyReceived->cheque()->create($pivotData);
            }
        }

        /**
         * * هنا تحديث الشيكات اللي قيد التحصيل
         * * cheques under collection
         */

        $oldIdsFromDatabase = $openingBalance->chequeUnderCollections->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input(MoneyReceived::CHEQUE_UNDER_COLLECTION, []), 'id') ;

        $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
        // $elementsToUpdate = array_diff($idsFromRequest, $elementsToDelete); // test

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
        $openingBalance->chequeUnderCollections()->whereIn('money_received.id', $elementsToDelete)->delete();

        
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input(MoneyReceived::CHEQUE_UNDER_COLLECTION), 'id', $id);
            $dataToUpdate['received_amount'] = isset($dataToUpdate['received_amount']) ? number_unformat($dataToUpdate['received_amount']) : 0;
            unset($dataToUpdate['id']);
            $dueDate = $dataToUpdate['due_date'] ?? null ;
            $dueDate = $dueDate ? Carbon::make($dueDate)->format('Y-m-d'):null;
            $pivotData = [
                'due_date' => $dueDate ,
                'expected_collection_date' => $dueDate,
                'drawee_bank_id' => isset($dataToUpdate['drawee_bank_id']) ? $dataToUpdate['drawee_bank_id'] : null,
                'cheque_number' => $dataToUpdate['cheque_number'],
                'deposit_date' => $dataToUpdate['deposit_date'] ?: null,
                'drawl_bank_id' => $dataToUpdate['drawl_bank_id'] ?: null,
                'account_type' => $dataToUpdate['account_type'] ?: null,
                'account_number' => $dataToUpdate['account_number'] ?: null,
                'clearance_days' => $dataToUpdate['clearance_days'] ?: 0,
                'company_id'=>$company->id
            ];
        
            foreach ($pivotData as $key => $val) {
                if ($key != 'company_id') {
                    unset($dataToUpdate[$key]);
                }
            }

            $dataToUpdate['partner_id'] = is_numeric($dataToUpdate['customer_id']) ? Partner::find($dataToUpdate['customer_id'])->id : Partner::where('is_customer', 1)->where('name', $dataToUpdate['customer_id'])->first()->id ;
            $dataToUpdate['receiving_date'] = $openingBalanceDate;
            $dataToUpdate['receiving_currency'] = $dataToUpdate['currency'];
            $dataToUpdate['company_id']=$company->id;
			/**
			 * @var MoneyReceived $currentChequeUnderCollection
			 */
			$currentChequeUnderCollection = $openingBalance->chequeUnderCollections()->where('money_received.id', $id)->first();
            $currentChequeUnderCollection->update(array_merge($dataToUpdate, ['updated_at'=>now()]));
            $currentChequeUnderCollection->cheque->update(array_merge($pivotData, ['updated_at'=>now()]));
        
        }

        foreach ($request->get(MoneyReceived::CHEQUE_UNDER_COLLECTION, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0')) {
                unset($data['id']);
                $dueDate = $data['due_date'] ? Carbon::make($data['due_date'])->format('Y-m-d') : null ;
                $pivotData = [
                    'due_date' => $dueDate,
                    'expected_collection_date' => $dueDate,
                    'status' => Cheque::UNDER_COLLECTION,
                    'drawee_bank_id' => isset($data['drawee_bank_id']) ? $data['drawee_bank_id'] : null,
                    'cheque_number' => $data['cheque_number'],
                    'deposit_date' => $data['deposit_date'] ?: null,
                    'drawl_bank_id' => $data['drawl_bank_id'] ?: null,
                    'account_type' => $data['account_type'] ?: null,
                    'account_number' => $data['account_number'] ?: null,
                    'clearance_days' => $data['clearance_days'] ?: 0,
                    'company_id'=>$company->id
                ];
                foreach ($pivotData as $key => $val) {
                    unset($data[$key]);
                }
                $data['partner_id'] = is_numeric($data['customer_id']) ? Partner::find($data['customer_id'])->id : Partner::where('is_customer', 1)->where('name', $data['customer_id'])->first()->id ;
                $data['receiving_date']=$openingBalanceDate;
                $data['receiving_currency']=$data['currency'];
                $data['company_id']=$company->id;
                $data['received_amount'] = isset($data['received_amount']) ? number_unformat($data['received_amount']) : 0 ;
                $moneyReceived = $openingBalance->chequeUnderCollections()->create(array_merge($data, [
                    'type' => MoneyReceived::CHEQUE,
                    'user_id' => auth()->id(),
                    'company_id'=>$company->id
                ]));
				/**
				 * @var MoneyReceived $moneyReceived
				 */
                $cheque = $moneyReceived->cheque()->create($pivotData);
                $cheque->update(['updated_at'=>now()]);
            }
        }
        
        
    
        /**
        * * هنا تحديث ال payable cheques
        * * payable cheques
        */

        $oldIdsFromDatabase = $openingBalance->payableCheques->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input(MoneyPayment::PAYABLE_CHEQUE, []), 'id') ;
        
        $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
        //  $elementsToUpdate = array_diff($idsFromRequest, $elementsToDelete); // test

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
        foreach ($elementsToDelete as $elementToDeleteId) {
			/**
			 * @var MoneyPayment $currentMoneyPayment
			 */
            $currentMoneyPayment = MoneyPayment::find($elementToDeleteId);
			
            $currentMoneyPayment->deleteRelations();
            $currentMoneyPayment->delete();
        }
 
        foreach ($elementsToUpdate as $id) {
            $moneyType= MoneyPayment::PAYABLE_CHEQUE ;
            $dataToUpdate = findByKey($request->input($moneyType), 'id', $id);
            $dataToUpdate['paid_amount'] = isset($dataToUpdate['paid_amount']) ? number_unformat($dataToUpdate['paid_amount']) : 0;
            $dataToUpdate['delivery_date'] = $openingBalanceDate ;
            unset($dataToUpdate['id']);
            $dataToUpdate['amount_in_invoice_currency'] = $dataToUpdate['paid_amount'];
                    
            $pivotData = [
                'due_date' =>$statementDate= $dataToUpdate['due_date'],
                'delivery_bank_id' => $financialInstitutionId = isset($dataToUpdate['delivery_bank_id']) ? $dataToUpdate['delivery_bank_id'] : null,
                'cheque_number' => $dataToUpdate['cheque_number'],
                'account_type' => $accountType = $dataToUpdate['account_type'] ?: null,
                'account_number' => $accountNumber = $dataToUpdate['account_number'] ?: null,
                'company_id'=>$company->id
                 
            ];
            foreach ($pivotData as $key => $val) {
                unset($dataToUpdate[$key]);
            }
            $dataToUpdate['partner_id'] = is_numeric($dataToUpdate['supplier_id']) ? Partner::find($dataToUpdate['supplier_id'])->id : Partner::where('is_supplier', 1)->where('name', $dataToUpdate['supplier_id'])->first()->id ;
            $dataToUpdate['company_id'] = $company->id;
             
            $dataToUpdate['payment_currency'] = $dataToUpdate['currency'];
            $dataToUpdate['amount_in_invoice_currency'] = $dataToUpdate['paid_amount'];
			/**
			 * @var MoneyPayment $currentMoneyPayment
			 */
            $currentMoneyPayment = $openingBalance->payableCheques()->where('money_payments.id', $id)->first() ;
            $currentMoneyPayment->update(array_merge($dataToUpdate, ['updated_at'=>now()]));
            $currentMoneyPayment->payableCheque->update(array_merge($pivotData, ['updated_at'=>now()]));
            $currentStatement = $currentMoneyPayment->getCurrentStatement();

             
            $amountInPaymentCurrency = $dataToUpdate['amount_in_invoice_currency'];
            $deliveryBranchId = null ;
            $paymentCurrency  = $dataToUpdate['currency'];
            $accountType  = AccountType::find($accountType);
            // $fullModelName = 'App\Models\\'.$accountType->model_name;
    
            if ($currentStatement) {
                /**
                 * ! Need To Change To Work With All Other Account Types
                 */
                $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
                $currentStatement->handleFullDateAfterDateEdit($statementDate, 0, $amountInPaymentCurrency, [
                    'financial_institution_account_id' =>  $financialInstitutionAccount->id
                ]);
            } else {
				/**
				 * @var MoneyPayment $currentMoneyPayment
				 */
                $currentMoneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
            }

        }
    
        foreach ($request->get(MoneyPayment::PAYABLE_CHEQUE, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0')) {
                unset($data['id']);
                $financialInstitutionId =isset($data['delivery_bank_id']) ? $data['delivery_bank_id'] : null;
                $chequeNumber= $data['cheque_number'];
                $accountType = $data['account_type'] ?: null;
                $accountNumber = $data['account_number'] ?: null ;
                $moneyType = MoneyPayment::PAYABLE_CHEQUE;
                $dueDate = $data['due_date'];
                $statementDate = $dueDate ;
                $pivotData = [
                    'due_date' => $dueDate ,
                    'status' => PayableCheque::PENDING,
                    'delivery_bank_id' => $financialInstitutionId,
                    'cheque_number' => $chequeNumber,
                    'account_type' => $accountType,
                    'account_number' => $accountNumber,
                    'company_id'=>$company->id,
                    'delivery_date'=>$openingBalanceDate
                   ];
                foreach ($pivotData as $key => $val) {
                    if ($key != 'delivery_date') {
                        unset($data[$key]);
                    }
                }
                $data['partner_id'] = is_numeric($data['supplier_id']) ? Partner::find($data['supplier_id'])->id : Partner::where('is_supplier', 1)->where('name', $data['supplier_id'])->first()->id ;
                $data['paid_amount'] = isset($data['paid_amount']) ? number_unformat($data['paid_amount']) : 0 ;
                $data['amount_in_invoice_currency'] = $data['paid_amount'];
                $amountInPaymentCurrency = $data['amount_in_invoice_currency'];
				/**
				 * @var MoneyPayment $moneyPayment
				 */
                $moneyPayment = $openingBalance->payableCheques()->create(array_merge($data, [
                    'type' => MoneyPayment::PAYABLE_CHEQUE,
                    'user_id' => auth()->id(),
                    'payment_currency'=>$paymentCurrency = $data['currency'],
                    'company_id'=>$company->id,
                    'delivery_date'=>$openingBalanceDate
                ]));
                $deliveryBranchId = null;
                $accountType  = AccountType::find($accountType);
                $moneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
				$payableCheque = $moneyPayment->payableCheque()->create($pivotData);
                $payableCheque->update(['updated_at'=>now()]);
            }
        }
        return response()->json([
           'redirectTo'=>route('opening-balance.index',['company'=>$company->id])
        ]);
        
    }
}
