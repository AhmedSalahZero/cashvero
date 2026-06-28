<?php
namespace App\Services\Api;

use App\Services\Api\Traits\AuthTrait;
use App\Services\Api\Traits\HasJournal;
use Exception;

class TimeOrCertificateOfDepositOdooService
{
    use AuthTrait,HasJournal;
    
    public function createAndPostJournalEntry(string $date, float $amount, int $odooCurrencyId, int $creditJournalId, int $creditOdooAccountId, int $debitOdooAccountId, ?string $ref, ?int $partnerId, ?string $message , $isBreakOrApplyDeposit =false)
    {
        $id = null ;  // in edit mode
        $journalEntryData =   $this->getDataFormatted($date, $amount, $odooCurrencyId, $creditJournalId, $debitOdooAccountId, $creditOdooAccountId, $ref, $partnerId, $message, $id,$isBreakOrApplyDeposit) ;

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
        $reference = $statementData[0]['move_id'][1]??null ;
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
            'reference'=>$reference
        ];
    }
    
    
    protected function getDataFormatted(string $date, float $amount, int $odooCurrencyId, int $creditJournalId, int $creditOdooAccountId, int $debitOdooAccountId, ?string $ref, ?int $partnerId, ?string $message, ?int $id = null , $isBreakOrApplyDeposit = false  ):array
    {
        $inEditMode = is_null($id) ? 0 : 1;
        $id = is_null($id) ? 0 : $id ;
		$debitAmount = $isBreakOrApplyDeposit ? 0 : abs($amount)   ;
		$creditAmount = $isBreakOrApplyDeposit ? abs($amount) : 0  ;
        return [
               'journal_id' => $creditJournalId, // account journal id (safe or bank journal id )
               'amount' =>$isBreakOrApplyDeposit ? -$amount : $amount ,
               'date' => $date,
               'partner_id' => $partnerId,
               'ref' =>  $ref, // create lg type
               'line_ids' => [
                    [$inEditMode, $id, [
                        'account_id' => $creditOdooAccountId, // lg cash cover odoo id (create lg cash cover)
                        'debit' => $debitAmount,
                        'credit' => $creditAmount,
                        'currency_id' => $odooCurrencyId,
                        'name' => $message , // cash cover
                        'partner_id' => $partnerId,
                    ]],
                    [$inEditMode, $id+1, [
                        'account_id' => $debitOdooAccountId , // chart of account odoo id
                        'debit' => $creditAmount,
                        'credit' => $debitAmount,
                        'currency_id' => $odooCurrencyId,
                        'name' => $message ,
                        'partner_id' => $partnerId,
                    ]],
                ],
            ];
    }
    public function createMoneyDepositInBank(string $date, float $amount, int $odooCurrencyId, int $debitJournalId, int $debitOdooAccountId, int $creditOdooAccountId, ?string $ref, ?int $partner_id, ?string $message)
    {
        $id = null ;  // in edit mode
        $journalEntryData = $this->getMoneyDepositDataFormatted($date, $amount, $odooCurrencyId, $debitJournalId, $debitOdooAccountId, $creditOdooAccountId, $ref, $partner_id, $message, $id) ;

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
            'journal_entry_id'=>$journalEntryId
        ];
    }
    protected function getMoneyDepositDataFormatted(string $date, float $amount, int $odooCurrencyId, int $debitJournalId, int $debitOdooAccountId, int $creditOdooAccountId, ?string $ref, ?int $partner_id, ?string $message, ?int $id = null):array
    {
        $inEditMode = is_null($id) ? 0 : 1;
        $id = is_null($id) ? 0 : $id ;
        
        
        return [
               'journal_id' => $debitJournalId, // account journal id (safe or bank journal id )
               'amount' => $amount,
               'date' => $date,
               'partner_id' => $partner_id,
               'ref' =>  $ref, // create lg type
               'line_ids' => [
                    [$inEditMode, $id, [
                        'account_id' => $debitOdooAccountId, // lg cash cover odoo id (create lg cash cover)
                        'debit' => abs($amount),
                        'credit' => 0.0,
                        'currency_id' => $odooCurrencyId,
                        'name' => $message , // cash cover
                        'partner_id' => $partner_id,
                    ]],
                    [$inEditMode, $id+1, [
                        'account_id' => $creditOdooAccountId, // chart of account odoo id
                        'debit' => 0.0,
                        'credit' => abs($amount),
                        'currency_id' => $odooCurrencyId,
                        'name' => $message ,
                        'partner_id' => $partner_id,
                    ]],
                ],
            ];
    }
    

}
