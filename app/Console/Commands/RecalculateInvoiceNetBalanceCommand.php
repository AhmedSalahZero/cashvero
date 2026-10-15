<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RecalculateInvoiceNetBalanceCommand
 * ------------------------------------------------------------------
 * The 2026-08-15 fix rounds net_balance to 2 decimals inside the
 * customer_invoices insert/update triggers (customer_invoices_triggers.sql),
 * matching what supplier_invoices_triggers.sql already did. That fix only
 * takes effect the next time a row is actually written — every invoice
 * already sitting in the database keeps its old, unrounded net_balance
 * (and whatever floating-point noise came with it) until something
 * updates it again.
 *
 * This command doesn't compute anything itself — it just touches
 * updated_at on each row inside a real UPDATE statement, which makes the
 * (now-fixed) BEFORE UPDATE trigger recompute net_invoice_amount,
 * total_collected_amount, net_balance, and invoice_status from that
 * row's own current values, cleanly and rounded. Same technique already
 * used by RepairStatementBalancesCommand for the statement tables.
 *
 * ⚠️ php artisan run:sql must be run FIRST so the rounded trigger is
 *    actually installed — this command checks and refuses to run
 *    otherwise.
 *
 * ⚠️ IMPORTANT LIMITATION: this only fixes ROUNDING noise. It cannot fix
 *    an invoice whose numbers are wrong because of bad underlying data
 *    (e.g. invoice #3919 — a settlement with a corrupted amount still
 *    attached to it). Recalculating that invoice will still recompute
 *    everything correctly EXCEPT it will still be wrong, because the
 *    bad settlement itself hasn't been removed yet. Use
 *    invoices:trace --id=<id> to deal with those first — see that
 *    command's own docblock.
 *
 * USAGE
 *   php artisan invoices:recalculate-net-balance                 # report only (default, no writes)
 *   php artisan invoices:recalculate-net-balance --fix            # actually recalculate
 *   php artisan invoices:recalculate-net-balance --company=92 --fix
 *   php artisan invoices:recalculate-net-balance --id=3919 --fix  # a single invoice
 */
class RecalculateInvoiceNetBalanceCommand extends Command
{
    protected $signature = 'invoices:recalculate-net-balance
        {--fix : Actually recalculate. Without this, only reports what would change.}
        {--company= : Restrict to a single company id}
        {--id= : Restrict to a single customer_invoices id}
        {--samples=5 : How many sample rows to print}
        {--skip-trigger-check : Skip the check that the rounded trigger is installed (diagnostic only)}';

    protected $description = 'Recalculate net_balance (and related fields) on existing customer invoices so the rounding fix applies to rows written before it was deployed';

    /**
     * Bug fix (client-flagged, confirmed 2026-08-15, second pass): the
     * original epsilon check here (> 0.000001) missed rows with large
     * integer parts — a DOUBLE's binary rounding error grows with
     * magnitude, so a value like 20518686.720000003 has an absolute
     * error around 0.000000003, comfortably under that epsilon, even
     * though it's clearly not 2-decimal-clean. A REGEXP check on the
     * stored string itself has no such blind spot: it flags ANY
     * net_balance with more than 2 digits after the decimal point,
     * regardless of how large the number is.
     */
    private const NOISY_NET_BALANCE_WHERE = "net_balance NOT REGEXP '^-?[0-9]+(\\\\.[0-9]{1,2})?$'";

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $companyId = $this->option('company');
        $invoiceId = $this->option('id');
        $samples = (int) $this->option('samples');

        if ($fix && ! $this->option('skip-trigger-check') && ! $this->assertRoundedTriggerInstalled()) {
            return self::FAILURE;
        }

        $this->line('');
        $this->info($fix ? 'MODE: FIX (rows will be recalculated)' : 'MODE: REPORT ONLY (nothing will be changed)');
        $this->line('');

        $query = DB::table('customer_invoices');
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        $total = (clone $query)->count();

        // Rows where the *stored* net_balance already differs from what
        // rounding it to 2 decimals would give — the population this
        // command actually needs to touch. Cast through decimal since
        // net_balance is stored as varchar.
        $noisy = (clone $query)
            ->whereRaw(self::NOISY_NET_BALANCE_WHERE)
            ->count();

        $this->line("Invoices in scope: {$total}");
        $this->line("Invoices with unrounded net_balance (floating-point noise): {$noisy}");

        if ($noisy === 0) {
            $this->info('Nothing to recalculate.');

            return self::SUCCESS;
        }

        if ($samples > 0) {
            $this->line('');
            $this->line('Sample affected rows:');
            $rows = (clone $query)
                ->whereRaw(self::NOISY_NET_BALANCE_WHERE)
                ->limit($samples)
                ->get(['id', 'invoice_number', 'customer_name', 'net_balance', 'invoice_status']);
            $this->table(['id', 'invoice #', 'customer', 'net_balance (current)', 'status'], $rows->map(fn ($r) => [
                $r->id, $r->invoice_number, $r->customer_name, $r->net_balance, $r->invoice_status,
            ]));
        }

        if (! $fix) {
            $this->line('');
            $this->warn('Nothing was changed. Re-run with --fix to recalculate these rows.');

            return self::SUCCESS;
        }

        $this->line('');
        $ids = (clone $query)
            ->whereRaw(self::NOISY_NET_BALANCE_WHERE)
            ->pluck('id');

        $bar = $this->output->createProgressBar($ids->count());
        $bar->start();
        foreach ($ids as $id) {
            DB::table('customer_invoices')->where('id', $id)->update(['updated_at' => now()]);
            $bar->advance();
        }
        $bar->finish();
        $this->line('');

        $stillNoisy = (clone $query)
            ->whereRaw(self::NOISY_NET_BALANCE_WHERE)
            ->count();

        $this->line('');
        if ($stillNoisy > 0) {
            $this->warn("{$stillNoisy} row(s) still show unrounded net_balance — likely rows with genuinely bad underlying data (see the limitation note in this command's docblock), not just rounding noise.");
        } else {
            $this->info('✔ All net_balance values are now rounded to 2 decimals.');
        }

        return self::SUCCESS;
    }

    private function assertRoundedTriggerInstalled(): bool
    {
        $body = DB::table('information_schema.triggers')
            ->where('trigger_schema', DB::getDatabaseName())
            ->where('event_object_table', 'customer_invoices')
            ->where('action_timing', 'BEFORE')
            ->where('event_manipulation', 'UPDATE')
            ->value('action_statement');

        if ($body === null) {
            $this->error('No BEFORE UPDATE trigger found on customer_invoices — has it ever been installed?');

            return false;
        }

        if (! str_contains(strtolower((string) $body), 'cast(round(')) {
            $this->error('The installed trigger rounds net_balance but does not CAST it to an exact decimal — it will still leave binary floating-point noise on larger amounts (e.g. 20518686.720000003 instead of 20518686.72).');
            $this->warn('Run: php artisan run:sql   (after deploying the latest customer_invoices_triggers.sql, which wraps net_balance in CAST(... AS DECIMAL(14,2)))');

            return false;
        }

        $this->info('✔ Rounded trigger is installed.');

        return true;
    }
}
