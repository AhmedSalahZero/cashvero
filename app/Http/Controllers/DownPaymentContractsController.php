<?php

namespace App\Http\Controllers;
use App\Http\Requests\StoreDownPaymentSettlementRequest;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\CustomerInvoice;
use App\Models\Log;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\SupplierInvoice;
use App\Services\Api\OdooPayment;
use App\Traits\Models\HasBasicFilter;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * DownPaymentContractsController
 * ------------------------------------------------------------------
 * Down payments waiting to be settled against invoices. Reached from
 * the "Down Payment Amount Settlement" button on the Invoice Report
 * page. Serves BOTH Customer and Supplier via the `$modelType`
 * parameter ('CustomerInvoice' or 'SupplierInvoice') — one shared
 * implementation, same pattern as every other Customer/Supplier
 * shared controller in this app.
 *
 * IMPORTANT — settlement logic is SHARED, not this feature's own:
 * downPaymentSettlements()/storeDownPaymentSettlement() use the exact
 * same storeNewSettlement() engine as MoneyReceivedController and
 * MoneyPaymentController (Treasury Operations). It also writes
 * directly to Odoo when the company has Odoo credentials
 * (settleAdvanceWithInvoices). Building this was deliberately
 * deferred until Treasury Operations (Money Payment / Money Received)
 * was migrated, so the settlement engine's real shape (payload,
 * validation, Odoo call) was already proven working in Vue first —
 * that's now done, so this page follows.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   - viewContractsWithDownPayments() → ALREADY migrated (read-only,
 *                                        no risk). Returns
 *                                        Inertia::render(), served by
 *                                        resources/js/Pages/Balances/DownPaymentContracts.vue
 *   - downPaymentSettlements()  → NOW migrated. Inertia::render(),
 *                                  served by Pages/DownPaymentSettlement/Form.vue
 *   - storeDownPaymentSettlement() → real Laravel redirect/back()
 *                                  (Inertia-compatible), was raw JSON
 *                                  for the old jQuery/AJAX form.
 *                                  Business logic (storeNewSettlement,
 *                                  Odoo settleAdvanceWithInvoices)
 *                                  left completely untouched.
 */
class DownPaymentContractsController extends Controller
{
	use HasBasicFilter;
    public function viewContractsWithDownPayments(Company $company,Request $request,int $partnerId,string $modelType,string $currency)
	{
		$fullModelType = 'App\Models\\'.$modelType;
		$moneyModelName = $fullModelType::MONEY_MODEL_NAME ;
		$fullMoneyModelName = 'App\Models\\'.$fullModelType::MONEY_MODEL_NAME ;
		$moneyTableName = $fullModelType::MONEY_RECEIVED_OR_PAYMENT_TABLE_NAME ;
		$receivingOrPaymentCurrencyColumnName = $fullMoneyModelName::RECEIVING_OR_PAYMENT_CURRENCY_COLUMN_NAME;
		$partner = Partner::find($partnerId);
		$partnerId = $partner->id;
		$partnerName = $partner->getName();
		$contractsWithDownPayments = $fullMoneyModelName::CONTRACTS_WITH_DOWN_PAYMENTS;
		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$filterDates = [];
		foreach([$contractsWithDownPayments] as $type){
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
		$moneyModels = $fullMoneyModelName::whereIn('money_type',[
			$fullMoneyModelName::DOWN_PAYMENT
			,$fullMoneyModelName::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT
		])
		->where($moneyTableName.'.company_id',$company->id)
		->where($moneyTableName.'.partner_id',$partnerId)
		->where($moneyTableName.'.'.'currency',$currency)
		->leftJoin('contracts','contracts.id','=','contract_id')
		->where(function($q){
			$q->where('contract_id','=',null)->orWhere('contracts.status','!=',Contract::FINISHED);
		})
		->with('contract')
		->selectRaw($moneyTableName.'.*,contracts.id as contractId')
		->get();

		// Note: $filterDates is computed above but was never actually
		// applied as a WHERE clause in the original query either — the
		// date pickers on this page display those defaults but don't
		// currently filter results. Preserved exactly as-is (not a
		// bug we introduced, and not ours to silently "fix" without
		// a decision from the project owner).
		$isMoneyReceived = $modelType == 'CustomerInvoice';
		$invoiceModelName = $isMoneyReceived ? CustomerInvoice::class : SupplierInvoice::class;
		$rows = $moneyModels->map(function ($moneyModel) use ($company, $modelType, $invoiceModelName) {
			/**
			 * FIX (per request, 2026-08-13): the down payment's own
			 * 👍/🐞 only ever reflected whether the ADVANCE ITSELF
			 * synced with Odoo when it was first received/paid. It
			 * said nothing about what happens later when that advance
			 * gets matched against specific invoices (a separate Odoo
			 * journal entry per settlement, via
			 * OdooPayment::settleAdvanceWithInvoices()). Both icons now
			 * reflect BOTH: the down payment's own sync AND every
			 * settlement made against it — a row can show both icons
			 * at once if some settlements succeeded and others didn't.
			 */
			$settlements = $moneyModel->settlements;

			$settlementReferences = $settlements
				->filter(fn ($s) => $s->odoo_reference)
				->map(fn ($s) => $s->odoo_reference.' — Transfer Customer Advance to Receivable')
				->values()
				->all();

			$failedSettlements = $settlements
				->filter(fn ($s) => $s->hasOdooError())
				->map(function ($s) use ($invoiceModelName) {
					$invoice = $invoiceModelName::find($s->invoice_id);
					$invoiceLabel = $invoice ? $invoice->getInvoiceNumber() : ('#'.$s->invoice_id);
					return 'Invoice '.$invoiceLabel.': '.$s->getOdooError();
				})
				->values()
				->all();

			$odooReferenceNames = array_merge($moneyModel->getOdooReferenceNames(), $settlementReferences);
			$hasOdooError = $moneyModel->hasOdooError() || count($failedSettlements) > 0;
			$isFullyIntegrated = ($company->hasOdooIntegrationCredentials() && $moneyModel->fullyIntegratedWithOdoo()) || count($settlementReferences) > 0;

			return [
				'id' => $moneyModel->id,
				'date_formatted' => $moneyModel->getReceivingOrPaymentMoneyDateFormatted(),
				'down_payment_amount_formatted' => $moneyModel->getDownPaymentAmountFormatted(),
				'settlement_amount_formatted' => $moneyModel->getTotalSettlementAmountForDownPaymentFormatted(),
				'net_amount_formatted' => number_format($moneyModel->getTotalSettlementsNetBalanceForDownPayment()),
				'currency' => $moneyModel->currency,
				'contract_name' => $moneyModel->getContractName(),
				'contract_amount_formatted' => $moneyModel->getContractAmountFormatted(),
				/**
				 * FIX (per request, 2026-08-13): this page had neither
				 * the success (👍) nor failure (🐞) Odoo status icon at
				 * all, unlike every other list of Odoo-touching records
				 * in the app. $moneyModel is always a MoneyPayment or
				 * MoneyReceived here, which already has all three
				 * methods via the IsMoney trait — nothing new needed on
				 * the model side, just exposing it here.
				 */
				'is_fully_integrated_with_odoo' => $isFullyIntegrated,
				'odoo_reference_names' => $odooReferenceNames,
				'has_odoo_error' => (bool) $hasOdooError,
				'odoo_error' => $moneyModel->getOdooError(),
				'failed_settlement_errors' => $failedSettlements,
				// Still Blade — see class docblock. Left as a plain
				// link out, same as Adjust Due Date / Money Received
				// were before their own pages got migrated.
				'settlement_url' => route('view.down.payment.settlement', ['company' => $company->id, 'downPaymentId' => $moneyModel->id, 'modelType' => $modelType]),
			];
		})->values();

        return Inertia::render('Balances/DownPaymentContracts', [
			'title' => $partnerName . ' ' . __('Down Payment'),
			'currency' => $currency,
			'rows' => $rows,
			'backUrl' => route('view.invoice.report', ['company' => $company->id, 'partnerId' => $partnerId, 'currency' => $currency, 'modelType' => $modelType]),
		]);
    }
	public function downPaymentSettlements(Company $company,Request $request, int $downPaymentId ,string $modelType)
	{

		$fullClassName = 'App\Models\\'.$modelType;
		$downPaymentModelName=$fullClassName::MONEY_MODEL_NAME;
		$downPaymentModelFullName = 'App\Models\\'.$downPaymentModelName ;   
		$downPayment =$downPaymentModelFullName::find($downPaymentId);
		$contract = $downPayment->contract;
		$partnerId = $downPayment->getPartnerId();
		$partnerName = $downPayment->getPartnerName();
		$fullClassName = ('\App\Models\\' . $modelType) ;
        $clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME ;
        $clientNameColumnName = $fullClassName::CLIENT_NAME_COLUMN_NAME ;
		$customerNameText = (new $fullClassName)->getClientNameText();
		$contractCurrency = $downPayment->getCurrency();
		$currencies = $fullClassName::getCurrencies();
		$currencies = array_filter($currencies,function($item) use ($contractCurrency){
			return $item == $contractCurrency;
		});
		
		$invoices =  $fullClassName::
		when($contract,function($q) use ($contract){
			$q->where('contract_code',$contract->getCode());
		})
		->where($clientNameColumnName,$partnerName)
		->where('currency','=',$contractCurrency)
		->where('company_id',$company->id)
		->where('net_invoice_amount','>',0);
		// ⚠️ Same note as the original: $inEditMode always evaluates
		// to false here, so the ->where('net_balance', '>', 0) filter
		// was never actually applied — every matching invoice shows
		// up regardless of remaining balance. Preserved exactly as-is,
		// not ours to silently "fix" without a decision from the
		// project owner.
		$invoices = $invoices->orderBy('invoice_date','asc')->get() ; 
		
		$downPaymentAmount =  $downPayment->getDownPaymentAmount();
		$isDownPaymentFromMoneyPayment = $downPayment->isInvoiceSettlementWithDownPayment();
		$hasProjectNameColumn = $fullClassName::hasProjectNameColumn();

		$rows = $invoices->map(function ($invoice) use ($downPayment, $partnerId, $isDownPaymentFromMoneyPayment, $hasProjectNameColumn) {
			$totalSettlementAmount = $downPayment->sumSettlementsForInvoice($invoice->id, $partnerId, $isDownPaymentFromMoneyPayment);
			$totalWithholdAmount = $downPayment->sumWithholdAmountForInvoice($invoice->id, $partnerId, $isDownPaymentFromMoneyPayment);

			return [
				'invoice_id' => $invoice->id,
				'project_name' => $hasProjectNameColumn ? $invoice->getProjectName() : null,
				'invoice_number' => $invoice->getInvoiceNumber(),
				'invoice_date_formatted' => $invoice->getInvoiceDateFormatted(),
				'invoice_due_date_formatted' => $invoice->getInvoiceDueDateFormatted(),
				'currency' => $invoice->getCurrency(),
				'net_invoice_amount_formatted' => $invoice->getNetInvoiceAmountFormatted(),
				'collected_amount_formatted' => number_format($invoice->getCollectedOrPaidInEditModeForDownPayment(true, $totalSettlementAmount), 0),
				'net_balance_formatted' => number_format($invoice->calculateNetBalanceInEditMode(true, $totalSettlementAmount, $totalWithholdAmount), 0),
				'settlement_amount' => (float) $totalSettlementAmount,
				'withhold_amount' => (float) $totalWithholdAmount,
			];
		})->values();

		return Inertia::render('DownPaymentSettlement/Form', [
			'modelType' => $modelType,
			'hasProjectNameColumn' => $hasProjectNameColumn,
			'company' => ['id' => $company->id, 'name' => $company->getName()],
			'contractName' => $contract ? $contract->getName() : null,
			'invoices' => $rows,
			'currency' => $contractCurrency,
			'customerNameText' => $customerNameText,
			'partnerId' => $partnerId,
			'partnerName' => $partnerName,
			'downPaymentId' => $downPayment->id,
			'downPaymentAmountFormatted' => $downPayment->getDownPaymentAmountFormatted(),
			'downPaymentAmount' => (float) $downPaymentAmount,
			'urls' => [
				'store' => route('store.down.payment.settlement', ['company' => $company->id, 'downPaymentId' => $downPaymentId, 'partnerId' => $partnerId, 'modelType' => $modelType]),
				'back' => route('view.contracts.down.payments', ['company' => $company->id, 'partnerId' => $partnerId, 'modelType' => $modelType, 'currency' => $contractCurrency]),
			],
		]);
	}
	
	
	

// ==========================================
// Controller Method - محدّث
// ==========================================
public function storeDownPaymentSettlement(
    StoreDownPaymentSettlementRequest $request, 
    Company $company, 
    int $downPaymentId, 
    int $partnerId, 
    string $modelType
)
{
	
	
	
	
    $fullClassName = 'App\Models\\' . $modelType;
    $downPaymentModelName = $fullClassName::MONEY_MODEL_NAME;
    $isMoneyReceived = $modelType == 'CustomerInvoice'; // true = customer, false = supplier
    $downPaymentModelFullName = 'App\Models\\' . $downPaymentModelName;   
    $downPayment = $downPaymentModelFullName::find($downPaymentId);
    
    $downPayment->update([
        'down_payment_settlement_date' => $request->get('settlement_date')
    ]);
	$fetch = null ;
	if($company->hasOdooIntegrationCredentials() ){
		$fetch = (new OdooPayment($company));
	}
	$currency = $downPayment->currency;
	// $currency = $isMoneyReceived ? $downPayment->getReceivingCurrency() :  $downPayment->getPaymentCurrency();
	 $odooCurrencyId =Currency::getOdooId($currency);
   $exchangeRate = $downPayment->getExchangeRate();

    $settlements = $downPayment->settlements;
    $isFromDownPayment = 0;
    
    if ($downPayment->isInvoiceSettlementWithDownPayment()) {
        $settlements = $downPayment->settlementsForDownPaymentThatComeFromMoneyModel;
        $isFromDownPayment = 1;
    }
    
    $settlements->each(function($settlement) use($fetch) {
		if($settlement->odoo_move_id){
			$fetch->unlink($settlement->odoo_move_id);
		}
         $settlement->delete();
    });
    
    $syncWithOdoo = false;
	/**
	 * @var MoneyPayment|MoneyReceived $downPayment
	 */
    $totalWithholdAmountAndSettlements = $downPayment->storeNewSettlement(
        $request->get('settlements', []),
        $downPayment->getPartnerId(),
        $company,
        $isFromDownPayment,
        $syncWithOdoo
    );

    $odooMoveId = $downPayment->odoo_move_id ?: $downPayment->journal_entry_id;
    
    if ($company->hasOdooIntegrationCredentials() && $odooMoveId) {
        
        $settlements = $totalWithholdAmountAndSettlements['settlements'];
        
        $invoiceMatches = [];
        
        foreach ($settlements as $settlement) {
            $amountInCurrency = $settlement->getAmount();
            $invoiceId = $settlement->invoice_id;
            $invoice = $isMoneyReceived 
                ? CustomerInvoice::find($invoiceId) 
                : SupplierInvoice::find($invoiceId);
            
            $invoiceMatches[] = [
                'amount' => $amountInCurrency,
                'invoice_id' => $invoice->odoo_id,
//				'move_id'=>$settlement->odoo_move_id,
				'settlement'=>$settlement
            ];
        }
        
        $partner = Partner::find($partnerId);
        $odooPartnerId = $partner->getOdooId();
        
        // استخدام المتغير $isMoneyReceived لتحديد إذا كان عميل أو مورد
        $result = $fetch->settleAdvanceWithInvoices(
			$odooCurrencyId,
			$exchangeRate,
            $odooMoveId,
            $invoiceMatches,
            $odooPartnerId,
            $isMoneyReceived  // true للعملاء، false للموردين
        );
        
        if (!$result['success']) {
            // Log::error('Odoo advance settlement failed', [
            //     'down_payment_id' => $odooMoveId,
            //     'is_customer' => $isMoneyReceived,
            //     'error' => $result['message']
            // ]);
			return back()->withErrors(['odoo' => 'Odoo settlement failed: ' . $result['message']])->withInput();
        }
    }
    
	return redirect()->route('view.contracts.down.payments', [
		'company' => $company->id,
		'partnerId' => $partnerId,
		'modelType' => $modelType,
		'currency' => $downPayment->getCurrency()
	])->with('success', __('Data Store Successfully'));
}



}
