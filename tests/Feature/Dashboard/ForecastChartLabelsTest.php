<?php

namespace Tests\Feature\Dashboard;

use Tests\TestCase;

/**
 * The forecast dashboard's two chart rows plot different things and
 * must be named for what they actually show.
 *
 *   Aging row   — InvoiceAgingService → invoice aging.
 *   Cheques row — ChequeAgingService  → cheques coming due.
 *
 * Naming a customer's cheque a receivable and a supplier's a payable is
 * right for the cheque row; putting "Cheques" on the Aging row would
 * label a chart of invoices as cheques, which is why the two rows read
 * from separate label maps rather than one shared one.
 */
class ForecastChartLabelsTest extends TestCase
{
    private function page(): string
    {
        return file_get_contents(resource_path('js/Pages/Dashboard/Forecast.vue'));
    }

    public function test_the_cheque_charts_are_named_after_the_instrument(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression(
            "/const chequeTypeLabels = \{ CustomerInvoice: 'Customer Receivable Cheques', SupplierInvoice: 'Supplier Payable Cheques' \}/",
            $page
        );
    }

    /**
     * The cheque row moved from a coming-due-only donut to the same
     * diverging bar chart the invoices use, so the heading dropped the
     * "— Cheques Coming Due" suffix and now reads "... Aging" to match
     * the row above it. The labels themselves are unchanged.
     */
    public function test_the_cheque_row_reads_the_cheque_labels(): void
    {
        $this->assertStringContainsString(
            '{{ chequeTypeLabels[modelType] || modelType }} Aging',
            $this->page()
        );
    }

    public function test_the_cheque_heading_no_longer_claims_coming_due_only(): void
    {
        $this->assertStringNotContainsString(
            'Cheques Coming Due',
            $this->page(),
            'The chart now shows past due as well, so that heading would be wrong.'
        );
    }

    /**
     * The Aging row plots invoice aging, so it keeps the invoice
     * labels. Renaming it to "Cheques" would be a mislabelled chart,
     * not a rename.
     */
    public function test_the_aging_row_still_reads_the_invoice_labels(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('{{ invoiceTypeLabels[modelType] || modelType }} Aging', $page);
        $this->assertStringContainsString(
            "const invoiceTypeLabels = { CustomerInvoice: 'Customer Invoices', SupplierInvoice: 'Supplier Invoices' }",
            $page
        );
    }

    /**
     * The two maps have to stay separate: one map for both rows is
     * exactly the mistake this guards.
     */
    public function test_the_two_rows_do_not_share_one_label_map(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('const invoiceTypeLabels', $page);
        $this->assertStringContainsString('const chequeTypeLabels', $page);
        $this->assertStringNotContainsString(
            '{{ invoiceTypeLabels[modelType] || modelType }} — Cheques Coming Due',
            $page,
            'The cheque row fell back to the invoice labels.'
        );
    }

    /**
     * Each row still keeps its own data source — a rename must not have
     * been accompanied by pointing a chart at the other service.
     */
    public function test_each_row_still_plots_its_own_data(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('invoices_aging', $page, 'The Aging row reads invoice aging.');
        $this->assertStringContainsString('cheques_aging_for_chart', $page, 'The cheque row reads cheque aging.');
    }
}
