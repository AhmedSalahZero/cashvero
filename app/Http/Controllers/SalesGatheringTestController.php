<?php

namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Imports\ImportData;
use App\Jobs\Caches\HandleBreakdownDashboardCashingJob;
use App\Jobs\Caches\HandleCustomerDashboardCashingJob;
use App\Jobs\Caches\HandleCustomerNatureCashingJob;
use App\Jobs\Caches\RemoveExpenseIntervalYearCashingJob;
use App\Jobs\Caches\RemoveIntervalYearCashingJob;
use App\Jobs\NotifyUserOfCompletedImport;
use App\Jobs\RemoveCachingCompaniesData;
use App\Jobs\SalesGatheringTestJob;
use App\Jobs\ShowCompletedMessageForSuccessJob;
use App\Models\ActiveJob;
use App\Models\CachingCompany;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\LastUploadFileName;
use App\Models\LeasingContract;
use App\Models\MediumTermLoan;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SupplierInvoice;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;

/**
 * SalesGatheringTestController
 * ------------------------------------------------------------------
 * The actual upload engine behind "Upload New Customer/Supplier
 * Invoices Data" — despite the "Test" in its name (legacy naming,
 * not a testing/staging controller in the QA sense). Two-phase async
 * flow, both phases backed by queued jobs:
 *   1. Upload an Excel file → Excel::queueImport() parses it into a
 *      Cache-backed "preview" (not yet in the real table)
 *   2. Review the preview, then insertToMainTable() dispatches
 *      SalesGatheringTestJob to write the real customer_invoices/
 *      supplier_invoices rows.
 *
 * NEW in this migration (confirmed with the project owner):
 * duplicate detection. Re-uploading invoices that already exist
 * (same invoice_number + company_id + currency) now gets flagged at
 * the preview stage AND silently skipped (not inserted, not
 * replaced) at the actual insert step in SalesGatheringTestJob — see
 * that job's own docblock for why "replace" was deliberately not
 * offered (real financial records — collections, deductions,
 * settlements — reference an invoice by its database id, and
 * replacing would orphan them).
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   - import() GET  → ALREADY migrated. Returns Inertia::render(),
 *                      served by resources/js/Pages/InvoiceUpload/Import.vue
 *   - import() POST → UNCHANGED. Already returns a plain redirect,
 *                      which works natively with an Inertia
 *                      file-upload form (no JSON-response fix needed
 *                      here, unlike some other write actions in this app).
 *   - editCachedRow / updateCachedRow / lastUploadFailed → still
 *      Blade, scoped as a follow-up once the core flow is confirmed working.
 */
class SalesGatheringTestController extends Controller
{
	
	
	public function paginate($items, $perPage = 50, $page = null, $options = [])
	{
		$page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
		$items = $items instanceof Collection ? $items : Collection::make($items);
		return (new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, [
			'path'  => Request()->url(),
			'query' => Request()->query(),
		]));
	}

	/**
	 * import() GET — the upload form + live preview of parsed rows,
	 * not yet committed. See class docblock for the full two-phase
	 * flow and the new duplicate-detection behavior.
	 */
	public function import(Company $company,string $modelName = 'SalesGathering')
	{
		$loanId = request('medium_term_loan_id') ?? request('loanId');
		$contractId = request('leasing_contract_id') ?? request('loanId');
		if ($modelName == 'LoanSchedule' && $loanId) {
			session(['loan_schedule_import_loan_id_' . $company->id => $loanId]);
		} elseif ($modelName == 'LoanSchedule') {
			$loanId = session('loan_schedule_import_loan_id_' . $company->id);
		}
		if ($modelName == 'ContractLoanSchedule' && $contractId) {
			session(['contract_loan_schedule_import_contract_id_' . $company->id => $contractId]);
			$loanId = $contractId;
		} elseif ($modelName == 'ContractLoanSchedule') {
			$loanId = session('contract_loan_schedule_import_contract_id_' . $company->id);
		}
		$uploadParamsType = getUploadParamsFromType($modelName);
		$importHeaderText = $uploadParamsType['importHeaderText'];
		$company_id = $company->id;
		$user = Auth::user();
		/**
		 * @var User $user
		 */
		$user_id = $user->id;
		$exportableFields = exportableFields($company_id, $modelName);

		/**
		 * * حاله ال 
		 * * labelingitem 
		 * * بس اللي هنقبل انه يدخل من غير ما يكون عنده داتا اكسبورت لاننا هناخدها من الاكسل اللي هو هيرفعه
		 */
		if ($exportableFields === null && !in_array($modelName, ['LabelingItem', 'LoanSchedule', 'ContractLoanSchedule'], true)) {
			toastr()->warning('Please choose exportable fields first');
			return redirect()->back();
		}


		if (request()->method()  == 'GET') {
			
			$cacheKeys = CachingCompany::where('company_id', $company_id)->where('model',$modelName)->get();

			$salesGatherings = [];
			foreach ($cacheKeys as $cacheKey) {
				$salesGatherings = array_merge(Cache::get($cacheKey->key_name) ?: [], $salesGatherings);
			}
			$exportableFields  = (new ExportTable)->customizedTableField($company, $modelName, 'selected_fields');
			$viewing_names = array_values($exportableFields);
			$db_names = array_keys($exportableFields);

			// ── NEW: duplicate detection. Only meaningful for
			// CustomerInvoice/SupplierInvoice (has invoice_number +
			// currency); other upload types skip this entirely.
			$duplicateInvoiceNumbers = [];
			$duplicateCount = 0;
			if (in_array($modelName, ['CustomerInvoice', 'SupplierInvoice'], true) && count($salesGatherings)) {
				$invoiceTableName = $uploadParamsType['dbName'];
				$candidates = collect($salesGatherings)
					->filter(fn ($row) => !empty($row['invoice_number']))
					->map(fn ($row) => ($row['invoice_number'] ?? '') . '|' . strtoupper($row['currency'] ?? ''))
					->unique();
				if ($candidates->count()) {
					$existing = DB::table($invoiceTableName)
						->where('company_id', $company_id)
						->whereIn('invoice_number', collect($salesGatherings)->pluck('invoice_number')->filter()->unique())
						->get(['invoice_number', 'currency'])
						->map(fn ($r) => $r->invoice_number . '|' . strtoupper($r->currency ?? ''))
						->flip();
					$duplicateInvoiceNumbers = $candidates->filter(fn ($key) => $existing->has($key))->values()->toArray();
					$duplicateCount = collect($salesGatherings)->filter(function ($row) use ($duplicateInvoiceNumbers) {
						$key = ($row['invoice_number'] ?? '') . '|' . strtoupper($row['currency'] ?? '');
						return in_array($key, $duplicateInvoiceNumbers, true);
					})->count();
				}
			}

			// Preview cap: 20 for most upload types (deliberately —
			// some of these can be up to 50,000 rows, so previewing
			// everything would be slow and unwieldy). LoanSchedule /
			// ContractLoanSchedule are naturally small (installment
			// counts, realistically dozens not thousands), so show the
			// whole thing — no reason to make the user wonder what's
			// in the other 10 rows on something this size.
			$isScheduleModel = in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule'], true);
			$previewLimit = $isScheduleModel ? PHP_INT_MAX : 20;
			$previewRows = collect($salesGatherings)->take($previewLimit)->map(function ($row) use ($db_names, $company, $modelName) {
				$key = ($row['invoice_number'] ?? '') . '|' . strtoupper($row['currency'] ?? '');
				return [
					'id' => $row['id'] ?? null,
					'cells' => collect($db_names)->map(fn ($name) => $row[$name] ?? '-')->all(),
					'isDuplicate' => false, // filled client-side against duplicateInvoiceNumbers to avoid computing twice
					'_dupKey' => $key,
					'editUrl' => isset($row['id']) ? route('salesGatheringTest.editCachedRow', ['company' => $company->id, 'model' => $modelName, 'rowId' => $row['id']]) : null,
				];
			})->values();

			$activeJob = ActiveJob::where('company_id', $company_id)->where('status', 'test_table')->where('model_name', 'SalesGatheringTest')->where('model', $modelName)->first();
			$activeJobForSaving = ActiveJob::where('company_id', $company_id)->where('status', 'save_to_table')->where('model_name', 'SalesGatheringTest')->where('model', $modelName)->first();
			$canViewPleaseReviewMessage = !hasFailedRow($company_id, $modelName) && hasCachingCompany($company_id, $modelName) && !$activeJobForSaving && Cache::get(getShowCompletedTestMessageCacheKey($company_id, $modelName)) && !(bool) Cache::get(getCanReloadUploadPageCachingForCompany($company_id, $modelName));

			$currentFileNameLabel = null;
			if ($company->hasLastCurrentUploadFileForModel($modelName)) {
				$currentFileNameLabel = 'Current File Name: ' . $company->getCurrentLastFileNameForModel($modelName);
			} elseif (hasFailedRow($company_id, $modelName)) {
				$currentFileNameLabel = 'Current Failed File Name: ' . $company->getCurrentLastFileNameForModel($modelName);
			} elseif ($company->hasLastSuccessfullyUploadFileForModel($modelName)) {
				$currentFileNameLabel = 'Last Successfully Uploaded File Name: ' . $company->getSuccessLastFileNameForModel($modelName);
			}

			// ⚠️ Same confirmed bug as insertToMainTable()'s final
			// redirect: this omitted loanId, so on a non-sync queue
			// driver (where this URL is actually used, once the save
			// job's percentage hits 100%), the user would land on the
			// UNFILTERED schedule table — every leasing contract's
			// rows mixed together — instead of just this one.
			$redirectUrlAfterSave = in_array($modelName, ['CustomerInvoice', 'SupplierInvoice'], true)
				? route('view.balances', ['company' => $company->id, 'modelType' => $modelName])
				: route('view.uploading', getUploadingRouteParams($company->id, $modelName, $loanId));

			return Inertia::render('InvoiceUpload/Import', [
				'modelName' => $modelName,
				'modelDisplayName' => camelToTitle($modelName),
				'uploadUrl' => route('salesGatheringImport', ['company' => $company->id, 'model' => $modelName]),
				'saveDataUrl' => route('salesGatheringTest.insertToMainTable', ['company' => $company->id, 'modelName' => $modelName]),
				'deleteSelectedUrl' => route('deleteMultiRowsFromCaching', ['company' => $company->id, 'modelName' => $modelName]),
				'deleteAllUrl' => route('deleteAllCaches', ['company' => $company->id, 'modelType' => $modelName]),
				'lastUploadFailedUrl' => hasFailedRow($company_id, $modelName) ? route('last.upload.failed', ['company' => $company->id, 'model' => $modelName]) : null,
				'percentagePollUrl' => url('get-uploading-percentage/' . $company->id . '/' . $modelName),
				'columns' => collect($viewing_names)->map(fn ($label, $i) => ['label' => $label, 'field' => $db_names[$i]])->values(),
				'previewRows' => $previewRows,
				'totalCachedRows' => count($salesGatherings),
				'duplicateInvoiceNumbers' => $duplicateInvoiceNumbers,
				'duplicateCount' => $duplicateCount,
				'isParsing' => (bool) $activeJob,
				'isSaving' => (bool) $activeJobForSaving,
				'canReview' => $canViewPleaseReviewMessage,
				'currentFileNameLabel' => $currentFileNameLabel,
				'redirectUrlAfterSave' => $redirectUrlAfterSave,
				'indexUrl' => route('view.uploading', ['company' => $company->id, 'model' => $modelName]),
				'skippedDuplicateCount' => Cache::get(getSkippedDuplicatesCacheKey($company_id, $modelName), 0),
			]);
		} else {
			// Get The Selected exportable fields returns a pair of ['field_name' => 'viewing name']
			$exportable_fields = (new ExportTable)->customizedTableField($company, $modelName, 'selected_fields');
			// Customizing the collection to be exported
			$salesGathering_fields = [];
			foreach ($exportable_fields as $field_name => $column_name) {
				$salesGathering_fields[$field_name] = $column_name;
			}
			$salesGathering_fields['company_id'] = $company_id;
			$salesGathering_fields['created_by'] = $user_id;
			$company->deleteAllOldLastUploadFileNamesFor($modelName,LastUploadFileName::CURRENT);
			$fileName = request()->file('excel_file') ? request()->file('excel_file')->getClientOriginalName() : __('N/A') ;
			$company->addNewFileUploadFileNameFor($fileName,$modelName);
			$active_job = ActiveJob::where('company_id',  $company_id)->where('model',$modelName)->where('status', 'test_table')->where('model_name', 'SalesGatheringTest')->first();
			if ($active_job === null) {
				$active_job = ActiveJob::create([
					'company_id'  => $company_id,
					'model_name'  => 'SalesGatheringTest',
					'status'  => 'test_table',
					'model'=>$modelName,
					
				]);
			}
			$validationCacheKey = generateCacheKeyForValidationRow($company_id,$modelName);
			Cache::forget($validationCacheKey);
			Cache::forget(getSkippedDuplicatesCacheKey($company_id, $modelName));

			CachingCompany::where('company_id', $company_id)
				->where('model', $modelName)
				->get()
				->each(function (CachingCompany $cache) use ($company_id, $modelName) {
					Cache::forget($cache->key_name);
					Cache::forget(getTotalUploadCacheKey($company_id, $cache->job_id, $modelName));
					$cache->delete();
				});
			
			// for  Labeling Item Only 
		
			
			
			
			
			$fileUpload = new  ImportData($company_id, request()->format, 'SalesGatheringTest', $salesGathering_fields, $active_job->id,auth()->user()->id,$modelName);
				Excel::queueImport($fileUpload, request()->file('excel_file'))->chain([
					new NotifyUserOfCompletedImport(request()->user(), $active_job->id,$company_id,$modelName),
					new ShowCompletedMessageForSuccessJob($company_id, $modelName)
				]);
				
				



			toastr('Import started!', 'success');

			$redirectParams = ['company' => $company_id, 'model' => $modelName];
			if ($modelName == 'LoanSchedule' && $loanId) {
				$redirectParams['medium_term_loan_id'] = $loanId;
			}
			if ($modelName == 'ContractLoanSchedule' && $loanId) {
				$redirectParams['leasing_contract_id'] = $loanId;
			}
			return redirect()->route('salesGatheringImport', $redirectParams);
		}
	}
	/**
	 * Guards the "already-running facility onboarding" contract: if the
	 * MTL / Leasing record has first_installment_date and/or
	 * remaining_installment_count set, the just-uploaded (still cached,
	 * not yet committed) schedule must match both exactly — same row
	 * count, first row's date equal to the configured date. Hard
	 * reject (returns an error message, nothing gets committed) rather
	 * than a soft warning: a wrong row count or start date would break
	 * the guarantee that everything from here on is payable through
	 * CashVero. Returns null when everything checks out (or when the
	 * facility has none of these fields set, e.g. a brand-new facility
	 * with no pre-CashVero history).
	 */
	protected function validateScheduleUploadShape(Company $company, string $modelName, $loanId): ?string
	{
		$facility = $modelName === 'ContractLoanSchedule'
			? LeasingContract::find($loanId)
			: MediumTermLoan::find($loanId);

		if (! $facility || (! $facility->first_installment_date && ! $facility->remaining_installment_count)) {
			return null;
		}

		$rows = collect();
		foreach (CachingCompany::where('company_id', $company->id)->where('model', $modelName)->get() as $cache) {
			$cacheGroup = Cache::get($cache->key_name) ?: [];
			$rows = $rows->merge(collect($cacheGroup)->filter(fn ($row) => ! isMappedImportRowEmpty($row)));
		}

		if ($rows->isEmpty()) {
			return null;
		}

		if ($facility->remaining_installment_count && $rows->count() != $facility->remaining_installment_count) {
			return __('This schedule has :uploaded row(s), but :expected remaining installment(s) were expected for this facility.', [
				'uploaded' => $rows->count(),
				'expected' => $facility->remaining_installment_count,
			]);
		}

		if ($facility->first_installment_date) {
			$firstRow = $rows->sortBy('date')->first();
			$firstRowDate = isset($firstRow['date']) ? Carbon::make($firstRow['date'])?->format('Y-m-d') : null;
			$expectedDate = Carbon::make($facility->first_installment_date)->format('Y-m-d');

			if ($firstRowDate !== $expectedDate) {
				return __('The first installment date in this schedule (:uploaded) does not match the First Installment Date configured for this facility (:expected).', [
					'uploaded' => $firstRowDate ?? __('N/A'),
					'expected' => $expectedDate,
				]);
			}
		}

		return null;
	}

	public function insertToMainTable(Company $company , string $modelName)
	{
		$loanId = request('medium_term_loan_id') ?? request('loanId') ?? session('loan_schedule_import_loan_id_' . $company->id);
		if ($modelName == 'LoanSchedule' && !$loanId) {
			// ⚠️ Pre-existing bug, unrelated to this session's changes,
			// fixed here because it's the exact same failure mode as
			// above: toastr() isn't wired into this app's Inertia flash
			// system, so this error was always silent too.
			return redirect()->back()->with('fail', __('Loan is required to save loan schedule data.'));
		}
		$contractId = request('leasing_contract_id') ?? request('loanId') ?? session('contract_loan_schedule_import_contract_id_' . $company->id);
		if ($modelName == 'ContractLoanSchedule' && !$contractId) {
			return redirect()->back()->with('fail', __('Contract is required to save contract loan schedule data.'));
		}
		if ($modelName == 'ContractLoanSchedule') {
			$loanId = $contractId;
		}

		if (in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule'], true)) {
			$shapeError = $this->validateScheduleUploadShape($company, $modelName, $loanId);
			if ($shapeError) {
				// ⚠️ toastr() is NOT wired into this app's Inertia flash
				// system (see HandleInertiaRequests::share() — it only
				// bridges session('success')/session('fail') into
				// page.props.flash, which AppLayout.vue watches).
				// with('fail', ...) is the correct channel here.
				return redirect()->back()->with('fail', $shapeError);
			}
		}

		$active_job = ActiveJob::where('company_id',  $company->id)->where('model',$modelName)->where('status', 'save_to_table')->where('model_name', 'SalesGatheringTest')->first();
		if ($active_job === null) {
			$active_job = ActiveJob::create([
				'company_id'  => $company->id,
				'model_name'  => 'SalesGatheringTest',
				'status'  => 'save_to_table',
				'model'=>$modelName
			]);
		}
		
		$validationCacheKey = generateCacheKeyForValidationRow($company->id,$modelName);
		Cache::forget($validationCacheKey);
		Cache::forget(getShowCompletedTestMessageCacheKey($company->id,$modelName));
		$company->updateLastUploadFileNameStatus($modelName);
		
	
			SalesGatheringTestJob::withChain([
				new NotifyUserOfCompletedImport(request()->user(), $active_job->id, $company->id,$modelName),
				new RemoveCachingCompaniesData($company->id,$modelName),
			])->dispatch($company->id,$modelName,$loanId);

		return $this->redirectAfterInsertToMainTable($company, $modelName, $loanId);
	}

	/**
	 * Customer/Supplier invoice saves should land on Balances once the
	 * job is done — that is the page the upload exists to feed. Loan
	 * schedules still go to their filtered table.
	 *
	 * On an async queue the save job is still running when this
	 * returns, so invoices bounce back to the Import page: Import.vue
	 * already polls percentage and visits redirectUrlAfterSave
	 * (Balances) at 100%. On a sync queue the chain has already
	 * finished, so we skip that hop and go to Balances immediately.
	 */
	protected function redirectAfterInsertToMainTable(Company $company, string $modelName, $loanId)
	{
		if (in_array($modelName, ['CustomerInvoice', 'SupplierInvoice'], true)) {
			$stillSaving = ActiveJob::where('company_id', $company->id)
				->where('model', $modelName)
				->where('status', 'save_to_table')
				->where('model_name', 'SalesGatheringTest')
				->exists();

			if ($stillSaving) {
				return redirect()->route('salesGatheringImport', [
					'company' => $company->id,
					'model' => $modelName,
				]);
			}

			return redirect()->route('view.balances', [
				'company' => $company->id,
				'modelType' => $modelName,
			]);
		}

		return redirect()->route('view.uploading', getUploadingRouteParams($company->id, $modelName, $loanId));
	}

	/**
	 * Edit one not-yet-saved cached row before committing the import.
	 * Renders resources/js/Pages/InvoiceUpload/EditCachedRow.vue.
	 *
	 * ContractLoanSchedule's Drawee Bank / Account Number cascading
	 * dropdown (confirmed real, not incidental — picking a bank
	 * scopes Account Number to just that bank's accounts, a real
	 * data-integrity guard) is implemented here. The account-number
	 * lookup itself is the existing, UNCHANGED
	 * contract.loan.schedule.account.numbers endpoint.
	 */
	public function editCachedRow(Company $company, string $modelName, string $rowId)
	{
		$row = $this->findCachedImportRow($company->id, $modelName, $rowId);
		if (!$row) {
			toastr()->error(__('Row not found'));
			return redirect()->back();
		}
		$loanId = request('medium_term_loan_id') ?? request('loanId') ?? session('loan_schedule_import_loan_id_' . $company->id);
		if ($modelName == 'ContractLoanSchedule') {
			$loanId = request('leasing_contract_id') ?? request('loanId') ?? session('contract_loan_schedule_import_contract_id_' . $company->id);
		}
		$exportableFields = (new ExportTable)->customizedTableField($company, $modelName, 'selected_fields');

		$dateFields = ['date', 'invoice_due_date', 'invoice_date'];
		$amountFields = ['invoice_amount', 'vat_amount', 'withhold_amount', 'collected_amount', 'paid_amount', 'net_balance', 'net_invoice_amount', 'beginning_balance', 'schedule_payment', 'cheque_amount', 'interest_amount', 'principle_amount', 'end_balance'];
		$isContractLoanSchedule = $modelName === 'ContractLoanSchedule';
		$fields = collect($exportableFields)->map(function ($label, $fieldName) use ($row, $dateFields, $amountFields, $isContractLoanSchedule, $company) {
			$value = $row[$fieldName] ?? '';
			$type = 'text';
			$options = null;
			if ($isContractLoanSchedule && $fieldName === 'drawee_bank') {
				$type = 'bank_select';
				// Same fix as renderScheduleForm() — guarantee the row's
				// own current value is always a selectable option.
				$options = getCompanyDraweeBankNames($company->id);
				if ($value && ! in_array($value, $options, true)) {
					array_unshift($options, $value);
				}
			} elseif ($isContractLoanSchedule && $fieldName === 'account_number') {
				$type = 'account_number_select';
			} elseif (in_array($fieldName, $dateFields, true)) {
				$type = 'date';
				if ($value) {
					try { $value = \Carbon\Carbon::parse($value)->format('Y-m-d'); } catch (\Exception $e) {}
				}
			} elseif (in_array($fieldName, $amountFields, true)) {
				$type = 'number';
			}
			return [
				'field' => $fieldName,
				'label' => $label,
				'type' => $type,
				'value' => $value,
				'options' => $options,
			];
		})->values();

		return Inertia::render('InvoiceUpload/EditCachedRow', [
			'modelName' => $modelName,
			'modelDisplayName' => camelToTitle($modelName),
			'fields' => $fields,
			'accountNumbersUrl' => $isContractLoanSchedule ? route('contract.loan.schedule.account.numbers', ['company' => $company->id]) : null,
			'updateUrl' => route('salesGatheringTest.updateCachedRow', array_merge(
				['company' => $company->id, 'model' => $modelName, 'rowId' => $rowId],
				$modelName == 'ContractLoanSchedule' && $loanId ? ['leasing_contract_id' => $loanId] : ($loanId ? ['medium_term_loan_id' => $loanId] : [])
			)),
			'backUrl' => route('salesGatheringImport', ['company' => $company->id, 'model' => $modelName]),
		]);
	}

	public function updateCachedRow(Request $request, Company $company, string $modelName, string $rowId)
	{
		$exportableFields = (new ExportTable)->customizedTableField($company, $modelName, 'selected_fields');
		$updateData = $request->only(array_keys($exportableFields));

		if ($modelName === 'ContractLoanSchedule') {
			$existingRow = $this->findCachedImportRow($company->id, $modelName, $rowId) ?? [];
			$validationRow = array_merge($existingRow, $updateData);
			$validationErrors = getContractLoanScheduleBankAccountValidationErrors($company->id, $validationRow);

			if (count($validationErrors)) {
				toastr()->error(implode(' ', $validationErrors));
				return redirect()->back()->withInput();
			}
		}

		if (!$this->updateCachedImportRow($company->id, $modelName, $rowId, $updateData)) {
			toastr()->error(__('Row not found'));
			return redirect()->back();
		}
		toastr()->success(__('Updated Successfully'));
		$loanId = request('medium_term_loan_id') ?? request('loanId') ?? session('loan_schedule_import_loan_id_' . $company->id);
		if ($modelName == 'ContractLoanSchedule') {
			$loanId = request('leasing_contract_id') ?? request('loanId') ?? session('contract_loan_schedule_import_contract_id_' . $company->id);
		}
		$redirectParams = ['company' => $company->id, 'model' => $modelName];
		if ($modelName == 'LoanSchedule' && $loanId) {
			$redirectParams['medium_term_loan_id'] = $loanId;
		}
		if ($modelName == 'ContractLoanSchedule' && $loanId) {
			$redirectParams['leasing_contract_id'] = $loanId;
		}
		return redirect()->route('salesGatheringImport', $redirectParams);
	}

	protected function findCachedImportRow(int $companyId, string $modelName, string $rowId): ?array
	{
		foreach (CachingCompany::where('company_id', $companyId)->where('model', $modelName)->get() as $cache) {
			foreach (Cache::get($cache->key_name) ?: [] as $row) {
				if (($row['id'] ?? null) == $rowId) {
					return $row;
				}
			}
		}
		return null;
	}

	protected function updateCachedImportRow(int $companyId, string $modelName, string $rowId, array $data): bool
	{
		foreach (CachingCompany::where('company_id', $companyId)->where('model', $modelName)->get() as $cache) {
			$rows = Cache::get($cache->key_name) ?: [];
			foreach ($rows as $index => $row) {
				if (($row['id'] ?? null) == $rowId) {
					$rows[$index] = array_merge($row, $data, ['id' => $rowId]);
					Cache::forever($cache->key_name, $rows);
					return true;
				}
			}
		}
		return false;
	}

	// public function edit(Company $company, SalesGatheringTest $salesGatheringTest,string $modelName)
	// {
	// 	$exportableFields  = (new ExportTable)->customizedTableField($company, $modelName, 'selected_fields');
	// 	$db_names = array_keys($exportableFields);
	// 	return view('client_view.sales_gathering.importRowForm', compact('company', 'exportableFields', 'db_names', 'salesGatheringTest'));
	// }

	// public function update(Request $request, Company $company, SalesGatheringTest $salesGatheringTest,string $modelName)
	// {
	// 	$salesGatheringTest->update($request->all());
	// 	toastr()->success('Updated Successfully');
	// 	return redirect()->route('salesGatheringImport', ['company'=>$company->id , 'model'=>$modelName]);
	// }

	// public function destroy(Company $company, SalesGatheringTest $salesGatheringTest)
	// {

	// 	$salesGatheringTest->delete();
	// 	toastr()->error('Deleted Successfully');
	// 	return redirect()->back();
	// }

	public function activeJob(Request $request, Company $company,string $modelName)
	{
		$row = DB::table('active_jobs')
			->where('company_id', $company->id)
			->where('status', 'test_table')
			->where('model_name', 'SalesGatheringTest')
			->where('model',$modelName)
			->first();
		return ($row === null) ? 0 :  1;
	}
	/**
	 * Shows which fields in the last failed upload didn't validate,
	 * and why. Renders resources/js/Pages/InvoiceUpload/Failed.vue.
	 */
	public function lastUploadFailed($companyId,$modelName){
		$rows = Cache::get(generateCacheKeyForValidationRow($companyId,$modelName),[]);
		$headers = exportableFields($companyId,$modelName)->fields ;
		if($modelName != 'SalesGathering'){
			$headers = HArr::removeKeyFromArrayByValue($headers,['net_sales_value']);
		}
		$headers = convertIdsToNames($headers);

		$formattedRows = collect($rows)->map(function ($items, $rowNumber) use ($headers) {
			$cells = collect($headers)->map(function ($header) use ($items) {
				$failed = isset($items[$header]['value']);
				return [
					'failed' => $failed,
					'message' => $failed ? ($items[$header]['message'] ?? '-') : null,
					'value' => $failed ? ($items[$header]['value'] ?? '-') : null,
				];
			})->values();
			return ['rowNumber' => $rowNumber, 'cells' => $cells];
		})->values();

		return Inertia::render('InvoiceUpload/Failed', [
			'modelName' => $modelName,
			'modelDisplayName' => camelToTitle($modelName),
			'headers' => array_values($headers),
			'rows' => $formattedRows,
			'backUrl' => route('salesGatheringImport', ['company' => $companyId, 'model' => $modelName]),
		]);
	}

	/**
	 * Real single-record Customer/Supplier Invoice form — the actual
	 * scope, once narrowed down from the original 865-line generic
	 * shared form (which also serves Financial Statements, Expense
	 * Analysis, Contract Loan Schedule, etc. — none of that applies
	 * here). Renders resources/js/Pages/InvoiceUpload/InvoiceForm.vue,
	 * shared for both add and edit (same pattern used throughout
	 * this migration). The cascading Customer/Supplier → Project Name
	 * → Sales/Purchase Order dropdowns use the exact same two
	 * existing, UNCHANGED lookup endpoints the original used
	 * (get.projects.for.customer.or.supplier, get.po.or.so.from.contract).
	 */
	protected function renderInvoiceForm(Company $company, string $modelName, $model)
	{
		$isCustomer = $modelName === 'CustomerInvoice';
		$exportables = getExportableFieldsForModel($company->id, $modelName);

		$dateWords = ['date', 'Date', 'Estimated'];
		$numericFields = getNumericExportFields();
		$numericNegativeFields = getNumericWithNegativeAllowedExportFields();

		// Resolve the model's currently-selected cascading IDs (contract/
		// sales-order/purchase-order), matching the original's hidden
		// #current-contract-id / #current-sales-order-id lookups exactly.
		$currentContractId = ($model && $model->contract_name) ? optional(\App\Models\Contract::where('company_id', $company->id)->where('name', $model->contract_name)->first())->id : null;
		$currentSalesOrderId = ($model && $isCustomer && $model->sales_order_number) ? optional(\App\Models\SalesOrder::where('company_id', $company->id)->where('so_number', $model->sales_order_number)->first())->id : null;
		$currentPurchaseOrderId = ($model && !$isCustomer && $model->purchases_order_number) ? optional(\App\Models\PurchaseOrder::where('company_id', $company->id)->where('po_number', $model->purchases_order_number)->first())->id : null;

		$fields = collect($exportables)->map(function ($label, $fieldName) use ($model, $isCustomer, $dateWords, $numericFields, $numericNegativeFields, $company, $currentContractId, $currentSalesOrderId, $currentPurchaseOrderId) {
			$type = 'text';
			$options = null;
			$value = $model ? ($model->{$fieldName} ?? null) : null;
			$submitField = $fieldName;

			if (str_contains($label, 'Customer Name')) {
				$type = 'customer_select';
				$submitField = 'customer_id';
				$options = \App\Models\Partner::where('company_id', $company->id)->where('is_customer', 1)->pluck('name', 'id');
				$value = $model->customer_id ?? null;
			} elseif (str_contains($label, 'Supplier Name')) {
				$type = 'supplier_select';
				$submitField = 'supplier_id';
				$options = \App\Models\Partner::where('company_id', $company->id)->where('is_supplier', 1)->pluck('name', 'id');
				$value = $model->supplier_id ?? null;
			} elseif (str_contains($label, 'Business Sector')) {
				$type = 'business_sector_select';
				$options = \App\Models\CashVeroBusinessSector::where('company_id', $company->id)->pluck('name', 'name');
				$value = $model->business_sector ?? null;
			} elseif (str_contains($label, 'Project Name') || str_contains($label, 'Contract Name')) {
				// SupplierInvoice's cascading field is labeled 'Contract Name'
				// (CustomerInvoice's equivalent is 'Project Name') — same
				// project_select cascade (Partner → Contract →
				// Sales/Purchase Order) applies to both; see
				// getProjectsForCustomerOrSupplierController, which already
				// returns whichever contracts belong to the selected
				// customer/supplier regardless of what this field is called.
				$type = 'project_select';
				$submitField = 'contract_id';
				$value = $currentContractId;
			} elseif (str_contains($label, 'Sales Order Number')) {
				$type = 'sales_order_select';
				$submitField = 'sales_order_id';
				$value = $currentSalesOrderId;
			} elseif (str_contains($label, 'Purchase') && str_contains($label, 'Order') && str_contains($label, 'Number')) {
				// Bug fix: this used to only check for 'Purchase' + 'Order',
				// which also matched the 'Purchases Order Date' label —
				// turning it into a second dropdown bound to the same
				// purchases_order_id field as the real Purchases Order
				// Number dropdown (so it displayed the PO number instead of
				// a date, and silently dropped the real date value on
				// submit). Requiring 'Number' too, matching how 'Sales
				// Order Number' is already matched by exact phrase above.
				$type = 'purchase_order_select';
				$submitField = 'purchases_order_id';
				$value = $currentPurchaseOrderId;
			} elseif ($fieldName === 'currency') {
				/**
				 * Currency is chosen, not typed. It was a free-text box,
				 * so "egp" / "EGP " / "L.E" all saved as different
				 * currencies — and every screen downstream groups and
				 * filters by this exact string (the balances tabs, the
				 * invoice report, the statements), so one typo silently
				 * split a customer's balance across two currencies.
				 */
				$type = 'currency_select';
				/**
				 * getCurrencies() is already [code => translated label].
				 * This used to be mapWithKeys(fn ($c) => [$c => $c]),
				 * which receives the VALUE — so under /ar it built
				 * ['جنيه مصري' => 'جنيه مصري'] and the option's value,
				 * the string actually written to `currency`, became the
				 * Arabic name. Every screen downstream groups by that
				 * exact string, so an invoice saved in Arabic would have
				 * become a currency of its own. Keep the code as the
				 * value; translate only what is displayed.
				 */
				$options = getCurrencies();
			} elseif ($fieldName === 'net_invoice_amount') {
				/**
				 * "Total Invoice Amount" is not typed either — it is
				 * invoice amount + VAT − withholding, computed live in
				 * the form and shown read-only. Typing it by hand let it
				 * disagree with its own three components, and it is the
				 * number the receivable (net_balance) is built from.
				 */
				$type = 'computed_total';
			} elseif (str_contains($fieldName, 'date') || collect($dateWords)->contains(fn ($w) => str_contains($label, $w))) {
				$type = 'date';
				if ($value) {
					try { $value = \Carbon\Carbon::parse($value)->format('Y-m-d'); } catch (\Exception $e) {}
				}
			} elseif (collect($numericFields)->contains($label) || collect($numericNegativeFields)->contains($label)) {
				$type = 'number';
			}

			return [
				'field' => $submitField,
				'label' => $label,
				'type' => $type,
				'value' => $value,
				'options' => $options,
			];
		})->values();

		return Inertia::render('InvoiceUpload/InvoiceForm', [
			'modelName' => $modelName,
			'modelDisplayName' => camelToTitle($modelName),
			'fields' => $fields,
			/**
			 * The three inputs "Total Invoice Amount" is computed from.
			 * Named here rather than assumed in the form, because which
			 * columns a company shows is configurable — company 146 does
			 * not display Withhold Amount at all, and a term that is not
			 * on the form contributes 0 rather than breaking the sum.
			 */
			'totalInvoiceFormula' => [
				'target' => 'net_invoice_amount',
				'add' => ['invoice_amount', 'vat_amount'],
				'subtract' => ['withhold_amount'],
			],
			'projectsUrl' => route('get.projects.for.customer.or.supplier', ['company' => $company->id]),
			'poOrSoUrl' => route('get.po.or.so.from.contract', ['company' => $company->id]),
			'submitUrl' => $model
				? route('admin.update.analysis', ['company' => $company->id, 'model' => $modelName, 'modelId' => $model->id])
				: route('admin.store.analysis', ['company' => $company->id, 'model' => $modelName]),
			'isEdit' => (bool) $model,
			'backUrl' => route('view.uploading', ['company' => $company->id, 'model' => $modelName]),
		]);
	}

	public function createModel(Company $company ,Request $request, string $modelName )
	{
		if (in_array($modelName, ['CustomerInvoice', 'SupplierInvoice'], true)) {
			return $this->renderInvoiceForm($company, $modelName, null);
		}
		if (in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule'], true)) {
			return $this->renderScheduleForm($company, $request, $modelName, null);
		}

		abort(404);
	}

	/**
	 * Create / edit a single LoanSchedule or ContractLoanSchedule row.
	 * Renders resources/js/Pages/InvoiceUpload/ScheduleForm.vue.
	 */
	protected function renderScheduleForm(Company $company, Request $request, string $modelName, $model)
	{
		$exportables = getExportableFieldsForModel($company->id, $modelName);
		$isContractLoanSchedule = $modelName === 'ContractLoanSchedule';
		$dateFields = ['date', 'invoice_due_date', 'invoice_date'];
		$amountFields = ['invoice_amount', 'vat_amount', 'withhold_amount', 'collected_amount', 'paid_amount', 'net_balance', 'net_invoice_amount', 'beginning_balance', 'schedule_payment', 'cheque_amount', 'interest_amount', 'principle_amount', 'end_balance'];

		$loanId = $request->get('medium_term_loan_id') ?? $request->get('loanId') ?? session('loan_schedule_import_loan_id_' . $company->id);
		$contractId = $request->get('leasing_contract_id') ?? $request->get('loanId') ?? session('contract_loan_schedule_import_contract_id_' . $company->id);
		if ($model) {
			$loanId = $model->medium_term_loan_id ?? $loanId;
			$contractId = $model->leasing_contract_id ?? $contractId;
		}

		$fields = collect($exportables)->map(function ($label, $fieldName) use ($model, $dateFields, $amountFields, $isContractLoanSchedule, $company) {
			$value = $model ? ($model->{$fieldName} ?? null) : null;
			if ($isContractLoanSchedule && $fieldName === 'drawee_bank' && $model) {
				/**
				 * ⚠️ REAL BUG FIXED HERE: ContractLoanSchedule has both
				 * a draweeBank() relation AND a getDraweeBankAttribute()
				 * accessor (for the raw 'drawee_bank' pseudo-column).
				 * Str::studly('draweeBank') and Str::studly('drawee_bank')
				 * both resolve to 'DraweeBank', so Eloquent's magic
				 * property access ($model->draweeBank) gets intercepted
				 * by the ACCESSOR and returns a plain string — never the
				 * relation object — causing "Attempt to read property
				 * bank on string" the moment ->bank is chained onto it.
				 * getRelationValue() calls the relation method directly,
				 * bypassing the accessor collision entirely — same
				 * pattern the model's own hasDraweeBank()/
				 * getDraweeBankName() already use for this exact reason.
				 */
				$draweeBankFinancialInstitution = $model->getRelationValue('draweeBank');
				$value = $draweeBankFinancialInstitution?->bank?->view_name ?: $draweeBankFinancialInstitution?->name ?? null;
			}
			$type = 'text';
			$options = null;
			if ($isContractLoanSchedule && $fieldName === 'drawee_bank') {
				$type = 'bank_select';
				/**
				 * ⚠️ REAL BUG FIXED HERE (client-flagged): getCompanyDraweeBankNames()
				 * only returns banks matching its own filter (company +
				 * type=BANK). If the row's actually-saved bank doesn't
				 * happen to be in that filtered list for any reason —
				 * renamed since, deactivated, a data mismatch — the
				 * <select> has no matching <option> to show as selected,
				 * and silently renders blank even though the value is
				 * correctly saved on the row. Guaranteeing the row's own
				 * current value is always present in the list closes
				 * that gap regardless of the underlying reason.
				 */
				$options = getCompanyDraweeBankNames($company->id);
				if ($value && ! in_array($value, $options, true)) {
					array_unshift($options, $value);
				}
			} elseif ($isContractLoanSchedule && $fieldName === 'account_number') {
				$type = 'account_number_select';
			} elseif (in_array($fieldName, $dateFields, true)) {
				$type = 'date';
				if ($value) {
					try { $value = \Carbon\Carbon::parse($value)->format('Y-m-d'); } catch (\Exception $e) {}
				}
			} elseif (in_array($fieldName, $amountFields, true)) {
				$type = 'number';
				if ($value === null) {
					$value = 0;
				}
			}
			return [
				'field' => $fieldName,
				'label' => $label,
				'type' => $type,
				'value' => $value,
				'options' => $options,
			];
		})->values();

		$contextId = $isContractLoanSchedule ? $contractId : $loanId;
		$backParams = getUploadingRouteParams($company->id, $modelName, $contextId ? (string) $contextId : null);

		return Inertia::render('InvoiceUpload/ScheduleForm', [
			'modelName' => $modelName,
			'modelDisplayName' => $isContractLoanSchedule ? __('Contract Leasing Schedule') : camelToTitle($modelName),
			'fields' => $fields,
			'accountNumbersUrl' => $isContractLoanSchedule ? route('contract.loan.schedule.account.numbers', ['company' => $company->id]) : null,
			'submitUrl' => $model
				? route('admin.update.analysis', ['company' => $company->id, 'model' => $modelName, 'modelId' => $model->id])
				: route('admin.store.analysis', ['company' => $company->id, 'model' => $modelName]),
			'isEdit' => (bool) $model,
			'backUrl' => route('view.uploading', $backParams),
			'leasingContractId' => $isContractLoanSchedule ? $contractId : null,
			'mediumTermLoanId' => ! $isContractLoanSchedule ? $loanId : null,
		]);
	}
	/**
	 * ⚠️ REAL BUG FIXED HERE (client-flagged): this used to run
	 * str_replace(",", "", $value) on EVERY submitted field — not
	 * just numeric ones. For ContractLoanSchedule specifically, that
	 * included `drawee_bank` (a bank's display NAME, not a number)
	 * and `account_number`. Any bank whose name legitimately contains
	 * a comma (a perfectly normal thing in a display name) would get
	 * silently mangled before prepareContractLoanScheduleRowForStorage()
	 * tried to look it up — the lookup would then fail to find a
	 * match, and drawee_bank_id would be saved as null, silently
	 * erasing a correctly-selected bank on every single save (which
	 * also cascades into Account Number disappearing on the next
	 * edit, since that field's options are keyed off Drawee Bank).
	 * Now this only strips thousands-separator commas from fields
	 * that are actually numeric — everything else (bank names,
	 * account numbers, cheque numbers, dates, statuses, notes, etc.)
	 * passes through completely untouched.
	 */
	protected function removeCommaFromNumbers(array $items):array{
		$numericFieldNames = [
			'beginning_balance', 'cheque_amount', 'schedule_payment',
			'interest_amount', 'principle_amount', 'end_balance', 'remaining',
			'invoice_amount', 'vat_amount', 'withhold_amount', 'collected_amount',
			'paid_amount', 'net_balance', 'net_invoice_amount',
		];
		$result = [];
		foreach($items as $key => $value){
			$result[$key] = in_array($key, $numericFieldNames, true) ? str_replace(",","",$value) : $value;
		}
		return $result ;
	}
	public function storeModel(Company $company ,Request $request, string $modelName )
	{
		$companyId = $company->id;
		$class = '\App\Models\\'.$modelName ;
		$model = new $class;
		
		/**
		 * ⚠️ REAL BUG FIXED HERE (client-flagged): company_id was never
		 * set on an invoice created or edited through this form — only
		 * the LoanSchedule / ContractLoanSchedule branches below ever
		 * set it. The row landed with company_id = 0, which made it
		 * invisible almost everywhere:
		 *
		 *   - /uploading/{model}      filters where('company_id', ...)  → gone
		 *   - the Invoice Report      filters where('company_id', ...)  → "No Data Found"
		 *   - Customers Balances      joins on partners.company_id and never
		 *                             filters the invoice's own company_id
		 *                             → the invoice SHOWED here, which is why
		 *                               the balance looked right while every
		 *                               other screen denied the invoice existed.
		 *
		 * Set from the route-bound company, not from the request, so it
		 * cannot be spoofed by posting a company_id field.
		 */
		if($modelName == 'CustomerInvoice'){
					$tableDataArr = $request->except(['tableIds','_token','model_id','id','creator_id','contract_id','sales_order_id','company_id']);
						$tableDataArr['company_id'] = $companyId;
						$salesOrderId = $request->get('sales_order_id') ;
						$contractId = $request->get('contract_id') ;
						$tableDataArr['sales_order_number'] =$salesOrderId ? SalesOrder::find($salesOrderId)->getNumber() : null ; 
						$contractName = $contractId ? Contract::find($contractId)->getName() : null ;
						$tableDataArr['contract_name'] = $contractName ; 
						$tableDataArr['project_name'] = $contractName ; 
						$customerName = Partner::find($tableDataArr['customer_id'])->getName();
						$tableDataArr['customer_name'] = $customerName ; 
					
						CustomerInvoice::create($tableDataArr);
					}
					if($modelName == 'SupplierInvoice'){
						// Same company_id fix as CustomerInvoice above.
						$tableDataArr = $request->except(['tableIds','_token','model_id','id','creator_id','contract_id','purchases_order_id','company_id']);
						$tableDataArr['company_id'] = $companyId;
						$purchasesOrderId = $request->get('purchases_order_id') ;
						$contractId = $request->get('contract_id') ;
						$tableDataArr['purchases_order_number'] =$purchasesOrderId ? PurchaseOrder::find($purchasesOrderId)->getNumber() : null ; 
						$contractName = $contractId ? Contract::find($contractId)->getName() : null;
						$tableDataArr['contract_name'] = $contractName ; 
							$tableDataArr['project_name'] = $contractName ; 
								$supplierName = Partner::find($tableDataArr['supplier_id'])->getName();
								$tableDataArr['supplier_name'] = $supplierName ; 
							SupplierInvoice::create($tableDataArr);
					}

		if ($modelName === 'ContractLoanSchedule') {
			$tableDataArr = $request->except(['tableIds','_token','model_id','id','creator_id','company_id','leasing_contract_id','loanId','medium_term_loan_id']);
			$tableDataArr['company_id'] = $companyId;
			$tableDataArr = $this->removeCommaFromNumbers($tableDataArr);
			$tableDataArr = $this->prepareContractLoanScheduleRowForStorage($companyId, $tableDataArr, $request);
			$model->create($tableDataArr);
		} elseif ($modelName === 'LoanSchedule') {
			$tableDataArr = $request->except(['tableIds','_token','model_id','id','creator_id','company_id','leasing_contract_id','loanId','medium_term_loan_id']);
			$tableDataArr['company_id'] = $companyId;
			$tableDataArr = $this->removeCommaFromNumbers($tableDataArr);
			$tableDataArr = $this->prepareLoanScheduleRowForStorage($companyId, $tableDataArr, $request);
			$model->create($tableDataArr);
		}

		$redirectParams = getUploadingRouteParams(
			$companyId,
			$modelName,
			$modelName === 'ContractLoanSchedule'
				? (string) ($request->get('leasing_contract_id') ?? $request->get('loanId') ?? session('contract_loan_schedule_import_contract_id_' . $companyId))
				: ($modelName === 'LoanSchedule'
					? (string) ($request->get('medium_term_loan_id') ?? $request->get('loanId') ?? session('loan_schedule_import_loan_id_' . $companyId))
					: null)
		);
		
		return redirect()->route('view.uploading', $redirectParams)->with('success',__('Done'));	
	}

	protected function prepareContractLoanScheduleRowForStorage(int $companyId, array $tableDataArr, Request $request): array
	{
		$contractId = $request->get('leasing_contract_id') ?? $request->get('loanId') ?? session('contract_loan_schedule_import_contract_id_' . $companyId);
		$draweeBankName = trim((string) ($tableDataArr['drawee_bank'] ?? ''));
		$draweeBankId = $draweeBankName !== ''
			? resolveDraweeBankFinancialInstitutionId($companyId, $draweeBankName)
			: null;
		$chequeAmount = (float) ($tableDataArr['cheque_amount'] ?? 0);

		// Bug fix (client-flagged, confirmed 2026-08-15): same resolution as
		// SalesGatheringTestJob's bulk-import path — see that class for the
		// full explanation. This is the single-row path (the "Create" form
		// and "Edit cached row" screen), so it needs the same fix.
		$accountNumberText = trim((string) ($tableDataArr['account_number'] ?? ''));
		$financialInstitutionAccountId = ($draweeBankId && $accountNumberText !== '')
			? \App\Models\FinancialInstitutionAccount::findByAccountNumber($accountNumberText, $companyId, $draweeBankId)?->id
			: null;

		unset($tableDataArr['drawee_bank']);

		return array_merge($tableDataArr, [
			'leasing_contract_id' => $contractId,
			'drawee_bank_id' => $draweeBankId,
			'financial_institution_account_id' => $financialInstitutionAccountId,
			'remaining' => $chequeAmount,
			'status' => resolveLoanScheduleStatus($chequeAmount, $chequeAmount, $tableDataArr['date'] ?? null),
		]);
	}

	protected function prepareLoanScheduleRowForStorage(int $companyId, array $tableDataArr, Request $request): array
	{
		$loanId = $request->get('medium_term_loan_id') ?? $request->get('loanId') ?? session('loan_schedule_import_loan_id_' . $companyId);
		$schedulePayment = (float) ($tableDataArr['schedule_payment'] ?? 0);

		return array_merge($tableDataArr, [
			'medium_term_loan_id' => $loanId,
			'remaining' => $tableDataArr['remaining'] ?? $schedulePayment,
			'status' => $tableDataArr['status'] ?? resolveLoanScheduleStatus($schedulePayment, $schedulePayment, $tableDataArr['date'] ?? null),
		]);
	}
	
	
	
	
	
	public function editModel(Company $company ,Request $request, string $modelName,$modelId )
	{
		if (in_array($modelName, ['CustomerInvoice', 'SupplierInvoice'], true)) {
			$model = ('\App\Models\\'.$modelName)::find($modelId);
			return $this->renderInvoiceForm($company, $modelName, $model);
		}
		if (in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule'], true)) {
			$query = ('\App\Models\\'.$modelName)::query();
			if ($modelName === 'ContractLoanSchedule') {
				$query->with('draweeBank.bank');
			}
			$model = $query->findOrFail($modelId);
			return $this->renderScheduleForm($company, $request, $modelName, $model);
		}

		abort(404);
	}
	public function updateModel(Company $company ,Request $request, string $modelName,$modelId )
	{
		$companyId = $company->id;
		$model = ('\App\Models\\'.$modelName)::find($modelId) ;
		
		if($modelName == 'CustomerInvoice'){
					// Same company_id fix as storeModel() — which also repairs a
					// row already saved without one, the next time it is edited.
					$tableDataArr = $request->except(['tableIds','_token','model_id','id','creator_id','contract_id','sales_order_id','company_id']);
						$tableDataArr['company_id'] = $companyId;
						$salesOrderId = $request->get('sales_order_id') ;
						$contractId = $request->get('contract_id') ;
						$tableDataArr['sales_order_number'] =$salesOrderId ? SalesOrder::find($salesOrderId)->getNumber() : null ; 
						$contractName = $contractId ? Contract::find($contractId)->getName() : null ;
						$tableDataArr['contract_name'] = $contractName ; 
						$tableDataArr['project_name'] = $contractName ; 
							$customerName = Partner::find($tableDataArr['customer_id'])->getName();
						$tableDataArr['customer_name'] = $customerName ; 
						$model->update($tableDataArr);
					}
					if($modelName == 'SupplierInvoice'){
						// Same company_id fix as storeModel().
						$tableDataArr = $request->except(['tableIds','_token','model_id','id','creator_id','contract_id','purchases_order_id','company_id']);
						$tableDataArr['company_id'] = $companyId;
						$purchasesOrderId = $request->get('purchases_order_id') ;
						$contractId = $request->get('contract_id') ;
						$tableDataArr['purchases_order_number'] =$purchasesOrderId ? PurchaseOrder::find($purchasesOrderId)->getNumber() : null ; 
						$contractName = $contractId ? Contract::find($contractId)->getName() : null;
						$tableDataArr['contract_name'] = $contractName ; 
							$tableDataArr['project_name'] = $contractName ; 
								$supplierName = Partner::find($tableDataArr['supplier_id'])->getName();
								$tableDataArr['supplier_name'] = $supplierName ; 
							$model->update($tableDataArr);
							
					}
					
					
					
		if ($modelName === 'ContractLoanSchedule') {
			/**
			 * Bug fix (client-flagged, confirmed 2026-08-15): hiding the
			 * Edit button in the UI for a paid/partially-paid installment
			 * (see InvoiceUpload/Index.vue) only stops it from that
			 * screen — this route had no matching check, so the same edit
			 * could still be submitted directly. Guarded here too, same
			 * condition as the UI: any recorded payment blocks it.
			 */
			if ((float) $model->getRemaining() < (float) $model->getChequeAmount()) {
				toastr()->error(__('This installment has a payment recorded against it and can no longer be edited.'));
				return redirect()->back();
			}

			$tableDataArr = $request->except(['tableIds','_token','model_id','id','creator_id','company_id','leasing_contract_id','loanId','medium_term_loan_id']);
			$tableDataArr['company_id'] = $companyId;
			$tableDataArr = $this->removeCommaFromNumbers($tableDataArr);
			$tableDataArr = $this->prepareContractLoanScheduleRowForStorage($companyId, $tableDataArr, $request);
			$model->update($tableDataArr);
		} elseif ($modelName === 'LoanSchedule') {
			/**
			 * Bug fix (client-flagged, confirmed 2026-08-15): hiding the
			 * Edit button in the UI for a paid/partially-paid Medium Term
			 * Loan installment (see InvoiceUpload/Index.vue) only stops
			 * it from that screen — this route had no matching check, so
			 * the same edit could still be submitted directly. Guarded
			 * here too, same condition as the UI: any recorded payment
			 * blocks it.
			 */
			if ((float) $model->getRemaining() < (float) $model->getSchedulePayment()) {
				toastr()->error(__('This installment has a payment recorded against it and can no longer be edited.'));
				return redirect()->back();
			}

			$tableDataArr = $request->except(['tableIds','_token','model_id','id','creator_id','company_id','leasing_contract_id','loanId','medium_term_loan_id']);
			$tableDataArr['company_id'] = $companyId;
			$tableDataArr = $this->removeCommaFromNumbers($tableDataArr);
			$tableDataArr = $this->prepareLoanScheduleRowForStorage($companyId, $tableDataArr, $request);
			$model->update($tableDataArr);
		}
		if($partnerId = $request->get('customer_id')){
			$partner = Partner::find($partnerId);
			$model->update([
				'customer_id'=>$partnerId,
				'customer_name'=>$partner->getName(),
			]);
		}
		if($partnerId = $request->get('supplier_id')){
			$partner = Partner::find($partnerId);
			$model->update([
				'supplier_id'=>$partnerId,
				'supplier_name'=>$partner->getName(),
			]);
		}
		
		
		return redirect()->route('view.uploading', getUploadingRouteParams(
			$companyId,
			$modelName,
			$modelName === 'ContractLoanSchedule'
				? (string) ($model->leasing_contract_id ?? $request->get('leasing_contract_id') ?? session('contract_loan_schedule_import_contract_id_' . $companyId))
				: ($modelName === 'LoanSchedule'
					? (string) ($model->medium_term_loan_id ?? $request->get('medium_term_loan_id') ?? session('loan_schedule_import_loan_id_' . $companyId))
					: null)
		))->with('success',__('Done'));	
	}
	
	
}
