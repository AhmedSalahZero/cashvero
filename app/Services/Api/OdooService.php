<?php
namespace App\Services\Api;

use App\Helpers\HStr;
use App\Http\Controllers\MoneyReceivedController;
use App\Interfaces\Models\ISyncsWithOdooChartOfAccount;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Str;

class OdooService
{
	use AuthTrait , CommonHelper,HasUnlink;

	/**
	 * * بتجيب الـ odoo_id لليوزر المبعوت مش لليوزر اللي عامل لوجن
	 * * بتفضّي القيمة في الذاكرة بس عشان AuthTrait يعمل authenticate من جديد
	 * * بترجّع الـ uid لو نجح و null لو فشل، ومش بترمي استثناء
	 */
	public static function refreshUserOdooId(Company $company, User $user): ?int
	{
		if (! $company->hasOdooIntegrationCredentials($user)) {
			return null;
		}

		$originalOdooId = $user->getOdooId();

		/**
		 * * الكونستراكتور بيعيد المصادقة بس لما getOdooId() ترجع null ،
		 * * فبنفضّي العمود في الذاكرة بس — مش في الداتابيز
		 *
		 * * قبل كده كان بيتحفظ null في الصف الاول ، فلو المصادقة فشلت
		 * * (باسورد اتغير ، اودو واقع ، الشبكة قطعت) اليوزر كان بيفضل
		 * * بـ odoo_id فاضي للابد و تكامله بيتكسر بالكامل — و ده حصل فعلا
		 */
		$user->setAttribute('odoo_id', null);

		try {
			$uid = (new self($company, $user))->getUid();
		} catch (\Throwable $e) {
			Log::error('Odoo odoo_id refresh failed: '.$e->getMessage(), [
				'company_id' => $company->id,
				'user_id' => $user->id,
				'odoo_username' => $user->getOdooDBUserName(),
				'exception' => get_class($e),
			]);

			$uid = null;
		}

		if (is_null($uid)) {
			/**
			 * * المصادقة ما نجحتش ، فبنسيب القيمة القديمة زي ما هي بدل ما
			 * * نكسر تكامل شغال . الصف ما اتلمسش اصلا فمفيش حاجة نرجّعها
			 * * غير القيمة اللي في الذاكرة
			 */
			$user->setAttribute('odoo_id', $originalOdooId);

			return null;
		}

		return $uid;
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
		
	/**
	 * * كاش لأسعار الصرف اللي اتقريت من اودو في الرن الواحد
	 *
	 * @var array<int, float|null>
	 */
	protected array $currencyRateCache = [];

	/**
	 * * سعر الصرف بالعملة الاساسية للشركة مقابل وحدة واحدة من العملة دي
	 *
	 * * اودو بتخزن res.currency.rate بالعكس : كام وحدة من العملة دي مقابل
	 * * وحدة واحدة من عملة الشركة .. فعملة الشركة نفسها بتقرا 1 و الدولار
	 * * مثلا بيقرا 0.0207 مقابل الجنيه .. احنا بنخزن العكس (كام جنيه للدولار)
	 * * وده نفس التحويل اللي getInvoices() بتعمله بالظبط (1 / invoice_currency_rate)
	 *
	 * * لو القراءة فشلت بنرجع null و الكولوم ما بيتلمسش خالص — احسن من اننا
	 * * نكتب 1 و نبوظ قيمة صح متسجلة عندنا
	 */
	protected function getExchangeRateForCurrency(?int $currencyId): ?float
	{
		if (! $currencyId) {
			return null;
		}

		if (array_key_exists($currencyId, $this->currencyRateCache)) {
			return $this->currencyRateCache[$currencyId];
		}

		$rate = null;

		try {
			$records = $this->models->execute_kw($this->db, $this->uid, $this->password, 'res.currency', 'read', array(array($currencyId)), [
				'fields' => ['rate'],
			]);
			$odooRate = $records[0]['rate'] ?? null;

			if (is_numeric($odooRate) && (float) $odooRate > 0) {
				$rate = round(1 / (float) $odooRate, 5);
			}
		} catch (\Throwable $e) {
			Log::warning('Odoo currency rate lookup failed', [
				'currency_id' => $currencyId,
				'error' => $e->getMessage(),
			]);
		}

		return $this->currencyRateCache[$currencyId] = $rate;
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

		/**
		 * * أودو بيرجّع array فيها faultCode بدل ما يرمي استثناء ، و هي
		 * * array بردو — فمن غير الشرط ده اللوب كان بيمشي على القيم
		 * * النصية بتاعتها و يضرب
		 */
		if (! is_array($projects) || isset($projects['faultCode']) || ! count($projects)) {
			return;
		}

		/**
		 * * قبل كده كل مشروع كان بياخد ٦ نداءات لوحده : بحث و قراءة
		 * * أوامر البيع ، و تلات مصادر لأوامر الشراء ، و قراءتها. يعني
		 * * مية مشروع = ستمية نداء XML-RPC بالتتابع ، كل واحد برحلة
		 * * شبكة كاملة. دلوقتي بنجيبهم كلهم لكل المشاريع مرة واحدة قبل
		 * * اللوب ، فالعدد ثابت مهما كان عدد المشاريع.
		 */
		$salesOrdersByProject = $this->getSalesOrdersByProject(array_column($projects, 'id'));
		$purchaseOrdersByProject = $this->getPurchaseOrdersByProject($projects, $salesOrdersByProject);

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
				$salesOrders = $salesOrdersByProject[$currentOdooProjectId] ?? [];
				$salesOrderFormatted = [];
				foreach($salesOrders as $orderIndex => $salesOrderArr){
					$projectFormatted['currency']=$salesOrderArr['currency_id'][1];
					/**
					 * * العملة كانت بتتقري من اودو من غير سعر الصرف .. فالعقد
					 * * بيتخزن بـ exchange_rate = 1 مهما كانت عملته ، و اي تقرير
					 * * بيحول للعملة الاساسية بيطلع غلط
					 *
					 * * لو الجلب فشل ما بنحطش المفتاح اصلا — storeBasicForm
					 * * بتكتب المفاتيح اللي موجودة بس ، فالقيمة القديمة بتفضل
					 */
					$currentExchangeRate = $this->getExchangeRateForCurrency($salesOrderArr['currency_id'][0] ?? null);
					if ($currentExchangeRate !== null) {
						$projectFormatted['exchange_rate'] = $currentExchangeRate;
					}
					$currentOrderIndex =$orderIndex+1;
					$currentSalesOrderId = $salesOrderArr['id'];
					$currentSalesOrderAmount = $salesOrderArr['amount_total'];
					$projectAmount += $currentSalesOrderAmount;

					$currentSalesOrderArr = [
						'odoo_id'=>$currentSalesOrderId,
						'so_number'=>$salesOrderArr['display_name'],
						// 'id'=>$currentSalesOrderId,
						'amount'=>$currentSalesOrderAmount,
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
					/**
					 * * اودو مالهاش نسبة تنفيذ اصلا ، فالـ 100 دي قيمة افتراضية
					 * * لأمر بيع جديد مش بيانات جاية من اودو .. كانت بتتكتب في
					 * * كل مزامنة و بتمسح اي نسبة المستخدم ظبطها بايده
					 *
					 * * لو فيه قيمة متسجلة عندنا (حتي لو صفر — الصفر قرار برضه)
					 * * ما بنبعتش المفتاح ، فالعمود ما بيتلمسش
					 */
					$executionPercentageKey = 'execution_percentage_'.$currentOrderIndex;
					if (! $oldSalesOrder || $oldSalesOrder->{$executionPercentageKey} === null) {
						$currentSalesOrderArr[$executionPercentageKey] = 100;
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
					$this->storeSupplierContractsFromPurchaseOrders($contract, $purchaseOrdersByProject[$currentOdooProjectId] ?? [], $currentProjectEndDate, $companyId);
				}

		}



	}

	/**
	 * * كل أوامر البيع لكل المشاريع في نداء واحد ، مجمّعة بالمشروع
	 *
	 * * أودو بيرجّع النتيجة بترتيب البحث الافتراضي ، و التجميع بيحافظ
	 * * على الترتيب النسبي جوه كل مشروع — و ده مهم لأن ترتيب أمر البيع
	 * * هو اللي بيحدد رقم الخانة (start_date_1 .. start_date_5)
	 *
	 * * project_id بقى مطلوب في القراءة عشان نجمّع بيه — قبل التجميع
	 * * كان بيتشال لأن البحث نفسه كان لمشروع واحد
	 *
	 * @param  array<int, int>  $projectIds
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	protected function getSalesOrdersByProject(array $projectIds): array
	{
		$projectIds = array_values(array_unique(array_filter($projectIds)));

		if (! count($projectIds)) {
			return [];
		}

		$salesOrders = $this->readFromOdoo('sale.order', 'search_read', [
			[['project_id', 'in', $projectIds]],
			[
				'id',
				'name', // الاسم المجرد ، ودا اللي بيتكتب في origin بتاع الـ PO
				'display_name', // so_number
				'currency_id',
				'amount_total',
				'project_id',
			],
		]);

		if (! is_array($salesOrders)) {
			return [];
		}

		$byProject = [];

		foreach ($salesOrders as $salesOrder) {
			if (! is_array($salesOrder['project_id'] ?? null)) {
				continue;
			}

			$byProject[$salesOrder['project_id'][0]][] = $salesOrder;
		}

		return $byProject;
	}

	/**
	 * * كل أوامر الشراء لكل المشاريع : تلات نداءات بحث + قراءة واحدة
	 * * على دفعات ، مجمّعة بالمشروع
	 *
	 * @param  array<int, array<string, mixed>>  $projects
	 * @param  array<int, array<int, array<string, mixed>>>  $salesOrdersByProject
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	protected function getPurchaseOrdersByProject(array $projects, array $salesOrdersByProject): array
	{
		$keysByProject = [];
		$allSalesOrderIds = [];
		$allSalesOrderNames = [];
		$allAnalyticAccountIds = [];

		foreach ($projects as $projectArr) {
			$projectId = $projectArr['id'] ?? null;

			if (is_null($projectId)) {
				continue;
			}

			$salesOrderIds = [];
			$salesOrderNames = [];

			foreach ($salesOrdersByProject[$projectId] ?? [] as $salesOrder) {
				$salesOrderIds[] = $salesOrder['id'];

				if (! empty($salesOrder['name'])) {
					$salesOrderNames[] = $salesOrder['name'];
				}
			}

			$analyticAccountId = $projectArr['account_id'][0] ?? null;

			$keysByProject[$projectId] = [
				'sale_orders' => $salesOrderIds,
				'names' => $salesOrderNames,
				'analytic_account' => $analyticAccountId,
			];

			$allSalesOrderIds = array_merge($allSalesOrderIds, $salesOrderIds);
			$allSalesOrderNames = array_merge($allSalesOrderNames, $salesOrderNames);

			if ($analyticAccountId) {
				$allAnalyticAccountIds[] = $analyticAccountId;
			}
		}

		$bySaleOrder = $this->purchaseOrderIdsBySaleOrder(array_values(array_unique($allSalesOrderIds)));
		$byOriginName = $this->purchaseOrderIdsByOriginName(array_values(array_unique($allSalesOrderNames)));
		$byAnalyticAccount = $this->purchaseOrderIdsByAnalyticAccount(array_values(array_unique($allAnalyticAccountIds)));

		$this->warnAboutUngroupedPurchaseOrders($bySaleOrder, $byAnalyticAccount);

		$purchaseOrderIdsByProject = [];
		$allPurchaseOrderIds = [];

		foreach ($keysByProject as $projectId => $keys) {
			$purchaseOrderIds = [];

			foreach ($keys['sale_orders'] as $salesOrderId) {
				$purchaseOrderIds = array_merge($purchaseOrderIds, $bySaleOrder[$salesOrderId] ?? []);
			}

			foreach ($keys['names'] as $salesOrderName) {
				$purchaseOrderIds = array_merge($purchaseOrderIds, $byOriginName[$salesOrderName] ?? []);
			}

			if ($keys['analytic_account']) {
				$purchaseOrderIds = array_merge($purchaseOrderIds, $byAnalyticAccount[$keys['analytic_account']] ?? []);
			}

			$purchaseOrderIds = array_values(array_unique($purchaseOrderIds));
			$purchaseOrderIdsByProject[$projectId] = $purchaseOrderIds;
			$allPurchaseOrderIds = array_merge($allPurchaseOrderIds, $purchaseOrderIds);
		}

		$purchaseOrdersById = [];

		foreach ($this->readPurchaseOrders(array_values(array_unique($allPurchaseOrderIds))) as $purchaseOrder) {
			$purchaseOrdersById[$purchaseOrder['id']] = $purchaseOrder;
		}

		$byProject = [];

		foreach ($purchaseOrderIdsByProject as $projectId => $purchaseOrderIds) {
			$byProject[$projectId] = [];

			foreach ($purchaseOrderIds as $purchaseOrderId) {
				if (isset($purchaseOrdersById[$purchaseOrderId])) {
					$byProject[$projectId][] = $purchaseOrdersById[$purchaseOrderId];
				}
			}
		}

		return $byProject;
	}

	/**
	 * * أودو المفروض يرجّع المصدر اللي اتبحث بيه (sale_order_id /
	 * * analytic_distribution) لأنه جزء من الدومين نفسه. لو ما رجّعوش
	 * * ما نقدرش نعرف أمر الشراء ده تبع أنهي مشروع ، فبنسجّله بدل ما
	 * * نلزقه على مشروع بالتخمين
	 *
	 * @param  array<int|string, array<int, int>>  ...$groups
	 */
	protected function warnAboutUngroupedPurchaseOrders(array ...$groups): void
	{
		$ungrouped = [];

		foreach ($groups as $group) {
			$ungrouped = array_merge($ungrouped, $group[self::UNGROUPED_SOURCE] ?? []);
		}

		if (! count($ungrouped)) {
			return;
		}

		Log::warning('Odoo returned purchase order lines without the source they were searched by', [
			'company_id' => $this->company_id ?? null,
			'purchase_order_ids' => array_values(array_unique($ungrouped)),
		]);
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
	 * * مفتاح الصفوف اللي أودو ما رجّعش معاها المصدر اللي اتبحث بيه
	 *
	 * * المسار بتاع المشروع الواحد بيضمّها عادي زي ما كان قبل التجميع ،
	 * * و المسار المجمّع بيتخطّاها و بيسجّل تحذير — أهون من إننا نلزق
	 * * أمر شراء على مشروع بالتخمين
	 */
	protected const UNGROUPED_SOURCE = '*';

	protected function getPurchaseOrdersForProject(array $salesOrderOdooIds, array $salesOrderNames, ?int $projectAnalyticAccountId): array
	{
		$purchaseOrderIds = array_merge(
			$this->purchaseOrderIdsFromSaleOrderLines($salesOrderOdooIds),
			$this->purchaseOrderIdsFromOrigin($salesOrderNames),
			$this->purchaseOrderIdsFromProjectAnalyticAccount($projectAnalyticAccountId)
		);
		$purchaseOrderIds = array_values(array_unique($purchaseOrderIds));

		return $this->readPurchaseOrders($purchaseOrderIds);
	}

	/**
	 * * قراءة صفوف أوامر الشراء ، مشتركة بين مسار المشروع الواحد و
	 * * المسار المجمّع
	 *
	 * @param  array<int, int>  $purchaseOrderIds
	 * @return array<int, array<string, mixed>>
	 */
	protected function readPurchaseOrders(array $purchaseOrderIds): array
	{
		if (! count($purchaseOrderIds)) {
			return [];
		}

		$purchaseOrders = $this->readFromOdooInBatches('purchase.order', $purchaseOrderIds, [
			'id',
			'name', // po_number
			'origin',
			'partner_id', // المورّد
			'currency_id',
			'amount_total',
			'date_order',
			'state',
			'invoice_ids', // فواتير المورّد المربوطة بالـ PO — حقل مخزّن في أودو 18
		]);

		return is_array($purchaseOrders) ? $purchaseOrders : [];
	}

	/**
	 * @param  array<int>  $salesOrderOdooIds
	 * @return array<int>
	 */
	protected function purchaseOrderIdsFromSaleOrderLines(array $salesOrderOdooIds): array
	{
		return $this->flattenPurchaseOrderIds($this->purchaseOrderIdsBySaleOrder($salesOrderOdooIds));
	}

	/**
	 * @param  array<string>  $salesOrderNames
	 * @return array<int>
	 */
	protected function purchaseOrderIdsFromOrigin(array $salesOrderNames): array
	{
		return $this->flattenPurchaseOrderIds($this->purchaseOrderIdsByOriginName($salesOrderNames));
	}

	/**
	 * @return array<int>
	 */
	protected function purchaseOrderIdsFromProjectAnalyticAccount(?int $projectAnalyticAccountId): array
	{
		if (is_null($projectAnalyticAccountId)) {
			return [];
		}

		return $this->flattenPurchaseOrderIds($this->purchaseOrderIdsByAnalyticAccount([$projectAnalyticAccountId]));
	}

	/**
	 * * أوامر الشراء المربوطة بأوامر بيع ، مجمّعة بأمر البيع
	 *
	 * * الدالة دي بتتنده مرة واحدة بكل أوامر البيع بتاعة كل المشاريع ،
	 * * فمحتاجين نرجّع sale_order_id كمان عشان نعرف كل أمر شراء تبع
	 * * أنهي مشروع
	 *
	 * @param  array<int, int>  $salesOrderOdooIds
	 * @return array<int|string, array<int, int>>
	 */
	protected function purchaseOrderIdsBySaleOrder(array $salesOrderOdooIds): array
	{
		if (! count($salesOrderOdooIds)) {
			return [];
		}

		$purchaseOrderLines = $this->readFromOdoo('purchase.order.line', 'search_read', [
			[['sale_order_id', 'in', array_values($salesOrderOdooIds)]],
			['order_id', 'sale_order_id'],
		]);

		if (! is_array($purchaseOrderLines)) {
			return [];
		}

		$grouped = [];

		foreach ($purchaseOrderLines as $purchaseOrderLine) {
			if (! is_array($purchaseOrderLine['order_id'] ?? null)) {
				continue;
			}

			$key = is_array($purchaseOrderLine['sale_order_id'] ?? null)
				? $purchaseOrderLine['sale_order_id'][0]
				: self::UNGROUPED_SOURCE;

			$grouped[$key][] = $purchaseOrderLine['order_id'][0];
		}

		return $this->uniquePurchaseOrderIdsPerKey($grouped);
	}

	/**
	 * * origin ممكن يكون فيه أكتر من مستند مفصولين بفاصلة ، فبنفلتر
	 * * في أودو بـ ilike (فلترة تقريبية سريعة) وبعدين بنتأكد بالظبط
	 * * في PHP عشان S00001 ماتمسكش S000012
	 *
	 * @param  array<int, string>  $salesOrderNames
	 * @return array<string, array<int, int>>
	 */
	protected function purchaseOrderIdsByOriginName(array $salesOrderNames): array
	{
		$salesOrderNames = array_values(array_filter($salesOrderNames));

		if (! count($salesOrderNames)) {
			return [];
		}

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

		$grouped = [];

		foreach ($purchaseOrders as $purchaseOrder) {
			foreach (explode(',', (string) ($purchaseOrder['origin'] ?? '')) as $origin) {
				$origin = trim($origin);

				if (in_array($origin, $salesOrderNames, true)) {
					$grouped[$origin][] = $purchaseOrder['id'];
				}
			}
		}

		return $this->uniquePurchaseOrderIdsPerKey($grouped);
	}

	/**
	 * * أوامر الشراء المحجوزة على الحسابات التحليلية بتاعة المشاريع.
	 * * analytic_distribution في أودو 17+ حقل Json ، مفاتيحه أرقام
	 * * الحسابات التحليلية (و ممكن يكون المفتاح أكتر من رقم مفصولين
	 * * بفاصلة لما التوزيع على أكتر من محور)
	 *
	 * @param  array<int, int>  $analyticAccountIds
	 * @return array<int|string, array<int, int>>
	 */
	protected function purchaseOrderIdsByAnalyticAccount(array $analyticAccountIds): array
	{
		$analyticAccountIds = array_values(array_unique(array_filter(array_map('intval', $analyticAccountIds))));

		if (! count($analyticAccountIds)) {
			return [];
		}

		$purchaseOrderLines = $this->readFromOdoo('purchase.order.line', 'search_read', [
			[['analytic_distribution', 'in', $analyticAccountIds]],
			['order_id', 'analytic_distribution'],
		]);

		if (! is_array($purchaseOrderLines)) {
			return [];
		}

		$grouped = [];

		foreach ($purchaseOrderLines as $purchaseOrderLine) {
			if (! is_array($purchaseOrderLine['order_id'] ?? null)) {
				continue;
			}

			$purchaseOrderId = $purchaseOrderLine['order_id'][0];
			$lineAccountIds = $this->analyticAccountIdsOfLine($purchaseOrderLine['analytic_distribution'] ?? null, $analyticAccountIds);

			if (! count($lineAccountIds)) {
				$grouped[self::UNGROUPED_SOURCE][] = $purchaseOrderId;

				continue;
			}

			foreach ($lineAccountIds as $analyticAccountId) {
				$grouped[$analyticAccountId][] = $purchaseOrderId;
			}
		}

		return $this->uniquePurchaseOrderIdsPerKey($grouped);
	}

	/**
	 * @param  array<int, int>  $wantedAccountIds
	 * @return array<int, int>
	 */
	private function analyticAccountIdsOfLine($analyticDistribution, array $wantedAccountIds): array
	{
		if (! is_array($analyticDistribution)) {
			return [];
		}

		$analyticAccountIds = [];

		foreach (array_keys($analyticDistribution) as $key) {
			foreach (explode(',', (string) $key) as $analyticAccountId) {
				$analyticAccountId = (int) trim($analyticAccountId);

				if ($analyticAccountId && in_array($analyticAccountId, $wantedAccountIds, true)) {
					$analyticAccountIds[] = $analyticAccountId;
				}
			}
		}

		return array_values(array_unique($analyticAccountIds));
	}

	/**
	 * @param  array<int|string, array<int, int>>  $grouped
	 * @return array<int|string, array<int, int>>
	 */
	private function uniquePurchaseOrderIdsPerKey(array $grouped): array
	{
		return array_map(
			static fn (array $purchaseOrderIds): array => array_values(array_unique($purchaseOrderIds)),
			$grouped
		);
	}

	/**
	 * @param  array<int|string, array<int, int>>  $grouped
	 * @return array<int, int>
	 */
	protected function flattenPurchaseOrderIds(array $grouped): array
	{
		if (! count($grouped)) {
			return [];
		}

		return array_values(array_unique(array_merge(...array_values($grouped))));
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
			/**
			 * * نفس قاعدة أمر البيع : اودو مالهاش نسبة تنفيذ ، فالـ 100 دي
			 * * قيمة افتراضية لأمر شراء جديد بس .. لو المستخدم ظبطها ما بنلمسهاش
			 */
			if (! $oldPurchaseOrder || $oldPurchaseOrder->execution_percentage_1 === null) {
				$purchaseOrderFormatted['execution_percentage_1'] = 100;
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
	/**
	 * The fields the invoice import actually reads.
	 *
	 * This list used to be entirely commented out, leaving $fields = [] —
	 * and an empty field list makes Odoo return EVERY readable field of
	 * account.move (around 200 in Odoo 18 Enterprise, including the HTML
	 * narration and the computed invoice_payments_widget / tax_totals).
	 * The importer consumes twelve of them. Naming them keeps the payload
	 * to what is used; every entry below is read in startImportInvoices().
	 */
	/**
	 * * حجم الدفعة في قراءة أودو
	 *
	 * * أودو بيبني الحقول المحسوبة (زي tax_totals و
	 * * invoice_payments_widget) في بايثون لكل صف على حدة وقت القراءة ،
	 * * و بيخلّص الرد بالكامل قبل ما يبعت أول بايت. فقراءة كل الفواتير
	 * * في نداء واحد كانت بتخلي النداء ده يقعد دقايق و الاستريم يقفل
	 * * قبل ما يوصل أي حاجة.
	 */
	public const READ_BATCH_SIZE = 200;

	/**
	 * * قراءة صفوف أودو على دفعات بدل نداء واحد عملاق
	 *
	 * * لو دفعة واحدة فشلت بنرجّع null — مش نص النتيجة. ده شرط أساسي
	 * * للسلامة لأن syncDeletedInvoices() بتعتبر أي فاتورة مش موجودة في
	 * * الراجع ده "اتمسحت من أودو" و بتمسحها عندنا ، فرد ناقص معناه مسح
	 * * فواتير سليمة.
	 *
	 * * بنستخدم execute() مش readFromOdoo() عشان انقطاع الاتصال أو
	 * * التايم أوت يفضل استثناء طالع للمستخدم زي ما كان بالظبط ، مش
	 * * يتبلع و الاستيراد يقول "تمّت" و هو ما قراش حاجة.
	 *
	 * @param  array<int, int>  $ids
	 * @param  array<int, string>  $fields
	 * @return array<int, array<string, mixed>>|null
	 */
	protected function readInBatches(string $model, array $ids, array $fields): ?array
	{
		$rows = [];

		foreach (array_chunk(array_values(array_unique($ids)), self::READ_BATCH_SIZE) as $chunk) {
			$batch = $this->execute($model, 'read', [$chunk], ['fields' => $fields]);

			if (! is_array($batch) || isset($batch['faultCode'])) {
				return null;
			}

			foreach ($batch as $row) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * * نفس الفكرة بس بالمسار المتسامح (readFromOdoo) اللي بيسجّل الغلط
	 * * و يرجّع null — ده المسار اللي أوامر الشراء شغالة بيه من الأول ،
	 * * و مفيش حاجة بتتمسح بناءً عليه
	 *
	 * @param  array<int, int>  $ids
	 * @param  array<int, string>  $fields
	 * @return array<int, array<string, mixed>>|null
	 */
	protected function readFromOdooInBatches(string $model, array $ids, array $fields): ?array
	{
		$rows = [];

		foreach (array_chunk(array_values(array_unique($ids)), self::READ_BATCH_SIZE) as $chunk) {
			$batch = $this->readFromOdoo($model, 'read', [$chunk], ['fields' => $fields]);

			if (! is_array($batch)) {
				return null;
			}

			foreach ($batch as $row) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	public const INVOICE_FIELDS = [
		'id',
		'name',                                 // invoice_number
		'move_type',                            // in_invoice / out_invoice
		'invoice_date',
		'invoice_date_due',
		'invoice_origin',                       // so_number / po_number
		'invoice_currency_rate',                // exchange rate
		'amount_untaxed_in_currency_signed',
		'partner_id',
		'currency_id',
		'tax_totals',                           // vat + withhold
		'invoice_payments_widget',              // collected / paid
	];

	/**
	 * @param  array<int, string>|null  $fields  narrow the read further —
	 *         syncDeletedInvoices only needs the ids.
	 */
	protected function getInvoices(string $startDate,string $endDate, ?array $fields = null)
	{
		$fields = $fields ?? self::INVOICE_FIELDS;
		$filters = array(array(array('move_type', 'in', [
			'in_invoice',
			'out_invoice'
		])
		,array('state', '=', 'posted'),
			array('write_date', '>=', $startDate),
			array('write_date', '<=', $endDate),
		));
		/**
		 * * قبل كده fetchData() كانت بتعمل search و بعدين read واحد بكل
		 * * الـ ids مرة واحدة. الـ search رخيص (أرقام بس) ، الغالي هو
		 * * الـ read ، فبنقسّمه على دفعات عشان مفيش نداء واحد يقرّب من
		 * * أي مهلة.
		 */
		$invoiceIds = $this->execute('account.move', 'search', $filters);

		if (! is_array($invoiceIds) || isset($invoiceIds['faultCode'])) {
			return null;
		}

		if (! count($invoiceIds)) {
			return [];
		}

		return $this->readInBatches('account.move', $invoiceIds, $fields);
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
	


	/**
	 * * حجم الدفعة في سؤال أودو "الأرقام دي لسه موجودة؟"
	 *
	 * * الرد أرقام مجردة من غير أي حقل محسوب ، فالدفعة تقدر تكون أكبر
	 * * بكتير من دفعة القراءة الكاملة
	 */
	public const EXISTENCE_CHECK_BATCH_SIZE = 1000;

	/**
	 * * بتسأل أودو: من الأرقام دي ، إيه اللي لسه موجود عندك؟
	 *
	 * * قبل كده الفحص ده كان بيجيب كل فواتير ٤٥٠ يوم من أودو و يطرحها
	 * * من اللي عندنا. الطرح ده كان بيمسح فواتير سليمة في تلات حالات:
	 * *
	 * *   ١) أي رد غلط من أودو (fault) كان array بردو ، و
	 * *      array_column عليه بترجّع [] — فكل فاتورة عندنا كانت بتبان
	 * *      كأنها "اتمسحت من أودو"
	 * *
	 * *   ٢) الفلتر عندنا كان على invoice_date و الفلتر عند أودو على
	 * *      write_date ، و دول عمودين مختلفين تماما. فاتورة اتعدّلت في
	 * *      أودو بعد نهاية المدى (أو حتى النهاردة ، لأن
	 * *      write_date <= '2026-09-17' معناها <= الساعة ١٢ بالليل)
	 * *      كانت بتختفي من رد أودو و تتمسح عندنا و هي موجودة
	 * *
	 * *   ٣) رد فاضي — قاعدة غلط أو صلاحيات ناقصة — كان بيمسح كل
	 * *      الفواتير في المدى
	 *
	 * * السؤال بالأرقام مالوش اللبس ده: إحنا بنبعت أرقامنا و أودو
	 * * بيرجّع اللي لسه موجود منها ، فمفيش أي مدى أو عمود تواريخ في
	 * * النص. و هو أرخص كمان — بحث بالمفتاح الأساسي من غير أي قراءة.
	 *
	 * @param  array<int, int>  $odooIds
	 * @return array<int, true>|null  مفتاح لكل رقم لسه موجود ، أو null لو أودو ما ردّش صح
	 */
	protected function existingInvoiceIdsInOdoo(array $odooIds): ?array
	{
		$existing = [];

		foreach (array_chunk(array_values(array_unique($odooIds)), self::EXISTENCE_CHECK_BATCH_SIZE) as $chunk) {
			$found = $this->execute('account.move', 'search', [[
				['id', 'in', $chunk],
				['move_type', 'in', ['in_invoice', 'out_invoice']],
				['state', '=', 'posted'],
			]]);

			/**
			 * * دفعة فشلت = مش عارفين ، مش "مش موجودة". بنرجّع null
			 * * فالمزامنة كلها بتتوقف من غير ما تمسح حاجة.
			 */
			if (! is_array($found) || isset($found['faultCode'])) {
				return null;
			}

			foreach ($found as $odooId) {
				$existing[(int) $odooId] = true;
			}
		}

		return $existing;
	}

	private function syncDeletedInvoices(int $companyId, string $odooEndDate)
	{
		$startDate = Carbon::make($odooEndDate)->subDays(450)->format('Y-m-d');
		$endDate = $odooEndDate;

		$localOdooIdsByClass = [];
		$allLocalOdooIds = [];

		foreach ([CustomerInvoice::class, SupplierInvoice::class] as $invoiceClass) {
			/**
			 * * pluck بدل get : إحنا محتاجين أرقام نقارن بيها بس ، و
			 * * get() كانت بتحمّل فواتير ٤٥٠ يوم كلها كـ Eloquent models
			 * * كاملة (ستين عمود في نسختين لكل صف). الموديلات بتتحمّل
			 * * دلوقتي للمحذوفة بس — و دي عادة حفنة صفوف.
			 */
			$localOdooIds = $invoiceClass::where('company_id', $companyId)
				->where('invoice_date', '>=', $startDate)
				->where('invoice_date', '<=', $endDate)
				->where('odoo_id', '>', 0)
				->pluck('odoo_id', 'id');

			$localOdooIdsByClass[$invoiceClass] = $localOdooIds;

			foreach ($localOdooIds as $odooId) {
				$allLocalOdooIds[] = (int) $odooId;
			}
		}

		if (! count($allLocalOdooIds)) {
			return;
		}

		$existingInOdoo = $this->existingInvoiceIdsInOdoo($allLocalOdooIds);

		if (is_null($existingInOdoo)) {
			Log::warning('Skipped the deleted-invoice sync: Odoo did not answer the existence check', [
				'company_id' => $companyId,
				'checked_invoices' => count($allLocalOdooIds),
			]);

			return;
		}

		/**
		 * * صمام أمان: لو أودو قال إن ولا رقم واحد من بتوعنا موجود ،
		 * * ده على الأرجح اتصال بقاعدة غلط أو صلاحيات ناقصة — مش إن
		 * * الشركة مسحت كل فواتيرها في يوم واحد. بنوقف و نسجّل بدل ما
		 * * نمسح كل حاجة على رد مشكوك فيه.
		 */
		if (! count($existingInOdoo)) {
			Log::warning('Skipped the deleted-invoice sync: Odoo reported that none of our invoices exist', [
				'company_id' => $companyId,
				'checked_invoices' => count($allLocalOdooIds),
			]);

			return;
		}

		foreach ($localOdooIdsByClass as $invoiceClass => $localOdooIds) {
			$deletedLocalIds = [];

			foreach ($localOdooIds as $localId => $odooId) {
				if (! isset($existingInOdoo[(int) $odooId])) {
					$deletedLocalIds[] = $localId;
				}
			}

			if (! count($deletedLocalIds)) {
				continue;
			}

			foreach ($invoiceClass::whereIn('id', $deletedLocalIds)->cursor() as $invoice) {
				/**
				 * * الفاتورة اتمسحت في اودو ، بس لو عندنا تسويات نازلة
				 * * عليها فمسحها هنا كان بيسيب التسويات يتيمة — مبالغ
				 * * محسوبة على رصيد الشريك من غير ما نعرف مقابل ايه
				 *
				 * * التعارض ده لازم بني ادم يحله (يشيل التسوية الاول ،
				 * * او يرجّع الفاتورة في اودو) ، فبنسيبها و نكمّل بدل
				 * * ما نمسح غلط او نوقف الاستيراد كله
				 */
				try {
					$invoice->delete();
				} catch (\InvalidArgumentException $e) {
					Log::warning('Kept a locally settled invoice that was deleted in Odoo', [
						'invoice' => getModelNameWithoutNamespace($invoice).'#'.$invoice->getKey(),
						'odoo_id' => $invoice->odoo_id,
						'company_id' => $companyId,
						'reason' => $e->getMessage(),
					]);

					continue;
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
	/**
	 * * شجرة حسابات اودو كاملة , بتتسحب مرة واحدة بس لكل instance
	 * * قبل كدا كانت بتتسحب مع كل حساب لوحده , يعني مزامنة ٢٠ حساب = ٢٠ نداء تقيل
	 * * و دا اللي كان بيعمل timeout في الجوب
	 */
	private ?array $chartOfAccountsKeyedByCode = null ;

	private function getChartOfAccountsKeyedByCode(): array
	{
		if ($this->chartOfAccountsKeyedByCode === null) {
			$chartOfAccounts = $this->fetchData('account.account',['id','code'],[[]]);
			$this->chartOfAccountsKeyedByCode = collect($chartOfAccounts)->keyBy('code')->toArray();
		}

		return $this->chartOfAccountsKeyedByCode ;
	}

	public function getChartOfAccountIdFromOdooCode(string $odooCode)
	{
		return  $this->getChartOfAccountsKeyedByCode()[$odooCode]['id']??null;

	}
	/**
	 * * اودو بيرجع null لما ما يلاقيش سطر طريقة دفع مطابق (مثلا اليومية لسه
	 * * ما اتظبطش عليها سطر شيكات) . لو كتبنا الـ null ده فوق رقم صحيح كان
	 * * متخزن من مزامنة قبل كدا بنكون مسحنا ربط شغال بايدينا , والحركة اللي
	 * * بعده بتقع في اودو بـ "Please define a payment method line on your payment"
	 *
	 * * فا بنكتب العمود بس في حالتين :
	 * *   1. اودو رجعلنا رقم فعلا
	 * *   2. القيمة القديمة اصلا مش رقم صالح (null او '[]' اللي كانت بتتخزن
	 * *      كنص من نتيجة بحث فاضية قديمة) فمفيش حاجة نحافظ عليها
	 */
	private function keepOldValueIfNewIsEmpty(array $newValues , array $currentValues): array
	{
		$valuesToUpdate = [];
		foreach($newValues as $column => $newValue){
			if(!is_null($newValue)){
				$valuesToUpdate[$column] = $newValue ;
				continue ;
			}
			$currentValue = $currentValues[$column] ?? null ;
			$currentValueIsUsable = is_numeric($currentValue) && (int) $currentValue > 0 ;
			if(!$currentValueIsUsable){
				$valuesToUpdate[$column] = null ;
			}
		}
		return $valuesToUpdate ;
	}
	/**
	 * * بترجع true لو الحساب اترط فعلا بحساب في شجرة حسابات اودو
	 * * و false لو الكود مش موجود في اودو (ساعتها الربط القديم بيفضل زي ما هو)
	 * * علشان اللي بينده يقدر يعرف المستخدم بدل ما الموضوع يعدي في صمت
	 *
	 * * الباراميتر بقى
	 * * ISyncsWithOdooChartOfAccount
	 * * بدل
	 * * FinancialInstitutionAccount
	 * * لان القرض متوسط الاجل بقى هو كمان حساب ينفع تدفع منه، ومحتاج نفس
	 * * الربط بالظبط. جسم الميثود ما اتغيرش: هو اصلا مش بيستخدم غير
	 * * getOdooCode() و getAttributes() و update().
	 */
	public function syncFinancialInstitutions(Model&ISyncsWithOdooChartOfAccount $financialInstitutionAccount): bool
	{
		$odooSetting = $this->company->odooSetting;

		$chartOfAccounts = $this->getChartOfAccountsKeyedByCode();
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
						
						
						$valuesThatMustNotBeWipedOut = $this->keepOldValueIfNewIsEmpty([
							'journal_id'=>$journalId ,
							'odoo_inbound_transfer_payment_method_id'=>$odooInboundTransferPaymentMethodId??null ,
							'odoo_outbound_transfer_payment_method_id'=>$odooOutboundTransferPaymentMethodId??null,
							'odoo_inbound_cheque_payment_method_id'=>$odooInboundChequePaymentMethodId??null ,
							'odoo_outbound_cheque_payment_method_id'=>$odooOutboundChequePaymentMethodId??null,
						],$financialInstitutionAccount->getAttributes());

						$financialInstitutionAccount->update(array_merge([
							'odoo_id'=>$chartOfAccountId,
						],$valuesThatMustNotBeWipedOut));

						return true ;
					}

				}
		//	}


		return false ;

	}
	/**
	 * * بترجع true لو الخزنة اترطبت فعلا بحساب في شجرة حسابات اودو
	 * * و false لو الكود مش موجود في اودو (ساعتها الربط القديم بيفضل زي ما هو)
	 * * علشان اللي بينده يقدر يعرف المستخدم بدل ما الموضوع يعدي في صمت
	 */
	public function syncBranchSafe(?string $odooCode,int $companyId): bool
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
		if(!$chartOfAccountId){
			return false ;
		}

		$branches = DB::table('branch')->where('company_id',$companyId)->where('odoo_code',$odooCode);
		$currentBranch = (clone $branches)->first();

		$valuesThatMustNotBeWipedOut = $this->keepOldValueIfNewIsEmpty([
			'journal_id'=>$journalId,
			'odoo_inbound_transfer_payment_method_id'=>$odooInboundTransferPaymentMethodId??null ,
			'odoo_outbound_transfer_payment_method_id'=>$odooOutboundTransferPaymentMethodId??null,
			'odoo_inbound_cheque_payment_method_id'=>$odooInboundChequePaymentMethodId??null ,
			'odoo_outbound_cheque_payment_method_id'=>$odooOutboundChequePaymentMethodId??null,
		],$currentBranch ? (array) $currentBranch : []);

		$branches->update(array_merge([
			'odoo_id'=>$chartOfAccountId,
		],$valuesThatMustNotBeWipedOut));

		return true ;
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
	
		
	
	/**
	 * * بترجع null لو اودو مالوش payment method line على الجورنال دا بالحساب دا
	 * * (قبل كدا كانت بترجع [] و دي كانت بتتخزن في العمود كنص '[]'
	 * * و بعدين تتحول لصفر وتتبعت لاودو فيطلع ايرور مبهم)
	 */
	public function getPaymentMethodId(int $journalId , int $accountId , string $inboundOrOutbound ): ?int
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
                return null;
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
