<?php

namespace Tests\Unit;

use App\Console\Commands\DeletePermittedTreasuryTransactionsCommand;
use App\Models\Company;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\MoneyReceived;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DeletePermittedTreasuryTransactionsCommandTest extends TestCase
{
    public function test_money_received_delete_visibility_matches_all_frontend_tabs_with_a_delete_button(): void
    {
        $command = new DeletePermittedTreasuryTransactionsCommand;
        $method = new ReflectionMethod($command, 'visibleDeleteAction');
        $company = new Company;

        foreach ([
            MoneyReceived::INCOMING_TRANSFER,
            MoneyReceived::CASH_IN_SAFE,
            MoneyReceived::CASH_IN_BANK,
        ] as $type) {
            $record = new MoneyReceived;
            $record->type = $type;

            self::assertTrue($method->invoke($command, 'Money Received', $record, $company), $type);
        }
    }

    public function test_money_received_delete_visibility_excludes_cheque_tabs_without_a_delete_button(): void
    {
        $command = new DeletePermittedTreasuryTransactionsCommand;
        $method = new ReflectionMethod($command, 'visibleDeleteAction');
        $company = new Company;

        foreach ([
            MoneyReceived::CHEQUE_UNDER_COLLECTION,
            MoneyReceived::CHEQUE_COLLECTED,
        ] as $type) {
            $record = new MoneyReceived;
            $record->type = $type;

            self::assertFalse($method->invoke($command, 'Money Received', $record, $company), $type);
        }
    }

    public function test_letter_of_guarantee_delete_visibility_excludes_cancelled_rows(): void
    {
        $command = new DeletePermittedTreasuryTransactionsCommand;
        $method = new ReflectionMethod($command, 'visibleDeleteAction');
        $company = new Company;

        $running = new LetterOfGuaranteeIssuance;
        $running->status = LetterOfGuaranteeIssuance::RUNNING;
        self::assertTrue($method->invoke($command, 'Letter Of Guarantee Issuance', $running, $company));

        $cancelled = new LetterOfGuaranteeIssuance;
        $cancelled->status = LetterOfGuaranteeIssuance::CANCELLED;
        self::assertFalse($method->invoke($command, 'Letter Of Guarantee Issuance', $cancelled, $company));
    }

    public function test_letter_of_credit_delete_visibility_excludes_paid_rows(): void
    {
        $command = new DeletePermittedTreasuryTransactionsCommand;
        $method = new ReflectionMethod($command, 'visibleDeleteAction');
        $company = new Company;

        $running = new LetterOfCreditIssuance;
        $running->status = LetterOfCreditIssuance::RUNNING;
        self::assertTrue($method->invoke($command, 'Letter Of Credit Issuance', $running, $company));

        $paid = new LetterOfCreditIssuance;
        $paid->status = LetterOfCreditIssuance::PAID;
        self::assertFalse($method->invoke($command, 'Letter Of Credit Issuance', $paid, $company));
    }
}
