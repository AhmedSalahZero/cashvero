<?php
namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Http\Requests\ApplyCollectionToChequeRequest;
use App\Http\Requests\BackToUnderCollectionChequeRequest;
use App\Http\Requests\DeleteMoneyReceivedRequest;
use App\Http\Requests\SendToUnderCollectionChequeRequest;
use App\Http\Requests\StoreMoneyReceivedRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\CustomerInvoice;
use App\Models\FinancialInstitution;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\SalesOrder;
use App\Services\Api\OdooPayment;
use App\Traits\GeneralFunctions;
use App\Traits\Models\HasBasicFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MoneyReceivedController
{
    use GeneralFunctions, HasBasicFilter;
   
    public function index(Company $company, Request $request)
    {
		$paginationPerPage = GeneralFunctions::getPaginationLimit();
        // $company->load(['moneyReceived.cheque','moneyReceived.partner','moneyReceived.incomingTransfer','moneyReceived.cashInSafe.receivingBranch']);
        $numberOfMonthsBetweenEndDateAndStartDate = 18 ;
        $activeTab = $request->get('active', MoneyReceived::CHEQUE) ;
        $filterDates = [];
        foreach (MoneyReceived::getAllTypes() as $type) {
            $startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
            $endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
            $filterDates[$type] = [
                'startDate'=>$startDate,
                'endDate'=>$endDate
            ];
        }
        // cash in safe
        $receivedCashesInSafeStartDate = $filterDates[MoneyReceived::CASH_IN_SAFE]['startDate'] ?? null ;
        $receivedCashesInSafeEndDate = $filterDates[MoneyReceived::CASH_IN_SAFE]['endDate'] ?? null ;
        
        // cashes in Bank
        $cashesInBankStartDate = $filterDates[MoneyReceived::CASH_IN_BANK]['startDate'] ?? null ;
        $cashesInBankEndDate = $filterDates[MoneyReceived::CASH_IN_BANK]['endDate'] ?? null ;
        // incoming transfer
        $incomingTransferStartDate = $filterDates[MoneyReceived::INCOMING_TRANSFER]['startDate'] ?? null ;
        $incomingTransferEndDate = $filterDates[MoneyReceived::INCOMING_TRANSFER]['endDate'] ?? null ;
            
        /**
         * * cheques in safe
         */
        $chequesInSafeStartDate = $filterDates[MoneyReceived::CHEQUE]['startDate'] ?? null ;
        $chequesInSafeEndDate = $filterDates[MoneyReceived::CHEQUE]['endDate'] ?? null ;
        
        /**
         * * rejected cheques
         */
        $chequesRejectedStartDate = $filterDates[MoneyReceived::CHEQUE_REJECTED]['startDate'] ?? null ;
        $chequesRejectedEndDate = $filterDates[MoneyReceived::CHEQUE_REJECTED]['endDate'] ?? null ;
        
        
        /**
         * *  cheques under collection
         */
        $chequesUnderCollectionStartDate = $filterDates[MoneyReceived::CHEQUE_UNDER_COLLECTION]['startDate'] ?? null ;
        $chequesUnderCollectionEndDate = $filterDates[MoneyReceived::CHEQUE_UNDER_COLLECTION]['endDate'] ?? null ;
        /**
         * *  cheques collection
         */
        $chequesCollectedStartDate = $filterDates[MoneyReceived::CHEQUE_COLLECTED]['startDate'] ?? null ;
        $chequesCollectedEndDate = $filterDates[MoneyReceived::CHEQUE_COLLECTED]['endDate'] ?? null ;
        
        
        
    
    
        
        $receivedCashesInSafe = $company->getReceivedCashesInSafe($receivedCashesInSafeStartDate, $receivedCashesInSafeEndDate , $activeTab)->paginate($paginationPerPage,['*'],'cash-in-safe-page') ;
		
		
        $receivedCashesInBanks = $company->getReceivedCashesInBank($cashesInBankStartDate, $cashesInBankEndDate,$activeTab)->paginate($paginationPerPage,['*'],'cash-in-bank-page') ;
        $receivedTransfer = $company->getReceivedTransfer($incomingTransferStartDate, $incomingTransferEndDate,$activeTab)->paginate($paginationPerPage,['*'],'incoming-transfer-page') ;
        $receivedChequesInSafe = $company->getReceivedChequesInSafe($chequesInSafeStartDate, $chequesInSafeEndDate, $activeTab)->paginate($paginationPerPage,['*'],'cheques-in-safe-page') ;
        $receivedRejectedChequesInSafe = $company->getReceivedRejectedChequesInSafe($chequesRejectedStartDate, $chequesRejectedEndDate,$activeTab)->paginate($paginationPerPage,['*'],'rejected-cheques-in-safe-page') ;
        $receivedChequesUnderCollection=  $company->getReceivedChequesUnderCollection($chequesUnderCollectionStartDate, $chequesUnderCollectionEndDate, $activeTab)->paginate($paginationPerPage,['*'],'cheques-under-collection-page') ;
        $collectedCheques=  $company->getCollectedCheques($chequesCollectedStartDate, $chequesCollectedEndDate,$activeTab)->paginate($paginationPerPage,['*'],'collected-cheques-page') ;
        
        
        
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        
        $accountTypes = AccountType::onlyCashAccounts()->get();
        // $receivedCashesInSafe = $activeTab == MoneyReceived::CASH_IN_SAFE ? $this->applyFilter($request, $receivedCashesInSafe) :$receivedCashesInSafe  ;
        // $receivedCashesInBanks = $activeTab == MoneyReceived::CASH_IN_BANK ? $this->applyFilter($request, $receivedCashesInBanks) :$receivedCashesInBanks  ;
        // $receivedTransfer = $activeTab === MoneyReceived::INCOMING_TRANSFER ? $this->applyFilter($request, $receivedTransfer) : $receivedTransfer  ;
        
    
        // $receivedChequesInSafe = $activeTab == MoneyReceived::CHEQUE ? $this->applyFilter($request, $receivedChequesInSafe) : $receivedChequesInSafe;
        
        
        // $receivedRejectedChequesInSafe = $activeTab == MoneyReceived::CHEQUE_REJECTED ? $this->applyFilter($request, $receivedRejectedChequesInSafe) : $receivedRejectedChequesInSafe;
        
        // $receivedChequesUnderCollection=  $activeTab == MoneyReceived::CHEQUE_UNDER_COLLECTION ? $this->applyFilter($request, $receivedChequesUnderCollection) : $receivedChequesUnderCollection ;
        
        // $collectedCheques=  $activeTab == MoneyReceived::CHEQUE_COLLECTED ? $this->applyFilter($request, $collectedCheques) : $collectedCheques ;
        
        
        $selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id) ;
        $chequesReceivedTableSearchFields = [
            'partner_name'=>__('Customer Name'),
            'receiving_date'=>__('Receiving Date'),
            'cheque_number'=>__('Cheque Number'),
            'currency'=>__('Currency'),
			'receiving_currency'=>__('Receiving Currency'),
            'drawee_bank_name'=>__('Drawee Bank'),
            'due_date'=>__('Due Date'),
            // 'cheque_status'=>__('Status')
        ];
        $chequesRejectedTableSearchFields = [
            'partner_name'=>__('Customer Name'),
            'receiving_date'=>__('Receiving Date'),
            'cheque_number'=>__('Cheque Number'),
            'currency'=>__('Currency'),
            'drawee_bank_name'=>__('Drawee Bank'),
            'due_date'=>__('Due Date'),
            // 'cheque_status'=>__('Status')
        ];
        $chequesUnderCollectionTableSearchFields = [
            'partner_name'=>__('Customer Name'),
            'cheque_number'=>__('Cheque Number'),
            'received_amount'=>__('Cheque Amount'),
            'deposit_date'=>__('Deposit Date'),
            'drawl_bank_name'=>__('Drawl Bank'),
            // 'account_type'=>__('Account Number'),
            'clearance_days'=>'Clearance Days'
        ];
        
        $collectedChequesTableSearchFields = [
            'partner_name'=>__('Customer Name'),
            'cheque_number'=>__('Cheque Number'),
            'received_amount'=>__('Cheque Amount'),
			'drawee_bank_name'=>__('Drawee Bank'),
			'due_date'=>__('Due Date'),
			'currency'=>__('Currency'),
			'receiving_currency'=>__('Receiving Currency'),
			'account_number'=>__('Account Number'),
        ];
        
        $incomingTransferTableSearchFields = [
            'partner_name'=>__('Customer Name'),
            'receiving_date'=>__('Receiving Date'),
            'receiving_bank_name'=>__('Receiving Bank'),
            'received_amount'=>__('Transfer Amount'),
            'currency'=>__('Currency'),
			'receiving_currency'=>__('Receiving Currency'),
            'account_number'=>__('Account Number')
        ];
        
        $cashInBankTableSearchFields = [
            'partner_name'=>__('Customer Name'),
            'receiving_date'=>__('Receiving Date'),
            'receiving_bank_name'=>__('Receiving Bank'),
            'received_amount'=>__('Deposit Amount'),
            'currency'=>__('Currency'),
			'receiving_currency'=>__('Receiving Currency'),
            'account_number'=>__('Account Number')
        ];
        
        $cashInSafeReceivedTableSearchFields = [
            'partner_name'=>__('Customer Name'),
            'receiving_date'=>__('Receiving Date'),
            'receiving_branch_name'=>__('Branch'),
            'received_amount'=>__('Received Amount'),
            'currency'=>__('Currency'),
			'receiving_currency'=>__('Receiving Currency'),
            'receipt_number'=>__('Receipt Number')
        ];
        
        
        
        
        $banks = Bank::pluck('view_name', 'id');
        return view('reports.moneyReceived.index', [
            'company'=>$company ,
            'selectedBanks'=>$selectedBanks,
            'receivedChequesInSafe'=>$receivedChequesInSafe,
            'receivedCashesInSafe'=>$receivedCashesInSafe,
            'chequesReceivedTableSearchFields'=>$chequesReceivedTableSearchFields,
            'receivedTransfer'=>$receivedTransfer,
            'receivedCashesInBanks'=>$receivedCashesInBanks,
            'banks'=>$banks,
            'receivedChequesUnderCollection'=>$receivedChequesUnderCollection,
            'chequesUnderCollectionTableSearchFields'=>$chequesUnderCollectionTableSearchFields ,
            'cashInSafeReceivedTableSearchFields'=>$cashInSafeReceivedTableSearchFields,
            'incomingTransferTableSearchFields'=>$incomingTransferTableSearchFields,
            'cashInBankTableSearchFields'=>$cashInBankTableSearchFields,
            'financialInstitutionBanks'=>$financialInstitutionBanks,
            'accountTypes'=>$accountTypes,
            'chequesRejectedTableSearchFields'=>$chequesRejectedTableSearchFields,
            'receivedRejectedChequesInSafe'=>$receivedRejectedChequesInSafe,
            'collectedCheques'=>$collectedCheques,
            'collectedChequesTableSearchFields'=>$collectedChequesTableSearchFields,
            'filterDates'=>$filterDates,

        ]);
    }
    
    public function create(Company $company, $customerInvoiceId = null)
    {
        $isDownPayment = Request()->has('type');
        $customerInvoiceCurrencies = CustomerInvoice::getCurrencies($customerInvoiceId);
        
        $viewName = $isDownPayment  ?  'reports.moneyReceived.down-payments-form' : 'reports.moneyReceived.form';
        $banks = Bank::pluck('view_name', 'id');
        $accountTypes = AccountType::onlyCashAccounts()->get();
        $selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        $selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id) ;
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $invoiceNumber = $customerInvoiceId ? CustomerInvoice::where('id', $customerInvoiceId)->first()->getInvoiceNumber():null;
        /**
         * * for contracts
         */
        $customers =  $customerInvoiceId ?  Partner::orderBy('name')->where('id', CustomerInvoice::find($customerInvoiceId)->customer_id)->where('company_id', $company->id)
        ->when($isDownPayment, function (Builder $q) {
            $q->has('contracts');
        })
        ->pluck('name', 'id')->toArray() : Partner::orderBy('name')->where('is_customer', 1)->where('company_id', $company->id)->when($isDownPayment, function (Builder $q) {
            $q->has('contracts');
        })->pluck('name', 'id')->toArray();
        $contracts = [];
        return view($viewName, [
            'financialInstitutionBanks'=>$financialInstitutionBanks,
            'customers'=>$customers ,
            'selectedBranches'=>$selectedBranches,
            'selectedBanks'=>$selectedBanks,
            'singleModel'=>$customerInvoiceId,
            'invoiceNumber'=>$invoiceNumber,
            'banks'=>$banks,
            'accountTypes'=>$accountTypes,
            'contracts'=>$contracts,
            'currencies'=>$customerInvoiceCurrencies
        ]);
    }
    
    public function result(Company $company, Request $request)
    {
        
        return view('reports.moneyReceived.form', [
        ]);
    }
    public function getContractsForCustomer(Company $company, Request $request)
    {
        $contracts = Contract::where('partner_id', $request->get('customerId'))
        ->where('model_type', 'Customer')
        ->where('currency', $request->get('currency'))->pluck('name', 'id')->toArray();
        return response()->json([
            'status'=>true ,
            'contracts'=>$contracts
        ]);
    }
    public function getContractsForCustomerWithStartAndEndDate(Company $company, Request $request)
    {

        $contracts = Contract::where('partner_id', $request->get('customerId'))
        ->whereDoesntHave('lendingInformationForAgainstAssignmentContract')
        ->where('currency', $request->get('currency'))
        ->where('model_type', 'Customer')
        ->get();
        return response()->json([
            'status'=>true ,
            'contracts'=>$contracts
        ]);
    }
    public function getSalesOrdersForContract(Company $company, Request $request, $contractId = 0, ?string $selectedCurrency=null)
    {
        $downPaymentId = $request->get('down_payment_id');
        $moneyReceived = MoneyReceived::find($downPaymentId);
        $salesOrders = SalesOrder::where('contract_id', $contractId)->get();
        $formattedSalesOrders = [];
        foreach ($salesOrders as $index=>$salesOrder) {
            /**
             * @var SalesOrder $salesOrder
             */
            $receivedAmount = $moneyReceived ? $moneyReceived->downPaymentSettlements->where('sales_order_id', $salesOrder->id)->first() : null ;
            $formattedSalesOrders[$index]['received_amount'] = $receivedAmount && $receivedAmount->down_payment_amount ? $receivedAmount->down_payment_amount : 0;
            $formattedSalesOrders[$index]['so_number'] = $salesOrder->so_number;
            $formattedSalesOrders[$index]['amount'] = $salesOrder->getAmount();
            $formattedSalesOrders[$index]['id'] = $salesOrder->id;
        }
        if (!count($salesOrders)) {
            $index = 0;
            $receivedAmount = $moneyReceived ? $moneyReceived->downPaymentSettlements->where('contract_id', null)->first() : null ;
            $formattedSalesOrders[$index]['received_amount'] = $receivedAmount && $receivedAmount->down_payment_amount ? $receivedAmount->down_payment_amount : 0;
            $formattedSalesOrders[$index]['so_number'] = 'General';
            $formattedSalesOrders[$index]['amount'] =0;
            $formattedSalesOrders[$index]['id'] = -1;
        }
        return response()->json([
            'status'=>true ,
            'sales_orders'=>$formattedSalesOrders,
            'selectedCurrency'=>$selectedCurrency
        ]);
        
    }
    public function getInvoiceNumber(Company $company, Request $request, int $customerId, ?string $selectedCurrency=null)
    {
        $inEditMode = $request->get('inEditMode');
        $moneyReceivedId = $request->get('money_received_id');
        
        $moneyReceived = MoneyReceived::find($moneyReceivedId);
        $partner = Partner::find($customerId);
        if (!$partner) {
            return response()->json([
                'status'=>true ,
                'invoices'=>[],
                'currencies'=>[],
                'selectedCurrency'=>[]
            ]);
        }
        $downPaymentContract = Contract::find($request->get('downPaymentContractId'));
        $partnerId = $partner->id;
        $invoices = CustomerInvoice::where('customer_id', $partnerId)
        ->where('company_id', $company->id)
    //	->whereNull('opening_balance_id')
        ->where('net_invoice_amount', '>', 0)
        ->when($downPaymentContract, function ($q) use ($downPaymentContract) {
            $q->where('contract_code', $downPaymentContract->getCode());
        });
        
        if (!$inEditMode) {
            $invoices->where('net_balance', '>', 0);
        }
        $contractsWithDownPaymentsCurrencies =$invoices->pluck('currency', 'currency')->mapWithKeys(function ($value, $key) {
            return [
                $key=>$value
            ];
        });

        if ($selectedCurrency) {
            $invoices = $invoices->where('currency', '=', $selectedCurrency);
        }

        $invoices = $invoices->orderBy('invoice_date', 'asc')
        ->get(['id','invoice_number','project_name','invoice_date','invoice_due_date','net_invoice_amount','total_collected_amount','net_balance','currency'])
        ->toArray();
        
        
        foreach ($invoices as $index=>$invoiceArr) {
            $invoices[$index]['settlement_amount'] = $moneyReceived ? $moneyReceived->sumSettlementsForInvoice($invoiceArr['id'], $partnerId, 0) : 0;
            $invoices[$index]['withhold_amount'] = $moneyReceived ? $moneyReceived->sumWithholdAmountForInvoice($invoiceArr['id'], $partnerId, 0) : 0;
        }

        $invoices = $this->formatInvoices($invoices, $inEditMode);
        return response()->json([
            'status'=>true ,
            'invoices'=>$invoices,
            'currencies'=>$contractsWithDownPaymentsCurrencies,
            'selectedCurrency'=>$selectedCurrency
        ]);
        
    }
    protected function formatInvoices(array $invoices, int $inEditMode)
    {
        return CustomerInvoice::formatInvoices($invoices, $inEditMode);
    }
    
    public function store(Company $company, StoreMoneyReceivedRequest $request, $returnModel = false, $accountNumberHasChanged=false)
    {
		
        $syncWithOdoo = !$request->has('stop-sync-with-odoo')  ;
        $hasUnappliedAmount = (bool)$request->get('unapplied_amount');
        $isGeneralDownPaymentOrSettlementOpening = $request->get('down_payment_type') == MoneyReceived::DOWN_PAYMENT_GENERAL || $request->get('down_payment_type') == MoneyReceived::SETTLEMENT_OF_OPENING_BALANCE;
		
        $partnerType = $request->get('partner_type', 'is_customer');
        $moneyType = $request->get('type');
        $financialInstitutionId = null;
        $contractId = $request->get('contract_id');
        $contractId = is_numeric($contractId) ? $contractId : null;
        $partnerId = $request->get('customer_id');
        $customer = Partner::find($partnerId);
        $customerId = $customer->id;
		$isDownPaymentOverContract = $request->get('down_payment_type') == MoneyReceived::DOWN_PAYMENT_OVER_CONTRACT;
        $receivedBankName = $request->get('receiving_branch_id') ;
        $data = $request->only(['type','receiving_date','currency','receiving_currency','customer_id','down_payment_type','partner_type','user_comment','transaction_type','journal_entry_id','account_bank_statement_line_id']);
        $data['currency'] = $isGeneralDownPaymentOrSettlementOpening ? $data['receiving_currency'] : $data['currency']??null;
        $receivingCurrency = $data['receiving_currency'];
        $data['currency'] = is_null($data['currency']) ?  $receivingCurrency : $data['currency'];
        $receivingDate = $data['receiving_date'];
		$receivingDate = Carbon::make($receivingDate)->format('Y-m-d');
		$date = $receivingDate;
        $currency = $data['currency'] ;
        $companyId = $company->id;
        $receivingCurrency = $data['receiving_currency'] ;
        $isDownPayment = $request->get('is_down_payment') && $request->has('sales_orders_amounts');
        $isDownPaymentFromMoneyReceived = $request->get('unapplied_amount', 0) > 0 && !$request->get('is_down_payment') && $moneyType =='is_customer';
        $data['money_type'] =  !$isDownPayment ? 'money-received' : 'down-payment';
        $data['money_type'] = $isDownPaymentFromMoneyReceived ? MoneyReceived::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT : $data['money_type'];
        $data['partner_id'] = $partnerId;
        $hasUnappliedOrIsDownPayment = $hasUnappliedAmount || $isDownPayment ;
        $data['user_id'] = auth()->user()->id ;
        $data['company_id'] = $company->id ;
        $data['has_unapplied_or_down_payment'] =$hasUnappliedOrIsDownPayment ;
        $draweeBankName = null;
        $relationData = [];
        $relationName = null ;
        $isTheSameCurrency = $currency == $receivingCurrency ;
        
    
        $amountInReceivingCurrency = $request->input('received_amount.'.$moneyType, 0) ;
        
        $amountInReceivingCurrency = unformat_number($amountInReceivingCurrency);
        $invoiceCurrencyAmount =  $isTheSameCurrency ? $amountInReceivingCurrency  : HArr::sumFormattedArr(array_column($request->get('settlements', []), 'settlement_amount'))  ;
		if(!$isTheSameCurrency && !$request->has('settlements') && $request->has('amount_in_invoice_currency')){
			$invoiceCurrencyAmount = $request->input('amount_in_invoice_currency.'.$moneyType);
		}
        if ($moneyType == MoneyReceived::CASH_IN_SAFE) {
            $relationData = $request->only(['receipt_number']) ;
            $relationData['receiving_branch_id'] = $this->generateBranchId($receivedBankName, $company->id) ;
            $relationName = 'cashInSafe';
        } elseif ($moneyType ==MoneyReceived::INCOMING_TRANSFER) {
            $relationName = 'incomingTransfer';
            $financialInstitutionId = $request->input('receiving_bank_id.'.MoneyReceived::INCOMING_TRANSFER);
            $relationData = [
                'receiving_bank_id'=>$financialInstitutionId,
                'account_number'=>$request->input('account_number.'.MoneyReceived::INCOMING_TRANSFER),
                'account_type'=>$request->input('account_type.'.MoneyReceived::INCOMING_TRANSFER)
            ];
        } elseif ($moneyType ==MoneyReceived::CASH_IN_BANK) {
            $relationName = 'cashInBank';
            $financialInstitutionId = $request->input('receiving_bank_id.'.MoneyReceived::CASH_IN_BANK) ;
            $relationData = [
                'receiving_bank_id'=>$financialInstitutionId,
                'account_number'=>$request->input('account_number.'.MoneyReceived::CASH_IN_BANK),
                'account_type'=>$request->input('account_type.'.MoneyReceived::CASH_IN_BANK)
            ];
        } elseif ($moneyType ==MoneyReceived::CHEQUE) {
            $relationName = 'cheque';
            $draweeBankId = $request->input('drawee_bank_id');
            $draweeBankName = Bank::find($draweeBankId)->getName();
			$dueDate = $request->input('due_date');
			$date= Carbon::make($dueDate);
            $relationData = [
                'due_date'=>$dueDate,
                'cheque_number'=>$request->input('cheque_number'),
                'drawee_bank_id'=>$draweeBankId,
                'branch_id'=>$request->input('cheque_branch_id')
            ];
        }
        $receivedBank = FinancialInstitution::find($financialInstitutionId);
        $receivedBankName = $receivedBank ? $receivedBank->getName() : $draweeBankName;
        $bankNameOrBranchName =  $moneyType == MoneyReceived::CASH_IN_SAFE ? Branch::find($relationData['receiving_branch_id'])->getName() : $receivedBankName ;
        $data['received_amount'] =$amountInReceivingCurrency ;
        $data['amount_in_invoice_currency'] = $invoiceCurrencyAmount ;
		$exchangeRate = $isTheSameCurrency ? 1 : number_unformat($request->input('exchange_rate.'.$moneyType, 1)) ;
		$mainFunctionalCurrency = $company->getMainFunctionalCurrency();
		 $exchangeRate = $isGeneralDownPaymentOrSettlementOpening ? ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currency, $mainFunctionalCurrency, $date, $company->id) :$exchangeRate;
		 if($isDownPaymentOverContract && $receivingCurrency == $mainFunctionalCurrency){
				$exchangeRate = $request->input('exchange_rate.'.$moneyType, 1);
		 }
		 if($isDownPaymentOverContract && $receivingCurrency != $mainFunctionalCurrency){
				$exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($receivingCurrency, $mainFunctionalCurrency, $date, $company->id);
		 }
		 
        $data['exchange_rate'] =$exchangeRate ;
        $data['contract_id'] = $contractId ;
        /**
         * @var AccountType $accountType ;
         */
        $accountType = AccountType::find($request->input('account_type.'.$moneyType));
        $accountNumber = $request->input('account_number.'.$moneyType);
        $receivingDate = Carbon::make($receivingDate)->format('Y-m-d');
        if (!$isDownPayment && !$isDownPaymentFromMoneyReceived) {
            unset($data['contract_id']);
        }
        $moneyReceived = MoneyReceived::create($data);
        

    
        
        $currency = $data['currency'] ?? null ;
        $receivingBranchId = $relationData['receiving_branch_id'] ?? null ;
        $relationData['company_id'] = $company->id ;
        $moneyReceived->$relationName()->create($relationData);
        /**
         * @var MoneyReceived $moneyReceived
         */
        $moneyReceived = $moneyReceived->refresh();
        $statementDate = $moneyReceived->getStatementDate();
        $moneyReceived->handleDebitStatement($financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInReceivingCurrency, $receivingCurrency, $receivingBranchId);
      
        
        /**
         * * For Money Received Only
         */
        
        $totalWithholdAmountAndSettlements = $moneyReceived->storeNewSettlement($request->get('settlements', []), $partnerId, $company, false, $syncWithOdoo);
        $totalWithholdAmount = $totalWithholdAmountAndSettlements['total_withhold_amount'];
        $moneyReceived->update([
            'total_withhold_amount'=>$totalWithholdAmount
        ]);
        
        /**
         * * For Contract Only
         */
        
     
        if ($hasUnappliedOrIsDownPayment) {
            $moneyReceived->storeNewSalesOrdersAmounts($request->get('sales_orders_amounts', []), $contractId, $customerId, $companyId, $amountInReceivingCurrency);
            // if ($company->hasOdooIntegrationCredentials() &&  $partnerType == 'is_customer') {
            //     $odooPaymentService = new OdooPayment($company);
            //     $odooPaymentService->createDownPayment($moneyReceived);
            // }
        }
		  if (($partnerType && $partnerType != 'is_customer') || ($isDownPayment || $isDownPaymentFromMoneyReceived)) {
            $moneyReceived->handlePartnerCreditStatement($partnerType, $partnerId, $moneyReceived->id, $company->id, $statementDate, $amountInReceivingCurrency, $receivingCurrency, $bankNameOrBranchName, $accountType, $accountNumber);
            $moneyReceived->storeNonCustomerOrSupplierOdooExpense(($isDownPayment || $isDownPaymentFromMoneyReceived));
        }
        

        $activeTab = $moneyType;
        if ($returnModel) {
            return $moneyReceived;
        }

        return response()->json([
            'redirectTo'=>route('view.money.receive', ['company'=>$company->id,'active'=>$activeTab])
        ]);

        
    }
 
    public function edit(Company $company, Request $request, MoneyReceived $moneyReceived, $customerInvoiceId = null)
    {
        
        $isDownPayment = $moneyReceived->isDownPayment();
        $partnerType = $moneyReceived->partner->getCustomerType();
    
        $customerInvoiceCurrencies = CustomerInvoice::getCurrencies($customerInvoiceId);
        
        
        $viewName = $isDownPayment  ?  'reports.moneyReceived.down-payments-form' : 'reports.moneyReceived.form';
        $banks = Bank::pluck('view_name', 'id');
        $selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id) ;
        $selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        $accountTypes = AccountType::onlyCashAccounts()->get();
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id) ;
        /**
         * * for contracts
         */
        $customers =  $customerInvoiceId ?  Partner::orderBy('name')->where('id', CustomerInvoice::find($customerInvoiceId)->customer_id)->where('company_id', $company->id)
        ->when($isDownPayment, function (Builder $q) {
            $q->has('contracts');
        })
        ->pluck('name', 'id')->toArray() : Partner::orderBy('name')->where($partnerType, 1)->where('company_id', $company->id)->when($isDownPayment, function (Builder $q) {
            $q->has('contracts');
        })->pluck('name', 'id')->toArray();
        
        $contracts = Contract::where('company_id', $company->id)->get();
        if ($moneyReceived->isChequeUnderCollection()) {
            return view('reports.moneyReceived.edit-cheque-under-collection', [
                'banks'=>$banks,
                // 'customerInvoices'=>$customerInvoices ,
                'selectedBranches'=>$selectedBranches,
                'selectedBanks'=>$selectedBanks,
                'model'=>$moneyReceived,
                'singleModel'=>$customerInvoiceId,
                'accountTypes'=>$accountTypes,
                'financialInstitutionBanks'=>$financialInstitutionBanks,
                'currencies'=>$customerInvoiceCurrencies,
                
            ]);
        }
        $warningMessage = count($moneyReceived->settlementsForDownPaymentThatComeFromMoneyModel) ? __('Warning, please take care incase you changed the received amount, the invoices settled using this down payment will be deleted'):null;
        
        return view($viewName, [
            'banks'=>$banks,
            'customers'=>$customers,
            'contracts'=>$contracts,
            // 'customerInvoices'=>$customerInvoices ,
            'selectedBranches'=>$selectedBranches,
            'accountTypes'=>$accountTypes,
            'financialInstitutionBanks'=>$financialInstitutionBanks,
            'selectedBanks'=>$selectedBanks,
            'model'=>$moneyReceived,
            'singleModel'=>$customerInvoiceId,
            'currencies'=>$customerInvoiceCurrencies,
            'warningMessage'=>$warningMessage
        ]);
        
    }
    
    public function update(Company $company, StoreMoneyReceivedRequest $request, moneyReceived $moneyReceived)
    {
        
        $oldSettlementsForMoneyReceivedWithDownPayment  = $moneyReceived->settlementsForDownPaymentThatComeFromMoneyModel ;
        //	$companyId = $company->id ;
        $newType = $request->get('type');
        $moneyReceivedAmountHasChanged = $moneyReceived->getAmount() != $request->input('received_amount.'.$newType);
    
        
        $moneyReceived->deleteRelations();
        $moneyReceived->delete();
        
        $newMoneyReceived = $this->store($company, $request, true);
      
        
        
        if (!$moneyReceivedAmountHasChanged) {
            $newMoneyReceived->storeNewSettlement(
                $oldSettlementsForMoneyReceivedWithDownPayment->toArray(),
                $newMoneyReceived->getPartnerId(),
                $company,
                1
            );
        }
        $activeTab = $newType;

        return response()->json([
           'redirectTo'=>route('view.money.receive', ['company'=>$company->id,'active'=>$activeTab])
        ]);
    }
    
    public function destroy(Company $company, MoneyReceived $moneyReceived, DeleteMoneyReceivedRequest $request)
    {
        $moneyReceived->deleteRelations();
        $activeTab = $moneyReceived->getType();
        $moneyReceived->delete();
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>$activeTab])->with('success', __('Money Received Has Been Updated Successfully'));
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
    public function sendToCollection(Company $company, SendToUnderCollectionChequeRequest $request)
    {
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration) {
            $OdooPaymentService = new OdooPayment($company);
        }
        $moneyReceivedIds = $request->get('cheques') ;
        $moneyReceivedIds = is_array($moneyReceivedIds) ? $moneyReceivedIds :  explode(',', $moneyReceivedIds);
        $data = $request->only(['deposit_date','drawl_bank_id','account_type','account_number','account_balance','clearance_days']);
        $data['account_type'] =  $request->input('account_type.'.MoneyReceived::CHEQUE_UNDER_COLLECTION);
        $data['account_number'] = $request->input('account_number.'.MoneyReceived::CHEQUE_UNDER_COLLECTION);
        $data['account_type'] = is_null($data['account_type']) ? $request->get('account_type') : $data['account_type'] ;
        $data['drawl_bank_id'] = $request->input('receiving_bank_id.'.MoneyReceived::CHEQUE_UNDER_COLLECTION, $request->get('drawl_bank_id'));
       
    
        $data['account_number'] = is_null($data['account_number']) ? $request->get('account_number') : $data['account_number'] ;
        $data['status'] = Cheque::UNDER_COLLECTION;
        
        foreach ($moneyReceivedIds as $moneyReceivedId) {
            /**
             * @var MoneyReceived $moneyReceived
             */
            $moneyReceived = MoneyReceived::find($moneyReceivedId) ;
            $isOpening = $moneyReceived->isOpenBalance();
            $data['expected_collection_date'] = $moneyReceived->cheque->calculateChequeExpectedCollectionDate($data['deposit_date'], $data['clearance_days']);
            $moneyReceived->cheque->update(array_merge($data, ['updated_at'=>now()]));
            if (!$isOpening) {
                if ($hasOdooIntegration) {
                    foreach ($moneyReceived->settlements as $settlement) {
                        $OdooPaymentService->reCreatePayment($settlement);
                    }
                    if ($moneyReceived->isInvoiceSettlementWithDownPayment()) {
                        $odooPaymentService = new OdooPayment($company);
                        $odooPaymentService->recreateDownPayment($moneyReceived);
                    }
                }
                $moneyReceived->handleOdooDownPayments($OdooPaymentService, $hasOdooIntegration);
                
            }
        }
        if ($request->ajax()) {
            return response()->json([
                'status'=>true ,
                'msg'=>__('Good'),
                'pageLink'=>route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_UNDER_COLLECTION])
            ]);
        }
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_UNDER_COLLECTION]);
        
    }
    /**
     * * تحديد ان الشيك دا تم بالفعل صرفة من البنك ونزل في حسابك
     */
    public function applyCollection(Company $company, ApplyCollectionToChequeRequest $request, MoneyReceived $moneyReceived)
    {
        /**
         *
         * @var MoneyReceived $moneyReceived
         */
        // $collectionFeesAmount = $request->get('collection_fees',0) ;
        $actualCollectionDate = Carbon::make($request->get('actual_collection_date'))->format('Y-m-d')  ;
        $moneyReceived->cheque->update([
            'status'=>Cheque::COLLECTED,
            // 'collection_fees'=>$collectionFeesAmount,
            'actual_collection_date'=>$actualCollectionDate
        ]);
        $chequeNumber = $moneyReceived->cheque->getChequeNumber();
        $accountType = AccountType::find($moneyReceived->cheque->account_type) ;
        $currency = $moneyReceived->getReceivingCurrency();
        $receivedAmount = $moneyReceived->getReceivedAmount();
        // $receivingDate = $moneyReceived->getReceivingDate();
        $moneyType = MoneyReceived::CHEQUE;
        $accountNumber = $moneyReceived->cheque->account_number ;
        $financialInstitutionId = $moneyReceived->cheque->getDrawlBankId();
        $financialInstitution = $moneyReceived->cheque->getDrawlBank();
        /**
         * @var AccountType $accountType ;
         */
        $moneyReceived->handleDebitStatement($financialInstitutionId, $accountType, $accountNumber, $moneyType, $actualCollectionDate, $receivedAmount, $currency, null);
        // $moneyReceived->handleCreditStatement($company->id , $financialInstitutionId , $accountType,$accountNumber,'fees',$actualCollectionDate,$collectionFeesAmount,null,$currency,__('Cheque Collection Fees - Cheque [ :number ]' ,['number'=>$chequeNumber],'en' ),__('Cheque Collection Fees - Cheque [ :number ]' ,['number'=>$chequeNumber],'ar' ));
        
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration) {
            $OdooPaymentService = new OdooPayment($company);
        }
        
        
        if ($hasOdooIntegration && $company->withinIntegrationDate($actualCollectionDate)) {
            $odooSetting = $company->odooSetting;
            $hasSettlements = $moneyReceived->settlements->count();
            $items = $hasSettlements ? $moneyReceived->settlements : [$moneyReceived];
            $items = $hasSettlements ? $moneyReceived->settlements : [$moneyReceived];
       
            if ($moneyReceived->isInvoiceSettlementWithDownPayment()) {
                $items->push($moneyReceived);
            }
            foreach ($items as $settlementOrMoneyModel) {
                $odooId = $settlementOrMoneyModel->odoo_id ;
				$isMoneyReceived = $settlementOrMoneyModel instanceof MoneyReceived ;
                $isOpeningAndMoneyReceivedBalance = $isMoneyReceived && $settlementOrMoneyModel->isOpenBalance() ;
                $odooCurrencyId =Currency::getOdooId($currency);
                $accountTypeId=$moneyReceived->cheque->getAccountTypeId();
                $accountNumber = $moneyReceived->cheque->getAccountNumber();
                $journalId = $financialInstitution->getJournalIdForAccount($accountTypeId, $accountNumber);
                $debitAccountOdooId = $financialInstitution->getOdooIdForAccount($accountTypeId, $accountNumber);
                $creditOdooAccountId = $odooSetting->getChequesReceivableId();
                $odooPartnerId = $moneyReceived->getPartnerOdooId();
                $amountInMainFunctionalCurrency= $settlementOrMoneyModel->getAmountInReceivingCurrency();
				if($isMoneyReceived && $moneyReceived->isInvoiceSettlementWithDownPayment() ){
					$amountInMainFunctionalCurrency = $moneyReceived->downPaymentSettlements->sum('down_payment_amount') * $moneyReceived->getExchangeRate() ;
				}
                $ref = 'Cheque Collection ' . $settlementOrMoneyModel->getInvoiceNumber();
                if ($isOpeningAndMoneyReceivedBalance) {
                    $settlementOrMoneyModel->markOpeningPayableChequeAsPaidInOdoo(true);
                } else {
                    $res =$OdooPaymentService->chequeCollection($odooId, $amountInMainFunctionalCurrency, $actualCollectionDate, $odooCurrencyId, $journalId, $debitAccountOdooId, $creditOdooAccountId, $odooPartnerId, $ref);
                    $settlementOrMoneyModel->update([
                        'account_bank_statement_line_id'=>$res['statement_entry_id']??null,
                        'odoo_reference'=>$res['bank_reference']??null
                    ]);
                    
                }
            }
        }
        if ($request->ajax()) {
            return response()->json([
                'status'=>true ,
                'redirectTo'=>route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_COLLECTED])
            ]);
        }
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_COLLECTED])->with('success', __('Cheque Is Returned To Safe'));
    }
    public function sendToUnderCollection(Company $company, BackToUnderCollectionChequeRequest $request, MoneyReceived $moneyReceived)
    {
        $isOpenBalance=  $moneyReceived->isOpenBalance();
        $updateChequeData = [
            'status'=>Cheque::UNDER_COLLECTION,
            // 'collection_fees'=>null,
            'actual_collection_date'=>null
        ] ;

    
        $moneyReceived->cheque->update($updateChequeData);

        while ($currentStatement = $moneyReceived->getCurrentStatement()) {
            $currentStatement->delete();
            $moneyReceived = $moneyReceived->refresh();
        }
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration && !$isOpenBalance) {
            $OdooPaymentService = new OdooPayment($company);
            $hasSettlements = $moneyReceived->settlements->count();
            $items = $hasSettlements ? $moneyReceived->settlements : [$moneyReceived];
            if ($moneyReceived->isInvoiceSettlementWithDownPayment()) {
                $items->push($moneyReceived);
            }
            foreach ($items as $settlementOrMoneyModel) {
                if ($settlementOrMoneyModel->account_bank_statement_line_id) {
                    $OdooPaymentService->unlinkBankCollection($settlementOrMoneyModel->account_bank_statement_line_id);
                }
            }
        }

        if ($hasOdooIntegration && $isOpenBalance) {
            $moneyReceived->unlinkNonCustomerOrSupplierOdooExpense();
            $moneyReceived->update([
            'odoo_reference'=>null,
            'journal_entry_id'=>null ,
            'account_bank_statement_line_id'=>null
            ]);
        }

        
        $moneyReceived->handleOdooDownPayments($OdooPaymentService, $hasOdooIntegration);
        
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_UNDER_COLLECTION])->with('success', __('Cheque Is Under Collection'));
        
    }
    public function sendToSafe(Company $company, Request $request, MoneyReceived $moneyReceived)
    {
        
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration) {
            $OdooPaymentService = new OdooPayment($company);
        }
        $isOpeningBalance = $moneyReceived->isOpenBalance();
        $moneyReceived->cheque->update([
            'status'=>Cheque::IN_SAFE,
            'deposit_date'=>null ,
            'drawl_bank_id'=>null ,
            'account_type'=>null ,
            'account_number'=>null ,
            'account_balance'=>null ,
            'expected_collection_date'=>null ,
            'clearance_days'=>null
        ]);
        
        if ($hasOdooIntegration && !$isOpeningBalance) {
            foreach ($moneyReceived->settlements as $settlement) {
                $OdooPaymentService->reCreatePayment($settlement);
            }
            if ($moneyReceived->isInvoiceSettlementWithDownPayment()) {
                $odooPaymentService = new OdooPayment($company);
                $odooPaymentService->recreateDownPayment($moneyReceived);
            }
                    
        }
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE])->with('success', __('Cheque Is Returned To Safe'));
    }
    public function sendToSafeAsRejected(Company $company, Request $request, MoneyReceived $moneyReceived)
    {
        
        $moneyReceived->cheque->update([
            'status'=>Cheque::REJECTED,
            'deposit_date'=>null ,
            'drawl_bank_id'=>null ,
            'account_type'=>null ,
            'account_number'=>null ,
            'account_balance'=>null ,
            'expected_collection_date'=>null ,
            'clearance_days'=>null
        ]);
        
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_REJECTED])->with('success', __('Cheque Is Returned To Safe'));
        
    }

    public function getAccountNumbersForAccountType(Company $company, Request $request, string $accountType, ?string $selectedCurrency=null, ?int $financialInstitutionId = 0)
    {
        $accountType = AccountType::find($accountType);
        $modelName = $accountType->getModelName() ;
        $accountNumberModel =  ('\App\Models\\'.$modelName)::getAllAccountNumberForCurrency($company->id, $selectedCurrency, $financialInstitutionId);
        return response()->json([
            'status'=>true ,
            'data'=>$accountNumberModel
            
        ]);
    }
    public function getAccountIdsForAccountType(Company $company, Request $request, string $accountType, ?string $selectedCurrency=null, ?int $financialInstitutionId = 0)
    {
        $accountType = AccountType::find($accountType);
        $modelName = $accountType->getModelName() ;
        $accountNumberModel =  ('\App\Models\\'.$modelName)::getAllAccountNumberForCurrency($company->id, $selectedCurrency, $financialInstitutionId, 'id');
        return response()->json([
            'status'=>true ,
            'data'=>$accountNumberModel
            
        ]);
    }
    public function getAccountAmountForAccountId(Company $company, Request $request, string $accountTypeId, int $accountId, int $financialInstitutionId)
    {
    
        
        $accountType = AccountType::find($accountTypeId);
        $accountNumberModel =  ('\App\Models\\'.$accountType->getModelName())::find($accountId);
        $accountNumber = $accountNumberModel ? $accountNumberModel->account_number : '';
        $currencyName = $accountNumberModel ? $accountNumberModel->currency : '';
    
        return response()->json([
            'status'=>true ,
            'amount'=>$accountNumberModel ? $accountNumberModel->getAmount($currencyName, $accountNumber, $financialInstitutionId, $company->id) : 0 ,
       //     'interest_rate'=>$accountNumberModel ? $accountNumberModel->getInterestRate() : 0,
            'currencyName'=>$currencyName
        ]);
    }
    public function updateNetBalanceBasedOnAccountNumber(Request $request, Company $company, $accountTypeId = null, $accountNumber = null, $financialInstitutionId = null, $statementDate = null)
    {
        $additionalAmountInEditMode=  0 ;
        // $additionalAmountInEditMode = number_unformat($request->get('additionalBalanceInEditMode',0));
        $model = null ;
 
        $netBalanceDate = '' ;
        $accountTypeId = $request->get('accountType', $accountTypeId);
        $accountType = AccountType::find($accountTypeId);
        $statementDate = $statementDate ?: $request->get('balanceDate');
        $statementDate = $statementDate ?: now()->format('Y-m-d');
        $statementDate = Carbon::make($statementDate)->format('Y-m-d');
        
        $accountNumber = $request->get('accountNumber', $accountNumber);
        
        $financialInstitutionId = $request->get('financialInstitutionId', $financialInstitutionId);
        if (!$accountType) {
            return response()->json([
                'status'=>true ,
                'balance'=>0,
                'net_balance'=>0 ,
            ]);
        }
   
        $accountNumberModel =  ('\App\Models\\'.$accountType->getModelName())::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
        
        if (!$accountNumberModel) {
            
                return response()->json(
                    [
                        'status'=>true ,
                        'balance'=>0,
                        'net_balance'=>0 ,
                    ]
                );
          
        }
        
        if ($request->has('modelId')) {
            $modelId = $request->get('modelId')  ;
            $modelType = $request->get('modelType');
            $model = ('App\Models\\'.$modelType)::find($modelId);
            $oldAccountNumber = $model ? $model->getAccountNumber() : null;
            $oldAccountTypeId = $model ? $model->getAccountTypeId() : null;
            $statementDate = $model && $model->payableCheque ? $model->payableCheque->due_date : $statementDate ;
            // $oldFinancialInstitution = $model ? $model->getAccountTypeId() : null;
            if ($oldAccountNumber && $oldAccountNumber == $accountNumber
            && $oldAccountTypeId && $oldAccountTypeId == $accountTypeId
            ) {
                $additionalAmountInEditMode =  $model->getPaidAmount();
            }
        }
		
	
        
        $statementTableName = $accountNumberModel->getStatementTableName() ;
        $foreignKeyName = $accountNumberModel->getForeignKeyInStatementTable();
        $balanceRow = DB::table($statementTableName)->where($foreignKeyName, $accountNumberModel->id)->where('date', '<=', $statementDate)->orderByRaw('date desc , id desc')->first();
        $NetBalanceRow = DB::table($statementTableName)->where($foreignKeyName, $accountNumberModel->id)->orderByRaw('date desc , id desc')->first();
		
        $column = $accountType->isOverdraftAccount() ? 'room' : 'end_balance';
        $balance = 0;
        $balanceDate = '';

        $netBalance = 0;
        if ($balanceRow) {
            $balance = $balanceRow->{$column} ;
            $balanceDate = Carbon::make($balanceRow->date)->format('d-m-Y') ;
        }
        if ($NetBalanceRow) {
            $netBalance =$NetBalanceRow->{$column} ;
            $netBalanceDate =Carbon::make($NetBalanceRow->date)->format('d-m-Y') ;
        }
        return response()->json([
            'status'=>true ,
            'balance'=>$balance+$additionalAmountInEditMode,
            'net_balance'=>$netBalance+$additionalAmountInEditMode ,
            'balance_date'=>$balanceDate,
            'net_balance_date'=>$netBalanceDate ,
        ]);

    }
    
    public function updateNetBalanceBasedOnAccountIdByAjax(Request $request, Company $company, $accountType, $accountId, $financialInstitutionId)
    {
        $accountTypeId = $accountType ;
        $account = AccountType::find($accountTypeId);
        $fullModelName = 'App\Models\\'.$account->getModelName() ;
        $accountNumber = $fullModelName::find($accountId)->account_number;
        
        return $this->updateNetBalanceBasedOnAccountNumber((new Request())->replace([
            'accountType'=>$accountTypeId,
            'accountNumber'=>$accountNumber ,
            'financialInstitutionId'=>$financialInstitutionId
        ]), $company);
    }
    
    public function getCustomersWithOpeningBalance(Request $request, Company $company)
    {
        $type =$request->get('type') ;
        $partners = [];
        if ($type == 'over_contract') {
            $partners=  Partner::has('contracts')->where('is_customer', 1)->orderBy('name')
                                    ->where('company_id', $company->id)->pluck('id', 'name');
        } elseif ($type == 'general') {
            $partners =  Partner::where('is_customer', 1)->orderBy('name')
                                    ->where('company_id', $company->id)->pluck('id', 'name');
        } elseif ($type == 'settlement-of-opening-balance') {
            $partners = CustomerInvoice::orderBy('customer_name')
            ->whereNotNull('opening_balance_id')
            ->where('company_id', $company->id)->pluck('customer_id', 'customer_name');
        }
        return response()->json([
            'invoices' => $partners
        ]);
        
    }
    public function getCustomersBasedOnCurrency(Request $request, Company $company, string $currencyName)
    {
        return response()->json([
            'customerInvoices' => CustomerInvoice::orderBy('customer_name')->
            where('currency', $currencyName)
            ->where('company_id', $company->id)->pluck('customer_id', 'customer_name')
            
        ]);
    }
    public function getPartnersBasedOnCurrency(Request $request, Company $company, string $currencyName)
    {
        $partnerColumnName = $request->get('partnerColumnName');

        if ($partnerColumnName == 'is_customer') {
            $partners = CustomerInvoice::orderBy('customer_name')->where('currency', $currencyName)->where('company_id', $company->id)->pluck('customer_id', 'customer_name');
        } else {
            $partners = Partner::orderBy('name')->where('company_id', $company->id)->where($partnerColumnName, 1)->pluck('id', 'name')->toArray();
        }
        return response()->json([
            'partners'=>$partners
        ]);
    }
    public function markAsConfirmed(Company $company, Request $request, int $modelId)
    {
        $tableName = $request->get('table_name');
        DB::table($tableName)->where('id', $modelId)->update([
            'is_reviewed'=>1,
            'reviewed_by'=>auth()->user()->id
        ]);
        return redirect()->back();
    }
    public function resendToOdoo(Company $company, Request $request, MoneyReceived $moneyReceived)
    {
        $OdooPaymentService = new OdooPayment($company);
        foreach ($moneyReceived->settlements as $payment) {
            $OdooPaymentService->reCreatePayment($payment);
        }
        if (!session()->has('fail') && $moneyReceived->hasUnappliedOrDownPayment()) {
            $OdooPaymentService->RecreateDownPayment($moneyReceived);
        }
        return back();
    }
    
    

}
