<?php

namespace Tests\Unit\ShareholderAccounts;

use App\Support\ShareholderAccounts\AccountNumberLabel;
use Tests\TestCase;

class AccountNumberLabelTest extends TestCase
{
    public function test_format_leaves_a_company_account_unchanged(): void
    {
        $this->assertSame('12345', AccountNumberLabel::format('12345', null));
        $this->assertSame('12345', AccountNumberLabel::format('12345', ''));
        $this->assertSame('12345', AccountNumberLabel::format('12345', '   '));
    }

    public function test_format_appends_the_shareholder_name(): void
    {
        $this->assertSame('12345 — Ahmed', AccountNumberLabel::format('12345', 'Ahmed'));
    }

    public function test_format_returns_empty_account_numbers_unchanged(): void
    {
        $this->assertNull(AccountNumberLabel::format(null, 'Ahmed'));
        $this->assertSame('', AccountNumberLabel::format('', 'Ahmed'));
    }

    public function test_decorate_text_labels_a_shareholder_account_number_in_a_comment(): void
    {
        $this->assertSame(
            'From ADCB Account No 99999 — Amr Rostom',
            AccountNumberLabel::decorateTextWithMap(
                'From ADCB Account No 99999',
                ['99999' => 'Amr Rostom']
            )
        );
    }

    public function test_decorate_text_does_not_replace_a_number_inside_a_longer_account(): void
    {
        $this->assertSame(
            'Account No 99999',
            AccountNumberLabel::decorateTextWithMap('Account No 99999', ['999' => 'Amr'])
        );
    }

    public function test_decorate_text_does_not_label_a_comment_twice(): void
    {
        $labeled = 'From ADCB Account No 99999 — Amr Rostom';

        $this->assertSame(
            $labeled,
            AccountNumberLabel::decorateTextWithMap($labeled, ['99999' => 'Amr Rostom'])
        );
    }
}
