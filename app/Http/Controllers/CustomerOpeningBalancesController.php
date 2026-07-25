<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\CustomerOpeningBalance;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * CustomerOpeningBalancesController
 * ------------------------------------------------------------------
 * Manages the company's Customers Opening Balance — a SINGLETON per
 * company (Company::customerOpeningBalance() is a HasOne), holding
 * two repeaters: opening customer invoices, and advanced/down-payment
 * balances (each down payment also creates a linked
 * `DownPaymentSettlement` row). Same shape and same reasoning as
 * OpeningBalancesController (Cash in Safe & Cheque Balance) — see
 * that controller's docblock for the full rationale.
 *
 * `store()` / `update()` / `generateData()` / `generateAdvancedData()`
 * / `generateDownPaymentData()`'s SAVE LOGIC is completely UNCHANGED.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → REPURPOSED — read-only Vue summary page.
 *   ✅ manage() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/CustomerOpeningBalance/Form.vue, submitting
 *      the exact field/array names store()/update() already expect
 *      (`opening-balances[]`, `advanced-opening-balances[]`, each row
 *      keeping its `id` — 0 for new rows).
 *   ⚠️ store() / update() → response type fixed from raw JSON
 *      (`response()->json(['redirectTo'=>...])`, correct for the old
 *      jQuery-AJAX Blade form) to a real `redirect()->with('success')`
 *      — required once the caller became Inertia (same bug class
 *      found and fixed on OpeningBalancesController). Save logic
 *      itself untouched.
 *   ⚠️ Contract Name (on invoice rows) and the down-payment Contract
 *      picker are dependent dropdowns tied to the chosen customer —
 *      the original populated them via real, still-wired AJAX routes
 *      (`update.contracts.based.on.customer`,
 *      `update.sales.orders.based.on.contract`), so the Vue form
 *      calls those SAME routes client-side via fetch(), rather than
 *      the "fetch everything up front" workaround used where no
 *      traceable route existed (e.g. Cash in Safe's account-number
 *      picker). Nothing new was built here — just wired to what
 *      already exists.
 */
class CustomerOpeningBalancesController
{
    use GeneralFunctions;

    /**
     * NEW — read-only summary. Shows both repeaters (opening invoices
     * and down payments) if a record exists, with a "Manage" button
     * to the real form. Presentation only.
     */
    public function index(Company $company)
    {
        $openingBalance = $company->customerOpeningBalance;

        if (!$openingBalance) {
            return \Inertia\Inertia::render('CustomerOpeningBalance/Index', [
                'company' => ['id' => $company->id],
                'exists' => false,
                'manageUrl' => route('customers-opening-balance.manage', ['company' => $company->id]),
            ]);
        }

        $invoices = $openingBalance->customerInvoices->map(fn (CustomerInvoice $invoice) => [
            'id' => $invoice->id,
            'customer' => $invoice->getCustomerName(),
            'invoice_number' => $invoice->invoice_number,
            'invoice_due_date' => $invoice->invoice_due_date,
            'currency' => $invoice->currency,
            'amount' => (float) $invoice->invoice_amount,
            'contract_name' => $invoice->contract_name,
            'contract_code' => $invoice->contract_code,
            'sales_order_number' => $invoice->getSalesOrderNumber(),
        ])->values();

        $downPayments = $openingBalance->moneyModel->map(fn (MoneyReceived $money) => [
            'id' => $money->getId(),
            'customer' => $money->getCustomerName(),
            'down_payment_type' => $money->down_payment_type,
            'contract_name' => $money->getContractName(),
            'currency' => $money->getCurrency(),
            'amount' => (float) $money->getReceivedAmount(),
        ])->values();

        return \Inertia\Inertia::render('CustomerOpeningBalance/Index', [
            'company' => ['id' => $company->id],
            'exists' => true,
            'date' => $openingBalance->getDate(),
            'manageUrl' => route('customers-opening-balance.manage', ['company' => $company->id]),
            'invoices' => $invoices,
            'downPayments' => $downPayments,
        ]);
    }

    /**
     * MIGRATED — renders the real create/edit form as Vue + Inertia.
     */
    public function manage(Company $company, Request $request)
    {
        $model = $company->customerOpeningBalance;

        $customers = Partner::where('company_id', $company->id)->where('is_customer', 1)->orderBy('name')
            ->get()->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->getName()])->values();

        $modelData = null;
        if ($model) {
            $modelData = [
                'id' => $model->id,
                'date' => $model->getDate(),
                'invoices' => $model->customerInvoices->map(fn (CustomerInvoice $row) => [
                    'id' => $row->id,
                    'partner_id' => $row->getPartnerId(),
                    'invoice_number' => $row->invoice_number,
                    'contract_name' => $row->contract_name,
                    'contract_code' => $row->contract_code,
                    'contract_date' => $row->contract_date,
                    'sales_order_number' => $row->getSalesOrderNumber(),
                    'received_amount' => (float) $row->invoice_amount,
                    'currency' => $row->currency,
                    'exchange_rate' => (float) $row->exchange_rate,
                    'invoice_due_date' => $row->invoice_due_date,
                ])->values(),
                'downPayments' => $model->moneyModel->map(fn (MoneyReceived $row) => [
                    'id' => $row->getId(),
                    'partner_id' => $row->getPartnerId(),
                    'received_amount' => (float) $row->getReceivedAmount(),
                    'currency' => $row->getCurrency(),
                    'exchange_rate' => (float) $row->getExchangeRate(),
                    'down_payment_type' => $row->down_payment_type,
                    'contract_id' => $row->getContractId(),
                    'contract_name' => $row->getContractName(),
                ])->values(),
            ];
        }

        return \Inertia\Inertia::render('CustomerOpeningBalance/Form', [
            'company' => ['id' => $company->id, 'opening_balance_date' => $company->opening_balance_date],
            'submitUrl' => $model
                ? route('customers-opening-balance.update', ['company' => $company->id, 'customers_opening_balance' => $model->id])
                : route('customers-opening-balance.store', ['company' => $company->id]),
            'backUrl' => route('customers-opening-balance.index', ['company' => $company->id]),
            'isEdit' => (bool) $model,
            'model' => $modelData,
            'currencies' => getCurrencies(),
            'customers' => $customers,
            'contractsForCustomerUrl' => route('update.contracts.based.on.customer', ['company' => $company->id]),
            'salesOrdersForContractUrl' => route('update.sales.orders.based.on.contract', ['company' => $company->id]),
        ]);
    }

    public function store(StoreOpeningBalanceRequest $request, Company $company)
    {
		
        // Read-only field in the form now — always mirrors the company's
        // own Opening Balance Date, not whatever the request sends.
        $openingBalanceDate = Carbon::make($company->opening_balance_date)->format('Y-m-d');
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

       
        return redirect()
            ->route('customers-opening-balance.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
      
    }

public function update(Company $company, StoreOpeningBalanceRequest $request, CustomerOpeningBalance $customers_opening_balance)
    {
		
		// Read-only field — always mirrors the company's Opening Balance Date.
		$openingBalanceDate = Carbon::make($company->opening_balance_date)->format('Y-m-d');
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
		
		 return redirect()
			->route('customers-opening-balance.index', ['company' => $company->id])
			->with('success', __('Item Has Been Updated Successfully'));
		
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
