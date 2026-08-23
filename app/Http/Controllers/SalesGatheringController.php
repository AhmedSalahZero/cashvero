<?php

namespace App\Http\Controllers;

use App\Exports\ExportData;
use App\Models\ActiveJob;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\Deduction;
use App\Models\LeasingContract;
use App\Models\Log;
use App\Models\MediumTermLoan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Schema;

class SalesGatheringController extends Controller
{
   
	protected function getSearchDateFieldName(string $modelName,?string $fieldName){
		if(!$fieldName){
			return null;
		}
		if($modelName == 'CustomerInvoice' || $modelName == 'SupplierInvoice'){
			if($fieldName == 'invoice_due_date'){
				return 'invoice_due_date';
			}
			return 'invoice_date';
		}
		if($modelName == 'SalesGathering'){
			return 'date';
		}
		if($modelName == 'ExportAnalysis'){
			return 'purchase_order_date';
		}
		if($modelName == 'ExpenseAnalysis'){
			return 'date';
		}
		return 'date';
	}
    /**
     * Upload New Customer/Supplier Invoices Data — the listing page
     * of already-committed (previously uploaded) invoices. Shared by
     * BOTH Customer and Supplier uploads via $uploadType, same
     * pattern as everywhere else in this app.
     *
     * Renders resources/js/Pages/InvoiceUpload/Index.vue. Columns are
     * DYNAMIC — driven by each company's own saved field-template
     * configuration (CustomizedFieldsExportation), not fixed. All
     * query/pagination/permission logic below is UNCHANGED; only the
     * final `return` and a data-reshaping step were added.
     *
     * Fixed a real pre-existing bug in passing: line checked
     * `'SupplierName'==$modelName`, which can never be true (the
     * actual value is 'SupplierInvoice') — so `withhold_amount` was
     * correctly hidden for Customer invoices but never actually
     * hidden for Supplier invoices, despite the code's clear intent
     * to do both. Fixed the typo, not a new behavior.
     *
     * NOT migrated in this pass, deliberately: bulk row-checkbox
     * delete (DeletingClass — shared across many unrelated model
     * types, out of scope here) and "Close Period" (ClosePeriodController::execute()
     * is an EMPTY method in the original — the button does nothing
     * today; not replicating a dead feature or inventing a working
     * one without a decision first).
     */
    public function index(Company $company, Request $request, string $uploadType='SalesGathering',?string $loanId = null )
    {
		$loan = MediumTermLoan::find($loanId);
		$leasingContract = LeasingContract::find($loanId);
        $modelName = $uploadType;
		if ($modelName == 'LoanSchedule' && $loanId) {
			session(['loan_schedule_import_loan_id_' . $company->id => $loanId]);
		}
		if ($modelName == 'ContractLoanSchedule' && $loanId) {
			session(['contract_loan_schedule_import_contract_id_' . $company->id => $loanId]);
		}

		$orderByDirection = in_array($uploadType, ['LoanSchedule', 'ContractLoanSchedule'], true) ? 'asc' : 'desc';
		$fieldValue = $request->get('field') ;
		$searchDateField = $this->getSearchDateFieldName($modelName,$fieldValue);
		$hasField = $request->filled('field') ;
        $uploadingArr = getUploadParamsFromType($uploadType);
        $fullModelPath = $uploadingArr['fullModel'];
        $mainDateOrderBy = $uploadingArr['orderByDateField'];
        $uploadPermissionName = $uploadingArr['uploadPermissionName'];
        $exportPermissionName = $uploadingArr['exportPermissionName'];
        $deletePermissionName = $uploadingArr['deletePermissionName'];
        Log::storeNewLogRecord('enterSection', null, __('Data Gathering [ '. $uploadType . ' ]'));
		$pageLength = 50 ;
        $salesGatherings = $fullModelPath::company()->when($hasField, function ($q) use ($request,$fieldValue) {
            $q->where($fieldValue, 'like', '%'.$request->get('value') .'%');
        })
        ->when($request->filled('from'), function ($q) use ($request,$searchDateField) {
            $q->where($searchDateField, '>=', $request->get('from'));
        })
        ->when($request->filled('to'), function ($q) use ($request,$searchDateField) {
            $q->where($searchDateField, '<=', $request->get('to'));
        })
		->when($uploadType == 'LoanSchedule',function($q) use ($loanId){
			$q->where('medium_term_loan_id',$loanId);
		})
		->when($uploadType == 'ContractLoanSchedule',function($q) use ($loanId){
			$q->where('leasing_contract_id',$loanId);
		})
        ->orderBy($mainDateOrderBy, $orderByDirection)->paginate($pageLength)->withQueryString();
        $exportableFields  = (new ExportTable)->customizedTableField($company, $uploadType, 'selected_fields');
        if($modelName == 'CustomerInvoice' || 'SupplierInvoice'==$modelName) {
            unset($exportableFields['withhold_amount']);
        }
        $viewing_names = array_values($exportableFields);
        $db_names = array_keys($exportableFields);
  
        $notPeriodClosedCustomerInvoices = $modelName == 'CustomerInvoice' ? CustomerInvoice::getOnlyNotClosedPeriods() : null;

		// Odoo-synced companies don't create/edit/delete Customer or
		// Supplier Invoices directly in CashVero — those rows come from
		// Odoo itself, same rule already applied to "Add Partner"
		// (PartnersController) and the Customer/Supplier Name field lock
		// elsewhere. Deliberately scoped to just these two model types:
		// Odoo integration has no bearing on SalesGathering, LoanSchedule,
		// etc., so their Create/Edit/Delete stay untouched regardless of
		// this company's Odoo credentials.
		$companyHasOdoo = in_array($modelName, ['CustomerInvoice', 'SupplierInvoice'], true) && $company->hasOdooIntegrationCredentials();

		// ── Reshape for Vue: dynamic columns (label + db field name
		// pairs), each row as a plain array of formatted cell values
		// in the same order, plus pre-resolved per-row URLs.
		$dateFields = ['date', 'invoice_due_date', 'invoice_date'];
		$amountFields = [
			'invoice_amount', 'vat_amount', 'withhold_amount', 'collected_amount', 'paid_amount', 'net_balance', 'net_invoice_amount',
			// Schedule Table columns (Leasing/ContractLoanSchedule uses
			// cheque_amount, MTL/LoanSchedule uses schedule_payment —
			// both share beginning_balance/interest_amount/
			// principle_amount/end_balance). These used to fall through
			// to the unformatted else-branch below, showing raw numbers
			// with no thousands separator or fixed decimals.
			'beginning_balance', 'cheque_amount', 'schedule_payment', 'interest_amount', 'principle_amount', 'end_balance',
		];
		$columns = [];
		foreach ($viewing_names as $i => $label) {
			$columns[] = ['label' => $label, 'field' => $db_names[$i]];
		}
		$isScheduleModel = in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule'], true);
		// ⚠️ UX fix: insertToMainTable() dispatches the actual DB-insert
		// job (SalesGatheringTestJob, chained) and redirects immediately
		// — it doesn't wait for the job to finish. If a queue worker
		// hasn't caught up yet by the time this page renders, the
		// schedule looks empty right after "Save," even though nothing
		// failed (reloading a few seconds later shows the real rows).
		// ActiveJob's 'save_to_table' row is the same signal
		// insertToMainTable()/NotifyUserOfCompletedImport already use
		// to track this — reused here rather than inventing a new one.
		$isProcessing = $isScheduleModel && ActiveJob::where('company_id', $company->id)
			->where('model', $modelName)
			->where('status', 'save_to_table')
			->exists();
		$rows = collect($salesGatherings->items())->map(function ($item) use ($columns, $dateFields, $amountFields, $company, $modelName, $isScheduleModel) {
			$cells = [];
			foreach ($columns as $col) {
				$raw = $item->{$col['field']} ?? null;
				if (in_array($col['field'], $dateFields, true)) {
					$cells[] = $raw ? date('d-M-Y', strtotime($raw)) : '-';
				} elseif (in_array($col['field'], $amountFields, true)) {
					$cells[] = number_format($raw ?: 0, 2);
				} else {
					$cells[] = $raw ?? '-';
				}
			}
			$settlementUrl = null;
			if ($modelName === 'LoanSchedule' && $item->hasMediumTermLoan()) {
				$settlementUrl = route('view.loan.schedule.settlements', ['company' => $company->id, 'loanSchedule' => $item->id]);
			} elseif ($modelName === 'ContractLoanSchedule' && $item->canSettle()) {
				$settlementUrl = route('view.contract.loan.schedule.settlements', ['company' => $company->id, 'contractLoanSchedule' => $item->id]);
			}
			// Bug fix (client-flagged, confirmed 2026-08-15, extended
			// 2026-08-15 to also cover Medium Term Loan installments): an
			// installment with ANY payment recorded against it — fully
			// paid or only partially paid — shouldn't be editable or
			// deletable from this table anymore, since either action would
			// now disagree with real settlement/ledger rows that already
			// exist for it. Delete was already hidden for
			// ContractLoanSchedule, but only once fully paid
			// (remaining_raw === 0); Edit was never hidden at all, and
			// LoanSchedule (Medium Term Loan) had neither check. Computed
			// here (not on the frontend) because the frontend only has
			// remaining_raw, not the installment's original payment amount
			// to compare it against — and that comparison shouldn't depend
			// on whether "Cheque Amount" / "Schedule Payment" happens to
			// be one of the columns the user chose to display.
			$isPaidOrPartiallyPaid = match ($modelName) {
				'ContractLoanSchedule' => (float) $item->getRemaining() < (float) $item->getChequeAmount(),
				'LoanSchedule' => (float) $item->getRemaining() < (float) $item->getSchedulePayment(),
				default => null,
			};
			return [
				'id' => $item->id,
				'cells' => $cells,
				'status' => $isScheduleModel ? $item->getStatusFormatted() : null,
				'remaining' => $isScheduleModel ? $item->getRemainingFormatted() : null,
				// Raw numeric value (not the formatted display string)
				// so the frontend can reliably check "is this fully
				// collected/settled" without parsing "0.00" vs "1,234.00".
				'remaining_raw' => $isScheduleModel ? (float) $item->getRemaining() : null,
				'isPaidOrPartiallyPaid' => $isPaidOrPartiallyPaid,
				'settlementUrl' => $settlementUrl,
				'editUrl' => route('edit.sales.form', ['company' => $company->id, 'model' => $modelName, 'modelId' => $item->id]),
				'deleteUrl' => route('salesGathering.destroy', ['company' => $company->id, 'salesGathering' => $item->id, 'modelType' => $modelName]),
			];
		});

        return Inertia::render('InvoiceUpload/Index', [
			'modelName' => $modelName,
			'modelDisplayName' => $uploadingArr['typePrefixName'],
			'isScheduleModel' => $isScheduleModel,
			'isProcessing' => $isProcessing,
			'columns' => $columns,
			'rows' => $rows,
			'pagination' => [
				'current_page' => $salesGatherings->currentPage(),
				'last_page' => $salesGatherings->lastPage(),
				'total' => $salesGatherings->total(),
				'per_page' => $salesGatherings->perPage(),
			],
			'canUpload' => $request->user()->can($uploadPermissionName),
			'canExport' => $request->user()->can($exportPermissionName),
			'canDelete' => $request->user()->can($deletePermissionName),
			'companyHasOdoo' => $companyHasOdoo,
			'createUrl' => route('create.sales.form', ['company' => $company->id, 'model' => $modelName]),
			'importUrl' => route('salesGatheringImport', ['company' => $company->id, 'model' => $modelName]),
			'exportUrl' => route('salesGathering.export', ['company' => $company->id, 'model' => $modelName]),
			'templateFieldsUrl' => route('table.fields.selection.view', ['company' => $company->id, 'model' => $modelName, 'view' => 'sales_gathering']),
			'currentField' => $fieldValue,
			'currentValue' => $request->get('value'),
			'currentFrom' => $request->get('from'),
			'currentTo' => $request->get('to'),
			'indexUrl' => route('view.uploading', ['company' => $company->id, 'model' => $modelName]),
			'deleteAllUrl' => route('uploading.destroy.all', ['company' => $company->id, 'modelName' => $modelName]),
			/**
			 * The paginator's grand total, not the current page's count:
			 * the Delete All confirmation names how many rows are about
			 * to go, and the page only ever holds one page of them.
			 */
			'totalRows' => $salesGatherings->total(),
        ]);
    }
    

   
    // public function create(Company $company)
    // {

    //     return view('client_view.sales_gathering.form', $customerInvoice);
    // }

 
    public function store(Request $request, Company $company)
    {
        abort(404);
    }


    public function show($salesGathering)
    {
        abort(404);
    }


    public function edit(Company $company, $salesGathering)
    {
        abort(404);
    }

 
    // public function update(Request $request, Company $company, SalesGathering $salesGathering)
    // {
    //     $salesGathering->update($request->all());
    //     toastr()->success('Updated Successfully');
    //     return (new SalesGatheringViewModel($company, $salesGathering))->view('client_view.sales_gathering.form');
    // }

 
    public function destroy(Company $company,Request $request, $modelId)
    {
		$modelType  = $request->get('modelType');
		$fullModelName = 'App\Models\\'.$modelType ;
		$model = $fullModelName::find($modelId);

		if (! $model) {
			return redirect()->back();
		}

		/**
		 * Bug fix (client-flagged, confirmed 2026-08-15, extended
		 * 2026-08-15 to Medium Term Loan installments): hiding the Delete
		 * button in the UI for a paid/partially-paid installment (see
		 * InvoiceUpload/Index.vue) only stops it from that screen — this
		 * route itself had no matching check, so the same delete could
		 * still be triggered directly. Guarded here too, same condition
		 * as the UI: any recorded payment blocks it.
		 */
		if ($modelType === 'ContractLoanSchedule' && (float) $model->getRemaining() < (float) $model->getChequeAmount()) {
			// Inertia page: session flash, not a flasher envelope. See destroy() below.
			return redirect()->back()->with('fail', __('This installment has a payment recorded against it and can no longer be deleted.'));
		}
		if ($modelType === 'LoanSchedule' && (float) $model->getRemaining() < (float) $model->getSchedulePayment()) {
			// Inertia page: session flash, not a flasher envelope. See destroy() below.
			return redirect()->back()->with('fail', __('This installment has a payment recorded against it and can no longer be deleted.'));
		}

        $model->delete();

        /**
         * ⚠️ REAL BUG FIXED HERE (two of them, on one line):
         *
         * 1. It said toastr()->ERROR for a successful delete, so the
         *    confirmation came up red.
         * 2. toastr() writes a php-flasher envelope, and flasher's
         *    flash_bag bridge is disabled (see config/flasher.php), so
         *    nothing carries it into an Inertia response. This page is
         *    Inertia — the message only ever appeared on the next FULL
         *    page load, which is exactly the "it shows after I reload"
         *    report.
         *
         * session flash is what HandleInertiaRequests reads and what
         * AppLayout toasts.
         */
        return redirect()->back()->with('success', __('Deleted Successfully'));
    }

    /**
     * Deletes every row of this upload type for the company, EXCEPT the
     * invoices that already have money recorded against them.
     *
     * An invoice with a collection or a payment behind it is not a
     * spreadsheet row any more: removing it would leave the settlement
     * pointing at nothing. Rather than refusing the whole operation or
     * quietly taking those too, it deletes what it can and says exactly
     * what it kept and why.
     *
     * Deletes through the model, one row at a time — the statement and
     * balance cascades hang off the model's own delete hooks, and a
     * mass query-builder delete would skip every one of them.
     *
     * @see \App\Support\Invoices\InvoiceMoneyLinks
     */
    public function destroyAll(Company $company, Request $request, string $modelName)
    {
        $uploadingArr = getUploadParamsFromType($modelName);

        if (! $request->user()->can($uploadingArr['deletePermissionName'])) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        $modelClass = 'App\\Models\\'.$modelName;

        if (! class_exists($modelClass)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $ids = $modelClass::where('company_id', $company->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $blockedIds = \App\Support\Invoices\InvoiceMoneyLinks::idsWithMoney($modelName, $ids);
        $deletableIds = array_values(array_diff($ids, $blockedIds));

        $deleted = 0;

        foreach (array_chunk($deletableIds, 200) as $chunk) {
            foreach ($modelClass::whereIn('id', $chunk)->get() as $row) {
                $row->delete();
                $deleted++;
            }
        }

        return redirect()->back()->with(
            $blockedIds === [] ? 'success' : 'fail',
            $this->deleteAllMessage($deleted, $blockedIds, $modelName)
        );
    }

    /**
     * @param  list<int>  $blockedIds
     */
    private function deleteAllMessage(int $deleted, array $blockedIds, string $modelName): string
    {
        if ($blockedIds === []) {
            return __(':count row(s) deleted.', ['count' => $deleted]);
        }

        $reasons = \App\Support\Invoices\InvoiceMoneyLinks::reasons($modelName, $blockedIds);

        $details = [];
        foreach ($reasons as $label => $count) {
            $details[] = $count.' '.__($label);
        }

        return __(':deleted row(s) deleted. :kept could not be deleted because they have money transactions recorded against them (:details). Delete those transactions first.', [
            'deleted' => $deleted,
            'kept' => count($blockedIds),
            'details' => implode(', ', $details),
        ]);
    }
    public function export(Company $company, string $modelName)
    {
        $uploadParams = getUploadParamsFromType($modelName);
        $exportableFields = exportableFields($company->id, $modelName);
        // If there are no exportable fields were found return with a warning msg
        if ($exportableFields === null) {
            // Same flash-channel fix as destroy() above: this page is Inertia.
            return redirect()->back()->with('fail', __('Please choose exportable fields first'));
        }
        // Get The Selected exportable fields returns a pair of ['field_name' => 'viewing name']
        $selected_fields = (new ExportTable)->customizedTableField($company, $modelName, 'selected_fields');
        // Array Contains Only the name of fields
        $exportable_fields = array_keys($selected_fields);
        
        $salesGathering = $uploadParams['fullModel']::where('company_id', $company->id)->get();
        // Customizing the collection to be exported
        $salesGathering = collect($salesGathering)->map(function ($invoice) use ($exportable_fields) {
            $data = [];
            foreach ($exportable_fields as $field) {
                if (str_contains($field, 'deduction_id_')) {
                    $value = Deduction::find($invoice->$field)->name[lang()] ??null;
                } elseif (str_contains($field, 'date')) {
                    $value = $invoice->$field ===null ?: date('d-m-Y', strtotime($invoice->$field));
                } else {
                    $value = $invoice->$field;
                }
                $data[$field] = $value ;
            }
            return $data;
        });

        return (new ExportData($company->id, array_values($selected_fields), $salesGathering))->download($modelName.'.xlsx');

    }
    public function getUploadingPageExportNavigation(string $modelName,string $uploadPermissionName,string $exportPermissionName,string $deletePermissionName,int $fromIndex=  0 , int $toIndex=0, ?string $loanId = null)
    {
		$additionalUploadDataArray = [];
		if ($modelName == 'LoanSchedule' && $loanId) {
			$additionalUploadDataArray = ['medium_term_loan_id' => $loanId];
		}
		if ($modelName == 'ContractLoanSchedule' && $loanId) {
			$additionalUploadDataArray = ['leasing_contract_id' => $loanId];
		}
		$viewName = in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule'], true) ? 'upload' : 'sales_gathering';
		$user = auth()->user();
		$company = currentCompany();
		$deleteAllDataTitle = $modelName == 'LabelingItem' ? __('Delete All Data (With Columns)') : __('Delete All Data') ;
		$deleteAllDataRouteName = $modelName == 'LabelingItem'? route('delete.all.labeling.items.with.columns',[$company->id]) : route('truncate',[$company,$modelName]);
		
		$exportNavArr = $modelName != 'LabelingItem' ? [
			'name'=>__('Export All Data'),
			'link'=>$user->can($exportPermissionName) ? route('salesGathering.export',['company'=>$company->id , 'model'=>$modelName]):'#',
			'show'=>$user->can($exportPermissionName),
			'icon'=>'fas fa-file-import',
			'attr'=>[
				// 'data-toggle'=>'modal',
				// 'data-target'=>'#search-form-modal',
			]
			] : 
			[
				'name'=>__('Export Data'),
				'link'=>'#',
				'show'=>$user->can($exportPermissionName),
				'icon'=>'fas fa-file-import',
				'sub_items'=>[
					[
						'name'=>__('Export Excel'),
						'link'=>$user->can($exportPermissionName) ? route('export.labeling.item',['company'=>$company->id , 'type'=>'excel']):'#',
						'show'=>$user->can($exportPermissionName),
						'icon'=>'fas fa-file-import',
					],
					[
						'name'=>__('Export PDF'),
						'link'=>$user->can($exportPermissionName) ? route('export.labeling.item',['company'=>$company->id , 'type'=>'pdf']):'#',
						'show'=>$user->can($exportPermissionName),
						'icon'=>'fas fa-file-import',
						'attr'=>[
							'data-toggle'=>'modal',
							'data-target'=>'#print_report'
						]
					],
					
					
				]
				]
			;
			
			
		
		
        return [
            
        [
           'name'=>__('Upload Data'),
           'link'=>'#',
           'show'=>true,
		   'icon'=>'fas fa-upload',
           'sub_items'=>[
               [
                   'name'=>__('Template Download'),
                   'link'=>$user->can($uploadPermissionName)?route('table.fields.selection.view',[$company,$modelName,$viewName]) : '#' ,
                   'show'=>$modelName != 'LabelingItem'
               ],
               [
                   'name'=>__('Upload Data'),
                   'link'=>$user->can($uploadPermissionName) ? route('salesGatheringImport',array_merge(['company'=>$company->id , 'model'=>$modelName],$additionalUploadDataArray)) : '#',
                   'show'=>true
               ]
           


			   ],
			   
        ],
		[
			'name'=>__('Filter'),
			'link'=>'#',
			'show'=>$modelName != 'LabelingItem',
			'icon'=>'fas fa-search ',
			'attr'=>[
				'data-toggle'=>'modal',
				'data-target'=>'#search-form-modal',
			]
			],
			
			$exportNavArr,
			// [
			// 	'name'=>__('Print QR Code'),
			// 	'link'=>$user->can($exportPermissionName) ? route('print.labeling.item.qrcode',['company'=>$company->id,'fromIndex'=>$fromIndex,'toIndex'=>$toIndex ]):'#',
			// 	'show'=>$modelName == 'LabelingItem',
			// 	'icon'=>'fas fa-print',
			// 	],
				// [
				// 	'name'=>__('Print Report'),
				// 	'link'=>$user->can($exportPermissionName) ? route('print.labeling.item.qrcode',['company'=>$company->id,'fromIndex'=>$fromIndex,'toIndex'=>$toIndex ]):'#',
				// 	'show'=>$modelName == 'LabelingItem',
				// 	'icon'=>'fas fa-print',
				// 	'attr'=>[
				// 		'data-toggle'=>'modal',
				// 		'data-target'=>'#print_report'
				// 	]
				// ]
				
			
			// ,
				
				
				
				[
					'name'=>__('Delete'),
					'link'=>'#',
					'show'=>true,
					'icon'=>'fas fa-trash',
					'sub_items'=>[
						[
							'name'=>Request()->segment(4) == 'LabelingItem'? __('Delete By Serial') :__('Delete By Date'),
							'link'=>'#',
							'show'=>true,
							'attr'=>[
								'data-toggle'=>'modal',
								'data-target'=>'#delete_from_to_modal'
							]
						],
						[
							'name'=>$deleteAllDataTitle,
							'link'=>$user->can($deletePermissionName)?$deleteAllDataRouteName:'#',
							'show'=>$user->can($deletePermissionName)
						]
					
		 
		 
						],
						
				 ],
				
				
				
				
				
				
				
				
        
    	];
    }

	

	
}