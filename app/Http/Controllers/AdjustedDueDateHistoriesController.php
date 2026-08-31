<?php
namespace App\Http\Controllers;

use App\Support\Instructions\PageInstructions;
use App\Interfaces\Models\IInvoice;
use App\Models\Company;
use App\Models\DueDateHistory;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * AdjustedDueDateHistoriesController
 * ------------------------------------------------------------------
 * Lets a user push an invoice's due date back, keeping a full history
 * of every adjustment made (each with the invoice's net balance at
 * that moment). Reached from the "Adjust Due Date" button on the
 * Invoice Report page. Business rule, preserved exactly: only the
 * MOST RECENT history entry can be edited or deleted — earlier
 * entries are a locked audit trail.
 *
 * index() and edit() are the same page in two modes (add a new
 * adjustment vs. edit the last one) — always were, even in the
 * original Blade (one shared view, `$model` present or not). Kept
 * that way here: one shared Vue page, ONE private formatter
 * (formatForInertia) builds the props for both, so the list-row
 * shaping logic isn't duplicated.
 *
 * store()/update()/destroy() are UNCHANGED — they already returned
 * plain redirects, which Inertia is fine with as-is; no
 * Inertia-compatibility fix was needed here (unlike
 * InvoiceDeductionsController). The due_date still arrives as
 * MM/DD/YYYY (matching the old jQuery datepicker — see
 * DueDateHistory::setDueDateAttribute()'s mutator, which relies on
 * that exact format); the Vue page converts its native ISO date
 * input to that format before submitting, same pattern already used
 * on Time Of Deposit's renewal dates.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   - index() / edit()          → ALREADY migrated. Both return
 *                                  Inertia::render(), served by
 *                                  resources/js/Pages/Balances/AdjustDueDateHistory.vue
 *   - store()/update()/destroy() → UNCHANGED (already Inertia-safe).
 */
class AdjustedDueDateHistoriesController
{
    use GeneralFunctions;

	/**
	 * Shared prop-builder for index()/edit() — both render the same
	 * Vue page, this is the one place the due-date-history list gets
	 * shaped for it. $editingHistory is null for index() (add mode).
	 */
	protected function formatForInertia(Company $company, $invoice, string $invoiceModelName, string $customerNameOrSupplierNameText, $dueDateHistories, ?DueDateHistory $editingHistory)
	{
		$fullClassName = 'App\Models\\'.$invoiceModelName;
		$clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME;
		$previousDate = null;
		$rows = $dueDateHistories->values()->map(function ($history, $index) use (&$previousDate, $dueDateHistories, $company, $invoice, $invoiceModelName) {
			$currentDueDate = $history->getDueDateFormatted();
			$daysCount = $previousDate ? getDiffBetweenTwoDatesInDays(\Carbon\Carbon::make($previousDate), \Carbon\Carbon::make($currentDueDate)) : null;
			$isLast = $index === $dueDateHistories->count() - 1;
			$previousDate = $history->getDueDate();
			return [
				'id' => $history->id,
				'due_date_formatted' => $currentDueDate,
				'is_original' => $index === 0,
				'days_count' => $daysCount,
				'amount_formatted' => $history->getAmountFormatted(),
				'is_last' => $isLast,
				'edit_url' => route('edit.adjust.due.dates', ['company' => $company->id, 'modelId' => $invoice->id, 'modelType' => $invoiceModelName, 'dueDateHistory' => $history->id]),
				'delete_url' => route('delete.adjust.due.dates', ['company' => $company->id, 'modelId' => $invoice->id, 'modelType' => $invoiceModelName, 'dueDateHistory' => $history->id]),
			];
		});

		return [
			'company' => ['id' => $company->id],
			'invoice' => [
				'id' => $invoice->id,
				'name' => $invoice->getName(),
				'invoice_number' => $invoice->getInvoiceNumber(),
				'due_date_formatted' => $invoice->getDueDateFormatted(),
				'net_balance_formatted' => $invoice->getNetBalanceFormatted(),
				'currency' => $invoice->getCurrency(),
			],
			/* Both the list and the edit view come through here, so the
		   guide link is built once rather than at each call site. */
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::ADJUST_DUE_DATE, 'modelType' => $invoiceModelName]),
			'modelType' => $invoiceModelName,
			'customerNameOrSupplierNameText' => $customerNameOrSupplierNameText,
			'dueDateHistories' => $rows,
			'editingHistory' => $editingHistory ? [
				'id' => $editingHistory->id,
				'due_date_iso' => $editingHistory->getDueDate() ? \Carbon\Carbon::make($editingHistory->getDueDate())->format('Y-m-d') : null,
			] : null,
			'storeUrl' => route('store.adjust.due.dates', ['company' => $company->id, 'modelId' => $invoice->id, 'modelType' => $invoiceModelName]),
			'updateUrl' => $editingHistory ? route('update.adjust.due.dates', ['company' => $company->id, 'modelId' => $invoice->id, 'modelType' => $invoiceModelName, 'dueDateHistory' => $editingHistory->id]) : null,
			'indexUrl' => route('adjust.due.dates', ['company' => $company->id, 'modelId' => $invoice->id, 'modelType' => $invoiceModelName]),
			// Back to the Invoice Report page this was reached from.
			'backUrl' => route('view.invoice.report', ['company' => $company->id, 'partnerId' => $invoice->{$clientIdColumnName}, 'currency' => $invoice->getCurrency(), 'modelType' => $invoiceModelName]),
		];
	}

	public function index(Company $company,Request $request,$invoiceId,$invoiceModelName)
	{
		
		$fullClassName = 'App\Models\\'.$invoiceModelName;
		$invoice = ('App\Models\\'.$invoiceModelName)::find($invoiceId);
		$customerNameOrSupplierNameText  =(new $fullClassName) ->getClientNameText();
		$dueDateHistories = $invoice->dueDateHistories;
		
        return Inertia::render('Balances/AdjustDueDateHistory', $this->formatForInertia(
			$company, $invoice, $invoiceModelName, $customerNameOrSupplierNameText, $dueDateHistories, null
		));
    }
	public function store(Request $request, Company $company, $invoiceId , $invoiceModelName){
		$invoice = ('App\Models\\'.$invoiceModelName)::find($invoiceId);
		$date = $request->get('due_date') ;
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$dueDate = $year.'-'.$month.'-'.$day ;
		if(!$invoice->dueDateHistories->count()){
			/**
			 * * في حالة اول مرة هنضيف تاريخ تحصيل الفاتورة الاصلي اكنة تاريخ علشان نحتفظ بيه علشان ما يضيعش
			 */
			DueDateHistory::create([
				'company_id'=>$company->id ,
				'amount'=>$invoice->getNetBalance(),
				'due_date'=>$invoice->getInvoiceDueDate(),
				'model_id'=>$invoice->id,
				'model_type'=>$invoiceModelName
			]);
		}
		DueDateHistory::create([
			'company_id'=>$company->id ,
			'amount'=>$invoice->getNetBalance(),
			'due_date'=>$dueDate,
			'model_id'=>$invoice->id,
			'model_type'=>$invoiceModelName,
		]);
		
		$invoice->update([
			'invoice_due_date'=>$dueDate
		]);
		
		return redirect()->route('adjust.due.dates',['company'=>$company->id,'modelType'=>$invoiceModelName,'modelId'=>$invoice->id]);
	}
	public function edit(Request $request , Company $company ,  $invoiceId , $invoiceModelName, DueDateHistory $dueDateHistory){
		$invoice = ('App\Models\\'.$invoiceModelName)::find($invoiceId); 
		$dueDateHistories = $invoice->dueDateHistories;
		$fullClassName = 'App\Models\\'.$invoiceModelName;
		$customerNameOrSupplierNameText  =(new $fullClassName) ->getClientNameText();
        return Inertia::render('Balances/AdjustDueDateHistory', $this->formatForInertia(
			$company, $invoice, $invoiceModelName, $customerNameOrSupplierNameText, $dueDateHistories, $dueDateHistory
		));
	}
	public function update(Request $request , Company $company ,  $InvoiceId , $invoiceModelName , DueDateHistory $dueDateHistory){
		$date = $request->get('due_date') ;
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$customerInvoice = ('App\Models\\'.$invoiceModelName)::find($InvoiceId);
		$dueDate = $year.'-'.$month.'-'.$day ;
		
		$dueDateHistory->update([
			'due_date'=>$dueDate 
		]);
		$customerInvoice->update([
			'invoice_due_date'=>$dueDate
		]);
		
		return redirect()->route('adjust.due.dates',['company'=>$company->id,'modelType'=>$invoiceModelName,'modelId'=>$customerInvoice->id]);
		
	}
	public function destroy(Request $request , Company $company ,  $invoiceId , string $invoiceModelName , DueDateHistory $dueDateHistory)
	{
		$invoice = ('App\Models\\'.$invoiceModelName)::find($invoiceId); 
		$dueDateHistory->delete();
		$lastHistory = $invoice->dueDateHistories->last();
		
		$invoice->update([
			'invoice_due_date'=>$lastHistory->due_date 
			]) ; 
			/**
			 * * لو معدش فاضل غيرها دا معناه انه حذف تاني عنصر وبالتالي العنصر الاول اللي معتش فاضل غيره هو الديو ديت الاصلي ففي الحاله
			 * * دي هنحذفه معتش ليه لزمة
			 */
			if($invoice->dueDateHistories->count() == 1){
				$lastHistory->delete();
			}
			return redirect()->route('adjust.due.dates',['company'=>$company->id,'modelId'=>$invoice->id,'modelType'=>$invoiceModelName]);
	}
	
}
