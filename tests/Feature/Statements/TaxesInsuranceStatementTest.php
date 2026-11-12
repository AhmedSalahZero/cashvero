<?php

namespace Tests\Feature\Statements;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Every row on the Taxes & Insurance statement is a payment. cashvero writes it in `credit`
 * (HasPartnerStatement), but rows that came from system.veroanalysisb.com carry it the old way, in
 * `debit` with credit 0 — and the page read `credit` only, so a whole imported history showed 0
 * (company 92: 61 payments, 13,717,722.47). The data is imported as it is; the page reads both.
 */
class TaxesInsuranceStatementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);
    }

    public function test_old_debit_rows_and_new_credit_rows_both_show_their_amount(): void
    {
        $company = $this->makeCompany();
        $partnerId = (int) DB::table('partners')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Tax Authority',
            'is_tax' => 1,
        ]);

        $row = fn (string $date, float $debit, float $credit) => DB::table('tax_statements')->insert([
            'company_id' => $company->id,
            'partner_id' => $partnerId,
            'currency_name' => 'EGP',
            'date' => $date,
            'full_date' => $date.' 00:00:00',
            'debit' => $debit,
            'credit' => $credit,
            'comment_en' => 'payment',
        ]);

        $row('2026-01-10', 1000, 0);   // written by system.veroanalysisb.com
        $row('2026-02-10', 0, 250);    // written by cashvero

        $user = $this->makeUser(['report_taxes_insurance.view'], $company);

        $this->actingAs($user)
            ->get(route('result.taxes.insurance.statement', [
                'company' => $company->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'currency' => 'EGP',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.amount', 1000)
                ->where('rows.1.amount', 250)
                ->where('rows.1.accumulated_amount', 1250)
                ->etc());
    }
}
