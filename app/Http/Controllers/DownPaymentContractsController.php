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
	//	$currentType = $request->get('active',$contractsWithDownPayments);
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
		// $moneyModels = $fullMoneyModelName::whereIn('money_type',[
		// 	$fullMoneyModelName::DOWN_PAYMENT
		// 	,$fullMoneyModelName::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT
		// ])
		// ->where($moneyTableName.'.company_id',$company->id)
		// ->where($moneyTableName.'.partner_id',$partnerId)
		// ->where($moneyTableName.'.'.$receivingOrPaymentCurrencyColumnName,$currency)
		// ->leftJoin('contracts','contracts.id','=','contract_id')
		// ->where(function($q){
		// 	$q->where('contract_id','=',null)->orWhere('contracts.status','!=',Contract::FINISHED);
		// })
		// ->with('contract')
		// ->selectRaw($moneyTableName.'.*,contracts.id as contractId')
		// ->get();
		
		 $searchFields = [
			$contractsWithDownPayments=>[
				'name'=>__('Name'),
				'start_date'=>__('Start Date'),
				'end_Date'=>__('End Date'),
			],
		];
	
		$models = [
			$contractsWithDownPayments =>$moneyModels ,
		];
	

        return view('contracts-down-payment.index', [
			'company'=>$company,
			'modelType'=>$modelType,
			'moneyModelName'=>$moneyModelName,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'title'=>$partnerName . ' ' .__('Down Payment'),
			'tableTitle'=>__('Down Payment Table') ,
			// 'financialInstitution'=>$financialInstitution,
			'filterDates'=>$filterDates
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
		// $inEditMode = false ;
		$fullClassName = ('\App\Models\\' . $modelType) ;
        $clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME ;
        $clientNameColumnName = $fullClassName::CLIENT_NAME_COLUMN_NAME ;
		$customerNameText = (new $fullClassName)->getClientNameText();
        $jsFile = $fullClassName::JS_FILE ;
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
		// if(!$inEditMode){
			/**
			 * ! $inEditMode always returns false 
			 * * وبالتالي لو بتحاول تعدل مش هيجيب اللي اتقفلت خالص
			 */
		//	$invoices->where('net_balance','>',0);
		// }

		$invoices = $invoices->orderBy('invoice_date','asc')->get() ; 
		
		$downPaymentAmount =  $downPayment->getDownPaymentAmount();
		$isDownPaymentFromMoneyPayment = $downPayment->isInvoiceSettlementWithDownPayment();
		$hasProjectNameColumn = $fullClassName::hasProjectNameColumn();
		$clientName = (new $fullClassName)->getClientNameText();
		
		
		
		
		
		
		return view('contracts-down-payment.settlement_form',[
	//		'modelType'=>$downPaymentModelName,
			'modelType'=>$modelType,
		//	'customerNameText'=>$clientName,
			'hasProjectNameColumn'=>$hasProjectNameColumn,
			'invoices'=>$invoices ,
			'downPayment'=>$downPayment,
			'currencies'=>$currencies,
			'contract'=>$contract,
			'model'=>$downPayment,
			'company'=>$company,
			'jsFile'=>$jsFile,
			
			'customerNameText'=>$customerNameText,
			'customerNameColumnName'=>$clientNameColumnName,
			'customerIdColumnName'=>$clientIdColumnName,
			'partnerId'=>$partnerId ,
			'partnerName'=>$partnerName,
			'downPaymentAmount'=>$downPaymentAmount,
			'isDownPaymentFromMoneyPayment'=>$isDownPaymentFromMoneyPayment

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
			return response()->json([
				'success' => false,
				'message' => 'Odoo settlement failed: ' . $result['message']
			]);
        }
    }
    
	return response()->json([
		'success' => true,
		'redirectTo' => route('view.contracts.down.payments', [
			'company' => $company->id,
			'partnerId' => $partnerId,
			'modelType' => $modelType,
			'currency' => $downPayment->getCurrency()
		])
	]);
    // return redirect()->route('view.contracts.down.payments', [
    //     'company' => $company->id,
    //     'partnerId' => $partnerId,
    //     'modelType' => $modelType,
    //     'currency' => $downPayment->getCurrency()
    // ]);
}



}
