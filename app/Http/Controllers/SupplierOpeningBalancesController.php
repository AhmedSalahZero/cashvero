<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Models\Company;
use App\Models\MoneyPayment;
use App\Models\Partner;
use App\Models\SupplierInvoice;
use App\Models\SupplierOpeningBalance;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * SupplierOpeningBalancesController
 * ------------------------------------------------------------------
 * Manages the company's Suppliers Opening Balance — a SINGLETON per
 * company (Company::supplierOpeningBalance() is a HasOne), holding
 * two repeaters: opening supplier invoices, and advanced/down-payment
 * balances (each also creates a linked `DownPaymentMoneyPaymentSettlement`
 * row). Mirror of CustomerOpeningBalancesController — see its
 * docblock, and OpeningBalancesController's, for the full rationale.
 *
 * `store()` / `update()` / `generateData()` / `generateAdvancedData()`
 * / `generateDownPaymentData()`'s SAVE LOGIC is completely UNCHANGED.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → REPURPOSED — read-only Vue summary page.
 *   ✅ manage() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/SupplierOpeningBalance/Form.vue, submitting
 *      the exact field/array names store()/update() already expect
 *      (`opening-balances[]`, `advanced-opening-balances[]`, each row
 *      keeping its `id` — 0 for new rows). Mirror of
 *      CustomerOpeningBalance/Form.vue with two field-name
 *      differences confirmed from the original Blade:
 *      `paid_amount` (not `received_amount`) and
 *      `purchases_order_number` (not `sales_order_number`).
 *   ⚠️ store() / update() → response type fixed from raw JSON to a
 *      real `redirect()->with('success')` — same fix as the other two
 *      opening balance controllers, required once the caller became
 *      Inertia. Save logic itself untouched.
 *   ⚠️ Contract Name / Contract picker → same real, still-wired AJAX
 *      routes as the Customers form
 *      (`update.contracts.based.on.customer`,
 *      `update.sales.orders.based.on.contract`), called with
 *      `is_lc=1` this time so the endpoint's existing
 *      `$contract->forSupplier()` branch is used instead of
 *      `forCustomer()` — that branching already existed in
 *      ContractsController, untouched here. The "Purchase Order
 *      Number" field reuses the SAME sales-orders endpoint the
 *      Customers form uses (not the separate, more complex
 *      `update.purchase.orders.based.on.contract` endpoint, which has
 *      its own -1/-2 "new PO" special cases for a different feature)
 *      — matching the original Blade, whose dropdown for this field
 *      was literally reusing the `sales_order_number` CSS class,
 *      i.e. already wired to the same endpoint before this migration.
 */
class SupplierOpeningBalancesController
{
    /**
     * NEW — read-only summary. Shows both repeaters (opening invoices
     * and down payments) if a record exists, with a "Manage" button
     * to the real form. Presentation only.
     */
    public function index(Company $company)
    {
        $openingBalance = $company->supplierOpeningBalance;

        if (!$openingBalance) {
            return \Inertia\Inertia::render('SupplierOpeningBalance/Index', [
                'company' => ['id' => $company->id],
                'exists' => false,
                'manageUrl' => route('suppliers-opening-balance.manage', ['company' => $company->id]),
            ]);
        }

        $invoices = $openingBalance->supplierInvoices->map(fn (SupplierInvoice $invoice) => [
            'id' => $invoice->id,
            'supplier' => $invoice->getSupplierName(),
            'invoice_number' => $invoice->invoice_number,
            'invoice_due_date' => $invoice->invoice_due_date,
            'currency' => $invoice->currency,
            'amount' => (float) $invoice->invoice_amount,
            'contract_name' => $invoice->contract_name,
            'contract_code' => $invoice->contract_code,
            'purchases_order_number' => $invoice->getPurchasesOrderNumber(),
        ])->values();

        $downPayments = $openingBalance->moneyModel->map(fn (MoneyPayment $money) => [
            'id' => $money->getId(),
            'supplier' => $money->getSupplierName(),
            'down_payment_type' => $money->down_payment_type,
            'contract_name' => $money->getContractName(),
            'currency' => $money->getCurrency(),
            'amount' => (float) $money->getPaidAmount(),
        ])->values();

        return \Inertia\Inertia::render('SupplierOpeningBalance/Index', [
            'company' => ['id' => $company->id],
            'exists' => true,
            'date' => $openingBalance->getDate(),
            'manageUrl' => route('suppliers-opening-balance.manage', ['company' => $company->id]),
            'invoices' => $invoices,
            'downPayments' => $downPayments,
        ]);
    }

    /**
     * MIGRATED — renders the real create/edit form as Vue + Inertia.
     */
    public function manage(Company $company, Request $request)
    {
        $model = $company->supplierOpeningBalance;

        $suppliers = Partner::where('company_id', $company->id)->where('is_supplier', 1)->orderBy('name')
            ->get()->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->getName()])->values();

        $modelData = null;
        if ($model) {
            $modelData = [
                'id' => $model->id,
                'date' => $model->getDate(),
                'invoices' => $model->supplierInvoices->map(fn (SupplierInvoice $row) => [
                    'id' => $row->id,
                    'partner_id' => $row->getPartnerId(),
                    'invoice_number' => $row->invoice_number,
                    'contract_name' => $row->contract_name,
                    'contract_code' => $row->contract_code,
                    'contract_date' => $row->contract_date,
                    'purchases_order_number' => $row->getPurchasesOrderNumber(),
                    'paid_amount' => (float) $row->invoice_amount,
                    'currency' => $row->currency,
                    'exchange_rate' => (float) $row->exchange_rate,
                    'invoice_due_date' => $row->invoice_due_date,
                ])->values(),
                'downPayments' => $model->moneyModel->map(fn (MoneyPayment $row) => [
                    'id' => $row->getId(),
                    'partner_id' => $row->getSupplierId(),
                    'paid_amount' => (float) $row->getPaidAmount(),
                    'currency' => $row->getCurrency(),
                    'exchange_rate' => (float) $row->getExchangeRate(),
                    'down_payment_type' => $row->down_payment_type,
                    'contract_id' => $row->getContractId(),
                    'contract_name' => $row->getContractName(),
                ])->values(),
            ];
        }

        return \Inertia\Inertia::render('SupplierOpeningBalance/Form', [
            'company' => ['id' => $company->id, 'opening_balance_date' => $company->opening_balance_date],
            'submitUrl' => $model
                ? route('suppliers-opening-balance.update', ['company' => $company->id, 'suppliers_opening_balance' => $model->id])
                : route('suppliers-opening-balance.store', ['company' => $company->id]),
            'backUrl' => route('suppliers-opening-balance.index', ['company' => $company->id]),
            'isEdit' => (bool) $model,
            'model' => $modelData,
            'currencies' => getCurrencies(),
            'suppliers' => $suppliers,
            'contractsForSupplierUrl' => route('update.contracts.based.on.customer', ['company' => $company->id]),
            'salesOrdersForContractUrl' => route('update.sales.orders.based.on.contract', ['company' => $company->id]),
        ]);
    }

    public function store(StoreOpeningBalanceRequest $request, Company $company)
    {
		
        // Read-only field in the form now — always mirrors the company's
        // own Opening Balance Date, not whatever the request sends.
        $openingBalanceDate = Carbon::make($company->opening_balance_date)->format('Y-m-d');
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
		
       
		return redirect()
			->route('suppliers-opening-balance.index', ['company' => $company->id])
			->with('success', __('Data Store Successfully'));
      
    }

public function update(Company $company, StoreOpeningBalanceRequest $request, SupplierOpeningBalance $suppliers_opening_balance)
    {
		
		// Read-only field — always mirrors the company's Opening Balance Date.
		$openingBalanceDate = Carbon::make($company->opening_balance_date)->format('Y-m-d');
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
			/**
			 * * كان delete على الكويري بيلدر مباشرة
			 * * فكان بيمسح صف الـ money payment بس ويسيب البنك ستيتمنت وكشوف الشركاء وراه
			 * * لازم نحمّل الموديل وننده deleteRelations قبل الحذف
			 */
			$moneyPaymentToDelete = $suppliers_opening_balance->moneyModel()->where('money_payments.id', $idToDelete)->first();
			if($moneyPaymentToDelete){
				$moneyPaymentToDelete->deleteRelations();
				$moneyPaymentToDelete->delete();
			}
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
		
		 return redirect()
			->route('suppliers-opening-balance.index', ['company' => $company->id])
			->with('success', __('Item Has Been Updated Successfully'));
		
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
