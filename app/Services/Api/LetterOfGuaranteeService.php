<?php 
namespace App\Services\Api;

use App\Services\Api\Traits\AuthTrait;
use App\Services\Api\Traits\HasJournal;
use App\Services\Api\Traits\HasJournalEntry;
use App\Services\Api\Traits\HasPayment;
use App\Services\Api\Traits\HasUnlinkAccountBankStatementLine;

class LetterOfGuaranteeService
{
    use AuthTrait,HasPayment,HasJournal,HasJournalEntry,HasUnlinkAccountBankStatementLine;
	// string $date,int $outJournalId,float $amount,int $odooCurrencyId,int $lgOddoAccountId
	
	
    public function createLgIssuanceCashCover(string $date,float $amount,int $journalId,int $odooCurrencyId,int $lgDebitOdooAccountId,int $lgCreditOdooAccountId,int $odooPartnerId , string $ref  , string $message  , array $analytic_distribution )
    {
		  $amount = $amount * -1;
          return $this->createAndPostJournalEntry($date,$amount,$odooCurrencyId,$journalId,$lgDebitOdooAccountId,$lgCreditOdooAccountId,$ref,$odooPartnerId,$message,$analytic_distribution);
       
    }
	
	
	
	 public function createLgCancelCashCover(string $date,float $amount,int $journalId,int $odooCurrencyId,int $lgDebitOdooAccountId,int $lgCreditOdooAccountId,int $odooPartnerId,string $ref,string $message)
    {
          
          return $this->createAndPostJournalEntry($date,$amount,$odooCurrencyId,$journalId,$lgCreditOdooAccountId,$lgDebitOdooAccountId,$ref,$odooPartnerId,$message);
		  
        
       
    }
	
  
}
?>
