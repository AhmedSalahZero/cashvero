<?php
namespace App\Traits\Models;


use App\Models\CertificatesOfDeposit;
use App\Services\Api\InternalMoneyTransfer as OdooInternalMoneyTransfer;
use App\Services\Api\OdooSync;

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
            foreach ($columnsToDelete as $columnName) {
                if ($journalEntryId = $this->{$columnName}) {
                    /**
                     * * بنمرر ال id كقيمة لأن الصف ممكن يكون اتحذف قبل ما الاستدعاء يتنفذ
                     */
                    OdooSync::defer(function () use ($company, $journalEntryId) {
                        (new OdooInternalMoneyTransfer($company))->unlink($journalEntryId);
                    }, null, 'Unlink Odoo journal entry #'.$journalEntryId);
                }
            }

        }
    }
   
    
    

}
