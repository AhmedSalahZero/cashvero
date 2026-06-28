<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Models\Bank;
use App\Models\Company;
use App\Models\MoneyPayment;
use App\Models\Partner;
use App\Models\SupplierOpeningBalance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SupplierOpeningBalancesController
{
    public function index(Company $company, Request $request)
    {
        $suppliers = Partner::getSuppliersForCompanyFormattedForSelect($company);
//        $banks = Bank::pluck('view_name', 'id');
        return view('supplier-opening-balance.form', [
            'company' => $company,
            'model' => $company->supplierOpeningBalance,
			'isCustomer'=>0,
            'suppliersFormatted' => $suppliers,
   
        ]);
    }

    public function store(StoreOpeningBalanceRequest $request, Company $company)
    {
		
        $openingBalanceDate = $request->get('date');
		
		$openingBalanceDate = Carbon::make($openingBalanceDate)->format('Y-m-d');
        $openingBalance = SupplierOpeningBalance::create([
            'date' => $openingBalanceDate,
            'company_id' => $company->id
        ]);
		
		
        foreach ($request->get('opening-balances',[]) as $index => $openingBalanceArr) {
			$invoiceData = self::generateData($openingBalanceDate,$openingBalanceArr,$company);
			$openingBalance->supplierInvoices()->create($invoiceData);
        }
		
		// store opening balances
		$currentKey = 'advanced-opening-balances';
        foreach ($request->get($currentKey,[]) as $index => $openingBalanceArr) {
			$data = self::generateAdvancedData($openingBalanceDate,$openingBalanceArr,$company);
			$money = $openingBalance->moneyModel()->create($data);
			$money->downPaymentSettlements()->create(self::generateDownPaymentData($openingBalanceArr,$company,$money->id));
        } 
		
       
		return response()->json([
			'redirectTo'=>route('suppliers-opening-balance.index',['company'=>$company->id])
		]);
      
    }

public function update(Company $company, StoreOpeningBalanceRequest $request, SupplierOpeningBalance $suppliers_opening_balance)
    {
		
		$openingBalanceDate = $request->get('date') ;
		$openingBalanceDate = Carbon::make($openingBalanceDate)->format('Y-m-d');
        $suppliers_opening_balance->update([
            'date' => $openingBalanceDate,
        ]);
        /**
         * * هنا تحديث ال
         * * cash in safe
         */
        $oldIdsFromDatabase = $suppliers_opening_balance->supplierInvoices->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input('opening-balances', []), 'id') ;
		
		$elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
		foreach($elementsToDelete as $idToDelete){
			$suppliers_opening_balance->supplierInvoices()->where('supplier_invoices.id', $idToDelete)->delete();
		}
		
      //  $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
		
	//	CashInSafeStatement::deleteButTriggerChangeOnLastElement($openingBalance->supplierInvoices->whereIn('id', $elementsToDelete));
	
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input('opening-balances'), 'id', $id);
			$invoiceData = self::generateData($openingBalanceDate,$dataToUpdate,$company);
            $suppliers_opening_balance->supplierInvoices()->where('supplier_invoices.id', $id)->first()->update($invoiceData);
        }
        foreach ($request->get('opening-balances', []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0' )  ) {
                unset($data['id']);
				$invoiceData = self::generateData($openingBalanceDate,$data,$company);
                $suppliers_opening_balance->supplierInvoices()->create($invoiceData);
            }
        }
		
		
		
		
		
		
		
		
		/**
         * * هنا تحديث ال
         * * opening-balances
         */
		$currentKey = 'advanced-opening-balances';
        $oldIdsFromDatabase = $suppliers_opening_balance->moneyModel->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input($currentKey, []), 'id') ;
		
		$elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
		foreach($elementsToDelete as $idToDelete){
			$suppliers_opening_balance->moneyModel()->where('money_payments.id', $idToDelete)->delete();
		}
		
        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
	
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input($currentKey), 'id', $id);
			$moneyData = self::generateAdvancedData($openingBalanceDate,$dataToUpdate,$company);
            $suppliers_opening_balance->moneyModel()->where('money_payments.id', $id)->first()->update($moneyData);
			$moneyPayment = MoneyPayment::find($id);
			$moneyPayment->downPaymentSettlements()->update(self::generateDownPaymentData($dataToUpdate,$company,$id));
        }
        foreach ($request->get($currentKey, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0' )  ) {
                unset($data['id']);
				$moneyData = self::generateAdvancedData($openingBalanceDate,$data,$company);
                $money = $suppliers_opening_balance->moneyModel()->create($moneyData);
				$money->downPaymentSettlements()->create(self::generateDownPaymentData($data,$company,$money->id));
            }
        }
		
		 return response()->json([
			'redirectTo'=>route('suppliers-opening-balance.index',['company'=>$company->id])
		]);
		
    }
	public static function generateData(string $openingBalanceDate , array $openingBalanceArr , Company $company):array 
	{
		$amount = number_unformat($openingBalanceArr['paid_amount'] ?: 0) ;
            $partnerId = $openingBalanceArr['partner_id'] ?: null ;
			$currencyName = $openingBalanceArr['currency'];
			$partner = Partner::find($partnerId);
			$invoiceDueDate = Carbon::make($openingBalanceArr['invoice_due_date'])->format('Y-m-d');
			
			
			$invoiceNumber = $openingBalanceArr['invoice_number'];
			$contractName = $openingBalanceArr['contract_name']??null;
			$contractCode = $openingBalanceArr['contract_code']??null;
			$contractDate = $openingBalanceArr['contract_date']??null;
			$purchasesOrderNumber = $openingBalanceArr['purchases_order_number']??null;
			
            $exchangeRate = isset($openingBalanceArr['exchange_rate']) ? $openingBalanceArr['exchange_rate'] : 1  ;
			return [
				'company_id'=>$company->id ,
				'supplier_id'=>$partnerId,
				'supplier_name'=>$partner->getName(),
				'invoice_date'=>$openingBalanceDate,
				'invoice_due_date'=>$invoiceDueDate,
				'invoice_amount'=>$amount , 
				'exchange_rate'=>$exchangeRate,
				'currency'=>$currencyName,
				'invoice_number'=>$invoiceNumber,
				'contract_name'=>$contractName,
				'contract_code'=>$contractCode,
				'project_name'=>$contractName,
				'contract_date'=>$contractDate,
				'purchases_order_number'=>$purchasesOrderNumber,
		];
	}
	
	public static function generateAdvancedData(string $openingBalanceDate , array $openingBalanceArr , Company $company):array 
	{
		$amount = number_unformat($openingBalanceArr['paid_amount'] ?: 0) ;
            $partnerId = $openingBalanceArr['partner_id'] ?: null ;
			$currencyName = $openingBalanceArr['currency'];
			// $partner = Partner::find($partnerId);
			// $invoiceDueDate = Carbon::make($openingBalanceArr['invoice_due_date'])->format('Y-m-d');
            $exchangeRate = isset($openingBalanceArr['exchange_rate']) ? $openingBalanceArr['exchange_rate'] : 1  ;
			return [
				'company_id'=>$company->id ,
				'partner_id'=>$partnerId,
				'partner_type'=>'is_supplier',
				'paid_amount'=>$amount,
				'amount_in_invoice_currency'=>$amount,
				'money_type'=>'down-payment',
				'down_payment_type'=>$openingBalanceArr['down_payment_type'],
				'contract_id'=>$openingBalanceArr['contract_id']??null,
				'type'=>MoneyPayment::CASH_PAYMENT,
				'delivery_date'=>$openingBalanceDate,
				'exchange_rate'=>$exchangeRate,
				'currency'=>$currencyName,
				'payment_currency'=>$currencyName,
				'invoice_number'=>'opening-balance',
				'comment_en'=>__('Advanced Down Payment'),
				'comment_ar'=>__('Advanced Down Payment'),
		];
	}
	public static function generateDownPaymentData( array $openingBalanceArr , Company $company,int $moneyPaymentId):array 
	{
			$amount = number_unformat($openingBalanceArr['paid_amount'] ?: 0) ;
            $partnerId = $openingBalanceArr['partner_id'] ?: null ;
			$currencyName = $openingBalanceArr['currency'];
			// $partner = Partner::find($partnerId);
			// $invoiceDueDate = Carbon::make($openingBalanceArr['invoice_due_date'])->format('Y-m-d');
            // $exchangeRate = isset($openingBalanceArr['exchange_rate']) ? $openingBalanceArr['exchange_rate'] : 1  ;
			return [
				'company_id'=>$company->id ,
				'contract_id'=>$openingBalanceArr['contract_id']??null,
				'purchase_order_id'=>null ,
				'supplier_id'=>$partnerId,
				'down_payment_amount'=>$amount,
				'down_payment_balance'=>$amount,
				'currency'=>$currencyName,
				'money_payment_id'=>$moneyPaymentId,
		];
	}
	
}
