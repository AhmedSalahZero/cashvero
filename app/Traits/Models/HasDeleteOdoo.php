<?php
namespace App\Traits\Models;


use App\Models\CertificatesOfDeposit;
use App\Services\Api\InternalMoneyTransfer as OdooInternalMoneyTransfer;

trait HasDeleteOdoo
{
	
    public function deleteOdoo($isBreakOrApplyDeposit)
    {
        $company = $this->company;
		$breakColumns = $this->getBreakColumns();
		$storeColumns = ['inbound_journal_entry_id','outbound_journal_entry_id'] ;
		$columnsToDelete = $isBreakOrApplyDeposit ? $breakColumns : array_merge(
			$breakColumns , 
			$storeColumns
		);
        if ($company->hasOdooIntegrationCredentials()) {
            $internalMoneyTransferService = (new OdooInternalMoneyTransfer($company));
            foreach ($columnsToDelete as $columnName) {
                if ($journalEntryId = $this->{$columnName}) {
                    $internalMoneyTransferService->unlink($journalEntryId);
                }
            }
        
        }
    }
   
    
    

}
