<?php

namespace App\Jobs;

use App\Models\CachingCompany;
use App\Models\Contract;
use App\Models\Bank;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesGatheringTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels ;
	
    public $timeout = 500000*60;
    public $failOnTimeout = true;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $company_id;
    public $modelName;
	public $loanId ; 
    public function __construct($company_id,$modelName,$loanId = null)
    {
        $this->company_id = $company_id;
        $this->modelName = $modelName;
		$this->loanId = $loanId ;
    }

    /**
     * Execute the job.
     *
     * @return void
     */


    public function handle()
    {
		if (in_array($this->modelName, ['LoanSchedule', 'ContractLoanSchedule'], true) && !$this->loanId) {
			return;
		}
		$uploadParamsForType = getUploadParamsFromType($this->modelName);
		$modelTableName = $uploadParamsForType['dbName'];

		// ── Duplicate protection (confirmed with project owner): never
		// insert a row whose invoice_number+currency already exists
		// for this company. Deliberately SKIP, never replace/update —
		// real financial records (collections, deductions, settlements)
		// reference an invoice by its database id, and replacing an
		// existing row would orphan them. Pre-fetched once here rather
		// than per-chunk, since this company's existing invoice
		// numbers don't change mid-job.
		$existingInvoiceKeys = null;
		$skippedDuplicateCount = 0;
		if (in_array($this->modelName, ['CustomerInvoice', 'SupplierInvoice'], true)) {
			$existingInvoiceKeys = DB::table($modelTableName)
				->where('company_id', $this->company_id)
				->whereNotNull('invoice_number')
				->get(['invoice_number', 'currency'])
				->map(fn ($r) => $r->invoice_number . '|' . strtoupper($r->currency ?? ''))
				->flip();
		}
		
        CachingCompany::where('company_id' , $this->company_id )->get()->each(function($cachingCompany) use($modelTableName, $existingInvoiceKeys, &$skippedDuplicateCount){
            $cacheGroup = Cache::get($cachingCompany->key_name) ?: [];
            $chunks = \array_chunk($cacheGroup ,1000);
            foreach($chunks as $chunk)
            {
				$chunk = array_values(array_filter(
					$chunk,
					fn (array $row) => ! isMappedImportRowEmpty($row)
				));

				if ($chunk === []) {
					continue;
				}

				if ($existingInvoiceKeys !== null) {
					$chunk = array_values(array_filter($chunk, function (array $row) use ($existingInvoiceKeys, &$skippedDuplicateCount) {
						$key = ($row['invoice_number'] ?? '') . '|' . strtoupper($row['currency'] ?? '');
						if (($row['invoice_number'] ?? '') !== '' && $existingInvoiceKeys->has($key)) {
							$skippedDuplicateCount++;
							return false;
						}
						return true;
					}));
					if ($chunk === []) {
						continue;
					}
				}

				$chunk = $this->ReplaceAllSpecialCharactersInArrayValuesAndAddExtraFieldsToBeStored($chunk,$this->modelName,$this->loanId);
				
                DB::table($modelTableName)->insert($chunk);
                $key = getTotalUploadCacheKey($this->company_id , $cachingCompany->job_id,$modelTableName) ;
                $oldTotalUploaded = cache::get($key) ?:0 ;
                cache::forever( $key , $oldTotalUploaded + count($chunk) );
            }
        });

		if ($skippedDuplicateCount > 0) {
			cache::forever(getSkippedDuplicatesCacheKey($this->company_id, $this->modelName), $skippedDuplicateCount);
		}
    }
	public function ReplaceAllSpecialCharactersInArrayValuesAndAddExtraFieldsToBeStored(array $items,$modelName ,$loanId )
	{
		$newItems = [];
		foreach($items as $key => $value) {
			$newItems[$key] = is_array($value) ? sanitizeImportRowValues($value) : ($value ? str_replace(array('"', "'","\\"), ' ', $value) : $value);
			
			if($modelName == 'CustomerInvoice' && is_array($value)){
				$customerId = null ;
				if($this->modelName == 'CustomerInvoice'){
					/**
					 * * insert customer invoices
					 */
					$customerId = null ;
					$customerName = $value['customer_name'] ;
					$value['currency'] = isset($value['currency']) ? strtoupper($value['currency']) : null;
					$customerFound = DB::table('partners')->where('company_id',$this->company_id)->where('is_customer',1)->where('name',$customerName)->exists();
					if($customerFound){
						$customerId = DB::table('partners')->where('company_id',$this->company_id)->where('is_customer',1)->where('name',$customerName)->first()->id;
					}else{
						if($customerName){
							$customer = Partner::create([
								'name'=>$customerName,
								'company_id'=>$this->company_id,
								'is_customer'=>1 ,
								'is_supplier'=>0 
							]);
							$customerId = $customer->id ;
						}
						
					}
					/**
					 * * insert sales person , business unit , business sector
					 */
					
					foreach(['sales_person'=>'cash_vero_sales_persons','business_unit'=>'cash_vero_business_units','business_sector'=>'cash_vero_business_sectors'] as $columnName=>$tableName){
						$currentIds[$columnName] = 0 ;
						$currentColValue = $value[$columnName]??null ;
						if(is_null($currentColValue)){
							continue;
						}
					$isFound[$columnName] = DB::table($tableName)->where('company_id',$this->company_id)->where('name',$currentColValue)->exists();
					if($isFound[$columnName]){
						$currentIds[$columnName] = DB::table($tableName)->where('company_id',$this->company_id)->where('name',$currentColValue)->first()->id;
					}else{
						$currentRowInserted = DB::table($tableName)->insert([
							'name'=>$currentColValue,
							'created_at'=>now(),
							'company_id'=>$this->company_id
						]);
						$currentIds[$columnName] = $currentRowInserted ;
					}
					/**
					 * * Feature (client requested, 2026-08-15): a Sales Person
					 * * name filled in on the Customer Invoice Excel upload
					 * * should also exist as a real Employee — Employees in
					 * * this app are just Partner rows with is_employee=1
					 * * (same pattern already used a few lines above for
					 * * auto-creating the Customer partner from
					 * * $customerName). Only creates one if a Partner with
					 * * this exact name isn't already an employee for this
					 * * company, so re-uploading the same sheet doesn't
					 * * create duplicates every time.
					 */
					if($columnName === 'sales_person' && trim((string) $currentColValue) !== ''){
						$salesPersonName = trim((string) $currentColValue);
						$isAlreadyEmployee = DB::table('partners')
							->where('company_id', $this->company_id)
							->where('is_employee', 1)
							->where('name', $salesPersonName)
							->exists();
						if(!$isAlreadyEmployee){
							Partner::create([
								'name' => $salesPersonName,
								'company_id' => $this->company_id,
								'is_employee' => 1,
							]);
						}
					}
					}
					
				
					
					
					/**
					 * * insert customer contracts
					 */
					
					 
					$contractName = $value['contract_name']??null ;
					$contractCode = $value['contract_code']??null ;
					$contractAmount = $value['contract_amount']??null ;
					$contractDate = $value['contract_date'] ?? null ;
					$contractFound =  DB::table('contracts')->where('company_id',$this->company_id)->where('code',$contractCode)->exists();
					if($contractName && $contractCode && $contractAmount && $contractDate && !$contractFound){
						$customer = Contract::create([
							'status'=>Contract::RUNNING,
							'model_type'=>'Customer',
							'partner_id'=>$customerId,
							'name'=>$contractName,
							'code'=>$contractCode,
							'company_id'=>$this->company_id ,
							'start_date'=>$contractDate,
							'duration'=>0,
							'end_date'=>null,
							'amount'=>$contractAmount ,
							'currency'=>isset($value['currency']) ? strtoupper($value['currency']) : null,
							'exchange_rate'=>isset($value['exchange_rate']) ? strtoupper($value['exchange_rate']) : 1
						]);	
					}
					
					
					
				}
			$newItems[$key] = array_merge($value , [
				'customer_id'=>$customerId
			]);
			}
			
			
			
			
			if($modelName == 'SupplierInvoice' && is_array($value)){
				$supplierId = null ;
				if($this->modelName == 'SupplierInvoice'){
					$supplierId = null ;
					$supplierName = $value['supplier_name'] ;
					$value['currency'] = isset($value['currency']) ? strtoupper($value['currency']) : null;
					$supplierFound = DB::table('partners')->where('company_id',$this->company_id)->where('is_supplier',1)->where('name',$supplierName)->exists();
					if($supplierFound){
						$supplierId = DB::table('partners')->where('company_id',$this->company_id)->where('is_supplier',1)->where('name',$supplierName)->first()->id;
					}else{
						if($supplierName){
							$supplier = Partner::create([
								'name'=>$supplierName,
								'company_id'=>$this->company_id,
								'is_customer'=>0 ,
								'is_supplier'=>1 
							]);
							$supplierId = $supplier->id ;
						}
						
					}
					;
				}
			$newItems[$key] = array_merge($value , [
				'supplier_id'=>$supplierId
			]);
			}
			if($modelName == 'LoanSchedule'){
				$newItems[$key] = array_merge($value , [
					'medium_term_loan_id'=>$loanId,
					'remaining'=>$value['schedule_payment'] ?? 0
				]);
			}
			if($modelName == 'ContractLoanSchedule' && is_array($value)){
				$draweeBankName = trim((string) ($value['drawee_bank'] ?? ''));
				$draweeBankId = resolveDraweeBankFinancialInstitutionId($this->company_id, $draweeBankName);
				$chequeAmount = (float) ($value['cheque_amount'] ?? $value['schedule_payment'] ?? 0);
				$remaining = $chequeAmount;
				$status = resolveLoanScheduleStatus(
					$remaining,
					$chequeAmount,
					$value['date'] ?? null
				);

				// Bug fix (client-flagged, confirmed 2026-08-15): drawee bank was
				// already resolved to an id above, but account_number was passed
				// through as raw text with nothing linking it to the actual
				// account row — so it went stale the moment that account's
				// number was later edited. Resolve it to the real account here,
				// the same way it's already looked up elsewhere
				// (FinancialInstitutionAccount::findByAccountNumber, used by
				// ContractLoanScheduleSettlement::handleLoanStatement). The raw
				// text is still kept in the row as a fallback for the rare case
				// where no matching account is found (e.g. typo not caught by
				// the template's dropdown, or the account was deleted).
				$accountNumberText = trim((string) ($value['account_number'] ?? ''));
				$financialInstitutionAccountId = ($draweeBankId && $accountNumberText !== '')
					? \App\Models\FinancialInstitutionAccount::findByAccountNumber($accountNumberText, $this->company_id, $draweeBankId)?->id
					: null;

				$row = is_array($newItems[$key]) ? $newItems[$key] : $value;
				unset($row['drawee_bank'], $row['schedule_payment']);
				if (isset($row['id']) && !is_numeric($row['id'])) {
					unset($row['id']);
				}

				$newItems[$key] = array_merge($row, [
					'leasing_contract_id' => $loanId,
					'remaining' => $remaining,
					'drawee_bank_id' => $draweeBankId,
					'financial_institution_account_id' => $financialInstitutionAccountId,
					'company_id' => $this->company_id,
					'status' => $status,
				]);
			}
			
		}
		
		return $newItems ;
	}
	
}
