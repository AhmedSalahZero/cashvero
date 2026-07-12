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

    public const BANK_MOVEMENT_COLLECTION_RECEIVED = 'collection_received';

    public const BANK_MOVEMENT_REJECTION_PAYMENT = 'rejection_payment';

    protected function getDisbursementOverdraftType(): string
    {
        return $this->recourse_type === 'with_recourse'
            ? 'factoring-with-recourse'
            : 'factoring-without-recourse';
    }

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
                'type' => $this->getDisbursementOverdraftType(),
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
                'type' => $this->getDisbursementOverdraftType(),
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
                'type' => $this->getDisbursementOverdraftType(),
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
                'type' => $this->getDisbursementOverdraftType(),
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

    public function storeBankCreditStatementForCollection(
        int $companyId,
        int $financialInstitutionId,
        AccountType $accountType,
        string $accountNumber,
        string $date,
        float $credit,
        ?string $commentEn = null,
        ?string $commentAr = null
    ): void {
        $this->storeBankCreditStatementByMovementType(
            self::BANK_MOVEMENT_COLLECTION_RECEIVED,
            'factoring-with-recourse-collection',
            $companyId,
            $financialInstitutionId,
            $accountType,
            $accountNumber,
            $date,
            $credit,
            $commentEn,
            $commentAr
        );
    }

    public function storeBankDebitStatementForRejection(
        int $companyId,
        int $financialInstitutionId,
        AccountType $accountType,
        string $accountNumber,
        string $date,
        float $debit,
        ?string $commentEn = null,
        ?string $commentAr = null
    ): void {
        $movementType = self::BANK_MOVEMENT_REJECTION_PAYMENT;

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
                'type' => 'factoring-with-recourse-rejection',
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
                'type' => 'factoring-with-recourse-rejection',
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
                'type' => 'factoring-with-recourse-rejection',
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
                'type' => 'factoring-with-recourse-rejection',
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

    protected function storeBankCreditStatementByMovementType(
        string $movementType,
        string $overdraftType,
        int $companyId,
        int $financialInstitutionId,
        AccountType $accountType,
        string $accountNumber,
        string $date,
        float $credit,
        ?string $commentEn = null,
        ?string $commentAr = null
    ): void {
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
                'type' => $overdraftType,
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
                'type' => $overdraftType,
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
                'type' => $overdraftType,
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
                'type' => $overdraftType,
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
        $this->storeBankCreditStatementByMovementType(
            self::BANK_MOVEMENT_DIFFERENCE_RECEIVED,
            'factoring-without-recourse-difference',
            $companyId,
            $financialInstitutionId,
            $accountType,
            $accountNumber,
            $date,
            $credit,
            $commentEn,
            $commentAr
        );
    }

    public function deleteBankDebitStatements(): void
    {
        $this->deleteBankStatementsByMovementType(self::BANK_MOVEMENT_DISBURSEMENT, true);
    }

    public function deleteDifferenceReceivedBankStatements(): void
    {
        $this->deleteBankStatementsByMovementType(self::BANK_MOVEMENT_DIFFERENCE_RECEIVED, false);
    }

    public function deleteCollectionBankStatements(): void
    {
        $this->deleteBankStatementsByMovementType(self::BANK_MOVEMENT_COLLECTION_RECEIVED, false);
    }

    public function deleteRejectionBankStatements(): void
    {
        $this->deleteBankStatementsByMovementType(self::BANK_MOVEMENT_REJECTION_PAYMENT, false);
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
