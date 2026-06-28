<?php
namespace App\Services\Api\Traits;

use App\Helpers\HVero;
use App\Services\Api\Traits\HasAnalysisAccount;
use Exception;

trait HasJournalEntry
{

    public function createAndPostJournalEntry(string $date, float $amount, int $odooCurrencyId, int $journalId, int $debitOdooAccountId, int $creditOdooAccountId, ?string $ref, ?int $partnerId, ?string $message, $analytic_distribution= [])
    {
        $id = null ;  // in edit mode
        $journalEntryData = $this->getDataFormatted($date, $amount, $odooCurrencyId, $journalId, $debitOdooAccountId, $creditOdooAccountId, $ref, $partnerId, $message, $id, $analytic_distribution) ;

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
    protected function getDataFormatted(string $date, float $amount, int $odooCurrencyId, int $journalId, int $debitOdooAccountId, int $creditOdooAccountId, ?string $ref, ?int $partnerId, ?string $message, ?int $id = null, $analytic_distribution = []):array
    {
        $inEditMode = is_null($id) ? 0 : 1;
        $id = is_null($id) ? 0 : $id ;
       
        $distribution_analytic_account_ids = HVero::getAnalysisAccountIds($analytic_distribution, $partnerId);
        return [
               'journal_id' => $journalId, // account journal id (safe or bank journal id )
               'amount' => $amount,
               'date' => $date,
               'partner_id' => $partnerId,
               'ref' =>  $ref, // create lg type
               'line_ids' => [
                    [$inEditMode, $id, [
                        'account_id' => $debitOdooAccountId, // lg cash cover odoo id (create lg cash cover)
                        'debit' => abs($amount),
                        'credit' => 0.0,
                        'currency_id' => $odooCurrencyId,
                        'name' => $message , // cash cover
                        'partner_id' => $partnerId,
                        'analytic_distribution' =>$analytic_distribution,   // 87  -> x_plan2_id     80 -> percentage   Allocate Amount / Paid Amount * 100     ,
                        'distribution_analytic_account_ids' => $distribution_analytic_account_ids  ,
                                    
                    ]],
                    [$inEditMode, $id+1, [
                        'account_id' => $creditOdooAccountId, // chart of account odoo id
                        'debit' => 0.0,
                        'credit' => abs($amount),
                        'currency_id' => $odooCurrencyId,
                        'name' => $message ,
                        'partner_id' => $partnerId,
                        // 'analytic_distribution' => [],
                        // 'distribution_analytic_account_ids' => [[6, 0, []]] ,
                              'analytic_distribution' =>$analytic_distribution,   // 87  -> x_plan2_id     80 -> percentage   Allocate Amount / Paid Amount * 100     ,
                        'distribution_analytic_account_ids' => $distribution_analytic_account_ids  ,
                           
                    ]],
                ],
            ];
    }
}
