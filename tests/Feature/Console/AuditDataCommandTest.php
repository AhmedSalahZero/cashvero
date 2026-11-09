<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesBanks;
use Tests\Concerns\MakesFacilities;
use Tests\TestCase;

/**
 * cashvero:audit-data — the read-only checks of the real data — finds what it should, finds
 * nothing in clean data, and changes nothing.
 *
 * Run: php artisan test --filter=AuditDataCommandTest
 */
class AuditDataCommandTest extends TestCase
{
    use MakesBanks;
    use MakesFacilities;

    public function test_clean_data_passes(): void
    {
        $company = $this->makeCompany();
        $this->currentAccount($this->bank($company));

        $this->artisan('cashvero:audit-data', ['--company' => $company->id])
            ->expectsOutputToContain('No problems found.')
            ->assertSuccessful();
    }

    public function test_it_reports_a_fractional_overdraft_rate_and_a_settlement_without_invoice_and_changes_nothing(): void
    {
        $company = $this->makeCompany();
        $overdraft = $this->cleanOverdraft($this->bank($company));
        DB::table('clean_overdraft_rates')->insert([
            'company_id' => $company->id, 'clean_overdraft_id' => $overdraft->id, 'date' => '2020-01-01',
            'interest_rate' => 22.5, 'min_interest_rate' => 0,
        ]);
        DB::table('settlements')->insert(['company_id' => $company->id, 'invoice_id' => null, 'settlement_amount' => 500]);

        $before = [DB::table('clean_overdraft_rates')->count(), DB::table('settlements')->count()];

        $this->artisan('cashvero:audit-data', ['--company' => $company->id])
            ->expectsOutputToContain('Overdraft rates with a fraction of a percent — 1 found')
            ->expectsOutputToContain('Settlements with an amount but no invoice — 1 found')
            ->assertFailed();

        $this->assertSame($before, [DB::table('clean_overdraft_rates')->count(), DB::table('settlements')->count()]);
    }

    public function test_one_company_is_checked_without_seeing_the_others(): void
    {
        $mine = $this->makeCompany();
        $other = $this->makeCompany();
        DB::table('settlements')->insert(['company_id' => $other->id, 'invoice_id' => null, 'settlement_amount' => 500]);

        $this->artisan('cashvero:audit-data', ['--company' => $mine->id])->assertSuccessful();
        $this->artisan('cashvero:audit-data', ['--company' => $other->id])->assertFailed();
    }
}
