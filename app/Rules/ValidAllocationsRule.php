<?php

namespace App\Rules;

use App\Models\SupplierInvoice;
use Illuminate\Contracts\Validation\ImplicitRule;

class ValidAllocationsRule implements ImplicitRule
{
   
	protected $failedMessage = null;
    public function __construct()
    {
        //
    }

    public function passes($attribute, $allocationItems)
    {
		
        foreach((array)$allocationItems as $invoiceId=>$arrayOfAllocations){
			
			$totalAllocationsForInvoiceNumber = array_sum(array_column($arrayOfAllocations,'allocation_amount'));
			if($totalAllocationsForInvoiceNumber > Request()->input('settlements.'.$invoiceId.'.settlement_amount',0)){
				$invoiceNumber = SupplierInvoice::find($invoiceId)->getInvoiceNumber();
				$this->failedMessage  = __('Invalid Allocation For Invoice :invoiceNumber' ,['invoiceNumber'=>$invoiceNumber]);  
				return false ;
			}
		
		}
		return true ;
		
    }

   
    public function message()
    {
        return $this->failedMessage;
    }
}
