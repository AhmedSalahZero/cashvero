<?php
namespace App\Services\Api;

use App\Helpers\HStr;
use App\Http\Controllers\MoneyReceivedController;
use App\Http\Requests\StoreMoneyReceivedRequest;
use App\Models\CashVeroBranch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\CustomerInvoice;
use App\Models\FinancialInstitutionAccount;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Api\Traits\AuthTrait;
use App\Services\Api\Traits\CommonHelper;
use App\Services\Api\Traits\HasUnlink;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Str;

class OdooService
{
	use AuthTrait , CommonHelper,HasUnlink;

	/**
	 * * بتجيب الـ odoo_id لليوزر المبعوت مش لليوزر اللي عامل لوجن
	 * * بتصفّر القيمة القديمة الأول عشان AuthTrait يعمل authenticate من جديد
	 * * بترجّع الـ uid لو نجح و null لو فشل، ومش بترمي استثناء
	 */
	public static function refreshUserOdooId(Company $company, User $user): ?int
	{
		if (! $company->hasOdooIntegrationCredentials($user)) {
			return null;
		}

		$user->update(['odoo_id' => null]);

		try {
			return (new self($company, $user))->getUid();
		} catch (\Throwable $e) {
			Log::error('Odoo odoo_id refresh failed: '.$e->getMessage(), [
				'company_id' => $company->id,
				'user_id' => $user->id,
				'odoo_username' => $user->getOdooDBUserName(),
				'exception' => get_class($e),
			]);

			return null;
		}
	}

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
			/**
			 * * كان بيمرر $startDate — ودا متغير من برة اللوب (تاريخ بداية
			 * * المزامنة)، فالكود كان بيطلع بشهر/سنة المزامنة مش المشروع نفسه
			 * * (مثلًا c-01-2026 لمشروع بادئ في مايو). دلوقتي بيستخدم تاريخ
			 * * بداية المشروع الجاري زي مسار الإنشاء اليدوي بالظبط.
			 */
			$code = Contract::generateRandomContract($companyId,$currentOdooCustomerName,$currentProjectStartDate,$modelType);
			$partnerId = Partner::handlePartnerForOdoo($currentOdooCustomerId ,$currentOdooCustomerName,1,0,false,false,$companyId  );
			/**
			 * * لازم نقيّد البحث بالشركة والنوع: عقود الموردين بقت كمان
			 * * بتتخزن بـ odoo_id (بس بتاع الـ PO) ، ومن غير التقييد ده
			 * * ممكن مشروع رقمه 5 يلاقي عقد مورّد من PO رقمه 5
			 */
			$oldProject = Contract::where('odoo_id',$currentOdooProjectId)->where('company_id',$companyId)->where('model_type',$modelType)->first();

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
				/**
			 * REAL BUG FIXED HERE (same Carbon 3 sign-bug class already
			 * found and fixed in TimeOfDeposit, Cheque, FactoringTransaction,
			 * and LetterOfGuaranteeIssuanceRenewalDateController).
			 *
			 * $currentProjectEndDate (normally the LATER date) was the
			 * base, $currentProjectStartDate (the earlier date) was the
			 * argument -- exactly the order that returns a NEGATIVE
			 * month-count under Carbon 3's signed-by-default diffInMonths(),
			 * on every Odoo-synced contract. Fixed by forcing $absolute = true.
			 */
			'duration'=>Carbon::make($currentProjectEndDate)->diffInMonths($currentProjectStartDate, true)
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
						'name', // الاسم المجرد ، ودا اللي بيتكتب في origin بتاع الـ PO
						'display_name', // so_number
						'currency_id',
						'amount_total',
						'project_id'
					]
				]);
				$salesOrderFormatted = [];
				$salesOrderOdooIds = [];
				$salesOrderNames = [];
				foreach($salesOrders as $orderIndex => $salesOrderArr){
					$projectFormatted['currency']=$salesOrderArr['currency_id'][1];
					$currentOrderIndex =$orderIndex+1;
					$currentSalesOrderId = $salesOrderArr['id'];
					$currentSalesOrderAmount = $salesOrderArr['amount_total'];
					$projectAmount += $currentSalesOrderAmount;
					$salesOrderOdooIds[] = $currentSalesOrderId;
					if(! empty($salesOrderArr['name'])){
						$salesOrderNames[] = $salesOrderArr['name'];
					}

					$currentSalesOrderArr = [
						'odoo_id'=>$currentSalesOrderId,
						'so_number'=>$salesOrderArr['display_name'],
						// 'id'=>$currentSalesOrderId,
						'amount'=>$currentSalesOrderAmount,
						'execution_percentage_'.$currentOrderIndex=>100,
						'start_date_'.$currentOrderIndex=>$currentProjectStartDate,
						'end_date_'.$currentOrderIndex=>$currentProjectEndDate,
			//			'execution_days_'.$currentOrderIndex=>Carbon::make($currentProjectEndDate)->diffInMonths($currentProjectStartDate, true), // Carbon 3 sign-bug fix applied here too, in case this line is ever re-enabled
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
					/**
					 * * كل PO مربوط بالـ SOs بتاعة المشروع ده بيبقى عقد مورّد
					 * * تحت عقد العميل ، وفواتير الـ PO بتتربط بعقد المورّد
					 */
					$this->syncSupplierContractsFromPurchaseOrders($contract,$salesOrderOdooIds,$salesOrderNames,$projectArr['account_id'][0] ?? null,$currentProjectEndDate,$companyId);
				}

		}



	}

	/**
	 * * بتجيب الـ POs المربوطة بالـ SOs بتاعة المشروع من أودو ، وبتعمل
	 * * لكل PO عقد مورّد تحت عقد العميل — نفس الشكل اللي في الجداول
	 * * عندنا (عقد المورّد ليه PO واحد و parent_id بيشاور على عقد العميل)
	 *
	 * @param  array<int>  $salesOrderOdooIds
	 * @param  array<string>  $salesOrderNames
	 * @return array<Contract>
	 */
	public function syncSupplierContractsFromPurchaseOrders(Contract $customerContract, array $salesOrderOdooIds, array $salesOrderNames, ?int $projectAnalyticAccountId, string $projectEndDate, int $companyId): array
	{
		$purchaseOrders = $this->getPurchaseOrdersForProject($salesOrderOdooIds, $salesOrderNames, $projectAnalyticAccountId);

		return $this->storeSupplierContractsFromPurchaseOrders($customerContract, $purchaseOrders, $projectEndDate, $companyId);
	}

	/**
	 * * في أودو 18 فيه ٣ طرق قياسية للربط ، وبناخد الاتحاد بينهم:
	 * *   1) purchase.order.line.sale_order_id — بيتملي لما أودو نفسه
	 * *      يولّد الـ PO من الـ SO (موديول sale_purchase وهو auto_install)
	 * *   2) purchase.order.origin — المستند المصدر ، بيتكتب من الـ
	 * *      procurement أو بإيد المشتري
	 * *   3) analytic_distribution على سطور الـ PO فيها الحساب التحليلي
	 * *      بتاع المشروع — دي أوسع طريقة وبتمسك أوامر الشراء اللي
	 * *      اتحجزت على المشروع من غير ما حد يكتب الـ SO عليها
	 *
	 * @param  array<int>  $salesOrderOdooIds
	 * @param  array<string>  $salesOrderNames
	 * @return array<array<string,mixed>>
	 */
	protected function getPurchaseOrdersForProject(array $salesOrderOdooIds, array $salesOrderNames, ?int $projectAnalyticAccountId): array
	{
		$purchaseOrderIds = array_merge(
			$this->purchaseOrderIdsFromSaleOrderLines($salesOrderOdooIds),
			$this->purchaseOrderIdsFromOrigin($salesOrderNames),
			$this->purchaseOrderIdsFromProjectAnalyticAccount($projectAnalyticAccountId)
		);
		$purchaseOrderIds = array_values(array_unique($purchaseOrderIds));

		if (! count($purchaseOrderIds)) {
			return [];
		}

		$purchaseOrders = $this->readFromOdoo('purchase.order', 'read', [$purchaseOrderIds], [
			'fields'=>[
				'id',
				'name', // po_number
				'origin',
				'partner_id', // المورّد
				'currency_id',
				'amount_total',
				'date_order',
				'state',
				'invoice_ids', // فواتير المورّد المربوطة بالـ PO — حقل مخزّن في أودو 18
			],
		]);

		return is_array($purchaseOrders) ? $purchaseOrders : [];
	}

	/**
	 * @param  array<int>  $salesOrderOdooIds
	 * @return array<int>
	 */
	protected function purchaseOrderIdsFromSaleOrderLines(array $salesOrderOdooIds): array
	{
		if (! count($salesOrderOdooIds)) {
			return [];
		}

		$purchaseOrderLines = $this->readFromOdoo('purchase.order.line', 'search_read', [
			[['sale_order_id', 'in', array_values($salesOrderOdooIds)]],
			['order_id'],
		]);

		if (! is_array($purchaseOrderLines)) {
			return [];
		}

		$purchaseOrderIds = [];
		foreach ($purchaseOrderLines as $purchaseOrderLine) {
			if (is_array($purchaseOrderLine['order_id'] ?? null)) {
				$purchaseOrderIds[] = $purchaseOrderLine['order_id'][0];
			}
		}

		return array_values(array_unique($purchaseOrderIds));
	}

	/**
	 * @param  array<string>  $salesOrderNames
	 * @return array<int>
	 */
	protected function purchaseOrderIdsFromOrigin(array $salesOrderNames): array
	{
		$salesOrderNames = array_values(array_filter($salesOrderNames));

		if (! count($salesOrderNames)) {
			return [];
		}

		/**
		 * * origin ممكن يكون فيه أكتر من مستند مفصولين بفاصلة ، فبنفلتر
		 * * في أودو بـ ilike (فلترة تقريبية سريعة) وبعدين بنتأكد بالظبط
		 * * في PHP عشان S00001 ماتمسكش S000012
		 */
		$domain = [];
		foreach ($salesOrderNames as $index => $salesOrderName) {
			if ($index > 0) {
				array_unshift($domain, '|');
			}
			$domain[] = ['origin', 'ilike', $salesOrderName];
		}

		$purchaseOrders = $this->readFromOdoo('purchase.order', 'search_read', [$domain, ['id', 'origin']]);

		if (! is_array($purchaseOrders)) {
			return [];
		}

		$purchaseOrderIds = [];
		foreach ($purchaseOrders as $purchaseOrder) {
			foreach (explode(',', (string) ($purchaseOrder['origin'] ?? '')) as $origin) {
				if (in_array(trim($origin), $salesOrderNames, true)) {
					$purchaseOrderIds[] = $purchaseOrder['id'];
					break;
				}
			}
		}

		return array_values(array_unique($purchaseOrderIds));
	}

	/**
	 * * أوامر الشراء المحجوزة على الحساب التحليلي بتاع المشروع.
	 * * analytic_distribution في أودو 17+ حقل Json ، وبيقبل البحث بـ in
	 * * على أرقام الحسابات التحليلية
	 *
	 * @return array<int>
	 */
	protected function purchaseOrderIdsFromProjectAnalyticAccount(?int $projectAnalyticAccountId): array
	{
		if (is_null($projectAnalyticAccountId)) {
			return [];
		}

		$purchaseOrderLines = $this->readFromOdoo('purchase.order.line', 'search_read', [
			[['analytic_distribution', 'in', [$projectAnalyticAccountId]]],
			['order_id'],
		]);

		if (! is_array($purchaseOrderLines)) {
			return [];
		}

		$purchaseOrderIds = [];
		foreach ($purchaseOrderLines as $purchaseOrderLine) {
			if (is_array($purchaseOrderLine['order_id'] ?? null)) {
				$purchaseOrderIds[] = $purchaseOrderLine['order_id'][0];
			}
		}

		return array_values(array_unique($purchaseOrderIds));
	}

	/**
	 * * الجزء اللي بيكتب عندنا — مش بيكلم أودو خالص ، بياخد صفوف الـ POs
	 * * زي ما هي راجعة من أودو
	 *
	 * @param  array<array<string,mixed>>  $purchaseOrders
	 * @return array<Contract>
	 */
	public function storeSupplierContractsFromPurchaseOrders(Contract $customerContract, array $purchaseOrders, string $projectEndDate, int $companyId): array
	{
		$supplierContracts = [];

		foreach ($purchaseOrders as $purchaseOrderArr) {
			/**
			 * * الـ RFQ الملغي مش عقد
			 */
			if (($purchaseOrderArr['state'] ?? null) === 'cancel') {
				continue;
			}

			$vendor = $purchaseOrderArr['partner_id'] ?? null;

			if (! is_array($vendor)) {
				continue;
			}

			$odooPurchaseOrderId = $purchaseOrderArr['id'];
			$purchaseOrderNumber = $purchaseOrderArr['name'];
			$amount = $purchaseOrderArr['amount_total'] ?? 0;
			$currency = is_array($purchaseOrderArr['currency_id'] ?? null) ? $purchaseOrderArr['currency_id'][1] : $customerContract->currency;
			$startDate = substr((string) ($purchaseOrderArr['date_order'] ?? ''), 0, 10) ?: $customerContract->start_date;
			$endDate = $projectEndDate < $startDate ? $startDate : $projectEndDate;
			$partnerId = Partner::handlePartnerForOdoo($vendor[0], $vendor[1], 0, 1, false, false, $companyId);

			$oldSupplierContract = Contract::where('odoo_id', $odooPurchaseOrderId)->where('company_id', $companyId)->where('model_type', 'Supplier')->first();
			$oldPurchaseOrder = PurchaseOrder::where('odoo_id', $odooPurchaseOrderId)->where('company_id', $companyId)->first();

			/**
			 * * لو نفس الـ PO اتحجز على أكتر من مشروع هيتنقل لآخر عقد
			 * * عميل بيطالب بيه — بنسجّلها عشان تبان بدل ما تعدي في صمت
			 */
			if ($oldSupplierContract && $oldSupplierContract->parent_id && (int) $oldSupplierContract->parent_id !== (int) $customerContract->id) {
				Log::warning('Odoo purchase order moved to another customer contract', [
					'company_id' => $companyId,
					'purchase_order_odoo_id' => $odooPurchaseOrderId,
					'from_contract_id' => $oldSupplierContract->parent_id,
					'to_contract_id' => $customerContract->id,
				]);
			}

			$purchaseOrderFormatted = [
				'odoo_id'=>$odooPurchaseOrderId,
				'po_number'=>$purchaseOrderNumber,
				'amount'=>$amount,
				'execution_percentage_1'=>100,
				'start_date_1'=>$startDate,
				'end_date_1'=>$endDate,
				'collection_days_1'=>0,
				'company_id'=>$companyId,
			];

			/**
			 * * بنمرر الـ id عشان الصف يتحدّث مش يتمسح ويتعمل من جديد ،
			 * * وبالتالي الـ po allocations المربوطة بيه ما تضيعش
			 */
			if ($oldPurchaseOrder) {
				$purchaseOrderFormatted['id'] = $oldPurchaseOrder->id;
			}

			$supplierContractFormatted = [
				'odoo_id'=>$odooPurchaseOrderId,
				'code'=>$oldSupplierContract ? $oldSupplierContract->code : Contract::generateRandomContract($companyId, $vendor[1], $startDate, 'Supplier'),
				/**
				 * * اسم العقد = اسم المشروع ، مش رقم الـ PO. رقم الـ PO
				 * * مكانه po_number وبيظهر في صف الأمر تحت العقد ، والمورّد
				 * * ليه عمود Partner Name لوحده
				 */
				'name'=>$customerContract->name,
				'model_type'=>'Supplier',
				'partner_id'=>$partnerId,
				'parent_id'=>$customerContract->id,
				'start_date'=>$startDate,
				'end_date'=>$endDate,
				'currency'=>$currency,
				'amount'=>$amount,
				'company_id'=>$companyId,
				/**
				 * * نفس فيكس Carbon 3 اللي في عقد العميل فوق — $absolute = true
				 * * عشان الفرق ما يرجعش بالسالب
				 */
				'duration'=>Carbon::make($endDate)->diffInMonths($startDate, true),
				'purchasesOrders'=>[$purchaseOrderFormatted],
			];

			if ($oldSupplierContract) {
				$supplierContractFormatted['id'] = $oldSupplierContract->id;
			}

			$supplierContract = $oldSupplierContract ?: new Contract;
			$supplierContract->storeBasicForm((new Request())->merge($supplierContractFormatted));

			$this->linkSupplierInvoicesToContract($purchaseOrderArr['invoice_ids'] ?? [], $purchaseOrderNumber, $supplierContract, $companyId);

			$supplierContracts[] = $supplierContract;
		}

		return $supplierContracts;
	}

	/**
	 * * ربط فواتير المورّد بعقد المورّد. بنمشي بالـ odoo_id الجاي من
	 * * purchase.order.invoice_ids (ربط محاسبي حقيقي مش مطابقة نصوص) ،
	 * * وبنسند كمان على رقم الـ PO للفواتير اللي اتسحبت قبل العقد
	 *
	 * @param  array<int>  $billOdooIds
	 */
	protected function linkSupplierInvoicesToContract(array $billOdooIds, string $purchaseOrderNumber, Contract $supplierContract, int $companyId): void
	{
		$contractData = [
			'contract_code'=>$supplierContract->code,
			'contract_name'=>$supplierContract->name,
			'project_name'=>$supplierContract->name,
		];

		if (count($billOdooIds)) {
			SupplierInvoice::where('company_id', $companyId)->whereIn('odoo_id', $billOdooIds)->update($contractData);
		}

		SupplierInvoice::where('company_id', $companyId)
			->where(SupplierInvoice::SO_OR_PO_NUMBER, $purchaseOrderNumber)
			/**
			 * * بنربط الفواتير اللي لسه من غير عقد ، وبنحدّث كمان اللي
			 * * مربوطة بالعقد ده أصلاً عشان اسم العقد يفضل متزامن —
			 * * من غير ما نخطف فاتورة مربوطة بعقد تاني
			 */
			->where(function ($query) use ($supplierContract) {
				$query->whereNull('contract_code')->orWhere('contract_code', $supplierContract->code);
			})
			->update($contractData);
	}

	/**
	 * * أودو بيرجّع array فيها faultCode بدل ما يرمي استثناء ، فلو الحقل
	 * * أو الموديول مش موجود ما ينفعش نكمل على الراجع ده كأنه داتا
	 */
	protected function readFromOdoo(string $model, string $method, array $args, array $kwargs = [])
	{
		try {
			$result = $this->models->execute_kw($this->db, $this->uid, $this->password, $model, $method, $args, $kwargs);
		} catch (\Throwable $e) {
			Log::error('Odoo read failed: '.$model.'.'.$method.' — '.$e->getMessage(), [
				'company_id' => $this->company_id ?? null,
			]);

			return null;
		}

		if (is_array($result) && isset($result['faultCode'])) {
			Log::error('Odoo read returned a fault: '.$model.'.'.$method.' — '.($result['faultString'] ?? ''), [
				'company_id' => $this->company_id ?? null,
			]);

			return null;
		}

		return $result;
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
