<?php
namespace App\Services\Api;

use App\Services\Api\Traits\AuthTrait;
use App\Services\Api\Traits\HasAnalysisAccount;
use App\Services\Api\Traits\HasJournal;
use App\Services\Api\Traits\HasUnlinkAccountBankStatementLine;
use Exception;
use App\Helpers\HVero;

class CashExpenseOdooService
{
    use AuthTrait,HasJournal,HasUnlinkAccountBankStatementLine;
    // string $date,int $outJournalId,float $amount,int $odooCurrencyId,int $lgOddoAccountId
    
    protected function getRef()
    {
        return null ;
    }protected function getMessage()
    {
        return null;
    }
    public function getOdooPartnerId()
    {
        return null ;
    }
    
    protected function createAndPostJournalEntry(?string $subCategoryName, string $date, float $amountInCurrency, float $amountInMainFunctionalCurrency, int $odooCurrencyId, int $journalId, int $debitOdooAccountId, int $creditOdooAccountId, array $analytic_distribution, ?string $ref, ?int $partner_id, ?string $message, $paymentRef=null,$isMoneyReceived=false)
    {
        $id = null ;  // in edit mode
            
        $journalEntryData = $this->getDataFormatted($subCategoryName, $date, $amountInCurrency, $amountInMainFunctionalCurrency, $odooCurrencyId, $journalId, $debitOdooAccountId, $creditOdooAccountId, $analytic_distribution, $ref, $partner_id, $message, $id, $paymentRef,$isMoneyReceived) ;

        $context = [
            'check_move_validity' => true,
        ];
            
        $accountBankStatementLineId = $this->execute(
            'account.bank.statement.line',
            'create',
            [$journalEntryData],
            ['context' => $context]
        );
        if (!is_numeric($accountBankStatementLineId)) {
            throw new Exception("Failed to create journal entry: " . json_encode($accountBankStatementLineId));
        }
            
        
        $statementData = $this->execute(
            'account.bank.statement.line',
            'read',
            [[$accountBankStatementLineId], ['move_id']],
            []
        );

        if (!is_array($statementData) || empty($statementData[0]['move_id'])) {
            throw new Exception("Failed to retrieve move_id for statement entry: " . $accountBankStatementLineId);
        }
        $journalEntryId = $statementData[0]['move_id'][0];
        // if (!is_numeric($accountBankStatementLineId)) {
        //     throw new Exception("Failed to create journal entry: " . json_encode($accountBankStatementLineId));
        // }
            
        return [
            'account_bank_statement_line_id'=>$accountBankStatementLineId,
            'journal_entry_id'=>$journalEntryId,
            'reference'=>$statementData[0]['move_id'][1]??null
        ];
    }
    protected function getDataFormatted(?string $subCategoryName, string $date, float $amountInCurrency, float $amountInMainFunctionalCurrency, int $odooCurrencyId, int $journalId, int $debitOdooAccountId, int $creditOdooAccountId, array $analytic_distribution, ?string $ref, ?int $partner_id, ?string $message, ?int $id = null, $paymentRef = null, $isMoneyReceived = false):array
    {
        $inEditMode = is_null($id) ? 0 : 1;
        $id = is_null($id) ? 0 : $id ;
        
        $distribution_analytic_account_ids = HVero::getAnalysisAccountIds($analytic_distribution,$partner_id);
   		
        $paymentRef = $subCategoryName ?  'Expense Payment ' . $subCategoryName : $paymentRef;
        $message = is_null($message) ? $paymentRef : $message ;
        $ref = $paymentRef;
        $data = [
                       'journal_id' => $journalId, // account journal id (safe or bank journal id )
                       'amount' =>$isMoneyReceived ?  $amountInCurrency : -$amountInCurrency,
                       'date' => $date,
                      'partner_id' => $partner_id,
                       'ref' =>  $ref, // create lg type
                       'payment_ref' =>  $paymentRef, // create lg type
                       'line_ids' => [
                            [$inEditMode,0, [
                                'account_id' => $debitOdooAccountId, // lg cash cover odoo id (create lg cash cover)
                                'debit' => abs($amountInMainFunctionalCurrency),
                                'amount_currency'=>abs($amountInCurrency),
                                'credit' => 0.0,
                               'partner_id' => $partner_id,
                                'currency_id' => $odooCurrencyId,
                                'name' => $message , // cash cover
                                  'analytic_distribution' =>$analytic_distribution,   // 87  -> x_plan2_id     80 -> percentage   Allocate Amount / Paid Amount * 100     ,
                                    'distribution_analytic_account_ids' => $distribution_analytic_account_ids  ,
                            ]],
                            [$inEditMode,0, [
                                'account_id' => $creditOdooAccountId, // chart of account odoo id
                                'debit' => 0.0,
                                'credit' => abs($amountInMainFunctionalCurrency),
                                'amount_currency'=>-$amountInCurrency,
                                'currency_id' => $odooCurrencyId,
                                'name' => $message ,
                                'partner_id' => $partner_id,
						// 		 'analytic_distribution' =>$analytic_distribution,   // 87  -> x_plan2_id     80 -> percentage   Allocate Amount / Paid Amount * 100     ,
                        // 'distribution_analytic_account_ids' => $distribution_analytic_account_ids  ,
                        
						
                                'analytic_distribution' => [],
                                'distribution_analytic_account_ids' => [[6, 0, []]] ,
                            ]],
                        ],
                    ] ;
        return $data;
    }
    
    public function createCashExpense(?string $subCategoryName, string $date, float $amountInCurrency, float $amountInMainFunctionalCurrency, int $journalId, int $odooCurrencyId, int $debitOdooAccountId, int $creditOdooAccountId, $analytic_distribution, $paymentRef=null, $odooPartnerId = null,$isMoneyReceived = false , $message = null)
    {
        $ref = is_null($paymentRef) ? $this->getRef() : $paymentRef;
        $message = is_null($message) ?  $this->getMessage() : $message;
        return $this->createAndPostJournalEntry($subCategoryName, $date, $amountInCurrency, $amountInMainFunctionalCurrency, $odooCurrencyId, $journalId, $debitOdooAccountId, $creditOdooAccountId, $analytic_distribution, $ref, $odooPartnerId, $message, $paymentRef,$isMoneyReceived);
    }
  
    
    
    
    
    
    
    

}
