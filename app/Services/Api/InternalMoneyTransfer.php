<?php
namespace App\Services\Api;

use App\Services\Api\Traits\AuthTrait;
use App\Services\Api\Traits\HasPayment;
use App\Services\Api\Traits\HasUnlinkAccountBankStatementLine;
use Exception;

class InternalMoneyTransfer
{
    
    use AuthTrait,HasPayment,HasUnlinkAccountBankStatementLine;
    
    
    public function sendMoneyTo(bool $isBreakDeposit, string $date, float $amountInCurrency, float $amountInMainFunctionalCurrency, int $odooCurrencyId, int $journalId, int $bankOdooId, $message , $userComment )
    {
        $amountInCurrency = $amountInCurrency * -1;
        $LiquidTransferId = $this->company->odooSetting->getLiquidityAccountOdooId();
        
        $debitAccountId = $isBreakDeposit ?  $bankOdooId : $LiquidTransferId ;
        $creditAccountId = $isBreakDeposit ?  $LiquidTransferId : $bankOdooId ;
        $userComment = $userComment?:$message;
        $debitArr = [
                    'account_id' => $debitAccountId, // 87
                    'debit' => abs($amountInMainFunctionalCurrency),
                    'amount_currency'=>abs($amountInCurrency),
                    'credit' => 0.0,
                    'currency_id' => $odooCurrencyId,
                    'name' => $userComment ,
                ] ;
        $creditArr = [
            'account_id' => $creditAccountId,
            'debit' => 0.0,
            'credit' => abs($amountInMainFunctionalCurrency),
            'amount_currency'=>$amountInCurrency,
            'currency_id' => $odooCurrencyId,
            'name' => $userComment ,
        ] ;
        $journalEntryData = [
           'journal_id' => $journalId,
           'amount' => $amountInCurrency,
           'date' => $date,
           'ref' =>  $message,
		//    'rate'=>$exchangeRate,
           'line_ids' => [
                [0, 0, $debitArr ],
                   
                [0, 0, $creditArr],
                   
            ],
        ];
              

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
            throw new \Exception("Failed to create journal entry: " . json_encode($accountBankStatementLineId));
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
        $reference = $statementData[0]['move_id'][1]??null ;
        if ($reference) {
            $reference=explode(' ', $reference)[0];
        }
        return [
            'account_bank_statement_line_id'=>$accountBankStatementLineId,
            'journal_entry_id'=>$journalEntryId,
            'reference'=>$reference,
            /**
             * ! Need To Be Edited
             */
            'synced_with_odoo'=>true ,
            'odoo_error_message'=> null
        ];
    }
       
    
       
    public function storeReceiveMoneyTo(bool $isBreakDeposit, string $date, float $amountInCurrency, float $amountInMainFunctionalCurrency, int $odooCurrencyId, int $journalId, int $bankOdooId, $message,$userComment)
    {
        $LiquidTransferId = $this->company->odooSetting->getLiquidityAccountOdooId();
    
        
        $debitAccountId = $isBreakDeposit ?  $LiquidTransferId  :  $bankOdooId ;
        $creditAccountId = $isBreakDeposit ?  $bankOdooId : $LiquidTransferId ;
        $userComment = $userComment?:$message;
        $debitArr = [
                    'account_id' => $debitAccountId, // 87
                    'debit' => abs($amountInMainFunctionalCurrency),
                    'amount_currency'=>abs($amountInCurrency),
                    'credit' => 0.0,
                    'currency_id' => $odooCurrencyId,
                    'name' => $userComment ,
                        
                ] ;
                
        $creditArr = [
            'account_id' => $creditAccountId,
            'debit' => 0.0,
            'credit' => abs($amountInMainFunctionalCurrency),
            'amount_currency'=>$amountInCurrency*-1,
            'currency_id' => $odooCurrencyId,
            'name' => $userComment ,
                        
        ] ;
        
        $journalEntryData = [
           'journal_id' => $journalId,
           'amount' => $amountInCurrency,
           'date' => $date,
           'ref' =>  $message,
           'line_ids' => [
                [0, 0, $debitArr ],
                   
                [0, 0, $creditArr ],
                   
            ],
        ];
              

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
            throw new \Exception("Failed to create journal entry: " . json_encode($accountBankStatementLineId));
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
       
        $reference = $statementData[0]['move_id'][1]??null ;
        if ($reference) {
            $reference=explode(' ', $reference)[0];
        }
        return [
            'account_bank_statement_line_id'=>$accountBankStatementLineId,
            'journal_entry_id'=>$journalEntryId,
            'reference'=>$reference,
            /**
             * ! Need To Be Edited
             */
            'synced_with_odoo'=>true ,
            'odoo_error_message'=> null
        ];
    }
    public function cancelMoneyTransferPayment(int $paymentId)
    {
        return $this->cancelPayments($paymentId);
    }
}
