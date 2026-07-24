<?php

namespace App\Http\Controllers;

use App\Exports\ContractLoanScheduleHeadersExport;
use App\Exports\HeadersExport;
use App\Helpers\HArr;
use App\Models\Company;
use App\Models\ContractLoanSchedule;
use App\Models\CustomizedFieldsExportation;
use App\Models\LoanSchedule;
use App\Models\TablesField;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * ExportTable
 * ------------------------------------------------------------------
 * "Select Template Field" — one shared page serving several
 * different upload types (Customer Invoice, Supplier Invoice, Loan
 * Schedule, Contract/Leasing Schedule, and others via
 * getUploadParamsFromType()), with genuinely different rules per
 * type:
 *   - Customer/Supplier Invoice: real, saved-per-company field
 *     selection (CustomizedFieldsExportation). A few fields are
 *     always-on and locked (net_sales_value; sales_value once any
 *     discount field is picked), a few are hidden from the list
 *     entirely (invoice_status, net_balance, plus a small global
 *     hidden set), and 'date' is always forced on.
 *   - Loan Schedule / Contract Loan Schedule: NOT actually
 *     customizable — confirmed from the code, every field is forced
 *     checked AND disabled regardless of what's clicked; saving
 *     always exports the full fixed field set for these two models.
 *
 * Submitting triggers a real Excel file download (not a normal
 * save-and-redirect). The original used a session flag
 * ('redirectTo') plus 2-second client-side polling
 * (GET /removeSessionForRedirect) to redirect the browser to the
 * upload/import page once the download had started, since a file
 * response can't also carry an HTTP redirect. Per the project owner's
 * explicit decision, replaced here with an immediate client-side
 * redirect right alongside the native file-download form submission
 * — no polling needed. The 'redirectTo' session flag is no longer
 * set, since nothing reads it anymore.
 *
 * ⚠️ Confirmed real bug, NOT fixed here without a decision first:
 * customizedTableFieldSave() checks
 * `getLastSegmentInRequest() == 'customerInvoice'` / `'supplierInvoice'`
 * (lowercase-first) — but the actual URL segment passed everywhere
 * else in this app is 'CustomerInvoice' / 'SupplierInvoice'
 * (PascalCase, matching getUploadParamsFromType()'s array keys). This
 * is a case-sensitive `==` comparison that can never be true for the
 * real values in use — the same bug class already found and
 * documented elsewhere in this codebase (a comparison that looks
 * intentional but can never match). Net effect: the "always append
 * these extra fields" block for Customer/Supplier Invoice
 * (invoice_status, invoice_date/number, collected/paid_amount,
 * net_balance, customer/supplier name and amount) never actually
 * runs. Left completely untouched here, since fixing it would change
 * what columns land in a real exported Excel file people may already
 * depend on — flagging for a separate, explicit decision.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ customizedTableField() → the page-rendering branch (when
 *      $view !== 'selected_fields') MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/TemplateFieldSelection/Index.vue. The
 *      'selected_fields' branch — a plain data-returning helper used
 *      elsewhere (e.g. SalesGatheringController), not a page —
 *      UNCHANGED.
 *   ✅ customizedTableFieldSave() → UNCHANGED except removing the
 *      now-dead session('redirectTo') line (see above). Financial /
 *      export logic (field list building, the confirmed bug above,
 *      the actual Excel generation) untouched.
 */
class ExportTable extends Controller
{
	/**
	 * Redirect To the View Of fields For Each Model
	 *
	 * ✅ MIGRATED to Vue + Inertia for the page-rendering branch only.
	 */
	public  function customizedTableField(Company $company, $model, $view)
	{
		$loanScheduleExportables = LoanSchedule::getExportableFields();
		$contractLoanScheduleExportables = ContractLoanSchedule::getExportableFields();
	

		$model_name = 'App\\Models\\' . $model;
		$model_obj = new $model_name;
		$columns  = Schema::getColumnListing($model_obj->getTable());
		$modelExportableFields = CustomizedFieldsExportation::where('model_name', $model)
		->where('company_id', $company->id)->first();
		$selected_fields = ($modelExportableFields !== null) ? $modelExportableFields->fields : [];
		if($model=='LoanSchedule'){
			$selected_fields  = $loanScheduleExportables;
		}
		if($model=='ContractLoanSchedule'){
			$selected_fields  = $contractLoanScheduleExportables;
		}
		if ($view == 'selected_fields') {
			if($model == 'LoanSchedule'){
				return $loanScheduleExportables;
			}
			if($model == 'ContractLoanSchedule'){
				return $contractLoanScheduleExportables;
			}
			return  $this->columnsFiltration($model, $company, $view, $selected_fields);
		}
	
		$columnsWithViewingNames =  $this->columnsFiltration($model, $company, $view, $selected_fields);
		$isLoanScheduleModel = in_array($model, ['LoanSchedule', 'ContractLoanSchedule'], true);
		if($model == 'LoanSchedule'){
			 $columnsWithViewingNames = $loanScheduleExportables;
		}
		if($model == 'ContractLoanSchedule'){
			 $columnsWithViewingNames = $contractLoanScheduleExportables;
		}
		$modelName = $model;
		if($modelName == 'CustomerInvoice'){
			unset($columnsWithViewingNames['collected_amount']);
		}
		if($modelName == 'SupplierInvoice'){
			unset($columnsWithViewingNames['paid_amount']);
		}

		// Fields never shown as a checkbox row at all — matches
		// hideExportField() (global hidden set) plus invoice_status /
		// net_balance, hidden specifically on this page.
		$alwaysHiddenFields = ['local_or_export', 'sub_category', 'return_reason', 'quantity_status', 'quantity_bonus', 'invoice_status', 'net_balance'];

		$fields = collect($columnsWithViewingNames)
			->reject(fn ($label, $fieldName) => in_array($fieldName, $alwaysHiddenFields, true))
			->map(function ($label, $fieldName) use ($selected_fields, $isLoanScheduleModel) {
				$isLocked = $isLoanScheduleModel
					|| $fieldName === 'net_sales_value'
					|| $fieldName === 'invoice_status'
					|| $fieldName === 'date';
				$isChecked = $isLoanScheduleModel
					|| $fieldName === 'net_sales_value'
					|| $fieldName === 'invoice_status'
					|| $fieldName === 'date'
					|| in_array($fieldName, $selected_fields, true);
				return [
					'field_name' => $fieldName,
					'label' => $label,
					'checked' => $isChecked,
					'locked' => $isLocked,
				];
			})->values();

		return \Inertia\Inertia::render('TemplateFieldSelection/Index', [
			'company' => ['id' => $company->id],
			'model' => $model,
			'modelDisplayName' => camelToTitle($modelName),
			'view' => $view,
			'isLoanScheduleModel' => $isLoanScheduleModel,
			'fields' => $fields,
			'submitUrl' => route('table.fields.selection.save', ['company' => $company->id, 'model' => $model, 'modelName' => $modelName]),
			'redirectUrl' => route('salesGatheringImport', ['company' => $company->id, 'model' => $modelName]),
			'navUrls' => [
				'home' => route('home', ['company' => $company->id]),
				'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
				'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
				'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
				'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
			],
		]);
	}
	/**
	 * Saving Chosen Exportable Fields — UNCHANGED except removing the
	 * now-dead session('redirectTo') line (the Vue page redirects
	 * immediately client-side instead of polling for this flag).
	 */
	public  function customizedTableFieldSave(Request $request, Company $company, $model, $modelName)
	{

		$this->validation($request);

		$request['company_id'] = $company->id;
		$fields = [];
		$fields = $request->get('model_name') == 'LoanSchedule'
			? array_keys(LoanSchedule::getExportableFields())
			: $request['fields'];
		if ($request->get('model_name') == 'ContractLoanSchedule') {
			$fields = array_keys(ContractLoanSchedule::getExportableFields());
		}

		count(array_intersect($fields, ['quantity_discount', 'cash_discount', 'special_discount', 'other_discounts'])) == 0
			?: $fields[count($fields)] = 'sales_value';
		$fields[count($fields)] = 'net_sales_value';
		if('customerInvoice' ==getLastSegmentInRequest()){
			$fields[] = 'invoice_status';
			$fields[] = 'collected_amount';
			$fields[] = 'net_balance';
			$fields[] = 'invoice_date';
			$fields[] = 'invoice_number';
			$fields[] = 'customer_name';
			$fields[] = 'customer_amount';
		}
		if('supplierInvoice' ==getLastSegmentInRequest()){
			$fields[] = 'invoice_status';
			$fields[] = 'paid_amount';
			$fields[] = 'net_balance';
			$fields[] = 'invoice_date';
			$fields[] = 'invoice_number';
			$fields[] = 'supplier_name';
			$fields[] = 'supplier_amount';
		}
		$request['fields'] = $fields;

		$modelExportableFields = CustomizedFieldsExportation::where('model_name', $model)
			->where('company_id', $company->id)->first();
			
			
			$modelExportableFields !== null ? $modelExportableFields->update($request->all())
			: CustomizedFieldsExportation::create($request->all());
			
			$columnsWithViewingNames = $this->columnsFiltration($model, $company, 'selected_fields', $request->fields);
			if(isset($columnsWithViewingNames['invoice_status'])){
				unset($columnsWithViewingNames['invoice_status']);
			}
			if(isset($columnsWithViewingNames['net_balance'])){
				unset($columnsWithViewingNames['net_balance']);
			}
		if($request->get('model_name') == 'LoanSchedule'){
			$columnsWithViewingNames = LoanSchedule::getExportableFields();
		}
		if($request->get('model_name') == 'ContractLoanSchedule'){
			$columnsWithViewingNames = ContractLoanSchedule::getExportableFields();

			return (new ContractLoanScheduleHeadersExport($company->id, $columnsWithViewingNames))
				->download($model . 'Fields.xlsx');
		}
		return (new HeadersExport($company->id, $columnsWithViewingNames))->download($model . 'Fields.xlsx');
	}

	/**
	 * Filtering Fields and returns Exportable Fields
	 */
	public function columnsFiltration($model_name, $company, $view, $selected_fields)
	{
		if ($view == 'selected_fields') {

			$columnsWithViewingNames = TablesField::where('model_name', $model_name)
				->whereIn('field_name', $selected_fields)
				->pluck('view_name', 'field_name')
				->toArray();
			} else {
				$columnsWithViewingNames = TablesField::where('model_name', $model_name)
				->pluck('view_name', 'field_name')
				->toArray();
			}
			
		return $columnsWithViewingNames;
	}
	/**
	 * Adding Display Name For Each Column
	 */
	public function DisplayFieldsNames($columns, $translate = false)
	{

		$columnsWithViewingNames = [];
		array_walk($columns, function ($columnName, $key) use (&$columnsWithViewingNames, $translate) {
			if (str_contains($columnName, '_id_')) {
				$viewingName = ucwords(str_replace('_id_', ' ', $columnName));
			} else {
				$viewingName = ucwords(str_replace('_', ' ', $columnName));
			}

			$columnsWithViewingNames[$columnName] = $translate === true ?  __($viewingName) : $viewingName;
		});
		return $columnsWithViewingNames;
	}
	/**
	 * validation
	 */
	public function validation($request)
	{
		if($request->get('model_name') == 'LoanSchedule'){
			return ;
		}
		$validation = [];
		if (!isset($request->fields) || (count($request->fields) == 0)) {
			$validation['fields'] = 'required';
		}
		$this->validate($request, @$validation, [
			'fields.required' => __("You must choose fields to be exported into excel sheet"),
		]);
	}
}
