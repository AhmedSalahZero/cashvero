<?php
namespace App\Services\Api;

use App\Helpers\HStr;
use App\Http\Controllers\MoneyReceivedController;
use App\Http\Requests\StoreMoneyReceivedRequest;
use App\Models\CashVeroBranch;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\CustomerInvoice;
use App\Models\FinancialInstitutionAccount;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\SalesOrder;
use App\Models\SupplierInvoice;
use App\Services\Api\Traits\AuthTrait;
use App\Services\Api\Traits\CommonHelper;
use App\Services\Api\Traits\HasUnlink;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Str;

class OdooService
{
	use AuthTrait , CommonHelper,HasUnlink;
	/**
	 * * import project or contracts
	 */
	public function startImportContracts(string $startDate, string $endDate,int $companyId)
	{
		if(is_null($this->uid)){
			return ;
		}
		$this->getContracts($startDate,$endDate,$companyId);
	}
	/**
	 * * 
	 */
	// public function createPaymentFromOdooToInvoice(int $odooInvoiceId,int $invoiceId,int $partnerId,$invoiceCurrencyName,$newMoneyClass )
	// {
	// 	/**
	// 	 * @var MoneyReceived|MoneyPayment $newMoneyClass [new MoneyReceived empty class]
	// 	 */
	// 	$isMoneyReceived = $newMoneyClass instanceof MoneyReceived;
	// 	$settlementTableName = $isMoneyReceived ? 'settlements' : 'payment_settlements';
	// 	$inboundOrOutbound = $isMoneyReceived ? 'inbound' : 'outbound';
	// 	$isCustomerOrSupplier = $isMoneyReceived ? 'customer' : 'supplier';
	// 	$partnerType = $isMoneyReceived ? 'is_customer' : 'is_supplier';
	// 	$customerOrSupplierId = $isMoneyReceived ? 'customer_id' : 'supplier_id';
	// 	$moneyModel = $isMoneyReceived ? 'App\Models\MoneyReceived': 'App\Models\MoneyPayment';
	// 	$receivingDate = $isMoneyReceived ? 'receiving_date' : 'delivery_date';
	// 	$branchIdColumnName = $isMoneyReceived ? 'receiving_branch_id' : 'delivery_branch_id';
	// 	$amountColumnName = $isMoneyReceived ? 'received_amount' : 'paid_amount';
	// 	$bankColumnName = $isMoneyReceived ? 'receiving_bank_id' : 'delivery_bank_id';
	// 	$receivingOrDeliveryCurrencyName = $isMoneyReceived ? 'receiving_currency' : 'payment_currency';
	// 	$moneyModel = new $moneyModel;
	// 	$dataFormatted = [];
	// 	// foreach(['EGP','USD'] as $currencyName){
			
	// 	$currencyOdooId = Currency::getOdooId($invoiceCurrencyName);
	// 	$payments = $this->fetchData('account.payment',[],[[['invoice_ids','=',$odooInvoiceId],['currency_id','=',$currencyOdooId],['payment_type','=',$inboundOrOutbound],['partner_type','=',$isCustomerOrSupplier]]]);
		
				
	// 			foreach($payments as $paymentArr){
	// 				$paymentOdooId = $paymentArr['id'];
	// 				$isExist = DB::table($settlementTableName)->where('company_id',$this->company_id)->where('odoo_id',$paymentOdooId)->first();
	// 				if($isExist){
	// 					continue ;
	// 				}
	// 				$journalId = $paymentArr['journal_id'][0];
	// 				$date =$paymentArr['date'] ;
	// 				$currentJournal = $newMoneyClass::getMoneyTypeFromJournalId($journalId,$this->company_id);
	// 				$moneyType = $currentJournal['type'];
	// 				$branchId = $currentJournal['branch_id']??null;
	// 				$financialInstitutionId = $currentJournal['financial_institution_id']??null;
	// 				$amount  = $paymentArr['amount'];
	// 				$receiptNumber = HStr::generateReceiptNumber('receipt_number_');
	// 				$dataFormatted[$moneyType][$date]=[
	// 					'stop-sync-with-odoo'=> true ,
	// 					'partner_type'=>$partnerType,
	// 					'currency'=>$invoiceCurrencyName,
	// 					$receivingOrDeliveryCurrencyName=>$invoiceCurrencyName,
	// 					$customerOrSupplierId=>$partnerId,
	// 					'type'=>$moneyType,
	// 					$receivingDate=>$date,
	// 					$branchIdColumnName=>$branchId,
	// 					$amountColumnName => [
	// 						$moneyType=>$amount
	// 					],
	// 					'receipt_number'=>$receiptNumber,
	// 					'exchange_rate'=>[$moneyType=>1] , // not found in the model dd
	// 					'amount_in_invoice_currency'=>[
	// 						$moneyType=>$amount 
	// 					],
	// 					$bankColumnName=>[
	// 						$moneyType=>$financialInstitutionId 
	// 					],
	// 					'account_type'=>[
	// 						$moneyType => $currentJournal['account_type_id']??null 
	// 					],
	// 					'account_number'=>[
	// 						$moneyType=>$currentJournal['account_number']??null
	// 					],
	// 					'drawee_bank_id'=>null, // in case of cheque we have to fill it 
	// 					'due_date'=>null, // in case of cheque we have to fill it  
	// 					'cheque_number'=>null, // in case of cheque we have to fill it  
	// 					'settlements'=>[
	// 						$invoiceId => [
	// 							'odoo_id'=>$paymentOdooId,
	// 							'invoice_id'=>$invoiceId,
	// 							'settlement_amount'=>$amount ,
	// 							'withhold_amount'=>0 
	// 						]
	// 					]
	// 				];
					
					
	// 			}
	// 			// }		
	// 			foreach($dataFormatted as $moneyType => $date){
	// 				foreach($date as $receivingDate => $moneyArr){
	// 					(new MoneyReceivedController)->store($this->company,(new StoreMoneyReceivedRequest())->merge($moneyArr));
	// 				}
	// 			}
	
	// }
	/**
	 * * import invoices
	 */
	public function startImportInvoices($startDate , $endDate,$companyId)
	{
	
			if(is_null($this->uid)  ){
			return ;
		}
		$this->getPartners($startDate,$endDate,$companyId);
		$invoices = $this->getInvoices($startDate,$endDate);
		if (!is_array($invoices)) {
			return;
		}
		$this->syncDeletedInvoices($companyId,$endDate);
		
		foreach($invoices as $invoice){
			$odooInvoiceId = $invoice['id'];
			$invoiceDate = $invoice['invoice_date'];
			$invoiceDueDate = $invoice['invoice_date_due'];
			$soNumber = $invoice['invoice_origin']??null;
			$exchangeRate = 1/$invoice['invoice_currency_rate'];
			$vatPlusWithholdArr = $invoice['tax_totals']['subtotals'];
			$firstWithholdOrVatName = $vatPlusWithholdArr[0]['name'] ?? null;
			$secondWithholdOrVatName = $vatPlusWithholdArr[1]['name'] ?? null;
			$isFirstWithhold = $firstWithholdOrVatName == 'Subtotal W/O WHTax';
			$isSecondWithhold = $secondWithholdOrVatName == 'Subtotal W/O WHTax';
			$withholdAmount = 0 ;
			$withholdAmountInMainCurrency = 0 ;
			$excludeIndex = -1 ;
			if($isFirstWithhold){
				$withholdAmount = abs($vatPlusWithholdArr[0]['tax_amount_currency']);
				$withholdAmountInMainCurrency = abs($vatPlusWithholdArr[0]['tax_amount']);
				$excludeIndex = 0 ;
			}
			if($isSecondWithhold){
				$withholdAmount = abs($vatPlusWithholdArr[1]['tax_amount_currency']);
				$withholdAmountInMainCurrency = abs($vatPlusWithholdArr[1]['tax_amount']);
				$excludeIndex = 1 ;
			}
			
			$vatAmount = 0 ;
			foreach($vatPlusWithholdArr as $vatIndex => $vatArr){
				if($vatIndex != $excludeIndex){
					$vatAmount+=($vatArr['tax_amount_currency']);
				}
			}
			$vatAmount = abs($vatAmount);
			
			$invoiceAmount = abs($invoice['amount_untaxed_in_currency_signed']);
			$collectedAmount = 0 ;
			$collectedAmountInMainCurrency = 0 ;
			foreach($invoice['invoice_payments_widget']['content'] ??[] as $index=>$collectionArr){
				$isExchangeDifference = Str::startsWith($collectionArr['ref'],'EXCH/');
				if($isExchangeDifference){
					continue ; 
				}
				$collectedAmount+= ($collectionArr['amount']);
				$collectedAmountInMainCurrency+= convertStringWithNumberToNumber($collectionArr['amount_company_currency']);
			}
			$invoiceNumber = $invoice['name'];
			$odooPartnerId = $invoice['partner_id'][0];
			$odooPartnerName = $invoice['partner_id'][1];
			$invoiceCurrency = $invoice['currency_id'][1];
			$isSupplier = $invoice['move_type'] == 'in_invoice';
			$isCustomer = $invoice['move_type'] == 'out_invoice';
			$partnerId = Partner::handlePartnerForOdoo($odooPartnerId ,$odooPartnerName,$isCustomer,$isSupplier ,false,false,$companyId  );
			if($isCustomer){
				$invoiceId =  CustomerInvoice::createForOdoo($odooInvoiceId,$partnerId,$odooPartnerName,$invoiceDate,$invoiceDueDate,$invoiceNumber,$invoiceCurrency,$invoiceAmount,$vatAmount,$withholdAmount,$withholdAmountInMainCurrency,$collectedAmount,$collectedAmountInMainCurrency,$exchangeRate,$soNumber,$companyId);
			}elseif($isSupplier){
				$invoiceId= SupplierInvoice::createForOdoo($odooInvoiceId,$partnerId,$odooPartnerName,$invoiceDate,$invoiceDueDate,$invoiceNumber,$invoiceCurrency,$invoiceAmount,$vatAmount,$withholdAmount,$withholdAmountInMainCurrency,$collectedAmount,$collectedAmountInMainCurrency,$exchangeRate,$soNumber,$companyId);
			}
			
	
		}
		
		
		
	}
		
	public function getContracts(string $startDate ,string $endDate,int $companyId)
	{
		$contractFilters = array(array(
			// array('id', '=', 14), 
			array('id', '>=', 0),
			array('write_date', '>=', $startDate),
			array('write_date', '<=', $endDate)
		));
		$contractIds=$this->models->execute_kw($this->db, $this->uid, $this->password, 'project.project', 'search',$contractFilters);
		$projects = $this->models->execute_kw($this->db, $this->uid, $this->password, 'project.project', 'read', array($contractIds),[
			'fields'=>[
				'id',
				
				'account_id',
				'name',
				'partner_id',
				'date_start', // start date
				'date', //end date
			]
		]);
		
		foreach($projects as $projectArr){
			$projectAmount = 0 ;
			$modelType = 'Customer';
			$currentProjectStartDate = isset($projectArr['date_start']) && $projectArr['date_start'] ? $projectArr['date_start'] :  now()->format('Y-m-d') ;
			$currentProjectEndDate = isset($projectArr['date']) && $projectArr['date'] ? $projectArr['date'] : now()->format('Y-m-d') ;
			$currentOdooProjectId = $projectArr['id'];
			$currentOdooCustomerId = $projectArr['partner_id'][0]??null ;
			
			if(is_null($currentOdooCustomerId)){
				continue;
			}
			$currentOdooCustomerName = $projectArr['partner_id'][1] ;
			$code = Contract::generateRandomContract($companyId,$currentOdooCustomerName,$startDate,$modelType);
			$partnerId = Partner::handlePartnerForOdoo($currentOdooCustomerId ,$currentOdooCustomerName,1,0,false,false,$companyId  );
			$oldProject = Contract::where('odoo_id',$currentOdooProjectId)->first();
			
			$projectFormatted = [
				'odoo_id'=>$currentOdooProjectId,
				'code'=>$code,
				'project_account_id'=>$projectArr['account_id'][0]??null,
				'name'=>$projectArr['name'],
				'model_type'=>$modelType,
				'partner_id'=>$partnerId,
				'start_date'=>$currentProjectStartDate,
				'end_date'=>$currentProjectEndDate,
				'company_id'=>$companyId,
				'duration'=>Carbon::make($currentProjectEndDate)->diffInMonths($currentProjectStartDate)
			];
			if($oldProject){
				$projectFormatted['id'] = $oldProject->id;
				$projectFormatted['code'] = $oldProject->code;
			}
			$salesOrderFilters = array(array(
				['project_id','=',$currentOdooProjectId]
			));
				$salesOrderIds=$this->models->execute_kw($this->db, $this->uid, $this->password, 'sale.order', 'search',$salesOrderFilters
				// , array('limit' => 10)
			);
				$salesOrders = $this->models->execute_kw($this->db, $this->uid, $this->password, 'sale.order', 'read', array($salesOrderIds),[
					'fields'=>[
						'id',
						'display_name', // so_number
						'currency_id',
						'amount_total',
						'project_id'
					]
				]);
				$salesOrderFormatted = [];
				foreach($salesOrders as $orderIndex => $salesOrderArr){
					$projectFormatted['currency']=$salesOrderArr['currency_id'][1];
					$currentOrderIndex =$orderIndex+1;
					$currentSalesOrderId = $salesOrderArr['id'];
					$currentSalesOrderAmount = $salesOrderArr['amount_total'];
					$projectAmount += $currentSalesOrderAmount;
					
					$currentSalesOrderArr = [
						'odoo_id'=>$currentSalesOrderId,
						'so_number'=>$salesOrderArr['display_name'],
						// 'id'=>$currentSalesOrderId,
						'amount'=>$currentSalesOrderAmount,
						'execution_percentage_'.$currentOrderIndex=>100,
						'start_date_'.$currentOrderIndex=>$currentProjectStartDate,
						'end_date_'.$currentOrderIndex=>$currentProjectEndDate,
			//			'execution_days_'.$currentOrderIndex=>Carbon::make($currentProjectEndDate)->diffInMonths($currentProjectStartDate),
						'collection_days_'.$currentOrderIndex=>0,
						'company_id'=>$companyId
						
					] ;
					$oldSalesOrder = SalesOrder::where('odoo_id',$currentSalesOrderId)->first();
					if($oldSalesOrder){
						$currentSalesOrderArr['id'] = $oldSalesOrder->id;
					}
					$salesOrderFormatted[]=$currentSalesOrderArr;
				}
				$projectAmount = $projectAmount ? $projectAmount : 0 ;
				$projectFormatted['amount'] = $projectAmount ;
				if(count($salesOrderFormatted) && $projectAmount){
					$projectFormatted['salesOrders']=$salesOrderFormatted;
					$contract = $oldProject ? $oldProject : new Contract ;
			
					$request = (new Request())->merge($projectFormatted);
					$contract->storeBasicForm($request);
				}
				
		}

		
		
	}
	protected function getInvoices(string $startDate,string $endDate)
	{
		$fields= [
			// 'partner_id',
			// 'id',
			// 'invoice_date',
			// 'name',
			// 'move_type',
			// 'currency_id',
			// 'amount_residual',
			// 'amount_untaxed_in_currency_signed',
			// 'amount_tax',
			// 'invoice_date_due',
			// 'date',
			// 'invoice_currency_rate',//exchange rate
			// 'invoice_origin' ,// so_number
			// 'write_date',
			// 'state',
			// 'invoice_line_ids' // product ids 
		];
		$filters = array(array(array('move_type', 'in', [
			'in_invoice',
			'out_invoice'
		])
		,array('state', '=', 'posted'),
			array('write_date', '>=', $startDate),
			array('write_date', '<=', $endDate),
		));
		$invoices = $this->fetchData('account.move',$fields,$filters);
		return is_array($invoices) ? $invoices : null;
		// /**
		//  * * الكود اللي تحت دا بيجيب المنتجات
		//  */
		// $productIds = array_unique(Arr::flatten(array_column($invoices,'invoice_line_ids'))) ;
		// $filters = [[
		// 	['id','in',$productIds]
		// ]];
		// $fields = [
		// 	'name','display_name','product_id','quantity','price_unit','price_subtotal'
		// ];
		// return $invoices ;
		
		
	}
	protected function getUser(array $ids){
		 $user = $this->models->execute_kw($this->db, $this->uid, $this->password, 'res.partner', 'read', array($ids));
		 return $user;
	}
	


	private function syncDeletedInvoices(int $companyId,string $odooEndDate)
	{
		$startDate = Carbon::make($odooEndDate)->subDays(450)->format('Y-m-d');
		$endDate = $odooEndDate;
		$customerInvoices  = CustomerInvoice::where('company_id',$companyId)->where('invoice_date','>=',$startDate)->where('invoice_date','<=',$endDate)->where('odoo_id','>',0)->get();
		$supplierInvoices  = SupplierInvoice::where('company_id',$companyId)->where('invoice_date','>=',$startDate)->where('invoice_date','<=',$endDate)->where('odoo_id','>',0)->get();
		
		$deletedIds= [];
		$odooInvoices = $this->getInvoices($startDate, $endDate);
		if (!is_array($odooInvoices)) {
			return;
		}
		$odooInvoicesIds = array_column($odooInvoices, 'id');
		foreach([$customerInvoices,$supplierInvoices] as $invoices){
			foreach($invoices as $invoice){
				$invoiceOdooId = $invoice->getOdooId();
				if(!in_array($invoiceOdooId,$odooInvoicesIds)){
					$deletedIds[] = [
						'id'=>$invoiceOdooId,
						'type'=>getModelNameWithoutNamespace($invoice)
					];
					$invoice->delete();
					
				}
			}
			
		}
	}
	public function chartOfAccount(string $chartOfAccountCode) 
	{
		$filters = [
				[
					['code','=',$chartOfAccountCode],
				]
		];
		return $this->fetchData('account.account',[],$filters)[0]??null;
	}
	public function syncChartOfAccountNumbers(string $chartOfAccountCode,int $companyId)
	{
			$fields = [
				// 'id'
			];
			
			$filters = [
				[
					// ['account_type','=','expense'],
					['code','=',$chartOfAccountCode],
				]
		];
		$odooExpenseItem = $this->fetchData('account.account',$fields,$filters)[0]??null;
		if($odooExpenseItem){
			DB::table('cash_expense_category_names')->where('company_id',$companyId)->where('odoo_chart_of_account_number',$chartOfAccountCode)->update([
				'odoo_id'=>$odooExpenseItem['id']
			]);
		}
		return $odooExpenseItem ;
	}
	public function getChartOfAccountIdFromOdooCode(string $odooCode)
	{
			$fields = [
				'id',
				'code'
			];
			$filters = [
				[
					
				]
		];
		
		$chartOfAccounts = $this->fetchData('account.account',$fields,$filters);
			$chartOfAccounts = collect($chartOfAccounts)->keyBy('code')->toArray();
			return  $chartOfAccounts[$odooCode]['id']??null;
			 
	}
	public function syncFinancialInstitutions(FinancialInstitutionAccount $financialInstitutionAccount)
	{
		$odooSetting = $this->company->odooSetting;
	
			$fields = [
				'id',
				'code'
			];
			$filters = [
				[
					
				]
		];
		$chartOfAccounts = $this->fetchData('account.account',$fields,$filters);
		
		$chartOfAccounts = collect($chartOfAccounts)->keyBy('code')->toArray();
	//		$financialInstitutionAccounts = FinancialInstitutionAccount::where('company_id',$this->company_id)->whereNotNull('odoo_code')->get();

		//	foreach($financialInstitutionAccounts as $financialInstitutionAccount){
				$codeCode = $financialInstitutionAccount->getOdooCode();
		
				if($codeCode){
					$currentJournal = $chartOfAccounts[$codeCode]??null;
					$chartOfAccountId = $currentJournal ? $currentJournal['id'] : null;
					
					if($chartOfAccountId){
						$journalId = $this->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
						$odooInboundTransferPaymentMethodId = $this->getPaymentMethodId($journalId,$chartOfAccountId,'inbound');
						$odooOutboundTransferPaymentMethodId = $this->getPaymentMethodId($journalId,$chartOfAccountId,'outbound');
						$chequeReceivableId=$odooSetting ? $odooSetting->getChequesReceivableId() : null;
						$chequePayableId=$odooSetting ? $odooSetting->getChequesPayableId() : null;
						if($chequeReceivableId){
							$odooInboundChequePaymentMethodId = $this->getPaymentMethodId($journalId,$chequeReceivableId,'inbound');
						}
						if($chequePayableId){
							$odooOutboundChequePaymentMethodId = $this->getPaymentMethodId($journalId,$chequePayableId,'outbound');
						}
						
						
						$financialInstitutionAccount->update([
							'odoo_id'=>$chartOfAccountId,
							'journal_id'=>$journalId ,
							'odoo_inbound_transfer_payment_method_id'=>$odooInboundTransferPaymentMethodId??null ,
							'odoo_outbound_transfer_payment_method_id'=>$odooOutboundTransferPaymentMethodId??null,
							'odoo_inbound_cheque_payment_method_id'=>$odooInboundChequePaymentMethodId??null ,
							'odoo_outbound_cheque_payment_method_id'=>$odooOutboundChequePaymentMethodId??null,
						]);
					}
					
				}
		//	}
			
			
	
		
	}
	public function syncBranchSafe(?string $odooCode,int $companyId)
	{
			$fields = [
				'id',
				'code'
			];
			$filters = [
				[
					// ['type','=','cash'],
					['code','=',$odooCode]
				]
		];
			$odooSetting = $this->company->odooSetting;
		$odooBranch = $this->fetchData('account.account',$fields,$filters)[0]??null;
		$chartOfAccountId= $odooBranch ? $odooBranch['id'] : null;
		$journalId = $this->getJournalIdFromChartOfAccountId($chartOfAccountId);
		
		$odooInboundTransferPaymentMethodId = null ;
		$odooOutboundTransferPaymentMethodId = null ;
		$chequeReceivableId=$odooSetting ? $odooSetting->getChequesReceivableId() : null;
		$chequePayableId=$odooSetting ? $odooSetting->getChequesPayableId() : null;
						
		if($chartOfAccountId && $journalId){
			
						$odooInboundTransferPaymentMethodId = $this->getPaymentMethodId($journalId,$chartOfAccountId,'inbound');
						$odooOutboundTransferPaymentMethodId = $this->getPaymentMethodId($journalId,$chartOfAccountId,'outbound');
						
						if($chequeReceivableId){
							$odooInboundChequePaymentMethodId = $this->getPaymentMethodId($journalId,$chequeReceivableId,'inbound');
						}
						if($chequePayableId){
							$odooOutboundChequePaymentMethodId = $this->getPaymentMethodId($journalId,$chequePayableId,'outbound');
						}
						
					
		}
		if($chartOfAccountId){
			DB::table('branch')->where('company_id',$companyId)->where('odoo_code',$odooCode)->update([
							'odoo_id'=>$chartOfAccountId,
							'journal_id'=>$journalId,
							'odoo_inbound_transfer_payment_method_id'=>$odooInboundTransferPaymentMethodId??null ,
							'odoo_outbound_transfer_payment_method_id'=>$odooOutboundTransferPaymentMethodId??null,
							'odoo_inbound_cheque_payment_method_id'=>$odooInboundChequePaymentMethodId??null ,
							'odoo_outbound_cheque_payment_method_id'=>$odooOutboundChequePaymentMethodId??null,
						]);
		}
					
		
	}
	// public function syncBanks()
	// {
	// 		$fields = [
	// 			'id',
	// 			'code'
	// 		];
	// 		$filters = [
	// 			[
	// 				['type','=','cash'
	// 			],
	// 			]
	// 	];
	// 	$banks = $this->fetchData('account.account',$fields,$filters);
	
	// 	$chartOfAccounts = collect($banks)->keyBy('code')->toArray();
	// 	$banks = CashVeroBranch::where('company_id',$this->company_id)->whereNotNull('odoo_code')->get();

	// 		foreach($banks as $bank){
	// 			$codeCode = $bank->getOdooCode();
	// 			if($codeCode){
	// 				$currentJournal = $chartOfAccounts[$codeCode]??null;
	// 				$chartOfAccountId = $currentJournal ? $currentJournal['id'] : null;
	// 				if($chartOfAccountId){
	// 					$bank->update([
	// 						'odoo_id'=>$chartOfAccountId,
	// 						'journal_id'=>$this->getJournalIdFromChartOfAccountId($chartOfAccountId)
	// 					]);
	// 				}
					
	// 			}
	// 		}
	// }
	public function execute($model, $method, $args, $kwargs = [])
    {
        return $this->models->execute_kw($this->db, $this->uid, $this->password, $model, $method, $args, $kwargs);
    }
	// private function validateJournal($journalId)
    // {
    //     $journal = $this->execute('account.journal', 'read', [[$journalId], ['type', 'default_account_id']])[0];
    //     if (!in_array($journal['type'], ['bank', 'cash'])) {
    //         throw new \Exception('Journal must be of type bank or cash');
    //     }
    //     if (!$journal['default_account_id']) {
    //         throw new \Exception('Journal has no default account configured');
    //     }
    //     return $journal['default_account_id'][0]; // Return account ID
    // }
	// public function getFieldSelection($model, $field)
    // {
    //         $fields = $this->models->execute_kw($this->db, $this->uid, $this->password,$model, 'fields_get', [[$field]]);
    //         return $fields[$field]['selection'] ?? [];
      
    // }
	
	
 public function getPartners(string $startDate,string $endDate,int $companyId):array
    {
     		 $fields = ['name', 'email', 'phone', 'customer_rank', 'supplier_rank','employee_ids'];
            // Search all partners
            $partnerIds = $this->execute('res.partner', 'search', [[]]);
            if (empty($partnerIds)) {
                return [];
            }

            // Read partner details with role-related fields
			// $filters = [
			// 	[
			// 		array('write_date', '>=', $startDate),
			// 		array('write_date', '<=', $endDate),
			// 	]
			// ];
			// $partners = $this->fetchData('res.partner',$fields,$filters);
           $partners = $this->execute('res.partner', 'read', [$partnerIds, $fields]);
		   
		   if(isset($partners['faultCode']) && $partners['faultCode'] == 4) {
				throw new \Exception('You do not have enough rights to access the fields "employee_ids" on Contact (res.partner). Please contact your system administrator.');
		   }
		   
		   unset($partners[0]); // هنشيل اول واحد لانه بيكون الادمن
			
			
            // Check for employee role by searching hr.employee
            // Add role information to each partner
		
            foreach ($partners as &$partner) {
                $isCustomer = $partner['customer_rank'] > 0; 
                $isSupplier = $partner['supplier_rank'] > 0; 
				$currentOdooCustomerName =$partner['name']; 
				$currentOdooCustomerId =$partner['id']; 
                $isEmployee = count($partner['employee_ids']??[]) ;
				$isOtherPartner = false ;
				if(!$isEmployee && !$isCustomer && !$isSupplier){
					$isOtherPartner = true;
				}
				
				Partner::handlePartnerForOdoo($currentOdooCustomerId ,$currentOdooCustomerName,$isCustomer,$isSupplier,$isEmployee,$isOtherPartner,$companyId  );
            }
            return $partners;
    }
	
	//  public function getExpenseAccounts(string $startDate, string $endDate, int $companyId): array
    // {
    //     // Step 1: Find move lines related to expenses within date range and company
    //     $moveLineFields = ['account_id', 'name', 'date', 'amount_currency'];
	// 	$moveLineFields=[];
		
    //     $moveLineFilters = [
    //         [
    //             // ['date', '>=', $startDate],
    //             // ['date', '<=', $endDate],
    //             // ['account_type', '=', 'expense_direct_cost'] // Filter for expense accounts
    //             ['account_type', '=', 'expense'] // Filter for expense accounts
    //         ]
    //     ];
    //     $moveLines = $this->fetchData('account.account', $moveLineFields, $moveLineFilters);

    //     // Step 2: Extract unique account IDs
    //     $accountIds = [];
    //     foreach ($moveLines as $line) {
    //         if (!empty($line['account_id'])) {
    //             $accountIds[] = $line['account_id'][0];
    //         }
    //     }
    //     $accountIds = array_unique($accountIds);

    //     if (empty($accountIds)) {
    //         return [];
    //     }

    //     // Step 3: Fetch account details from account.account
    //     $accountFields = ['id', 'code', 'name', 'account_type'];
    //     $accounts = $this->execute('account.account', 'read', [$accountIds, $accountFields]);

    //     // Step 4: Enrich accounts with related expense data
    //     $result = [];
    //     foreach ($accounts as &$account) {
    //         // Find move lines for this account to get expense names
    //         $expenseNames = [];
    //         foreach ($moveLines as $line) {
    //             if ($line['account_id'][0] == $account['id']) {
    //                 $expenseNames[] = $line['name'] ?: 'Unnamed Expense';
    //             }
    //         }
    //         $account['expense_names'] = array_unique($expenseNames);
    //         $result[] = $account;
    //     }

    //     return $result;
    // }
	
		
	
	public function getPaymentMethodId(int $journalId , int $accountId , string $inboundOrOutbound )
	{
		try {
            $filters = [
              [
				  ['journal_id', '=', $journalId],
                ['payment_account_id', '=', $accountId],
                ['payment_type', '=', $inboundOrOutbound]
			  ]
            ];

       
			$fields = [];
            $records = $this->fetchData('account.payment.method.line', $fields,$filters);
            if (empty($records)) {
     //           Log::info("Odoo: No outgoing payment methods found for journal {$journalId} and account {$accountId}");
                return [];
            }
         //   Log::info("Odoo: Fetched " . count($records) . " outgoing payment methods", ['records' => $records]);
            return $records[0]['id']??null;
        } catch (\Exception $e) {
            // Log::error("Odoo Fetch Outgoing Payment Method Error: " . $e->getMessage(), [
            //     'journal_id' => $journalId,
            //     'account_id' => $accountId,
            //     'filters' => $filters,
            //     'trace' => $e->getTraceAsString()
            // ]);
            throw $e;
        }
	}
	
	 
	

}
