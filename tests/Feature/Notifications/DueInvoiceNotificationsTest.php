<?php

namespace Tests\Feature\Notifications;

use App\Jobs\CheckDueAndPastedInvoicesJob;
use App\Models\Company;
use App\Models\CustomizedFieldsExportation;
use App\Notification as AppNotification;
use App\Notifications\DueInvoiceNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * The due / past-due invoice notifications the company gets on login.
 * "Now" is Thursday 17 September 2026.
 *
 * Run: php artisan test --filter=DueInvoiceNotificationsTest
 */
class DueInvoiceNotificationsTest extends TestCase
{
    use MakesInvoices;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-17 10:00:00');
        $this->company = $this->makeCompany();

        // The job only looks at a company that has chosen its invoice columns.
        CustomizedFieldsExportation::create([
            'company_id' => $this->company->id,
            'model_name' => 'CustomerInvoice',
            'fields' => ['customer_name', 'invoice_number', 'invoice_date', 'invoice_due_date', 'currency', 'invoice_amount'],
        ]);

        $customer = $this->customer($this->company, ['name' => 'Nile Trading']);
        $invoice = fn (string $number, string $due) => $this->invoice($customer, [
            'invoice_number' => $number, 'customer_name' => 'Nile Trading', 'invoice_date' => '2026-08-01',
            'invoice_due_date' => $due, 'invoice_amount' => 1000, 'net_invoice_amount' => 1000, 'currency' => 'EGP',
        ]);

        $invoice('INV-PAST', '2026-09-01');         // 16 days late
        $invoice('INV-TODAY', '2026-09-17');        // due today
        $invoice('INV-SOON', '2026-09-20');         // due in 3 days
        $invoice('INV-FAR', '2027-06-30');          // far off: not worth a notice yet
        $paid = $invoice('INV-PAID', '2026-09-01'); // late, but nothing is left to pay
        $this->settle($this->received($customer, ['received_amount' => 1000, 'receiving_date' => '2026-09-02']), $paid, 1000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, array> invoice number => notification payload */
    private function sent(): array
    {
        $payloads = [];
        Notification::assertSentTo($this->company, DueInvoiceNotification::class, function (DueInvoiceNotification $n) use (&$payloads) {
            $payload = $n->toArray($this->company);
            $payloads[$payload['data_array']['Invoice Number'] ?? '?'] = $payload;

            return true;
        });

        return $payloads;
    }

    public function test_late_due_and_upcoming_invoices_are_announced_to_the_company(): void
    {
        Notification::fake();

        (new CheckDueAndPastedInvoicesJob($this->company->id))->handle();
        $sent = $this->sent();

        $this->assertSame(AppNotification::CUSTOMER_INVOICE_PAST_DUE, $sent['INV-PAST']['type']);
        $this->assertSame(16, (int) $sent['INV-PAST']['data_array']['Past Due Since (Days)']);
        $this->assertStringContainsString('Nile Trading', $sent['INV-PAST']['message_en']);
        $this->assertSame(AppNotification::CUSTOMER_INVOICE_CURRENT_DUE, $sent['INV-TODAY']['type']);
        $this->assertSame(AppNotification::CUSTOMER_INVOICE_COMING_DUE, $sent['INV-SOON']['type']);
    }

    public function test_a_paid_invoice_and_one_far_in_the_future_are_left_alone(): void
    {
        Notification::fake();

        (new CheckDueAndPastedInvoicesJob($this->company->id))->handle();
        $sent = $this->sent();

        $this->assertArrayNotHasKey('INV-PAID', $sent);
        $this->assertArrayNotHasKey('INV-FAR', $sent);
    }

    public function test_another_companys_invoices_are_never_announced_here(): void
    {
        $other = $this->makeCompany();
        $this->invoice($this->customer($other), ['invoice_number' => 'THEIRS', 'invoice_due_date' => '2026-09-01', 'invoice_amount' => 500, 'net_invoice_amount' => 500]);
        Notification::fake();

        (new CheckDueAndPastedInvoicesJob($this->company->id))->handle();

        $this->assertArrayNotHasKey('THEIRS', $this->sent());
        Notification::assertNotSentTo($other, DueInvoiceNotification::class);
    }

    /** It runs on every login: the second run replaces the first instead of adding to it. */
    public function test_running_it_again_does_not_repeat_the_notices(): void
    {
        $count = fn () => DB::table('notifications')->where('notifiable_id', $this->company->id)->count();

        (new CheckDueAndPastedInvoicesJob($this->company->id))->handle();
        $first = $count();
        (new CheckDueAndPastedInvoicesJob($this->company->id))->handle();

        $this->assertGreaterThanOrEqual(3, $first);
        $this->assertSame($first, $count());
    }

    public function test_the_late_invoice_is_marked_past_due(): void
    {
        Notification::fake();

        (new CheckDueAndPastedInvoicesJob($this->company->id))->handle();

        $this->assertSame('past_due', DB::table('customer_invoices')->where('invoice_number', 'INV-PAST')->value('invoice_status'));
    }
}
