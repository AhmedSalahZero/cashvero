<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreContractRequest;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SupplierInvoice;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;

/**
 * ContractsController
 * ------------------------------------------------------------------
 * Manages "Contracts" — a single shared entity used for BOTH Customer
 * Contracts and Supplier Contracts. Every action here is driven by a
 * `$type` route parameter ('Customer' or 'Supplier'), not by two
 * separate controllers. This was confirmed by direct inspection
 * (routes/web.php, this controller, and the Blade views it rendered)
 * before any Vue work started — see the roadmap's Contracts entry.
 *
 * A contract has one of three statuses: Running, Running And Against
 * (a customer contract that's also secured against a supplier
 * contract), or Finished. Each contract has one or more Sales Orders
 * (Customer side) or Purchase Orders (Supplier side) under it, and —
 * for Supplier POs specifically — each PO can further be "allocated"
 * across one or more customers via PoAllocation rows.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/Contracts/Index.vue, one shared page for
 *      both Customer and Supplier, distinguished by the `type` prop
 *      — mirroring this controller's own existing pattern.
 *   ✅ create() / edit() → MIGRATED to Vue + Inertia. Both render the
 *      same shared page, resources/js/Pages/Contracts/Form.vue,
 *      distinguished by a `mode: 'create' | 'edit'` prop. See
 *      buildFormProps() for how getCommonVars()'s existing,
 *      UNCHANGED output is turned into Inertia props.
 *   store() / update() / destroy() / markAsFinished() /
 *   markAsRunningAndAgainst() / storePoAllocations() → UNCHANGED,
 *   deliberately. All of them already respond with a redirect, so no
 *   change was needed for Inertia to work with them.
 *   getContractsForCustomerOrSupplier() / generateRandomCode() /
 *   updateContractsBasedOnCustomer() / updateSalesOrdersBasedOnContract() /
 *   updatePurchaseOrdersBasedOnContract() → UNCHANGED, deliberately.
 *   Small JSON AJAX endpoints, consumed as-is by the new Vue pages
 *   (via window.axios) exactly as they were consumed by jQuery before.
 */
class ContractsController
{
    use GeneralFunctions;

	/**
	 * List page — three tabs (Running / Running And Against /
	 * Finished), each contract row expandable to show its Sales/
	 * Purchase Order sub-rows.
	 *
	 * ✅ MIGRATED to Vue + Inertia. The query logic and $items
	 * shape-building below are UNCHANGED, deliberately — copied
	 * verbatim from the original Blade-rendering version. Only the
	 * final step (building Inertia-friendly, pre-formatted row data
	 * and pre-resolved action URLs, then Inertia::render() instead of
	 * view()) is new presentation-layer code.
	 */
	public function index(Company  $company ,Request $request ,$type)
    {
		// ⚠️ Was hardcoded to `false` for Supplier — this flag purely
		// describes whether any invoice of this $type actually has a
		// project name on it; it was never meant to also gate whether
		// Supplier Contracts get an Invoices button at all. Fixed to
		// mirror the Customer branch. See docblock below re: the
		// Invoices button itself, which no longer reads this flag.
		$hasProjectNameColumn = $type == 'Customer' ? CustomerInvoice::hasProjectNameColumn() : SupplierInvoice::hasProjectNameColumn();
		
		$contractStatues = [
			Contract::RUNNING ,
			Contract::RUNNING_AND_AGAINST ,
			Contract::FINISHED 
		];
		
		/**
		 * * كل تاب ليها بيچينيشن مستقلة بالـ page parameter بتاعها ، عشان
		 * * التنقل في تاب ما يحركش التابات التانية.
		 * * والـ eager loading هنا مهم: من غيره الصفحة كانت بتعمل كويري
		 * * لكل عقد (فواتيره وأوامره وتوزيعاتها) — دي كانت سبب البطء
		 */
		$contracts = [];
		$paginators = [];
		foreach($contractStatues as $contractStatus){
			$pageName = $this->pageNameForStatus($contractStatus);
			$paginator = Contract::where('contracts.company_id',$company->id)
				->where('status',$contractStatus)
				->where('model_type',$type)
				->join('partners','partners.id','=','contracts.partner_id')
				->selectRaw('contracts.*,partners.name as partner_name')
				->orderByRaw('start_date desc , partner_name asc')
				->with($this->relationsToEagerLoad($type))
				->paginate(GeneralFunctions::getPaginationLimit(),['*'],$pageName)
				/**
				 * * active بيخلي التاب المفتوحة تفضل مفتوحة بعد التنقل ،
				 * * وباقي البراميترز بتفضل عشان بيچينيشن التابات التانية
				 * * ما تترجعش لصفحة 1
				 */
				->appends(array_merge($request->except($pageName),['active'=>$contractStatus]));
			$paginators[$contractStatus] = $paginator;
			$contracts[$contractStatus] = $paginator->getCollection();
		}

		$customerOrSupplierContractsText = $type == 'Supplier' ? __('Supplier Contracts') : __('Customer Contracts');
		$items = [];
		foreach($contractStatues as $contractStatus){
			foreach($contracts[$contractStatus] as $index=>$contract){
				$contractId = $contract->id ;
				$invoices = $type === 'Supplier' ? $contract->supplierInvoices : $contract->customerInvoices;
				$items[$contractStatus][$contractId]['parent'] = [
					'name'=>$contract->getName() ,
					'contract'=>$contract,
					'client_name'=>$contract->getClientName(),
					'contract_code'=>$contract->getCode(),
					'start_date'=>$contract->getStartDateFormatted(),
					'end_date'=>$contract->getEndDateFormatted(),
					'currency'=> $contract->getCurrency() ,
					'amount'=>$contract->getAmountFormatted(),
					'invoices'=>$invoices,
					/**
					 * * عقود الموردين المربوطة بعقد العميل ده (parent_id)
					 */
					'related_contracts'=>$contract->relatedContracts
				];
				foreach($contract->getOrders() as $order){
					$items[$contractStatus][$contractId]['sub_items'][$order->id][$order->getOrderColumnName()] =$order->getNumber() ;
					$items[$contractStatus][$contractId]['sub_items'][$order->id]['amount'] =$order->getAmountFormatted() ;
					$items[$contractStatus][$contractId]['sub_items'][$order->id]['amount_raw'] =$order->getAmount() ;
					$items[$contractStatus][$contractId]['sub_items'][$order->id]['id'] =$order->id ;
					$items[$contractStatus][$contractId]['sub_items'][$order->id]['allocations'] =$order->allocations ;
				}
		}
		}

		$commonVars = $this->getCommonVars($company,$type);
		$clientsWithContracts = $commonVars['clientsWithContracts'];

		/**
		 * Everything below this line is new — it turns the $items
		 * array above (built by the untouched logic that used to feed
		 * the Blade view) into plain, pre-formatted arrays with every
		 * action URL pre-resolved, since Inertia serializes props to
		 * JSON and Ziggy isn't installed in this project.
		 */
		/**
		 * ⚠️ REAL BUG FIXED HERE (2026-08 permissions audit, F-04):
		 * $canCreate read `hasAuthFor('view ' . $permissionSlug)` —
		 * the VIEW permission — so the "Add Contract" button appeared
		 * for anyone who could merely look at the list. And $canDelete
		 * did not exist at all, even though 'delete customers contracts'
		 * and 'delete suppliers contracts' are real permissions, so the
		 * delete control was ungated in the UI.
		 *
		 * `$type` is 'Customer' or 'Supplier', giving the module prefix.
		 */
		$permissionModule = strtolower($type) . '_contract';
		$canCreate = hasAuthFor($permissionModule . '.create');
		$canUpdate = hasAuthFor($permissionModule . '.update');
		$canDelete = hasAuthFor($permissionModule . '.delete');
		$canApprove = hasAuthFor($permissionModule . '.approve');

		$mainFunctionalCurrency = $company->getMainFunctionalCurrency();
		$mapInvoice = function ($invoice) use ($mainFunctionalCurrency) {
			return [
				'id' => $invoice->id,
				'invoice_date' => $invoice->getInvoiceDateFormatted(),
				'invoice_number' => $invoice->getInvoiceNumber(),
				'currency' => $invoice->getCurrency(),
				'amount_formatted' => $invoice->getInvoiceAmountFormatted(),
				'withhold_amount_formatted' => $invoice->getTotalWithholdAmountFormatted(),
				'vat_amount_formatted' => $invoice->getVatAmountFormatted(),
				'total_deduction_formatted' => $invoice->getTotalDeductionFormatted(),
				'total_collected_formatted' => $invoice->getTotalCollectedOrPaidFormatted(),
				'due_date_formatted' => $invoice->getDueDateFormatted(),
				'net_balance_formatted' => $invoice->getNetBalanceFormatted(),
				'status_formatted' => $invoice->getStatusFormatted(),
				'aging' => $invoice->getAging(),
				// Same check + same two figures as the original Blade's (i)
				// icon (admin/reports/invoice-report-td.blade.php): only
				// shown when the invoice's own currency differs from the
				// company's main functional currency.
				'is_foreign_currency' => $invoice->getCurrency() !== $mainFunctionalCurrency,
				'amount_in_main_currency_formatted' => number_format((float) $invoice->getNetInvoiceInMainCurrencyAmount(), 2),
				'exchange_rate_formatted' => number_format((float) $invoice->getExchangeRate(), 4),
			];
		};

		$mapAllocation = function ($allocation) {
			return [
				'id' => $allocation->id,
				'partner_id' => $allocation->partner_id,
				'contract_id' => $allocation->contract_id,
				'percentage' => round($allocation->getPercentage(), 2),
				'amount_formatted' => number_format($allocation->getAmount(), 2),
			];
		};

		/**
		 * * صف واحد لكل عقد مورّد مربوط بعقد العميل ، ومعاه أرقام أوامر
		 * * الشراء بتاعته (عقد المورّد الجاي من أودو ليه PO واحد ، بس
		 * * العقد المتعمل بإيد ممكن يكون ليه أكتر من واحد)
		 */
		$mapRelatedContract = function ($relatedContract) {
			return [
				'id' => $relatedContract->id,
				'client_name' => $relatedContract->getClientName(),
				'name' => $relatedContract->getName(),
				'contract_code' => $relatedContract->getCode(),
				'purchase_order_numbers' => $relatedContract->purchasesOrders->map(fn ($purchaseOrder) => $purchaseOrder->getNumber())->filter()->implode(' , '),
				'start_date' => $relatedContract->getStartDateFormatted(),
				'end_date' => $relatedContract->getEndDateFormatted(),
				'currency' => $relatedContract->getCurrency(),
				'amount' => (float) $relatedContract->getAmount(),
				'amount_formatted' => $relatedContract->getAmountFormatted(),
			];
		};

		/**
		 * * الإجمالي بيتحسب لكل عملة لوحدها — عقود الموردين على المشروع
		 * * الواحد ممكن تكون بعملات مختلفة
		 */
		$totalsPerCurrency = function ($relatedContracts) {
			$totals = [];
			foreach ($relatedContracts as $relatedContract) {
				$currency = $relatedContract['currency'] ?: '-';
				$totals[$currency] = ($totals[$currency] ?? 0) + $relatedContract['amount'];
			}

			return collect($totals)->map(fn ($total, $currency) => [
				'currency' => $currency,
				'total_formatted' => number_format($total),
			])->values();
		};

		$contractsForTabs = [];
		foreach ($contractStatues as $contractStatus) {
			$rows = [];
			foreach (($items[$contractStatus] ?? []) as $mainItemId => $parentAndSubData) {
				$parent = $parentAndSubData['parent'];
				$subItems = $parentAndSubData['sub_items'] ?? [];
				/**
				 * * عقود الموردين بتتعرض تحت عقد العميل بس
				 */
				$relatedContracts = $type === 'Customer'
					? collect($parent['related_contracts'])->map($mapRelatedContract)->values()
					: collect();

				$rows[] = [
					'id' => $mainItemId,
					'client_name' => $parent['client_name'],
					'name' => $parent['name'],
					'contract_code' => $parent['contract_code'],
					'start_date' => $parent['start_date'],
					'end_date' => $parent['end_date'],
					'amount_formatted' => $parent['amount'],
					'currency' => $parent['currency'],
					'edit_url' => route('contracts.edit', ['company' => $company->id, 'contract' => $mainItemId, 'type' => $type]),
					'delete_url' => route('contracts.destroy', ['company' => $company->id, 'contract' => $mainItemId, 'type' => $type]),
					'mark_finished_url' => route('contract.mark.as.finished', ['company' => $company->id, 'contract' => $mainItemId, 'type' => $type]),
					'mark_running_and_against_url' => route('contract.mark.as.running.and.against', ['company' => $company->id, 'contract' => $mainItemId, 'type' => $type]),
					'invoices' => collect($parent['invoices'])->map($mapInvoice)->values(),
					'related_contracts' => $relatedContracts,
					'related_contracts_totals' => $totalsPerCurrency($relatedContracts),
					'sub_items' => collect($subItems)->map(function ($subItem) use ($type, $mapAllocation) {
						return [
							'id' => $subItem['id'],
							'order_label' => $type === 'Supplier' ? __('Purchase Order Number') : __('Sales Order Number'),
							'order_number' => $subItem['po_number'] ?? $subItem['so_number'] ?? null,
							'amount_formatted' => $subItem['amount'],
							'amount_raw' => $subItem['amount_raw'],
							'allocations' => $type === 'Supplier' ? collect($subItem['allocations'])->map($mapAllocation)->values() : [],
						];
					})->values(),
				];
			}
			$contractsForTabs[$contractStatus] = $rows;
		}

		return \Inertia\Inertia::render('Contracts/Index', [
			'company' => ['id' => $company->id],
			'type' => $type,
			'activeTab' => $request->query('active') ?: Contract::RUNNING,
			'pageTitle' => $customerOrSupplierContractsText,
			'canCreate' => $canCreate,
			'canUpdate' => $canUpdate,
			'canDelete' => $canDelete,
			'canApprove' => $canApprove,
			'hasProjectNameColumn' => $hasProjectNameColumn,
			/**
			 * * لما الشركة مربوطة بأودو ، توزيع أوامر الشراء بيتحدد من
			 * * أودو نفسه ، فزرار الـ Allocate بيتشال من عقود الموردين
			 */
			'hasOdooCredentials' => $company->hasOdooIntegrationCredentials(),
			'contractStatues' => $contractStatues,
			'contracts' => $contractsForTabs,
			'paginators' => collect($paginators)->map(fn ($paginator) => $paginator->toArray()),
			'createUrl' => route('contracts.create', ['company' => $company->id, 'type' => $type]),
			'tabUrls' => collect($contractStatues)->mapWithKeys(function ($status) use ($company, $type) {
				return [$status => route('contracts.index', ['company' => $company->id, 'type' => $type, 'active' => $status])];
			}),
			'clientsWithContracts' => $type === 'Supplier'
				? $clientsWithContracts->map(fn ($client) => ['id' => $client->id, 'name' => $client->getName()])->values()
				: [],
			'getContractsForCustomerOrSupplierUrl' => route('get.contracts.for.customer.or.supplier', ['company' => $company->id]),
			'storePoAllocationUrl' => route('store.po.allocations', ['company' => $company->id]),
		]);
    }
	/**
	 * * اسم الـ page parameter لكل تاب ، عشان كل تاب تتنقل لوحدها
	 */
	public function pageNameForStatus(string $contractStatus):string
	{
		return $contractStatus.'_page';
	}
	/**
	 * * العلاقات اللي الصفحة بتقراها لكل عقد. من غيرها بيحصل N+1
	 * * (كويري للفواتير وكويري للأوامر وكويري للتوزيعات لكل عقد لوحده)
	 *
	 * @return array<string>
	 */
	protected function relationsToEagerLoad(string $type):array
	{
		$isSupplier = $type == 'Supplier';

		/**
		 * * ملحوظة مهمة: فواتير العقد (supplierInvoices / customerInvoices)
		 * * **مينفعش** تتحط هنا. العلاقة دي فيها where('company_id',$this->company_id)
		 * * ولارافيل وقت الـ eager loading بيبني العلاقة من موديل فاضي ،
		 * * فالشرط بيتحول لـ company_id = null والفواتير بترجع صفر.
		 * * وشيل الشرط مش حل لأن الفهرس الفريد على العقود هو (company_id , code)
		 * * يعني نفس الكود ممكن يتكرر في شركة تانية. البيچينيشن أصلاً بتحدد
		 * * الكويريات دي بعدد صفوف الصفحة الواحدة
		 */
		return [
			'client',
			/**
			 * * التوزيعات موجودة على أمر الشراء بس ، مش على أمر البيع
			 */
			$isSupplier ? 'purchasesOrders.allocations' : 'salesOrders',
			'relatedContracts.client',
			'relatedContracts.purchasesOrders',
		];
	}
	/**
	 * Add Contract form.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Shares the same page component as
	 * edit() (resources/js/Pages/Contracts/Form.vue), distinguished by
	 * a `mode: 'create' | 'edit'` prop — same pattern used everywhere
	 * else in this project (e.g. Time Of Deposit's Form.vue).
	 * getCommonVars() below is UNCHANGED, deliberately — it's the same
	 * type-driven logic (Customer vs Supplier labels/relations/clients)
	 * that already worked, just fed into buildFormProps() instead of a
	 * Blade view.
	 */
	public function create(Request $request,Company $company,string $type)
	{
		return \Inertia\Inertia::render('Contracts/Form', $this->buildFormProps($company, $type, null));
	}

	/**
	 * Turns getCommonVars()'s existing output plus (in edit mode) the
	 * model's own data into the flat, pre-formatted prop shape Inertia
	 * needs. New presentation-layer code only — getCommonVars() itself,
	 * and every getter called on $model below, are pre-existing and
	 * UNCHANGED.
	 */
	protected function buildFormProps(Company $company, string $type, ?Contract $model): array
	{
		$commonVars = $this->getCommonVars($company, $type, $model);
		$orderRelationName = $commonVars['salesOrderOrPurchaseOrderRelationName'];
		$orderNumberField = $commonVars['salesOrderOrPurchaseNoText'];

		$mapPhase = function ($order, int $i) {
			return [
				'percentage' => $order ? $order->getExecutionPercentage($i) : 0,
				'start_date' => $order ? $order->getStartDate($i) : null,
				'end_date' => $order ? $order->getEndDate($i) : null,
				'collection_days' => $order ? $order->getCollectionDays($i) : 0,
			];
		};
		$mapOrder = function ($order) use ($orderNumberField, $mapPhase) {
			return [
				'id' => $order->id,
				'number' => $order->getNumber(),
				'amount' => $order->getAmount(),
				'number_field' => $orderNumberField,
				'phases' => collect(range(1, 5))->map(fn ($i) => $mapPhase($order, $i))->values(),
			];
		};

		return [
			'company' => ['id' => $company->id],
			'type' => $type,
			'mode' => $model ? 'edit' : 'create',
			'formTitle' => $commonVars['formTitle'],
			'clients' => collect($commonVars['clients'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->getName()])->values(),
			'currencies' => getCurrencies(),
			'salesOrderOrPurchaseOrderInformationText' => $commonVars['salesOrderOrPurchaseOrderInformationText'],
			'salesOrderOrPurchaseNumberText' => $commonVars['salesOrderOrPurchaseNumberText'],
			'salesOrderOrPurchaseNoText' => $commonVars['salesOrderOrPurchaseNoText'],
			'salesOrderOrPurchaseOrderRelationName' => $orderRelationName,
			'model' => $model ? [
				'id' => $model->id,
				'name' => $model->getName(),
				'code' => $model->getCode(),
				'partner_id' => $model->getClientId(),
				'start_date' => $model->getStartDate(),
				'end_date' => $model->getEndDate(),
				'amount' => $model->getAmount(),
				'currency' => $model->getCurrency(),
				'exchange_rate' => $model->getExchangeRate(),
				'orders' => $model->{$orderRelationName}->map($mapOrder)->values(),
			] : null,
			'submitUrl' => $model
				? route('contracts.update', ['company' => $company->id, 'contract' => $model->id, 'type' => $type])
				: route('contracts.store', ['company' => $company->id, 'type' => $type]),
			'backUrl' => route('contracts.index', ['company' => $company->id, 'type' => $type]),
			'generateCodeUrl' => route('generate.unique.rondom.contract.code', ['company' => $company->id, 'type' => $type]),
			'addNewPartnerUrl' => route('add.new.partner', ['company' => $company->id, 'type' => $type]),
		];
	}
	public function getCommonVars(Company $company,string $type,$model = null):array 
	{
		$salesOrderOrPurchaseOrderInformationText = __('Sales Order Information');
		$salesOrderOrPurchaseNumberText =  $type == 'Supplier' ? __('Purchase Order Number') : __('Sales Order Number'); 
		$salesOrderOrPurchaseNoText =  $type == 'Supplier' ? 'po_number' : 'so_number'; 
		$salesOrderOrPurchaseOrderRelationName = $type == 'Supplier' ? 'purchasesOrders' : 'salesOrders'; ;
		$contractsRelationName = 'contracts' ;
		$salesOrderOrPurchaseOrderObject =  $type == 'Supplier' ? new PurchaseOrder() : new SalesOrder(); 
		$clients = Partner::onlyCompany($company->id);
		$formTitle = __('Customer Contract Form');
		$clientsWithContracts = collect([]);
		if($type == 'Supplier'){
			$clients =$clients->onlySuppliers();
			$salesOrderOrPurchaseOrderInformationText = __('Purchases Order Information');
			$formTitle = __('Supplier Contract Form');
			$clientsWithContracts = Partner::onlyCompany($company->id)->onlyCustomers()->onlyThatHaveCustomerContracts()->orderBy('name')->get();
			$reverseTypeText = __('Customers');
		}else{
			$clients =$clients->onlyCustomers();
			$reverseTypeText = __('Suppliers');
		}
		$clients = $clients->get();
		return [
			'reverseTypeText'=>$reverseTypeText,
			'contractsRelationName'=>$contractsRelationName,
			'clientsWithContracts'=>$clientsWithContracts,
			'formTitle'=>$formTitle,
			'company'=>$company,
			'clients'=>$clients,
			'type'=>$type,
			'salesOrderOrPurchaseOrderInformationText'=>$salesOrderOrPurchaseOrderInformationText,
			'salesOrderOrPurchaseNumberText'=>$salesOrderOrPurchaseNumberText,
			'salesOrderOrPurchaseNoText'=>$salesOrderOrPurchaseNoText,
			'salesOrderOrPurchaseOrderObject'=>$salesOrderOrPurchaseOrderObject,
			'salesOrderOrPurchaseOrderRelationName'=>$salesOrderOrPurchaseOrderRelationName,
			'model'=>$model,
			'inEditMode'=>isset($model)
		];
	}
	public function store(StoreContractRequest $request, Company $company,string $type){
			$contract = new Contract ;
			$contract->storeBasicForm($request);
			return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);
	}
	public function edit(Request $request,Company $company,Contract $contract,string $type)
	{
		return \Inertia\Inertia::render('Contracts/Form', $this->buildFormProps($company, $type, $contract));
	}
	public function update(Company $company , StoreContractRequest $request , Contract $contract,string $type){
			/**
			 * Confirmed business rule (project owner, 2026-07-24): a contract voluntarily losing
			 * its collateral status (moving from RUNNING_AND_AGAINST back to plain RUNNING —
			 * not the natural "finished" transition, which is a different, already-handled
			 * case) must be blocked if the facility's available room is less than the limit
			 * this contract itself contributed there, since that gap means real transactions
			 * already rely on the room this contract provided.
			 */
			$wasCollateral = $contract->status === Contract::RUNNING_AND_AGAINST;
			$movingToPlainRunning = $request->get('status') === Contract::RUNNING;
			if ($wasCollateral && $movingToPlainRunning) {
				$collateralContribution = $contract->getActiveOverdraftAgainstAssignmentOfContractLimitContribution();
				if ($collateralContribution) {
					$collateralRule = new \App\Rules\OverdraftCollateralRemovalRule(
						'overdraft_against_assignment_of_contract_bank_statements',
						'overdraft_against_assignment_of_contract_id',
						$collateralContribution['facility_id'],
						$company->id,
						$collateralContribution['amount']
					);
					if (!$collateralRule->passes('status', null)) {
						return redirect()->back()->with('fail', $collateralRule->message());
					}
				}
			}
			$contract->storeBasicForm($request);
			return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);
	}
	public function destroy(Company $company , Request $request , Contract $contract,string $type){
		/**
		 * Confirmed business rule (project owner, 2026-07-24): deleting a contract that's
		 * currently collateral must be blocked under the same condition as removing its
		 * collateral status above — see the matching check and explanation in update().
		 */
		if ($contract->status === Contract::RUNNING_AND_AGAINST) {
			$collateralContribution = $contract->getActiveOverdraftAgainstAssignmentOfContractLimitContribution();
			if ($collateralContribution) {
				$collateralRule = new \App\Rules\OverdraftCollateralRemovalRule(
					'overdraft_against_assignment_of_contract_bank_statements',
					'overdraft_against_assignment_of_contract_id',
					$collateralContribution['facility_id'],
					$company->id,
					$collateralContribution['amount']
				);
				if (!$collateralRule->passes('status', null)) {
					return redirect()->back()->with('fail', $collateralRule->message());
				}
			}
		}
		$contract->delete();
		return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);  
	}	
	public function markAsFinished(Company $company , Request $request , Contract $contract,string $type){
		$contract->update([
			'status'=>Contract::FINISHED ,
		]);
		return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);  
	}
	public function markAsRunningAndAgainst(Company $company , Request $request , Contract $contract,string $type){
		$contract->update([
			'status'=>Contract::RUNNING_AND_AGAINST ,
		]);
		return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);  
	}
	public function updateContractsBasedOnCustomer(Request $request , Company $company ){
		$customer = Partner::find($request->get('customerId'));
		$isFromLc = $request->boolean('is_lc');
		if(!$customer){
			return response()->json([
				'contracts'=>[]
			]);
		}
		$contracts = $customer->contracts;
		$contractFormatted = [];
		foreach($contracts as $contract){
			$contractCanBeReturned= $isFromLc ? $contract->forSupplier()  :$contract->forCustomer();
			if($contractCanBeReturned){
				$contractFormatted[$contract->name] = [
					'id'=>$contract->id ,
					'currency'=>$contract->getCurrency(),
					// Added for the Customers Opening Balance form (Vue) —
					// used to auto-fill a read-only Contract Code field once
					// a contract is chosen, instead of leaving it free-text.
					// Additive only; existing 'id'/'currency' keys unchanged,
					// so any other page already consuming this endpoint is
					// unaffected.
					'code'=>$contract->getCode(),
				];
			}
		}
		$isCustomer = $customer->is_customer ;
		return response()->json([
			'contracts'=>$contractFormatted,
			'is_customer'=>$isCustomer
		]);
	}
	public function updateSalesOrdersBasedOnContract(Request $request , Company $company ){
		$contract = Contract::find($request->get('contractId'));
		$purchaseOrders = $contract->salesOrders->pluck('so_number','id')->toArray();
		return response()->json([
			'purchase_orders'=>$purchaseOrders
		]);
	}
	public function updatePurchaseOrdersBasedOnContract(Request $request , Company $company ){
		$contractId = $request->get('contractId') ;
		if($contractId == -1){ // no po
			return response()->json([
				'status'=>true ,
				'showTextInputForNewPO'=>true 
			]);
		}
		if($contractId == -2){ // existing po
			$currentPoNumber = $request->get('currentNewPurchaseOrder');
			return response()->json([
				'status'=>true ,
				'purchase_orders'=>PurchaseOrder::where('company_id',$company->id)->where('po_number','!=',$currentPoNumber)->where('contract_id',null)->pluck('po_number','id')
			]);
		}
		$contract = Contract::find($contractId);
		
		$purchaseOrders = $contract->purchasesOrders->pluck('po_number','id')->toArray();
		return response()->json([
			'purchase_orders'=>$purchaseOrders
		]);
	}
	public function getContractsForCustomerOrSupplier(Company $company , Request $request){
		$partner = Partner::find($request->get('partnerId'));
		if(!$partner){
			return [
				'contracts'=>[]
			];
		}
		/**
		 * @var Partner $partner 
		 */
		$contracts = $partner->contracts->sortBy('name') ;
		if(!$request->boolean('inEditMode')){
			$contracts = $contracts->where('parent_id',null)->values() ;
		}
		return response()->json([
			'status'=>true ,
			'contracts'=>$contracts 
		]);
		
	}
	public function generateRandomCode(Request $request,Company $company, string $modelType)
	{
		$partnerId = $request->get('partnerId');
		$partner = Partner::find($partnerId);
		$startDate = $request->get('startDate');
		$code = Contract::generateRandomContract($company->id,$partner->getName(),$startDate,$modelType);
		return response()->json([
			'code'=>$code
		]);
		
	}	
	public function storePoAllocations(Request $request , Company $company){
		$purchaseOrder = PurchaseOrder::find($request->get('po_id'));
		$purchaseOrder->allocations()->delete();
		foreach( $request->get('poAllocations',[])  as $index => $purchaseOrderArr){
				$purchaseOrderArr['allocation_amount'] = number_unformat($purchaseOrderArr['allocation_amount'] ?? 0);
				$purchaseOrder->allocations()->create($purchaseOrderArr);
		}
		return redirect()->back();
	}
}
