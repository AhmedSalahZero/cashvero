<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CashInSafeStatement;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\CustomerOpeningBalance;
use App\Models\FinancialInstitution;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\OpeningBalance;
use App\Models\Partner;
use App\Models\PayableCheque;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CustomerOpeningBalancesController
{
    use GeneralFunctions;

    public function index(Company $company, Request $request)
    {
        // $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        // $accountTypes = AccountType::onlyCashAccounts()->get();
        // $selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id) ;
        $customers = Partner::where('company_id', $company->id)->where('is_customer',1)->orderBy('name','asc')->get()->formattedForSelect(true, 'getId', 'getName');
        // $customers = Partner::where('company_id', $company->id)->where('is_customer',1)->get()->formattedForSelect(true, 'getId', 'getName');
		// $selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;

        $banks = Bank::pluck('view_name', 'id');
        return view('customer-opening-balance.form', [
            'company' => $company,
            'model' => $company->customerOpeningBalance,
			'isCustomer'=>1 ,
            // 'selectedBanks' => $selectedBanks,
            // 'banks' => $banks,
            'customersFormatted' => $customers,
            // 'financialInstitutionBanks' => $financialInstitutionBanks,
            // 'accountTypes' => $accountTypes,
			// 'customersFormatted'=>$customers,
			// 'selectedBranches'=>$selectedBranches
        ]);
    }

    public function store(StoreOpeningBalanceRequest $request, Company $company)
    {
		
        $openingBalanceDate = $request->get('date');
		$openingBalanceDate = Carbon::make($openingBalanceDate)->format('Y-m-d');
        $openingBalance = CustomerOpeningBalance::create([
			'date' => $openingBalanceDate,
            'company_id' => $company->id
        ]);
		
		// store opening balances
		$currentKey = 'opening-balances';
        foreach ($request->get($currentKey,[]) as $index => $openingBalanceArr) {
			$invoiceData = self::generateData($openingBalanceDate,$openingBalanceArr,$company);
			$openingBalance->customerInvoices()->create($invoiceData);
        }
		
		// store opening balances
		$currentKey = 'advanced-opening-balances';
        foreach ($request->get($currentKey,[]) as $index => $openingBalanceArr) {
			$data = self::generateAdvancedData($openingBalanceDate,$openingBalanceArr,$company);
			$money = $openingBalance->moneyModel()->create($data);
			$money->downPaymentSettlements()->create(self::generateDownPaymentData($openingBalanceArr,$company,$money->id));
        } 

       
		return response()->json([
			'redirectTo'=>route('customers-opening-balance.index',['company'=>$company->id])
		]);
      
    }

public function update(Company $company, StoreOpeningBalanceRequest $request, CustomerOpeningBalance $customers_opening_balance)
    {
		
		$openingBalanceDate = $request->get('date') ;
		$openingBalanceDate = Carbon::make($openingBalanceDate)->format('Y-m-d');
        $customers_opening_balance->update([
            'date' => $openingBalanceDate,
        ]);
        /**
         * * هنا تحديث ال
         * * opening-balances
         */
		$currentKey = 'opening-balances';
        $oldIdsFromDatabase = $customers_opening_balance->customerInvoices->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input($currentKey, []), 'id') ;
		
		$elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
		foreach($elementsToDelete as $idToDelete){
			$customers_opening_balance->customerInvoices()->where('customer_invoices.id', $idToDelete)->delete();
		}
		
        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
	
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input($currentKey), 'id', $id);
			$invoiceData = self::generateData($openingBalanceDate,$dataToUpdate,$company);
            $customers_opening_balance->customerInvoices()->where('customer_invoices.id', $id)->first()->update($invoiceData);
        }
        foreach ($request->get($currentKey, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0' )  ) {
                unset($data['id']);
				$invoiceData = self::generateData($openingBalanceDate,$data,$company);
                $customers_opening_balance->customerInvoices()->create($invoiceData);
            }
        }
		
		
		
		
		
		/**
         * * هنا تحديث ال
         * * opening-balances
         */
		$currentKey = 'advanced-opening-balances';
        $oldIdsFromDatabase = $customers_opening_balance->moneyModel->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input($currentKey, []), 'id') ;
		
	// 
		$elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
		foreach($elementsToDelete as $idToDelete){
			$customers_opening_balance->moneyModel()->where('money_received.id', $idToDelete)->delete();
		}
		
        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
	
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input($currentKey), 'id', $id);
			$moneyData = self::generateAdvancedData($openingBalanceDate,$dataToUpdate,$company);
            $customers_opening_balance->moneyModel()->where('money_received.id', $id)->first()->update($moneyData);
			$moneyReceived = MoneyReceived::find($id);
			$moneyReceived->downPaymentSettlements()->update(self::generateDownPaymentData($dataToUpdate,$company,$id));
        }
        foreach ($request->get($currentKey, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0' )  ) {
                unset($data['id']);
				$moneyData = self::generateAdvancedData($openingBalanceDate,$data,$company);
                $money = $customers_opening_balance->moneyModel()->create($moneyData);
				$money->downPaymentSettlements()->create(self::generateDownPaymentData($data,$company,$money->id));
            }
        }
		
		 return response()->json([
			'redirectTo'=>route('customers-opening-balance.index',['company'=>$company->id])
		]);
		
    }
	public static function generateData(string $openingBalanceDate , array $openingBalanceArr , Company $company):array 
	{
		$amount = number_unformat($openingBalanceArr['received_amount'] ?: 0) ;
            $partnerId = $openingBalanceArr['partner_id'] ?: null ;
			$currencyName = $openingBalanceArr['currency'];
			$partner = Partner::find($partnerId);
			$invoiceNumber = $openingBalanceArr['invoice_number'];
			$contractName = $openingBalanceArr['contract_name']??null;
			$contractCode = $openingBalanceArr['contract_code']??null;
			$contractDate = $openingBalanceArr['contract_date']??null;
			$salesOrderNumber = $openingBalanceArr['sales_order_number']??null;
			$invoiceDueDate = Carbon::make($openingBalanceArr['invoice_due_date'])->format('Y-m-d');
            $exchangeRate = isset($openingBalanceArr['exchange_rate']) ? $openingBalanceArr['exchange_rate'] : 1  ;
			return [
				'company_id'=>$company->id ,
				'customer_id'=>$partnerId,
				'customer_name'=>$partner->getName(),
				'invoice_date'=>$openingBalanceDate,
				'invoice_due_date'=>$invoiceDueDate,
				'invoice_amount'=>$amount , 
				'exchange_rate'=>$exchangeRate,
				'currency'=>$currencyName,
				
				'project_name'=>$contractName,
				'invoice_number'=>$invoiceNumber,
				'contract_name'=>$contractName,
				'contract_code'=>$contractCode,
				'contract_date'=>$contractDate,
				'sales_order_number'=>$salesOrderNumber,
		];
	}
	
	
	public static function generateAdvancedData(string $openingBalanceDate , array $openingBalanceArr , Company $company):array 
	{
		$amount = number_unformat($openingBalanceArr['received_amount'] ?: 0) ;
            $partnerId = $openingBalanceArr['partner_id'] ?: null ;
			$currencyName = $openingBalanceArr['currency'];
			// $partner = Partner::find($partnerId);
			// $invoiceDueDate = Carbon::make($openingBalanceArr['invoice_due_date'])->format('Y-m-d');
            $exchangeRate = isset($openingBalanceArr['exchange_rate']) ? $openingBalanceArr['exchange_rate'] : 1  ;
			return [
				'company_id'=>$company->id ,
				'partner_id'=>$partnerId,
				'partner_type'=>'is_customer',
				'received_amount'=>$amount,
				'amount_in_invoice_currency'=>$amount,
				'money_type'=>'down-payment',
				'down_payment_type'=>$openingBalanceArr['down_payment_type'],
				'contract_id'=>$openingBalanceArr['contract_id']??null,
				'type'=>MoneyReceived::CASH_IN_SAFE,
				'receiving_date'=>$openingBalanceDate,
				'exchange_rate'=>$exchangeRate,
				'currency'=>$currencyName,
				'receiving_currency'=>$currencyName,
				'invoice_number'=>'opening-balance',
				'comment_en'=>__('Advanced Down Payment'),
				'comment_ar'=>__('Advanced Down Payment'),
		];
	}
	public static function generateDownPaymentData( array $openingBalanceArr , Company $company,int $moneyReceivedId):array 
	{
			$amount = number_unformat($openingBalanceArr['received_amount'] ?: 0) ;
            $partnerId = $openingBalanceArr['partner_id'] ?: null ;
			$currencyName = $openingBalanceArr['currency'];
			// $partner = Partner::find($partnerId);
			// $invoiceDueDate = Carbon::make($openingBalanceArr['invoice_due_date'])->format('Y-m-d');
            // $exchangeRate = isset($openingBalanceArr['exchange_rate']) ? $openingBalanceArr['exchange_rate'] : 1  ;
			return [
				'company_id'=>$company->id ,
				'contract_id'=>$openingBalanceArr['contract_id']??null,
				'sales_order_id'=>null ,
				'customer_id'=>$partnerId,
				'down_payment_amount'=>$amount,
				'down_payment_balance'=>$amount,
				'currency'=>$currencyName,
				'money_received_id'=>$moneyReceivedId,
		];
	}
	
	
}
