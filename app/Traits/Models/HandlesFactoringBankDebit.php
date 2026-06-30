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
    public const BANK_MOVEMENT_DISBURSEMENT = 'disbursement';

    public const BANK_MOVEMENT_DIFFERENCE_RECEIVED = 'difference_received';

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
        $movementType = self::BANK_MOVEMENT_DISBURSEMENT;

        if ($accountType->isCurrentAccount()) {
            $account = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            CurrentAccountBankStatement::create([
                'financial_institution_account_id' => $account->id,
                'factoring_transaction_id' => $this->id,
                'factoring_bank_movement_type' => $movementType,
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
                'factoring_bank_movement_type' => $movementType,
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
                'factoring_bank_movement_type' => $movementType,
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
                'factoring_bank_movement_type' => $movementType,
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
                'factoring_bank_movement_type' => $movementType,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => 0,
                'debit' => $debit,
            ]);
        }
    }

    public function storeBankCreditStatementForDifference(
        int $companyId,
        int $financialInstitutionId,
        AccountType $accountType,
        string $accountNumber,
        string $date,
        float $credit,
        ?string $commentEn = null,
        ?string $commentAr = null
    ): void {
        $movementType = self::BANK_MOVEMENT_DIFFERENCE_RECEIVED;

        if ($accountType->isCurrentAccount()) {
            $account = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            CurrentAccountBankStatement::create([
                'financial_institution_account_id' => $account->id,
                'factoring_transaction_id' => $this->id,
                'factoring_bank_movement_type' => $movementType,
                'company_id' => $companyId,
                'date' => $date,
                'credit' => $credit,
                'debit' => 0,
                'comment_en' => $commentEn,
                'comment_ar' => $commentAr,
            ]);

            return;
        }

        if ($accountType->isCleanOverdraftAccount()) {
            $overdraft = CleanOverdraft::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            CleanOverdraftBankStatement::create([
                'type' => 'factoring-without-recourse-difference',
                'clean_overdraft_id' => $overdraft->id,
                'factoring_transaction_id' => $this->id,
                'factoring_bank_movement_type' => $movementType,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => $credit,
                'debit' => 0,
                'comment_en' => $commentEn,
                'comment_ar' => $commentAr,
            ]);

            return;
        }

        if ($accountType->isFullySecuredOverdraftAccount()) {
            $overdraft = FullySecuredOverdraft::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            FullySecuredOverdraftBankStatement::create([
                'type' => 'factoring-without-recourse-difference',
                'fully_secured_overdraft_id' => $overdraft->id,
                'factoring_transaction_id' => $this->id,
                'factoring_bank_movement_type' => $movementType,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => $credit,
                'debit' => 0,
            ]);

            return;
        }

        if ($accountType->isOverdraftAgainstCommercialPaperAccount()) {
            $overdraft = OverdraftAgainstCommercialPaper::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            OverdraftAgainstCommercialPaperBankStatement::create([
                'type' => 'factoring-without-recourse-difference',
                'overdraft_against_commercial_paper_id' => $overdraft->id,
                'factoring_transaction_id' => $this->id,
                'factoring_bank_movement_type' => $movementType,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => $credit,
                'debit' => 0,
            ]);

            return;
        }

        if ($accountType->isOverdraftAgainstAssignmentOfContractAccount()) {
            $overdraft = OverdraftAgainstAssignmentOfContract::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
            OverdraftAgainstAssignmentOfContractBankStatement::create([
                'type' => 'factoring-without-recourse-difference',
                'overdraft_against_assignment_of_contract_id' => $overdraft->id,
                'factoring_transaction_id' => $this->id,
                'factoring_bank_movement_type' => $movementType,
                'company_id' => $companyId,
                'date' => $date,
                'limit' => $overdraft->getLimit(),
                'credit' => $credit,
                'debit' => 0,
            ]);
        }
    }

    public function deleteBankDebitStatements(): void
    {
        $this->deleteBankStatementsByMovementType(self::BANK_MOVEMENT_DISBURSEMENT, true);
    }

    public function deleteDifferenceReceivedBankStatements(): void
    {
        $this->deleteBankStatementsByMovementType(self::BANK_MOVEMENT_DIFFERENCE_RECEIVED, false);
    }

    protected function deleteBankStatementsByMovementType(string $movementType, bool $includeLegacyNull): void
    {
        foreach ([
            CurrentAccountBankStatement::class,
            CleanOverdraftBankStatement::class,
            FullySecuredOverdraftBankStatement::class,
            OverdraftAgainstCommercialPaperBankStatement::class,
            OverdraftAgainstAssignmentOfContractBankStatement::class,
        ] as $modelClass) {
            $modelClass::query()
                ->where('factoring_transaction_id', $this->id)
                ->where(function ($query) use ($movementType, $includeLegacyNull) {
                    $query->where('factoring_bank_movement_type', $movementType);
                    if ($includeLegacyNull) {
                        $query->orWhereNull('factoring_bank_movement_type');
                    }
                })
                ->delete();
        }
    }
}
