<?php

namespace Tests\Feature\Balances;

use App\Http\Controllers\MoneyPaymentController;
use App\Http\Controllers\MoneyReceivedController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * The Money Received / Money Payment button on an invoice's row opens the
 * form for that one invoice. The Invoice Currency there is the invoice's
 * own and cannot change, but the Receive / Pay Currency must stay free: a
 * USD invoice can be settled in EGP from the standard form, so it has to be
 * settleable in EGP from the invoice row too.
 */
class ReceiveCurrencyFromInvoiceRowTest extends TestCase
{
    use MakesBanks;
    use MakesInvoices;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        // A company with a bank holding an EGP and a USD account: the currencies money can move in.
        $this->company = $this->makeCompany();
        $bank = $this->bank($this->company);
        $this->currentAccount($bank, 'EGP');
        $this->currentAccount($bank, 'USD');

        $this->user = $this->makeUser(['create money received', 'create supplier payment'], $this->company);
    }

    /**
     * The props of the create form for one invoice, as the browser gets them.
     *
     * The controller is called directly on a request shaped like the real URL
     * (/en/{company}/...): CustomerInvoice::getCurrencies() reads the company
     * from URL segment 2, and routes in the test kernel are registered without
     * the locale prefix, so going through the router would shift the segments.
     */
    private function createFormProps(string $controller, string $path, int $invoiceId): array
    {
        $request = Request::create('/en/'.$this->company->id.'/'.$path.'/'.$invoiceId, 'GET');
        $request->headers->set('X-Inertia', 'true');
        $this->app->instance('request', $request);

        return app($controller)->create($this->company, $invoiceId)->toResponse($request)->getData(true)['props'];
    }

    private function codes(array $options): array
    {
        return collect($options)->pluck('code')->all();
    }

    public function test_money_received_from_a_usd_invoice_row_can_still_receive_in_another_currency(): void
    {
        $customer = $this->customer($this->company);
        $usdInvoice = $this->invoice($customer, ['currency' => 'USD'])->id;
        $this->invoice($customer, ['currency' => 'EGP']);

        $this->actingAs($this->user);
        $props = $this->createFormProps(MoneyReceivedController::class, 'money-received/create', $usdInvoice);

        $this->assertSame(['USD'], $this->codes($props['currencies']), 'Invoice Currency stays the invoice\'s own.');
        $this->assertContains('EGP', $this->codes($props['receivingCurrencies']));
        $this->assertContains('USD', $this->codes($props['receivingCurrencies']));
    }

    public function test_the_standard_money_received_form_offers_the_same_receive_currencies(): void
    {
        $customer = $this->customer($this->company);
        $usdInvoice = $this->invoice($customer, ['currency' => 'USD'])->id;

        $this->actingAs($this->user);
        $fromRow = $this->createFormProps(MoneyReceivedController::class, 'money-received/create', $usdInvoice);

        $request = Request::create('/en/'.$this->company->id.'/money-received/create', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $this->app->instance('request', $request);
        $standard = app(MoneyReceivedController::class)->create($this->company)->toResponse($request)->getData(true)['props'];

        $this->assertEqualsCanonicalizing($this->codes($standard['currencies']), $this->codes($fromRow['receivingCurrencies']));
    }

    public function test_money_payment_from_a_usd_invoice_row_can_still_pay_in_another_currency(): void
    {
        $supplier = $this->supplier($this->company);
        $usdInvoice = $this->supplierInvoice($supplier, ['currency' => 'USD'])->id;

        $this->actingAs($this->user);
        $props = $this->createFormProps(MoneyPaymentController::class, 'money-payment/create', $usdInvoice);

        $this->assertSame(['USD'], $this->codes($props['currencies']), 'Invoice Currency stays the invoice\'s own.');
        $this->assertContains('EGP', $this->codes($props['paymentCurrencies']));
        $this->assertContains('USD', $this->codes($props['paymentCurrencies']));
    }
}
