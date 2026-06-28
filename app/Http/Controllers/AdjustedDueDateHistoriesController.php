<?php
namespace App\Http\Controllers;
use App\Interfaces\Models\IInvoice;
use App\Models\Company;
use App\Models\DueDateHistory;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;

class AdjustedDueDateHistoriesController
{
    use GeneralFunctions;
	public function index(Company $company,Request $request,$invoiceId,$invoiceModelName)
	{
		
		$fullClassName = 'App\Models\\'.$invoiceModelName;
		$invoice = ('App\Models\\'.$invoiceModelName)::find($invoiceId);
		$customerNameOrSupplierNameText  =(new $fullClassName) ->getClientNameText();
		$dueDateHistories = $invoice->dueDateHistories;
		
        return view('admin.adjusted-due-date-histories', [
			'company'=>$company,
			'invoice'=>$invoice,
			'dueDateHistories'=>$dueDateHistories,
			'modelType'=>$invoiceModelName,
			'customerNameOrSupplierNameText'=>$customerNameOrSupplierNameText,
		]);
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
        return view('admin.adjusted-due-date-histories', [
			'company'=>$company,
			'invoice'=>$invoice,
			'dueDateHistories'=>$dueDateHistories,
			'model'=>$dueDateHistory,
			'modelType'=>$invoiceModelName,
			'customerNameOrSupplierNameText'=>$customerNameOrSupplierNameText,
		]);
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
