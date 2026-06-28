<?php

namespace App\Jobs;

use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\PayableCheque;
use App\Notification;
use App\Notifications\DueInvoiceNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CheckDueAndPastedInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
	protected $company_id ;
    public function __construct(int $companyId)
    {
		$this->company_id = $companyId ; 
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
		$this->startHandling();
    }
	public function startHandling()
	{
		$dateFormat = 'Y-m-d' ;
		$companyId = $this->company_id;
        DB::table('notifications')->where('notifiable_id',$companyId)->delete();
		$companies = Company::where('id',$companyId)->get();
        foreach ($companies as $company) {
			/**
			 * @var Company $company 
			 */
            $companyId = $company->id;
            $customerInvoiceExportables = getExportableFieldsForModel($companyId, 'CustomerInvoice') ;
            $supplierInvoiceExportables = getExportableFieldsForModel($companyId, 'SupplierInvoice') ;
            if (count($customerInvoiceExportables)) {
                // $customerInvoicePastDueDays = 500 ;
                // $customerInvoiceComingDueDays = 500 ;
				
				// $chequesPastDueDays = 500 ;
				// $chequesUnderCollectionDays = 500 ;
				
				
			//	$customerInvoicePastDueDays = $company->getCustomerComingDuesInvoicesNotificationsDays()  ;
                $customerInvoiceComingDueDays = $company->getCustomerPastDuesInvoicesNotificationsDays() ;
				
				// $chequesPastDueDays = $company->getChequesInSafeNotificationDays() ;
				$comingReceivableChequesDays = $company->getComingReceivableChequesNotificationDays() ;
				$comingPayableChequeNotificationDays = $company->getComingPayableChequeNotificationDays() ;
				

                $todayDate = Carbon::make(now()->format($dateFormat))->format($dateFormat);
                // $dayBeforeDayDate = Carbon::make(now()->format($dateFormat))->subDay()->format($dateFormat);
                // $beforeIntervalDate = Carbon::make(now()->format($dateFormat))->subDays($customerInvoicePastDueDays)->format($dateFormat);
				
				/**
				 * ! Copy It To Supplier Also
				 */
				 DB::table('customer_invoices')->where('company_id', $companyId)
				 ->whereIn('invoice_status',['not_due_yet','due_to_day'])
                ->where('invoice_due_date', '<',$todayDate)
				->update([
					'invoice_status'=>'past_due'
				]);
				
				 DB::table('customer_invoices')->where('company_id', $companyId)
				 ->whereIn('invoice_status',['not_due_yet'])
                ->where('invoice_due_date', '=',$todayDate)
				->update([
					'invoice_status'=>'due_to_day'
				]);
				
				/**
				 * ! End Copy It To Supplier Also
				 */
				
				
				/**
                 * * فواتير متاخرة ولم تسدد بعد
                 */
				
                $pastDueCustomerInvoices = DB::table('customer_invoices')->where('company_id', $companyId)
                ->where('net_balance', '>', 0)
                ->where('invoice_due_date', '<',$todayDate)
				->select([
					'invoice_due_date',
					'invoice_number',
					'customer_name',
					'invoice_date',
					'currency',
					'invoice_amount',
				])->get();
                /**
                 * * مستحق الدفع اليوم
                 */
                $currentDueCustomerInvoices = DB::table('customer_invoices')->where('company_id', $companyId)
                ->where('net_balance', '>', 0)
                ->where('invoice_due_date', $todayDate)
				->select([
					'invoice_due_date',
					'invoice_number',
					'customer_name',
					'invoice_date',
					'currency',
					'invoice_amount',
				])->get();
		

                /**
                 * * القادم
                 */
                $dayAfterNowDate = Carbon::make(now()->format($dateFormat))->addDay()->format($dateFormat);
                $afterIntervalDate = Carbon::make(now()->format($dateFormat))->addDays($customerInvoiceComingDueDays)->format($dateFormat);
                $upcomingDueCustomerInvoices = DB::table('customer_invoices')->where('company_id', $companyId)
                ->where('net_balance', '>', 0)
                ->whereBetween('invoice_due_date', [$dayAfterNowDate, $afterIntervalDate])
				->select([
					'invoice_due_date',
					'invoice_number',
					'customer_name',
					'invoice_date',
					'currency',
					'invoice_amount',
				])->get();
				
				///////////////////////
				
				/**
                 * * شيكات معايا في الخزنة وال تاريخ الاستحقاق بتاعها فات وكان لازم تتبعت للبنك علشان تتحصل
                 */
				// 01-01-2025 due date
				// 01-10-2026 now 
	//			 $beforeIntervalDate = Carbon::make(now()->format($dateFormat))->subDays($chequesPastDueDays)->format($dateFormat);
				 $pastDueCheques = DB::table('cheques')->where('cheques.company_id', $companyId)
				 ->where('cheques.status',Cheque::IN_SAFE)
				 ->where('cheques.due_date','<', $todayDate)
				//  ->where('cheques.due_date','>=', $dayBeforeDayDate)
				 ->join('money_received','money_received.id','=','cheques.money_received_id')
				 ->join('partners','partners.id','=','money_received.partner_id')
				 ->select([
					'cheques.due_date',
					'cheques.cheque_number',
					'cheques.drawee_bank_id',
					'money_received.received_amount',
					'partners.name',
				])
				 ->get();
				
				 
				 
				/**
                 * * شيكات سيكون من المستحق الصرف اليوم ومعايا في الخزنة  فا لازم ابعتها تتحصل النهاردا
                 */
				$currentDueCheques = DB::table('cheques')->where('cheques.company_id', $companyId)
				->where('cheques.status',Cheque::IN_SAFE)
				->where('cheques.due_date', $todayDate)
				->join('money_received','money_received.id','=','cheques.money_received_id')
				->join('partners','partners.id','=','money_received.partner_id')
				->select([
					'cheques.due_date',
					'cheques.cheque_number',
					'cheques.drawee_bank_id',
					'money_received.received_amount',
					'partners.name',
				])
				->get();
				
				/**
				 * * الشيكات  القادمة خلال عدد معين من الايام
				 */
				
				 $afterIntervalDate = Carbon::make(now()->format($dateFormat))->addDays($comingReceivableChequesDays)->format($dateFormat);
				 $comingDueCheques = DB::table('cheques')->where('cheques.company_id', $companyId)
				 ->where('cheques.status',Cheque::IN_SAFE)
				 ->whereBetween('cheques.due_date',[$dayAfterNowDate,$afterIntervalDate])
				//  ->where('cheques.due_date','>=', $dayBeforeDayDate)
				 ->join('money_received','money_received.id','=','cheques.money_received_id')
				 ->join('partners','partners.id','=','money_received.partner_id')
				 ->select([
					'cheques.due_date',
					'cheques.cheque_number',
					'cheques.drawee_bank_id',
					'money_received.received_amount',
					'partners.name',
				])
				 ->get();
				 
				
				/**
				 * * الشيكات  التي ذهبت الي البنك ولكنها لاتزال تحت التحصيل وكان يجب ان تحصل منذ عدد من الايام ولكن لم تحصل بعد
				 */
                foreach ($pastDueCustomerInvoices as $customerInvoice) {
                    $invoiceDueDate = $customerInvoice->invoice_due_date ;
                    $invoiceNumber = $customerInvoice->invoice_number;
                    $customerName = $customerInvoice->customer_name ; //
					$invoiceDate = $customerInvoice->invoice_date ; 
					$currency = $customerInvoice->currency ;
					$invoiceAmount = number_format($customerInvoice->invoice_amount) ; 
                    $dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($invoiceDueDate));
                    $messageEn = __('Invoice Number ',[],'en') . $invoiceNumber . ' ' . __('Is Past Due Since ',[],'en') . ' ' . $dueDays . ' ' . __('days For Customer',[],'en') . ' ' . $customerName ;
                    $messageAr = __('Invoice Number ',[],'ar') . $invoiceNumber . ' ' . __('Is Past Due Since ',[],'ar') . ' ' . $dueDays . ' ' . __('days For Customer',[],'ar') . ' ' . $customerName ;
                    $company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::CUSTOMER_INVOICE_PAST_DUE,'customer',
					[
						'Customer Name'=>$customerName,
						'Invoice Number'=>$invoiceNumber ,
						'Invoice Date' => $invoiceDate ,
						'Currency'=>$currency , 
						'Invoice Amount' => $invoiceAmount ,
						'Due Date'=>$invoiceDueDate ,
						'Past Due Since (Days)'=>$dueDays ,
					]
				));
                }
                foreach ($currentDueCustomerInvoices as $customerInvoice) {
                    $invoiceDueDate = $customerInvoice->invoice_due_date ;
                    $invoiceNumber = $customerInvoice->invoice_number;
                    $customerName = $customerInvoice->customer_name ; // 
					$invoiceDate = $customerInvoice->invoice_date ; 
					$currency = $customerInvoice->currency ;
					$invoiceAmount = number_format($customerInvoice->invoice_amount) ; 
                    $messageEn = __('Invoice Number ',[],'en') . $invoiceNumber . ' ' . __('Is Due Now For Customer',[],'en') . ' ' . $customerName ;
                    $messageAr = __('Invoice Number ',[],'ar') . $invoiceNumber . ' ' . __('Is Due Now For Customer',[],'ar') . ' ' . $customerName ;
                    $company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::CUSTOMER_INVOICE_CURRENT_DUE,'customer',[
						'Customer Name'=>$customerName,
						'Invoice Number'=>$invoiceNumber ,
						'Invoice Date' => $invoiceDate ,
						'Currency'=>$currency , 
						'Invoice Amount' => $invoiceAmount ,
						'Due Date'=>$invoiceDueDate ,
					]));
                }

                foreach ($upcomingDueCustomerInvoices as $customerInvoice) {
                    $invoiceDueDate = $customerInvoice->invoice_due_date ;
                    $invoiceNumber = $customerInvoice->invoice_number;
                    $customerName = $customerInvoice->customer_name ; // 
					$invoiceDate = $customerInvoice->invoice_date ; 
					$currency = $customerInvoice->currency ;
					$invoiceAmount = number_format($customerInvoice->invoice_amount) ; 
                    $dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($invoiceDueDate));
                    $messageEn = __('Invoice Number ',[],'en') . $invoiceNumber . ' ' . __('Is Due After ',[],'en') . ' ' . $dueDays . ' ' . __('days For Customer',[],'en').' ' . $customerName ;
                    $messageAr = __('Invoice Number ',[],'ar') . $invoiceNumber . ' ' . __('Is Due After ',[],'ar') . ' ' . $dueDays . ' ' . __('days For Customer',[],'ar').' ' . $customerName ;
                    $company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::CUSTOMER_INVOICE_COMING_DUE,'customer',
					[
						'Customer Name'=>$customerName,
						'Invoice Number'=>$invoiceNumber ,
						'Invoice Date' => $invoiceDate ,
						'Currency'=>$currency , 
						'Invoice Amount' => $invoiceAmount ,
						'Due Date'=>$invoiceDueDate ,
						'Due After (Days)'=>$dueDays ,
					]
				));
                } 
				
				/**
				 * * الشيكات المتاخرة التي اوشكت علي الاستحقاق وبالتالي تذهب للبنك
				 */
				foreach ($pastDueCheques as $cheque) {
                    $chequeDueDate = $cheque->due_date ;
                    $chequeNumber = $cheque->cheque_number;
					$customerName = $cheque->name ;
					$chequeAmount = number_format($cheque->received_amount) ;
					$draweeBank = Bank::find($cheque->drawee_bank_id);
					$chequeDate = $cheque->due_date ;
					$draweeBankName =  $draweeBank ? $draweeBank->getName() : __('N/A');
                    $dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($chequeDueDate));
                    $messageEn = __('Cheque Number ',[],'en') . $chequeNumber . ' ' . __('Is Past Due Since ',[],'en') . ' ' . $dueDays  ;
                    $messageAr = __('Cheque Number ',[],'ar') . $chequeNumber . ' ' . __('Is Past Due Since ',[],'ar') . ' ' . $dueDays  ;
                    $company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::CHEQUE_PAST_DUE,'receivable_cheque',
					[
						'Customer Name'=>$customerName ,
						'Cheque Number'=>$chequeNumber ,
						'Cheque Amount'=>$chequeAmount,
						'Drawee Bank'=>$draweeBankName,
						'Cheque Date'=>$chequeDate ,
						'Past Due Since (Days)'=>$dueDays 
					]
					
				));
                }
				
				/**
				 * * الشيكات  التي تستحق الذهاب للبنك اليوم
				 */
				foreach ($currentDueCheques as $cheque) {
					$chequeNumber = $cheque->cheque_number;
					$chequeDueDate = $cheque->due_date ;
                    $chequeNumber = $cheque->cheque_number;
					$customerName = $cheque->name ;
					$chequeAmount = number_format($cheque->received_amount) ;
					$draweeBank = Bank::find($cheque->drawee_bank_id);
					$chequeDate = $cheque->due_date ;
					$draweeBankName =  $draweeBank ? $draweeBank->getName() : __('N/A');
					
                    $messageEn = __('Cheque Number ',[],'en') . $chequeNumber . ' ' . __('Is Due Today',[],'en')  ;
                    $messageAr = __('Cheque Number ',[],'ar') . $chequeNumber . ' ' . __('Is Due Today',[],'ar')  ;
                    $company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::CHEQUE_CURRENT_DUE,'receivable_cheque',
					[
						'Customer Name'=>$customerName ,
						'Cheque Number'=>$chequeNumber ,
						'Cheque Amount'=>$chequeAmount,
						'Drawee Bank'=>$draweeBankName,
						'Cheque Date'=>$chequeDate ,
					]
					
				));
                }
				
				
				/**
				 * * الشيكات  التي ذهبت الي البنك ولكنها تستحق التحصيل من البنك اليوم
				 */
				// foreach ($underCollectionChequesToday as $cheque) {
				// 	$chequeNumber = $cheque->cheque_number;
				// 	$chequeDueDate = $cheque->due_date ;
				// 	$customerName = $cheque->name ;
				// 	$chequeAmount = $cheque->received_amount ;
				// 	$drawalBank = FinancialInstitution::find($cheque->drawl_bank_id);
				// 	$drawalBankName =  $drawalBank ? $drawalBank->getName() : __('N/A');
					
                //     $messageEn = __('Cheque Number ',[],'en') . $chequeNumber . ' ' . __('Should Be Collected Today',[],'en')  ;
                //     $messageAr = __('Cheque Number ',[],'ar') . $chequeNumber . ' ' . __('Should Be Collected Today',[],'ar')  ;
                //     $company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::CHEQUE_UNDER_COLLECTION_TODAY,'receivable_cheque',
				// 	[
				// 		'Customer Name'=>$customerName ,
				// 		'Cheque Number'=>$chequeNumber ,
				// 		'Cheque Amount'=>$chequeAmount,
				// 		'Drawal Bank'=>$drawalBankName,
				// 		'Cheque Date'=>$chequeDueDate ,
				// 	]
				// ));
                // }
				
			
				
				foreach ($comingDueCheques as $cheque) {
					$chequeNumber = $cheque->cheque_number;
					$chequeDate = $cheque->due_date ;
					$customerName = $cheque->name ;
					$chequeAmount = number_format($cheque->received_amount) ;
					// $drawalBank = FinancialInstitution::find($cheque->drawl_bank_id);
					// $drawalBankName =  $drawalBank ? $drawalBank->getName() : __('N/A');
					$draweeBank = Bank::find($cheque->drawee_bank_id);
					$draweeBankName =  $draweeBank ? $draweeBank->getName() : __('N/A');
					$dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($chequeDate));
                    $messageEn = __('Cheque Number ',[],'en') . $chequeNumber . ' ' . __('Is Due After ',[],'en').' ' . $dueDays .  __('days For Customer',[],'en').' ' . $customerName  ;
                    $messageAr = __('Cheque Number ',[],'ar') . $chequeNumber . ' ' . __('Is Due After ',[],'en').' ' . $dueDays .  __('days For Customer',[],'en').' ' . $customerName  ;
                    $company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::COMING_RECEIVABLE_CHEQUES_NOTIFICATIONS_DAYS,'receivable_cheque',
				[
						'Customer Name'=>$customerName ,
						'Cheque Number'=>$chequeNumber ,
						'Cheque Amount'=>$chequeAmount,
						'Drawee Bank'=>$draweeBankName,
						'Cheque Date'=>$chequeDate ,
						// 'Should Have Collected Since (Days)'=>$dueDays
						'Due After (Days)'=>$dueDays ,
				]));
                }
				
            }
			
			
			
			if (count($supplierInvoiceExportables)) {
				
				
				
				
				
				
				
			
                $supplierInvoicePastDueDays = $company->getSupplierComingDuesInvoicesNotificationsDays()  ;
                $supplierInvoiceComingDueDays = $company->getSupplierPastDuesInvoicesNotificationsDays() ;
				
				$comingPayableChequeNotificationDays = $company->getComingPayableChequeNotificationDays() ;
				

                $todayDate = Carbon::make(now()->format($dateFormat))->format($dateFormat);
                $dayBeforeDayDate = Carbon::make(now()->format($dateFormat))->subDay()->format($dateFormat);
                $beforeIntervalDate = Carbon::make(now()->format($dateFormat))->subDays($supplierInvoicePastDueDays)->format($dateFormat);
				
				
				
				 DB::table('supplier_invoices')->where('company_id', $companyId)
				 ->whereIn('invoice_status',['not_due_yet','due_to_day'])
                ->where('invoice_due_date', '<',$todayDate)
				->update([
					'invoice_status'=>'past_due'
				]);
				
				 DB::table('supplier_invoices')->where('company_id', $companyId)
				 ->whereIn('invoice_status',['not_due_yet'])
                ->where('invoice_due_date', '=',$todayDate)
				->update([
					'invoice_status'=>'due_to_day'
				]);
				
				
				/**
                 * * سيكون مستحق الدفع بعد عدة ايام
                 */
				
                $pastDueSupplierInvoices = DB::table('supplier_invoices')->where('company_id', $companyId)
                ->where('net_balance', '>', 0)
                ->where('invoice_due_date', '<',$todayDate)
				->select([
					'invoice_due_date',
					'invoice_number',
					'supplier_name',
					'invoice_date',
					'currency',
					'invoice_amount',
				])
				->get();
				
                /**
                 * * مستحق الدفع اليوم
                 */
                $currentDueSupplierInvoices = DB::table('supplier_invoices')->where('company_id', $companyId)
                ->where('net_balance', '>', 0)
                ->where('invoice_due_date', $todayDate)
				->select([
					'invoice_due_date',
					'invoice_number',
					'supplier_name',
					'invoice_date',
					'currency',
					'invoice_amount',
				])
				->get();

                /**
                 * * القادم
                 */
                $dayAfterNowDate = Carbon::make(now()->format($dateFormat))->addDay()->format($dateFormat);
                $afterIntervalDate = Carbon::make(now()->format($dateFormat))->addDays($supplierInvoiceComingDueDays)->format($dateFormat);
                $upcomingDueSupplierInvoices = DB::table('supplier_invoices')->where('company_id', $companyId)
                ->where('net_balance', '>', 0)
                ->whereBetween('invoice_due_date', [$dayAfterNowDate, $afterIntervalDate])
				->select([
					'invoice_due_date',
					'invoice_number',
					'supplier_name',
					'invoice_date',
					'currency',
					'invoice_amount',
				])
				->get();
				
				
				
				/**
                 * * شيكات سيكون من المستحق الدفع بعد عدة ايام
                 */
				
				//  $beforeIntervalDate = Carbon::make(now()->format($dateFormat))->subDays($pendingPayableChequeNotificationDays)->format($dateFormat);
				 $currentPayableCheques = PayableCheque::where('payable_cheques.company_id', $companyId)
				 ->where('payable_cheques.status',PayableCheque::PENDING)
				 ->where('payable_cheques.due_date','<=',$todayDate)
				//  ->whereBetween('payable_cheques.due_date', [$beforeIntervalDate, $dayBeforeDayDate])
				 ->join('money_payments','money_payments.id','=','payable_cheques.money_payment_id')
				 ->join('partners','partners.id','=','money_payments.partner_id')
				 ->select([
					'payable_cheques.id',
					'payable_cheques.due_date',
					'payable_cheques.cheque_number',
					'payable_cheques.delivery_bank_id',
					DB::raw('money_payments.paid_amount as paid_amount'),
					'partners.name',
				])
				 ->get();
				 
				 $afterIntervalDate = Carbon::make(now()->format($dateFormat))->addDays($comingPayableChequeNotificationDays)->format($dateFormat);
				 $comingPayableCheques = PayableCheque::where('payable_cheques.company_id', $companyId)
				 ->where('payable_cheques.status',PayableCheque::PENDING)
				 ->whereBetween('payable_cheques.due_date',[$dayAfterNowDate,$afterIntervalDate])
				 ->join('money_payments','money_payments.id','=','payable_cheques.money_payment_id')
				 ->join('partners','partners.id','=','money_payments.partner_id')
				 ->select([
					'payable_cheques.id',
					'payable_cheques.due_date',
					'payable_cheques.cheque_number',
					'payable_cheques.delivery_bank_id',
					DB::raw('money_payments.paid_amount as paid_amount'),
					'partners.name',
				])
				 ->get();
			foreach ($pastDueSupplierInvoices as $supplierInvoice) {
				$invoiceDueDate = $supplierInvoice->invoice_due_date ;
				$invoiceNumber = $supplierInvoice->invoice_number;
				$supplierName = $supplierInvoice->supplier_name ;
				$invoiceDate = $supplierInvoice->invoice_date ; 
				$currency = $supplierInvoice->currency ;
				$invoiceAmount = number_format($supplierInvoice->invoice_amount) ; 
					
				$dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($invoiceDueDate));
				$messageEn = __('Invoice Number ',[],'en') . $invoiceNumber . ' ' . __('Is Past Due Since ',[],'en') . ' ' . $dueDays . ' ' . __('days For Supplier',[],'en') . ' ' . $supplierName ;
				$messageAr = __('Invoice Number ',[],'ar') . $invoiceNumber . ' ' . __('Is Past Due Since ',[],'ar') . ' ' . $dueDays . ' ' . __('days For Supplier',[],'ar').' ' . $supplierName ;
				$company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::SUPPLIER_INVOICE_PAST_DUE,'supplier',
				[
						'Supplier Name'=>$supplierName,
						'Invoice Number'=>$invoiceNumber ,
						'Invoice Date' => $invoiceDate ,
						'Currency'=>$currency , 
						'Invoice Amount' => $invoiceAmount ,
						'Due Date'=>$invoiceDueDate ,
						'Past Due Since (Days)'=>$dueDays ,
			
				]
			));
			}
			foreach ($currentDueSupplierInvoices as $supplierInvoice) {
				$invoiceDueDate = $supplierInvoice->invoice_due_date ;
				$invoiceNumber = $supplierInvoice->invoice_number;
				$supplierName = $supplierInvoice->supplier_name ;
				$invoiceDate = $supplierInvoice->invoice_date ; 
				$currency = $supplierInvoice->currency ;
				$invoiceAmount = number_format($supplierInvoice->invoice_amount) ;
				$messageEn = __('Invoice Number ',[],'en') . $invoiceNumber . ' ' . __('Is Due Now For Supplier',[],'en') . ' ' . $supplierName ;
				$messageAr = __('Invoice Number ',[],'ar') . $invoiceNumber . ' ' . __('Is Due Now For Supplier',[],'ar') . ' ' . $supplierName ;
				$dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($invoiceDueDate));
				$company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::SUPPLIER_INVOICE_CURRENT_DUE,'supplier',
				[
						'Supplier Name'=>$supplierName,
						'Invoice Number'=>$invoiceNumber ,
						'Invoice Date' => $invoiceDate ,
						'Currency'=>$currency , 
						'Invoice Amount' => $invoiceAmount ,
						'Due Date'=>$invoiceDueDate 
					
				]
			));
			}
			foreach ($upcomingDueSupplierInvoices as $supplierInvoice) {
				$invoiceDueDate = $supplierInvoice->invoice_due_date ;
				$invoiceNumber = $supplierInvoice->invoice_number;
				$supplierName = $supplierInvoice->supplier_name ;
				$invoiceDate = $supplierInvoice->invoice_date ; 
				$currency = $supplierInvoice->currency ;
				$invoiceAmount = number_format($supplierInvoice->invoice_amount) ; 
				$dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($invoiceDueDate));
				$messageEn = __('Invoice Number ',[],'en') . $invoiceNumber . ' ' . __('Is Due After ',[],'en') . ' ' . $dueDays . ' ' . __('days For Supplier ',[],'en') . $supplierName ;
				$messageAr = __('Invoice Number ',[],'ar') . $invoiceNumber . ' ' . __('Is Due After ',[],'ar') . ' ' . $dueDays . ' ' . __('days For Supplier ',[],'ar') . $supplierName ;
				$company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::SUPPLIER_INVOICE_COMING_DUE,'supplier',
				[
					'Supplier Name'=>$supplierName,
					'Invoice Number'=>$invoiceNumber ,
					'Invoice Date' => $invoiceDate ,
					'Currency'=>$currency , 
					'Invoice Amount' => $invoiceAmount ,
					'Due Date'=>$invoiceDueDate ,
					'Due After (Days)'=>$dueDays 
					
				]
			));
			} 
			
			foreach ($currentPayableCheques as $currentPayableCheque) {
				$invoiceDueDate = $currentPayableCheque->due_date ;
				$chequeNumber = $currentPayableCheque->cheque_number;
				$chequeAmountValue = $currentPayableCheque->moneyPayment?->getPaidAmount()
					?? $currentPayableCheque->cashExpenses?->getPaidAmount()
					?? 0;
				$chequeAmount = number_format($chequeAmountValue) ;
				$supplierName = $currentPayableCheque->moneyPayment?->getSupplierName() ?? __('N/A');
				$bankName = $currentPayableCheque->getDeliveryBankName();
				$dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($invoiceDueDate));
				$messageEn = __('Cheque Number ',[],'en') . $chequeNumber . ' ' . __('Is Past Due Since ',[],'en') . ' ' . $dueDays . ' ' . __('days For Bank',[],'en') . $bankName ;
				$messageAr = __('Cheque Number ',[],'ar') . $chequeNumber . ' ' . __('Is Past Due Since ',[],'ar') . ' ' . $dueDays . ' ' . __('days For Bank',[],'ar') . $bankName ;
				$company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::CURRENT_PAYABLE_CHEQUES,'current_payable_cheque',
				[
					'Supplier Name'=>$supplierName,
					'Cheque Amount'=>$chequeAmount,
					'Cheque Number'=>$chequeNumber ,
					'Payment Bank'=>$bankName,
					'Due Since (Days)'=>$dueDays ,
				]
			));
			} 

			foreach ($comingPayableCheques as $comingPayableCheque) {
				$invoiceDueDate = $comingPayableCheque->due_date ;
				$chequeNumber = $comingPayableCheque->cheque_number;
				$chequeAmountValue = $comingPayableCheque->moneyPayment?->getPaidAmount()
					?? $comingPayableCheque->cashExpense?->getPaidAmount()
					?? 0;
				$chequeAmount = number_format($chequeAmountValue) ;
				$supplierName = $comingPayableCheque->moneyPayment?->getSupplierName() ?? __('N/A');
				$bankName = $comingPayableCheque->getDeliveryBankName();
				$dueDays = Carbon::make(now()->format($dateFormat))->diffInDays(Carbon::make($invoiceDueDate));
				$messageEn = __('Cheque Number ',[],'en') . $chequeNumber . ' ' . __('Is Due After ',[],'en') . ' ' . $dueDays . ' ' . __('days For Bank',[],'en') . $bankName ;
				$messageAr = __('Cheque Number ',[],'ar') . $chequeNumber . ' ' . __('Is Due After ',[],'ar') . ' ' . $dueDays . ' ' . __('days For Bank',[],'ar') . $bankName ;
				$company->notify(new DueInvoiceNotification($messageEn, $messageAr, Notification::COMING_PAYABLE_CHEQUES,'coming_payable_cheque',
				[
					'Supplier Name'=>$supplierName,
					'Cheque Amount'=>$chequeAmount,
					'Cheque Number'=>$chequeNumber ,
					'Payment Bank'=>$bankName,
					'Due After (Days)'=>$dueDays ,
				]
			));
			} 
			
			

              
			
				
				
				
            }
			
			
        }
	}
}
