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
	
	
    /**
     * * $amountInMainFunctionalCurrency لازم يتبعت لو عملة الخطاب مش
     * * عملة الشركة — أودو بيحط debit/credit بعملة الشركة و
     * * amount_currency بالعملة الأجنبية
     */
    public function createLgIssuanceCashCover(string $date,float $amount,int $journalId,int $odooCurrencyId,int $lgDebitOdooAccountId,int $lgCreditOdooAccountId,int $odooPartnerId , string $ref  , string $message  , array $analytic_distribution , ?float $amountInMainFunctionalCurrency = null )
    {
		  $amount = $amount * -1;
		  $amountInMainFunctionalCurrency = is_null($amountInMainFunctionalCurrency) ? $amount : $amountInMainFunctionalCurrency * -1;
          return $this->createAndPostJournalEntry($date,$amount,$odooCurrencyId,$journalId,$lgDebitOdooAccountId,$lgCreditOdooAccountId,$ref,$odooPartnerId,$message,$analytic_distribution,$amountInMainFunctionalCurrency);
       
    }
	
	
	
	 public function createLgCancelCashCover(string $date,float $amount,int $journalId,int $odooCurrencyId,int $lgDebitOdooAccountId,int $lgCreditOdooAccountId,int $odooPartnerId,string $ref,string $message, ?float $amountInMainFunctionalCurrency = null)
    {
          
          return $this->createAndPostJournalEntry($date,$amount,$odooCurrencyId,$journalId,$lgCreditOdooAccountId,$lgDebitOdooAccountId,$ref,$odooPartnerId,$message,[],$amountInMainFunctionalCurrency);
		  
        
       
    }
	
  
}
?>
