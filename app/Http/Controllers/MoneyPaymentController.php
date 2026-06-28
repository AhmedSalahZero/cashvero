<?php
namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Http\Requests\DeleteMoneyPaymentRequest;
use App\Http\Requests\MarkChequeAsPaidRequest;
use App\Http\Requests\StoreMoneyPaymentRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CashVeroBranch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\PayableCheque;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Services\Api\OdooPayment;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MoneyPaymentController
{
    use GeneralFunctions;
// 	protected function applyFilter(Request $request, $query)
// {
//     $searchFieldName = $request->get('field');
//     $from = $request->get('from');
//     $to = $request->get('to');
//     $value = $request->query('value');

//     // تحديد اسم الحقل للتاريخ
//     $dateFieldName = ($searchFieldName === 'due_date') ? 'due_date' : 'delivery_date';
//     $query->when($value, function ($q) use ($value, $searchFieldName) {
//         $q->where(function($subQuery) use ($value, $searchFieldName) {
//             $subQuery->where($searchFieldName, 'LIKE', "%{$value}%")
//                      ->orWhereHas('payableCheque', function($relationQ) use ($value, $searchFieldName) {
//                          $relationQ->where($searchFieldName, 'LIKE', "%{$value}%");
//                      });
//         });
//     });

//     $query->when($from, function ($q) use ($dateFieldName, $from) {
//         $q->where($dateFieldName, '>=', $from);
//     });

//     $query->when($to, function ($q) use ($dateFieldName, $to) {
//         $q->where($dateFieldName, '<=', $to);
//     });

//     return $query->orderByDesc('delivery_date');
// }

    // protected function applyFilter2(Request $request,  $collection):Collection
    // {
    //     if (!count($collection)) {
    //         return $collection;
    //     }
    //     $searchFieldName = $request->get('field');
    //     $dateFieldName = $searchFieldName === 'due_date' ? 'due_date' : 'delivery_date';
    //     if ($searchFieldName =='delivery_date') {
    //         $dateFieldName = 'delivery_date';
    //     }
    //     $from = $request->get('from');
    //     $to = $request->get('to');
    //     $value = $request->query('value');
    //     $collection = $collection
    //     ->when($request->has('value'), function ($collection) use ($request, $value, $searchFieldName) {
    //         return $collection->filter(function ($moneyPayment) use ($value, $searchFieldName) {
    //             /**
    //              * @var MoneyPayment $moneyPayment
    //              */
    //             $currentValue = $moneyPayment->{$searchFieldName} ;
    //             // $moneyPaymentRelationName cash-in-safe -> cashInSafe relation ship name
    //             $moneyPaymentRelationName = dashesToCamelCase(Request('active')) ;
    //             $relationRecord = $moneyPayment->$moneyPaymentRelationName ;
    //             /**
    //              * * بمعني لو مالقناش القيمة في جدول ال
    //              * * moneyPayment
    //              * * هندور عليها في العلاقه
    //              */
    //             $currentValue = is_null($currentValue) && $relationRecord ? $relationRecord->{$searchFieldName}  :$currentValue ;
    //             if ($searchFieldName == 'delivery_branch_id') {
    //                 $currentValue = $moneyPayment->getCashPaymentBranchName() ;
    //             }
    //             if ($searchFieldName == 'delivery_bank_id') {
    //                 $currentValue = $moneyPayment->payableCheque ? $moneyPayment->payableCheque->getDeliveryBankName() :0 ;
    //             }
    //             return false !== stristr($currentValue, $value);
    //         });
    //     })
    //     ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
    //         return $collection->where($dateFieldName, '>=', $from);
    //     })
    //     ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
    //         return $collection->where($dateFieldName, '<=', $to);
    //     })
    //     ->sortByDesc('delivery_date')->values();
    //     return $collection;
    // }
    public function index(Company $company, Request $request)
    {
		$paginationPerPage = GeneralFunctions::getPaginationLimit();
		$suppliersFormatted = Partner::getSuppliersForCompanyFormattedForSelect($company);
      //  $company->load(['moneyPayments.payableCheque','moneyPayments.partner','moneyPayments.outgoingTransfer','moneyPayments.cashPayment.deliveryBranch']);
        $numberOfMonthsBetweenEndDateAndStartDate = 18 ;
        $activeTab = $request->get('active', MoneyPayment::CASH_PAYMENT) ;
        $filterDates = [];
        foreach (MoneyPayment::getAllTypes() as $type) {
            $startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
            $endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');

            $filterDates[$type] = [
                'startDate'=>$startDate,
                'endDate'=>$endDate
            ];
        }
        // cash
        $cashPaymentsStartDate = $filterDates[MoneyPayment::CASH_PAYMENT]['startDate'] ?? null ;
        $cashPaymentsEndDate = $filterDates[MoneyPayment::CASH_PAYMENT]['endDate'] ?? null ;


        // outgoing transfer
        $outgoingTransferStartDate = $filterDates[MoneyPayment::OUTGOING_TRANSFER]['startDate'] ?? null ;
        $outgoingTransferEndDate = $filterDates[MoneyPayment::OUTGOING_TRANSFER]['endDate'] ?? null ;

        /**
         * * cheques in safe
         */
        $payableChequesStartDate = $filterDates[MoneyPayment::PAYABLE_CHEQUE]['startDate'] ?? null ;
        $payableChequesEndDate = $filterDates[MoneyPayment::PAYABLE_CHEQUE]['endDate'] ?? null ;
        

    
        $cashPayments = $company->getMoneyPaymentCashPayments($cashPaymentsStartDate, $cashPaymentsEndDate,$activeTab  )->paginate($paginationPerPage,['*'],'cashPaymentsPage') ;
        $outgoingTransfer = $company->getMoneyPaymentOutgoingTransfer($outgoingTransferStartDate, $outgoingTransferEndDate,$activeTab)->paginate($paginationPerPage,['*'],'outgoingTransferPage') ;
        $payableCheques = $company->getMoneyPaymentPayableCheques($payableChequesStartDate, $payableChequesEndDate,$activeTab)->paginate($paginationPerPage,['*'],'payableChequesPage');
        
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        
        $accountTypes = AccountType::onlyCashAccounts()->get();
        // $cashPayments = $activeTab == MoneyPayment::CASH_PAYMENT ? $this->applyFilter ($request, $cashPayments) :$cashPayments  ;
		// $cashPayments = $cashPayments->paginate($paginationPerPage,['*'],'cashPaymentsPage');
        // $outgoingTransfer = $activeTab === MoneyPayment::OUTGOING_TRANSFER ? $this->applyFilter($request, $outgoingTransfer) : $outgoingTransfer  ;
        // $outgoingTransfer = $outgoingTransfer->paginate($paginationPerPage,['*'],'outgoingTransferPage');
        // $payableCheques = $activeTab == MoneyPayment::PAYABLE_CHEQUE ? $this->applyFilter($request, $payableCheques) : $payableCheques;
		// $payableCheques = $payableCheques->paginate($paginationPerPage,['*'],'payableChequesPage');

        $payableChequesTableSearchFields = [
            'partner_name'=>__('Supplier Name'),
            'delivery_date'=>__('Payment Date'),
            'cheque_number'=>__('Cheque Number'),
            'currency'=>__('Currency'),
            'payment_currency'=>__('Payment Currency'),
            'payment_bank_name'=>__('Payment Bank'),
            'due_date'=>__('Due Date'),
         //   'cheque_status'=>__('Status')
        ];


        

        $outgoingTransferTableSearchFields = [
            'partner_name'=>__('Supplier Name'),
            'delivery_date'=>__('Payment Date'),
            'payment_bank_name'=>__('Payment Bank'),
            // 'paid_amount'=>__('Transfer Amount'),
            'currency'=>__('Currency'),
			'payment_currency'=>__('Payment Currency'),			
            'account_number'=>__('Account Number')
        ];

        $payableCashTableSearchFields = [
            'partner_name'=>__('Supplier Name'),
            'delivery_date'=>__('Payment Date'),
            'delivery_branch_name'=>__('Branch'),
            // 'paid_amount'=>__('Paid Amount'),
            'currency'=>__('Currency'),
			'payment_currency'=>__('Payment Currency'),
            'receipt_number'=>__('Receipt Number')
        ];

        return view('reports.moneyPayments.index', [
            'company'=>$company ,
			'suppliersFormatted'=>$suppliersFormatted,
            'payableCheques'=>$payableCheques,
            'cashPayments'=>$cashPayments,
            'payableChequesTableSearchFields'=>$payableChequesTableSearchFields,
            'outgoingTransfer'=>$outgoingTransfer,
            'payableCashTableSearchFields'=>$payableCashTableSearchFields,
            'outgoingTransferTableSearchFields'=>$outgoingTransferTableSearchFields,
            'financialInstitutionBanks'=>$financialInstitutionBanks,
            'accountTypes'=>$accountTypes,
            'filterDates'=>$filterDates,
        ]);
    }

    public function create(Company $company, $supplierInvoiceId = null)
    {
        $clientsWithContracts = Partner::orderBy('name')->onlyCompany($company->id)	->onlyCustomers()->onlyThatHaveContracts()->get();
        $currencies = SupplierInvoice::getCurrencies();
        $isDownPayment = Request()->has('type');
        $viewName = $isDownPayment  ?  'reports.moneyPayments.down-payments-form' : 'reports.moneyPayments.form';
        
        $accountTypes = AccountType::onlyCashAccounts()->get();
        $selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $selectedCurrency = $supplierInvoiceId ? SupplierInvoice::where('id', $supplierInvoiceId)->first()->getCurrency() : null;

        $invoiceNumber = $supplierInvoiceId ? SupplierInvoice::where('id', $supplierInvoiceId)->first()->getInvoiceNumber():null;
        /**
         * * for contracts
         */
        $suppliers =  $supplierInvoiceId ?  Partner::orderBy('name')->where('id', SupplierInvoice::find($supplierInvoiceId)->supplier_id)
        ->when($isDownPayment, function (Builder $q) {
            $q->has('contracts');
        })
        ->where('company_id', $company->id)->pluck('name', 'id')->toArray() :Partner::orderBy('name')->where('is_supplier', 1)->where('company_id', $company->id)
        ->when($isDownPayment, function (Builder $q) {
            $q->has('contracts');
        })
        ->pluck('name', 'id')->toArray();
    
        $contracts = [];
        
        return view($viewName, [
            'financialInstitutionBanks'=>$financialInstitutionBanks,
            'selectedBranches'=>$selectedBranches,
            'singleModel'=>$supplierInvoiceId,
            'invoiceNumber'=>$invoiceNumber,
            'currencies'=>$currencies,
            'accountTypes'=>$accountTypes,
            'suppliers'=>$suppliers,
            'contracts'=>$contracts,
            'clientsWithContracts'=>$clientsWithContracts,
            'selectedCurrency'=>$selectedCurrency,
			
        ]);
    }

    public function result(Company $company, Request $request)
    {

        return view('reports.moneyPayments.form', [
        ]);
    }
    public function getContractsForSupplier(Company $company, Request $request)
    {
        $contracts = Contract::where('partner_id', $request->get('supplierId'))
        ->where('model_type', 'Supplier')
        ->where('currency', $request->get('currency'))->pluck('name', 'id')->toArray();
        return response()->json([
            'status'=>true ,
            'contracts'=>$contracts
        ]);
    }
    public function getSalesOrdersForContract(Company $company, Request $request, $contractId  = 0, ?string $selectedCurrency=null)
    {
        $downPaymentId = $request->get('down_payment_id');
        $moneyPayment = MoneyPayment::find($downPaymentId);
        $purchaseOrders = PurchaseOrder::where('contract_id', $contractId)->get();
        $formattedSalesOrders = [];
        foreach ($purchaseOrders as $index=>$purchaseOrder) {
            $paidAmount = $moneyPayment ? $moneyPayment->downPaymentSettlements->where('purchase_order_id', $purchaseOrder->id)->first() : null ;
            $formattedSalesOrders[$index]['paid_amount'] = $paidAmount && $paidAmount->down_payment_amount ? $paidAmount->down_payment_amount : 0;
            $formattedSalesOrders[$index]['po_number'] = $purchaseOrder->po_number;
            $formattedSalesOrders[$index]['amount'] = $purchaseOrder->getAmount();
            $formattedSalesOrders[$index]['id'] = $purchaseOrder->id;
        }
        if (!count($purchaseOrders)) {
            $index = 0;
            $downPaymentSettlement = $moneyPayment ? $moneyPayment->downPaymentSettlements->where('contract_id', null)->first() : null ;
            $formattedSalesOrders[$index]['paid_amount'] = $downPaymentSettlement && $downPaymentSettlement->down_payment_amount ? $downPaymentSettlement->down_payment_amount : 0;
            $formattedSalesOrders[$index]['po_number'] = 'General';
            $formattedSalesOrders[$index]['amount'] =0;
            $formattedSalesOrders[$index]['id'] = -1;
        }
        return response()->json([
            'status'=>true ,
            'purchases_orders'=>$formattedSalesOrders,
            'selectedCurrency'=>$selectedCurrency
        ]);

    }
    public function getInvoiceNumber(Company $company, Request $request, int $supplierInvoiceId, ?string $selectedCurrency=null)
    {
        $inEditMode = $request->get('inEditMode');
        $moneyPaymentId = $request->get('money_payment_id');
        $moneyPayment = MoneyPayment::find($moneyPaymentId);
        $partner = Partner::find($supplierInvoiceId);
        $downPaymentContract = Contract::find($request->get('downPaymentContractId'));
        if (!$partner) {
            return response()->json([
                'status'=>true ,
                'invoices'=>[],
                'currencies'=>[],
                'selectedCurrency'=>[],
                'clientsWithContracts'=>[]
            ]);
        }
        $partnerId = $partner->id ;
        
        $invoices = SupplierInvoice::where('supplier_id', $partnerId)->where('company_id', $company->id)
        ->where('net_invoice_amount', '>', 0)
        // ->whereNull('opening_balance_id')
        ->when($downPaymentContract, function ($q) use ($downPaymentContract) {
            $q->where('contract_code', $downPaymentContract->getCode());
        });
        if (!$inEditMode) {
            $invoices->where('net_balance', '>', 0);
        }
        $allCurrencies =$invoices->where('company_id', $company->id)->pluck('currency', 'currency')->mapWithKeys(function ($value, $key) {
            return [
                $key=>$value
            ];
        });
        if ($selectedCurrency) {
            $invoices = $invoices->where('currency', '=', $selectedCurrency);
        }
        $invoices = $invoices->orderBy('invoice_date', 'asc')
        ->get(['id','invoice_number','invoice_date','invoice_due_date','net_invoice_amount','total_paid_amount','net_balance','currency'])
        ->toArray();


        foreach ($invoices as $index=>$invoiceArr) {
            $invoices[$index]['settlement_amount'] = $moneyPayment ? $moneyPayment->sumSettlementsForInvoice($invoiceArr['id'], $partnerId, 0) : 0;
            $invoices[$index]['withhold_amount'] = $moneyPayment ? $moneyPayment->sumWithholdAmountForInvoice($invoiceArr['id'], $partnerId, 0) : 0;
        }

        $invoices = $this->formatInvoices($invoices, $inEditMode, $moneyPayment);
        $clientsWithContracts = Partner::onlyCompany($company->id)	->onlyCustomers()->onlyThatHaveContracts()->pluck('name', 'id')->toArray();
        
        return response()->json([
            'status'=>true ,
            'invoices'=>$invoices,
            'currencies'=>$allCurrencies,
            'selectedCurrency'=>$selectedCurrency,
            'clientsWithContracts'=>$clientsWithContracts
        ]);

    }
    protected function formatInvoices(array $invoices, int $inEditMode, $moneyPayment)
    {
        return SupplierInvoice::formatInvoices($invoices, $inEditMode, $moneyPayment);
    }

    public function store(
        Company $company,
        StoreMoneyPaymentRequest $request,
        $returnModel = false
        // ,$accountNumberHasChanged = false
    ) {
        $hasUnappliedAmount = (bool)$request->get('unapplied_amount');
        $partnerType = $request->get('partner_type', 'is_supplier');
        $moneyType = $request->get('type');
		
        $isGeneralDownPaymentOrSettlementOpening = $request->get('down_payment_type') == MoneyPayment::DOWN_PAYMENT_GENERAL || $request->get('down_payment_type') == MoneyPayment::SETTLEMENT_OF_OPENING_BALANCE;
		$isDownPaymentOverContract = $request->get('down_payment_type') == MoneyPayment::DOWN_PAYMENT_OVER_CONTRACT;
        $financialInstitutionId = null;
        $contractId = $request->get('contract_id');
        $contractId = is_numeric($contractId) ? $contractId : null;
        $partnerId = $request->get('supplier_id');
        $supplier = Partner::find($partnerId);
        $supplierId = $supplier->id;
        $paymentBranchName = $request->get('delivery_branch_id') ;
        $data = $request->only(['type','delivery_date','currency','payment_currency','down_payment_type','partner_type','user_comment','transaction_type','account_bank_statement_line_id','journal_entry_id']);
        // $isSupplier = $partnerType == 'is_supplier';
        $data['currency'] = $isGeneralDownPaymentOrSettlementOpening   ? $data['payment_currency'] : $data['currency']??null;
        $paymentCurrency = $data['payment_currency'];
        $data['currency'] = is_null($data['currency']) ?  $paymentCurrency : $data['currency'];
        $currencyName = $data['currency'];
        
        $data['partner_id'] = $supplierId;
        $data['user_id'] = auth()->user()->id ;
        $data['company_id'] = $company->id ;
        $isDownPayment =  $request->get('is_down_payment') && $request->has('purchases_orders_amounts');
        $isDownPaymentFromMoneyPayment = $request->get('unapplied_amount', 0) > 0 && !$request->get('is_down_payment') && $moneyType == 'is_supplier'  ;
        $data['money_type'] =  !$isDownPayment ? 'money-payment' : 'down-payment';
        $data['money_type'] = $isDownPaymentFromMoneyPayment ? MoneyPayment::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT : $data['money_type'];
        $currency = $data['currency'] ;
        $hasUnappliedOrIsDownPayment = $hasUnappliedAmount || $isDownPayment;
        $data['has_unapplied_or_down_payment'] = $hasUnappliedOrIsDownPayment;
        $relationData = [];
        $relationName = null ;
        $isTheSameCurrency = $currency == $paymentCurrency ;
		$date = $data['delivery_date'];
        $date = Carbon::make($date)->format('Y-m-d');
		
		
      
		$mainFunctionalCurrency= $company->getMainFunctionalCurrency();
       
        $amountInPaymentCurrency = $request->input('paid_amount.'.$moneyType, 0) ;
        $amountInPaymentCurrency = unformat_number($amountInPaymentCurrency);
        
        $invoiceCurrencyAmount =  $isTheSameCurrency ? $amountInPaymentCurrency  : HArr::sumFormattedArr(array_column($request->get('settlements', []), 'settlement_amount'))  ;
		if(!$isTheSameCurrency && !$request->has('settlements') && $request->has('amount_in_invoice_currency')){
			$invoiceCurrencyAmount = $request->input('amount_in_invoice_currency.'.$moneyType);
		}
        if ($moneyType == MoneyPayment::CASH_PAYMENT) {
            $relationData = $request->only(['receipt_number']) ;
            $relationData['delivery_branch_id'] = $this->generateBranchId($paymentBranchName, $company->id) ;
            $relationData['company_id'] = $company->id ;
            $relationName = 'cashPayment';
        } elseif ($moneyType ==MoneyPayment::OUTGOING_TRANSFER) {
            $relationName = 'outgoingTransfer';
            $financialInstitutionId = $request->input('delivery_bank_id.'.MoneyPayment::OUTGOING_TRANSFER) ;
            $relationData = [
                'delivery_bank_id'=>$financialInstitutionId,
                'actual_payment_date'=>$data['delivery_date'],
                'account_number'=>$request->input('account_number.'.MoneyPayment::OUTGOING_TRANSFER),
                'account_type'=>$request->input('account_type.'.MoneyPayment::OUTGOING_TRANSFER)
            ];
        } elseif ($moneyType ==MoneyPayment::PAYABLE_CHEQUE) {
            $relationName = 'payableCheque';
            $financialInstitutionId = $request->input('delivery_bank_id.'.MoneyPayment::PAYABLE_CHEQUE) ;
            $dueDate = $request->input('due_date') ;
			$date = Carbon::make($dueDate)->format('Y-m-d');
            $relationData = [
                'due_date'=>$dueDate ,
                'actual_payment_date'=>$dueDate,
                'cheque_number'=>$request->input('cheque_number'),
                'delivery_bank_id'=>$financialInstitutionId,
                'account_number'=>$request->input('account_number.'.MoneyPayment::PAYABLE_CHEQUE),
                'account_type'=>$request->input('account_type.'.MoneyPayment::PAYABLE_CHEQUE),
                'company_id'=>$company->id
            ];
        }
    
        if ($partnerType && $partnerType != 'is_supplier') {
            $data['paid_amount'] = $request->input('paid_amount.'.$moneyType, 0);
        }
        $deliveryBank = FinancialInstitution::find($financialInstitutionId);
        $deliveryBankName = $deliveryBank ? $deliveryBank->getName() : null;
        $bankNameOrBranchName =  $moneyType == MoneyPayment::CASH_PAYMENT ? Branch::find($relationData['delivery_branch_id'])->getName() : $deliveryBankName ;
        $data['paid_amount'] =$amountInPaymentCurrency ;
        $data['amount_in_invoice_currency'] = $invoiceCurrencyAmount ;
		 $exchangeRate = $currencyName == $paymentCurrency ? 1 : number_unformat($request->input('exchange_rate.'.$moneyType, 1)) ;
		 $exchangeRate = $isGeneralDownPaymentOrSettlementOpening  ? ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currency, $mainFunctionalCurrency, $date, $company->id) :$exchangeRate;
		 if($isDownPaymentOverContract && $paymentCurrency == $mainFunctionalCurrency){
				$exchangeRate = $request->input('exchange_rate.'.$moneyType, 1);
		 }
		 if($isDownPaymentOverContract && $paymentCurrency != $mainFunctionalCurrency){
				$exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($paymentCurrency, $mainFunctionalCurrency, $date, $company->id);
		 }

		 
        $data['exchange_rate'] =$exchangeRate ;
        
        //	$data['money_type'] = $isDownPayment ? 'down-payment' : 'money-payment' ;
        $data['contract_id'] = $contractId ;
        // $data['money_payment_id'] = $moneyPaymentId;

    
        if (!$isDownPayment && !$isDownPaymentFromMoneyPayment) {
            unset($data['contract_id']);
        }
		/**
		 * @var MoneyPayment $moneyPayment
		 */
        $moneyPayment = MoneyPayment::create($data);

        $relationData['company_id'] = $company->id ;
	
        $moneyPayment->$relationName()->create($relationData);
        $moneyPayment = $moneyPayment->refresh();
         
        $statementDate = $moneyPayment->getStatementDate();
        $accountType = AccountType::find($request->input('account_type.'.$moneyType));
        $accountNumber = $request->input('account_number.'.$moneyType) ;
        $deliveryBranchId = $relationData['delivery_branch_id'] ?? null ;
        $moneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
        
        /**
         * * دي علشان لو كان مثلا
         * * employee
         * * بس في التعديل غيرها ل
         * * supplier
         * * يبقي لازم تحذف ال employee
         */
        // if($partnerType == 'is_supplier' && $moneyPayment->journal_entry_id && $moneyPayment->account_bank_statement_line_id){
        // 	$moneyPayment->unlinkNonCustomerOrSupplierOdooExpense();
        // }
        /**
         * * For Money Payment Only
         */
        $totalWithholdAmountAndSettlements = $moneyPayment->storeNewSettlement(
            $request->get('settlements', []),
            $partnerId,
            $company
        );
		$totalWithholdAmount = $totalWithholdAmountAndSettlements['total_withhold_amount'];
        $moneyPayment->update([
            'total_withhold_amount'=>$totalWithholdAmount
        ]);
        /**
         * * For Contract Only
         */
        
        $moneyPayment->storeNewAllocation($request->get('allocations', []));
        
        if ($hasUnappliedOrIsDownPayment) {
            $moneyPayment->storeNewPurchaseOrders($request->get('purchases_orders_amounts', []), $contractId, $supplierId, $company->id, $amountInPaymentCurrency);
            // if ($company->hasOdooIntegrationCredentials() && $partnerType == 'is_supplier') {
            //     $odooPaymentService = new OdooPayment($company);
            //     $odooPaymentService->createDownPayment($moneyPayment);
            // }
        }
		if (($partnerType && $partnerType != 'is_supplier') || ($isDownPayment || $isDownPaymentFromMoneyPayment )) {
            $moneyPayment->handlePartnerDebitStatement($partnerType, $partnerId, $moneyPayment->id, $company->id, $statementDate, $invoiceCurrencyAmount, $paymentCurrency, $bankNameOrBranchName, $accountType, $accountNumber);
            $moneyPayment->storeNonCustomerOrSupplierOdooExpense(($isDownPayment || $isDownPaymentFromMoneyPayment  ));
        }
	
        $activeTab = $moneyType;
        if ($returnModel) {
            return $moneyPayment;
        }
        if ($request->ajax()) {
            return response()->json([
                'redirectTo'=>route('view.money.payment', ['company'=>$company->id,'active'=>$activeTab])
            ]);
        }
        return redirect()->route('view.money.payment', ['company'=>$company->id,'active'=>$activeTab])->with('success', __('Data Store Successfully'));

    }
  
	
    public function edit(Company $company, Request $request, moneyPayment $moneyPayment, $supplierInvoiceId = null)
    {
		
        $clientsWithContracts = Partner::onlyCompany($company->id)	->onlyCustomers()->onlyThatHaveContracts()->get();
        $currencies = SupplierInvoice::getCurrencies();
        $selectedCurrency = $supplierInvoiceId ? SupplierInvoice::where('id', $supplierInvoiceId)->first()->getCurrency() : null;
        $isDownPayment = $moneyPayment->isDownPayment();
        $viewName = $isDownPayment  ?  'reports.moneyPayments.down-payments-form' : 'reports.moneyPayments.form';
		// if($isOpeningBalance){
			// $viewName = 're';
		// }
        $banks = Bank::pluck('view_name', 'id');
        $selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        $accountTypes = AccountType::onlyCashAccounts()->get();
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $partnerType = $moneyPayment->partner->getSupplierType();
		
		// $customerInvoice = CustomerInvoice::find($supplierInvoiceId);
        // $suppliers =  $supplierInvoiceId ?  Partner::orderBy('name')->where('id', $customerInvoice->supplier_id)->where('company_id', $company->id)->has('contracts')->pluck('name', 'id')->toArray() :Partner::where('is_supplier', 1)->where('company_id', $company->id)->has('contracts')->pluck('name', 'id')->toArray();
        /**
         * * for contracts
         */
		/**
		 * @var SupplierInvoice $supplierInvoice
		 */
		$supplierInvoice = SupplierInvoice::find($supplierInvoiceId);
		
        $suppliers =  $supplierInvoiceId ?  Partner::orderBy('name')->where('id', $supplierInvoice->supplier_id)
        ->when($isDownPayment, function (Builder $q) {
            $q->has('contracts');
        })
        ->where('company_id', $company->id)->pluck('name', 'id')->toArray() :Partner::orderBy('name')->where($partnerType, 1)->where('company_id', $company->id)
        ->when($isDownPayment, function (Builder $q) {
            $q->has('contracts');
        })
        ->pluck('name', 'id')->toArray();
        
        $contracts = Contract::where('company_id', $company->id)->get();
        $warningMessage = count($moneyPayment->settlementsForDownPaymentThatComeFromMoneyModel) ? __('Warning, please take care incase you changed the paid amount, the invoices settled using this down payment will be deleted'):null;
        
        return view($viewName, [
            'banks'=>$banks,
            'suppliers'=>$suppliers,
            'contracts'=>$contracts,
            // 'supplierInvoices'=>$supplierInvoices ,
            'selectedBranches'=>$selectedBranches,
            'accountTypes'=>$accountTypes,
            'financialInstitutionBanks'=>$financialInstitutionBanks,
            'model'=>$moneyPayment,
            'singleModel'=>$supplierInvoiceId,
            'currencies'=>$currencies,
            'clientsWithContracts'=>$clientsWithContracts ,
            'selectedCurrency'=>$selectedCurrency,
            'warningMessage'=>$warningMessage
        ]);

    }

    public function update(Company $company, StoreMoneyPaymentRequest $request, moneyPayment $moneyPayment)
    {
	
        $oldSettlementsForMoneyReceivedWithDownPayment  = $moneyPayment->settlementsForDownPaymentThatComeFromMoneyModel ;
        $newType = $request->get('type');
        $request->merge([
            'journal_entry_id'=>$moneyPayment->journal_entry_id,
            'account_bank_statement_line_id'=>$moneyPayment->account_bank_statement_line_id,
        ]);
        $moneyPayment->deleteRelations();
        $moneyPaidAmountHasChanged = $moneyPayment->getAmount() != $request->input('paid_amount.'.$newType);
        $moneyPayment->delete();
        $newMoneyPayment = $this->store($company, $request, true);
        if (!$moneyPaidAmountHasChanged) {
            $newMoneyPayment->storeNewSettlement(
                $oldSettlementsForMoneyReceivedWithDownPayment->toArray(),
                $newMoneyPayment->getPartnerId(),
                $company,
                1
            );
        }
        $activeTab = $newType;
        if ($request->ajax()) {
            return response()->json([
                'redirectTo'=>route('view.money.payment', ['company'=>$company->id,'active'=>$activeTab])
            ]);
        }
        return redirect()->route('view.money.payment', ['company'=>$company->id,'active'=>$activeTab])->with('success', __('Money Payment Has Been Updated Successfully'));
    }

    public function destroy(Company $company, MoneyPayment $moneyPayment, DeleteMoneyPaymentRequest $request)
    {
        
        $moneyPayment->deleteRelations();
        $activeTab = $moneyPayment->getType();
        $moneyPayment->delete();
        return redirect()->route('view.money.payment', ['company'=>$company->id,'active'=>$activeTab])->with('success', __('Money Payment Has Been Updated Successfully'));
    }
    protected function generateBranchId($nameOrId, $companyId)
    {
        $branch = Branch::where('id', $nameOrId)->first();
        if (!$branch) {
            $branch = Branch::create([
                'name'=>$nameOrId,
                'company_id'=>$companyId ,
                'created_by'=>auth()->user()->id
            ]);
        }
        return $branch->id ;
    }
    public function markChequesAsPaid(Company $company, MarkChequeAsPaidRequest $request)
    {
    
        
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration) {
            $OdooPaymentService = new OdooPayment($company);
        }
            
        $moneyPaymentIds = $request->get('cheques') ;
        $moneyPaymentIds = is_array($moneyPaymentIds) ? $moneyPaymentIds :  explode(',', $moneyPaymentIds);
        $data = $request->only(['actual_payment_date']);
        $actualPaymentDate = Carbon::make($request->get('actual_payment_date'))->format('Y-m-d');
        $data['status'] = PayableCheque::PAID;
        
        foreach ($moneyPaymentIds as $moneyPaymentId) {
            /**
             * @var MoneyPayment $moneyPayment
             */
            $moneyPayment = MoneyPayment::find($moneyPaymentId) ;
            $currentPaidAmount = $moneyPayment->getAmount();
            $balancesResultJsonResponse = ((new MoneyReceivedController())->updateNetBalanceBasedOnAccountNumber($request, $company, $moneyPayment->getPayableChequeAccountType(), $moneyPayment->getPayableChequeAccountNumber(), $moneyPayment->getPayableChequePaymentBankId(), $actualPaymentDate));
            $balance = $balancesResultJsonResponse->getData()->balance;
			$balance = $balance + $currentPaidAmount;
            $errMessage = __('Net Balance Less Than Paid Amount');
    
            if ($balance < $currentPaidAmount) {
                if ($request->ajax()) {
                    return response()->json([
                        'status'=>false ,
                        'msg'=>$errMessage = __('Net Balance Less Than Paid Amount'),
                        'pageLink'=>route('view.money.payment', ['company'=>$company->id,'active'=>MoneyPayment::PAYABLE_CHEQUE])
                    ]);
                }
            
                return redirect()->back()->with('fail', $errMessage);
            }
            // $chequeDueDate = $moneyPayment->payableCheque->due_date;
            $moneyPayment->payableCheque->update($data);
            $currentStatement = $moneyPayment->getCurrentStatement();
        
        
            if ($hasOdooIntegration && $company->withinIntegrationDate($actualPaymentDate)) {
				
				if($moneyPayment->isOpenBalance()){
					$moneyPayment->markOpeningPayableChequeAsPaidInOdoo(false);
				}else{
					$moneyPayment->markPayableChequeAsPaidInOdoo();
				}
            }
        
            if ($currentStatement) {
                $currentStatement->handleFullDateAfterDateEdit(Carbon::make($data['actual_payment_date'])->format('Y-m-d'), $currentStatement->debit, $currentStatement->credit);
            }

        }
        if ($request->ajax()) {
            return response()->json([
                'status'=>true ,
                'msg'=>__('Good'),
                'pageLink'=>route('view.money.payment', ['company'=>$company->id,'active'=>MoneyPayment::PAYABLE_CHEQUE])
            ]);
        }
        return redirect()->route('view.money.payment', ['company'=>$company->id,'active'=>MoneyPayment::PAYABLE_CHEQUE]);

    }

    public function getAccountNumbersForAccountType(Company $company, Request $request, string $accountType, ?string $selectedCurrency=null, ?int $financialInstitutionId = 0)
    {
        $accountType = AccountType::find($accountType);
        $accountNumberModel =  ('\App\Models\\'.$accountType->getModelName())::getAllAccountNumberForCurrency($company->id, $selectedCurrency, $financialInstitutionId);
        return response()->json([
            'status'=>true ,
            'data'=>$accountNumberModel
        ]);
    }
    
    public function getSuppliersBasedOnCurrency(Request $request, Company $company, string $currencyName)
    {
        return response()->json([
            'supplierInvoices'=>SupplierInvoice::orderBy('supplier_name')->where('currency', $currencyName)->where('company_id', $company->id)->pluck('supplier_id', 'supplier_name')
        ]);
    }
    public function getSuppliersWithOpeningBalance(Request $request, Company $company)
    {
        
        $type =$request->get('type') ;
        $partners = [];
        if ($type == 'over_contract') {
            $partners=  Partner::has('contracts')->where('is_supplier', 1)->orderBy('name')
                                    ->where('company_id', $company->id)->pluck('id', 'name');
        } elseif ($type == 'general') {
            $partners =  Partner::where('is_supplier', 1)->orderBy('name')
                                    ->where('company_id', $company->id)->pluck('id', 'name');
        } elseif ($type == 'settlement-of-opening-balance') {
            $partners = SupplierInvoice::orderBy('supplier_name')
            ->whereNotNull('opening_balance_id')
            ->where('company_id', $company->id)->pluck('supplier_id', 'supplier_name');
        }
        return response()->json([
            'invoices' => $partners
        ]);
    }
    public function getCashInSafeStatementEndBalance(Request $request, Company $company, ?int $branchId = null, ?string $currencyName = null, ?string $deliveryDate = null)
    {
        
        $branchId = $request->get('branchId', $branchId);
        if (is_null($deliveryDate) && $request->has('balanceDate')) {
            $deliveryDate = $request->get('balanceDate');
            if (is_null($deliveryDate)) {
                return response()->json([
                    'end_balance'=>0
                ]);
            }
            $deliveryDate = Carbon::make($deliveryDate)->format('Y-m-d');
        }
        $currencyName = $request->get('currencyName', $currencyName);
        $currencyName = is_null($currencyName) ? $request->get('currency') :$currencyName  ;
        $additionalAmountInEditMode = 0 ;
        // $additionalAmountInEditMode = number_unformat($request->get('additionalBalanceInEditMode',0));
        /**
         * @var Branch|null $branch
         */
        $branch = Branch::find($branchId);
        $model = null ;
        if ($request->has('modelId')) {
            $modelId = $request->get('modelId')  ;
            $modelType = $request->get('modelType');
            $model = ('App\Models\\'.$modelType)::find($modelId);
            $oldBranchId = null ;
            if ($model && method_exists($model, 'getBranchId')) {
                $oldBranchId = $model->getBranchId();
            } elseif ($model && method_exists($model, 'getFromBranchId')) {
                $oldBranchId = $model->getFromBranchId();
            }
            if ($branch && $oldBranchId && $oldBranchId == $branch->id) {
                $additionalAmountInEditMode =  $model->getAmount();
            }
        }
        $branches  = CashVeroBranch::where('company_id', $company->id)->where('currency', $currencyName)->orderBy('name')->pluck('id', 'name')->toArray();
        $endBalance = $branch ? $branch->getCurrentEndBalance($company->id, $currencyName, $deliveryDate) : 0;
        if (isset($model) && $model instanceof MoneyReceived) {
            $endBalance = $endBalance-$additionalAmountInEditMode ;
        } else {
            $endBalance = $endBalance+$additionalAmountInEditMode ;
        }
        return response()->json([
            'end_balance'=>$endBalance,
            'branches'=>$branches
        ]);
        
    }

	public function updateOpeningPayableCheque(Request $request , Company $company,MoneyPayment $moneyPayment  , PayableCheque $payableCheque)
	{
		$moneyPayment->update([
			'currency'=>$paymentCurrency = $request->get('currency'),
			'paid_amount'=>$amountInPaymentCurrency = number_unformat($request->get('paid_amount',0)),
		]);
		$payableCheque->update([
			'status'=>PayableCheque::PENDING,
			'supplier_id'=>$request->get('supplier_id'),
			'exchange_rate'=>$request->get('exchange_rate'),
			'due_date'=>$statementDate = Carbon::make($request->get('due_date'))->format('Y-m-d'),
			'cheque_number'=>$request->get('cheque_number'),
			'delivery_bank_id'=>$financialInstitutionId = $request->get('drawl_bank_id'),
			'account_type'=>$accountType = $request->get('account_type'),
			'account_number'=>$accountNumber = $request->get('account_number')
		]);
		$moneyType = $moneyPayment->getMoneyType();
		$deliveryBranchId = null;
		$currentStatement = $moneyPayment->getCurrentStatement();
		   if ($currentStatement) {
                /**
                 * ! Need To Change To Work With All Other Account Types
                 */
                $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
                $currentStatement->handleFullDateAfterDateEdit($statementDate, 0, $amountInPaymentCurrency, [
                    'financial_institution_account_id' =>  $financialInstitutionAccount->id
                ]);
            } else {
                $moneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
            }
			
			
			 if ($moneyPayment->account_bank_statement_line_id) {
            $OdooPaymentService = new OdooPayment($moneyPayment->company);
            $OdooPaymentService->unlinkBankCollection($moneyPayment->account_bank_statement_line_id);
        }
		
		return redirect()->back()->with('success',__('Done'));
	
	}
}
