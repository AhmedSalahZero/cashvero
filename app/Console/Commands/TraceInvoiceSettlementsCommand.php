<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * TraceInvoiceSettlementsCommand
 * ------------------------------------------------------------------
 * READ-ONLY. Given an invoice id, walks the full chain:
 *
 *   customer_invoices  →  settlements  →  money_received
 *
 * and prints every settlement tied to that invoice next to the Money
 * Received record that created it — so a corrupted invoice (see the
 * unformat_number() decimal-stripping bug, fixed 2026-08-15) can be
 * traced back to exactly which Money Received record is responsible,
 * without having to search for it by hand in the UI.
 *
 * Nothing is changed by this command.
 *
 * USAGE
 *   php artisan invoices:trace --id=3919
 */
class TraceInvoiceSettlementsCommand extends Command
{
    protected $signature = 'invoices:trace {--id= : customer_invoices.id to trace}';

    protected $description = 'READ-ONLY. Trace one invoice\'s settlements back to their Money Received records';

    public function handle(): int
    {
        $invoiceId = $this->option('id');

        if (! $invoiceId) {
            $this->error('Pass --id=<customer_invoices.id>, e.g. --id=3919');

            return self::FAILURE;
        }

        $invoice = DB::table('customer_invoices')->where('id', $invoiceId)->first();

        if (! $invoice) {
            $this->error("customer_invoices #{$invoiceId} not found.");

            return self::FAILURE;
        }

        $this->line('');
        $this->info("Invoice #{$invoice->id} — {$invoice->invoice_number}  (customer: {$invoice->customer_name}, id {$invoice->customer_id})");
        $this->table(
            ['invoice_amount', 'collected_amount', 'total_collected_amount', 'net_balance', 'currency'],
            [[
                $invoice->invoice_amount,
                $invoice->collected_amount,
                $invoice->total_collected_amount,
                $invoice->net_balance,
                $invoice->currency,
            ]]
        );

        $settlements = DB::table('settlements')->where('invoice_id', $invoiceId)->orderBy('id')->get();

        if ($settlements->isEmpty()) {
            $this->warn('No settlement rows found for this invoice — the corruption is not coming from a settlement on this invoice.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Settlements found for this invoice:');

        foreach ($settlements as $settlement) {
            $isSuspicious = is_numeric($settlement->settlement_amount)
                && (float) $settlement->settlement_amount > (float) ($invoice->invoice_amount ?: 0) * 100;

            $this->line('');
            $this->line(($isSuspicious ? '⚠️  SUSPICIOUS ' : '   ')."settlement #{$settlement->id}");
            $this->line("     settlement_amount: {$settlement->settlement_amount}");
            $this->line("     withhold_amount:   {$settlement->withhold_amount}");
            $this->line("     money_received_id: {$settlement->money_received_id}");
            $this->line("     created_at:        {$settlement->created_at}");
            $this->line("     updated_at:        {$settlement->updated_at}");

            if (! $settlement->money_received_id) {
                $this->warn('     (no money_received_id on this settlement — cannot trace further)');

                continue;
            }

            $moneyReceived = DB::table('money_received')->where('id', $settlement->money_received_id)->first();

            if (! $moneyReceived) {
                $this->warn("     money_received #{$settlement->money_received_id} no longer exists (deleted, but this settlement was left behind — orphaned row).");

                continue;
            }

            $this->line('     ── money_received #'.$moneyReceived->id.' ──');
            $this->line('       received_amount:   '.$moneyReceived->received_amount);
            $this->line('       amount_in_invoice_currency: '.$moneyReceived->amount_in_invoice_currency);
            $this->line('       currency / receiving_currency: '.$moneyReceived->currency.' / '.$moneyReceived->receiving_currency);
            $this->line('       type:              '.$moneyReceived->type);
            $this->line('       receiving_date:    '.$moneyReceived->receiving_date);
            $this->line('       created_at:        '.$moneyReceived->created_at);
            $this->line('       updated_at:        '.$moneyReceived->updated_at);
            $this->line('       comment_en:        '.$moneyReceived->comment_en);

            if ($isSuspicious) {
                $this->warn('     ^ This settlement_amount is wildly larger than the invoice_amount — most likely the corrupted one.');
                $this->warn('       Its money_received (#'.$moneyReceived->id.") currently shows received_amount = {$moneyReceived->received_amount} —");
                $this->warn('       if that looks correct/small, it means THIS settlement row is stale and was never corrected when the money_received was fixed.');
            }
        }

        $this->line('');

        return self::SUCCESS;
    }
}
