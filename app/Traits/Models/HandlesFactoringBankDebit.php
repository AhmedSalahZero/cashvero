<?php

namespace App\Traits\Models;

use App\Models\AccountType;
use App\Models\CleanOverdraft;
use App\Models\CleanOverdraftBankStatement;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitutionAccount;
use App\Models\FullySecuredOverdraft;
use App\Models\FullySecuredOverdraftBankStatement;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstAssignmentOfContractBankStatement;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Models\OverdraftAgainstCommercialPaperBankStatement;

trait HandlesFactoringBankDebit
{
    public function storeBankDebitStatement(
        int $companyId,
        int $financialInstitutionId,
        AccountType $accountType,
        string $accountNumber,
        string $date,
        float $debit,
        ?string $commentEn = null,
        ?string $commentAr = null
    ): void {
        if ($accountType->isCurrentAccount()) {
            $account = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            CurrentAccountBankStatement::create([
                'financial_institution_account_id' => $account->id,
                'factoring_transaction_id' => $this->id,
                'company_id' => $companyId,
                'date' => $date,
                'credit' => 0,
                'debit' => $debit,
                'comment_en' => $commentEn,
                'comment_ar' => $commentAr,
            ]);

            return;
        }

        if ($accountType->isCleanOverdraftAccount()) {
            $overdraft = CleanOverdraft::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            CleanOverdraftBankStatement::create([
                'type' => 'factoring-without-recourse',
                'clean_overdraft_id' => $overdraft->id,
                'factoring_transaction_id' => $this->id,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => 0,
                'debit' => $debit,
                'comment_en' => $commentEn,
                'comment_ar' => $commentAr,
            ]);

            return;
        }

        if ($accountType->isFullySecuredOverdraftAccount()) {
            $overdraft = FullySecuredOverdraft::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            FullySecuredOverdraftBankStatement::create([
                'type' => 'factoring-without-recourse',
                'fully_secured_overdraft_id' => $overdraft->id,
                'factoring_transaction_id' => $this->id,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => 0,
                'debit' => $debit,
            ]);

            return;
        }

        if ($accountType->isOverdraftAgainstCommercialPaperAccount()) {
            $overdraft = OverdraftAgainstCommercialPaper::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            OverdraftAgainstCommercialPaperBankStatement::create([
                'type' => 'factoring-without-recourse',
                'overdraft_against_commercial_paper_id' => $overdraft->id,
                'factoring_transaction_id' => $this->id,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => 0,
                'debit' => $debit,
            ]);

            return;
        }

        if ($accountType->isOverdraftAgainstAssignmentOfContractAccount()) {
            $overdraft = OverdraftAgainstAssignmentOfContract::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            OverdraftAgainstAssignmentOfContractBankStatement::create([
                'type' => 'factoring-without-recourse',
                'overdraft_against_assignment_of_contract_id' => $overdraft->id,
                'factoring_transaction_id' => $this->id,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => 0,
                'debit' => $debit,
            ]);
        }
    }

    public function deleteBankDebitStatements(): void
    {
        CurrentAccountBankStatement::where('factoring_transaction_id', $this->id)->delete();
        CleanOverdraftBankStatement::where('factoring_transaction_id', $this->id)->delete();
        FullySecuredOverdraftBankStatement::where('factoring_transaction_id', $this->id)->delete();
        OverdraftAgainstCommercialPaperBankStatement::where('factoring_transaction_id', $this->id)->delete();
        OverdraftAgainstAssignmentOfContractBankStatement::where('factoring_transaction_id', $this->id)->delete();
    }
}
