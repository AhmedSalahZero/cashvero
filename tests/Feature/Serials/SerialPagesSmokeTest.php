<?php

namespace Tests\Feature\Serials;

use App\Models\MoneyReceived;
use App\Models\User;
use Database\Factories\MoneyReceivedFactory;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;

/**
 * * الصفحات نفسها : كل جدول index بيقبل البحث بالرقم المسلسل ، و صفحة
 * * الطباعة بتعرض الرقم بدل الـ id .
 *
 * * نفس طريقة PaginationSmokeTest : طلبات GET حقيقية على داتابيز
 * * التطوير بمستخدم سوبر أدمن .
 */
class SerialPagesSmokeTest extends SerialTestCase
{
    private ?User $actor = null;

    protected function setUp(): void
    {
        parent::setUp();

        // شوف PaginationSmokeTest : من غيرهم كل طلب بيتحوّل قبل الكونترولر
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->actor = $this->makeSuperAdmin($this->company);
    }

    /** A cash-in-safe receipt saved like one entered on the screen, so it carries its serial. */
    private function cashInSafeRow(): MoneyReceived
    {
        $row = MoneyReceivedFactory::new()->forCompany($this->company)->cashInSafe()->create();
        $this->assertNotNull($row->serial, 'The receipt was not given a serial number.');

        return $row;
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function searches(): array
    {
        return [
            'money received' => ['view.money.receive', 'active=cash-in-safe&field=serial&value=MR/'],
            'money payment' => ['view.money.payment', 'active=cash_payment&field=serial&value=MP/'],
            'cash expense' => ['view.cash.expense', 'active=cash_payment&field=serial&value=CE/'],
            'internal transfer' => ['internal-money-transfers.index', 'active=bank-to-bank&value=IMT/'],
            'buy or sell currency' => ['buy-or-sell-currencies.index', 'active=safe-to-safe&value=FX/'],
            'lg' => ['view.letter.of.guarantee.issuance', 'active=final-lgs&field=serial&value=LG/'],
            'lc' => ['view.letter.of.credit.issuance', 'field=serial&value=LC/'],
            'factoring with recourse' => ['factoring.with-recourse.index', 'field=serial&value=FAC/'],
            'factoring without recourse' => ['factoring.without-recourse.index', 'field=serial&value=FAC/'],
            'lc settlement' => ['lc-settlement-internal-money-transfers.index', 'serial=LCS/'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searches')]
    public function test_the_index_accepts_a_serial_search(string $route, string $query): void
    {
        $company = $this->cashInSafeRow()->company_id;
        $response = $this->actingAs($this->actor)->get(route($route, ['company' => $company]).'?'.$query);

        $this->assertSame(200, $response->getStatusCode(), "{$route} returned {$response->getStatusCode()}");
    }

    public function test_the_multiple_cash_expense_index_accepts_a_serial_search(): void
    {
        // The screen exists only for companies that do not use Odoo — the test company does not.
        $response = $this->actingAs($this->actor)->get(route('multiple-cash-expenses.index', ['company' => $this->company->id]).'?search=MCE/');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_a_serial_search_finds_rows_older_than_the_default_window(): void
    {
        $row = $this->cashInSafeRow();

        $this->inRolledBackTransaction(function () use ($row) {
            DB::table('money_received')->where('id', $row->id)->update(['receiving_date' => '2015-01-01']);

            $this->actingAs($this->actor)
                ->get(route('view.money.receive', ['company' => $row->company_id]).'?'.http_build_query([
                    'active' => MoneyReceived::CASH_IN_SAFE,
                    'field' => 'serial',
                    'value' => $row->serial,
                ]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('tabs.cash-in-safe.paginator.total', 1)
                    ->where('tabs.cash-in-safe.paginator.data.0.id', $row->id)
                    ->where('tabs.cash-in-safe.paginator.data.0.serial', $row->serial)
                    ->where('tabs.cash-in-safe.searchFields.serial', __('Serial'))
                    ->etc());
        });
    }

    public function test_the_print_page_shows_the_serial(): void
    {
        $row = $this->cashInSafeRow();

        $this->actingAs($this->actor)
            ->get(route('print.money.receive', ['company' => $row->company_id, 'moneyReceived' => $row->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('MoneyReceived/Print', false)
                ->where('record.serial', $row->serial)
                ->etc());
    }
}
