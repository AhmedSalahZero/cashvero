<?php
namespace App\Services\Api;

use App\Services\Api\Traits\AuthTrait;
use App\Services\Api\Traits\HasJournal;
use Exception;

class TimeOrCertificateOfDepositOdooService
{
    use AuthTrait,HasJournal;
    
    /**
     * * $amountInMainFunctionalCurrency هو اللي بيتحط في debit/credit ،
     * * لأن أودو بيعتبرهم **دايمًا** بعملة الشركة الأساسية ، و $amount
     * * بيتحط في amount_currency بالعملة اللي في currency_id
     */
    public function createAndPostJournalEntry(string $date, float $amount, int $odooCurrencyId, int $creditJournalId, int $creditOdooAccountId, int $debitOdooAccountId, ?string $ref, ?int $partnerId, ?string $message , $isBreakOrApplyDeposit =false, ?float $amountInMainFunctionalCurrency = null)
    {
        $id = null ;  // in edit mode
        $journalEntryData =   $this->getDataFormatted($date, $amount, $odooCurrencyId, $creditJournalId, $debitOdooAccountId, $creditOdooAccountId, $ref, $partnerId, $message, $id,$isBreakOrApplyDeposit, $amountInMainFunctionalCurrency) ;

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
    
    
    protected function getDataFormatted(string $date, float $amount, int $odooCurrencyId, int $creditJournalId, int $creditOdooAccountId, int $debitOdooAccountId, ?string $ref, ?int $partnerId, ?string $message, ?int $id = null , $isBreakOrApplyDeposit = false, ?float $amountInMainFunctionalCurrency = null  ):array
    {
        $inEditMode = is_null($id) ? 0 : 1;
        $id = is_null($id) ? 0 : $id ;
        /**
         * * من غير قيمة صريحة بنفترض إن الحركة بعملة الشركة نفسها
         */
        $amountInMainFunctionalCurrency = is_null($amountInMainFunctionalCurrency) ? $amount : $amountInMainFunctionalCurrency;
		/**
		 * * 0.0 مش 0 عشان الحمولة تفضل عشرية بالكامل زي باقي البنّائين
		 */
		$debitAmount = $isBreakOrApplyDeposit ? 0.0 : abs($amountInMainFunctionalCurrency)   ;
		$creditAmount = $isBreakOrApplyDeposit ? abs($amountInMainFunctionalCurrency) : 0.0  ;
        /**
         * * الإشارة بتمشي مع اتجاه السطر : موجب مع المدين و سالب مع
         * * الدائن . السطر الأول بيتقلب حسب isBreakOrApplyDeposit فالإشارة
         * * بتتقلب معاه ، و التاني عكسه دايمًا
         */
        $currencyAmount = $isBreakOrApplyDeposit ? -abs($amount) : abs($amount) ;
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
                        'amount_currency' => $currencyAmount,
                        'currency_id' => $odooCurrencyId,
                        'name' => $message , // cash cover
                        'partner_id' => $partnerId,
                    ]],
                    [$inEditMode, $id+1, [
                        'account_id' => $debitOdooAccountId , // chart of account odoo id
                        'debit' => $creditAmount,
                        'credit' => $debitAmount,
                        'amount_currency' => -$currencyAmount,
                        'currency_id' => $odooCurrencyId,
                        'name' => $message ,
                        'partner_id' => $partnerId,
                    ]],
                ],
            ];
    }
    /**
     * * زي createAndPostJournalEntry : debit/credit بعملة الشركة و
     * * amount_currency بالعملة الأجنبية
     */
    public function createMoneyDepositInBank(string $date, float $amount, int $odooCurrencyId, int $debitJournalId, int $debitOdooAccountId, int $creditOdooAccountId, ?string $ref, ?int $partner_id, ?string $message, ?float $amountInMainFunctionalCurrency = null)
    {
        $id = null ;  // in edit mode
        $journalEntryData = $this->getMoneyDepositDataFormatted($date, $amount, $odooCurrencyId, $debitJournalId, $debitOdooAccountId, $creditOdooAccountId, $ref, $partner_id, $message, $id, $amountInMainFunctionalCurrency) ;

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
    protected function getMoneyDepositDataFormatted(string $date, float $amount, int $odooCurrencyId, int $debitJournalId, int $debitOdooAccountId, int $creditOdooAccountId, ?string $ref, ?int $partner_id, ?string $message, ?int $id = null, ?float $amountInMainFunctionalCurrency = null):array
    {
        $inEditMode = is_null($id) ? 0 : 1;
        $id = is_null($id) ? 0 : $id ;
        $amountInMainFunctionalCurrency = is_null($amountInMainFunctionalCurrency) ? $amount : $amountInMainFunctionalCurrency;
        
        
        return [
               'journal_id' => $debitJournalId, // account journal id (safe or bank journal id )
               'amount' => $amount,
               'date' => $date,
               'partner_id' => $partner_id,
               'ref' =>  $ref, // create lg type
               'line_ids' => [
                    [$inEditMode, $id, [
                        'account_id' => $debitOdooAccountId, // lg cash cover odoo id (create lg cash cover)
                        'debit' => abs($amountInMainFunctionalCurrency),
                        'credit' => 0.0,
                        'amount_currency' => abs($amount),
                        'currency_id' => $odooCurrencyId,
                        'name' => $message , // cash cover
                        'partner_id' => $partner_id,
                    ]],
                    [$inEditMode, $id+1, [
                        'account_id' => $creditOdooAccountId, // chart of account odoo id
                        'debit' => 0.0,
                        'credit' => abs($amountInMainFunctionalCurrency),
                        'amount_currency' => -abs($amount),
                        'currency_id' => $odooCurrencyId,
                        'name' => $message ,
                        'partner_id' => $partner_id,
                    ]],
                ],
            ];
    }
    

}
