<?php

namespace App\Console\Commands;

use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ReportSettlementsWithoutInvoiceCommand
 * ------------------------------------------------------------------
 * Lists settlement rows that carry a real amount but no usable invoice.
 *
 * Two shapes, same symptom in the Settlement Details popup — a line
 * reading "No Invoice Linked … —" next to a real settlement amount:
 *
 *   1. invoice_id IS NULL — the row was stored without an invoice at all.
 *      storeNewSettlement() now refuses this, so no new ones can appear;
 *      the rows already on file were written by the older code.
 *
 *   2. invoice_id points at an invoice that has since been deleted.
 *      There is no foreign key on settlements.invoice_id in either
 *      system, so deleting an invoice leaves its settlements behind.
 *
 * REPORT ONLY — this command never writes anything. What each row should
 * be re-pointed at (or whether it should be removed) is an accounting
 * decision, not something to guess at: the amounts are real money that
 * has already been counted against a partner's balance.
 *
 * USAGE
 *     php artisan settlements:report-without-invoice
 *     php artisan settlements:report-without-invoice --company=92
 *     php artisan settlements:report-without-invoice --csv=/tmp/rows.csv
 */
class ReportSettlementsWithoutInvoiceCommand extends Command
{
    protected $signature = 'settlements:report-without-invoice
                            {--company= : limit to this company id}
                            {--csv= : also write the full list to this file}';

    protected $description = 'Reports settlement rows whose invoice is missing or was never linked (read-only)';

    public function handle(): int
    {
        $this->info('READ ONLY — this command does not change anything.');
        $this->newLine();

        $sides = [
            [
                'label' => 'MoneyReceived',
                'table' => 'settlements',
                'money' => 'money_received',
                'fk' => 'money_received_id',
                'invoices' => 'customer_invoices',
                'model' => MoneyReceived::class,
            ],
            [
                'label' => 'MoneyPayment',
                'table' => 'payment_settlements',
                'money' => 'money_payments',
                'fk' => 'money_payment_id',
                'invoices' => 'supplier_invoices',
                'model' => MoneyPayment::class,
            ],
        ];

        $all = [];
        $grandTotal = 0.0;

        foreach ($sides as $side) {
            $rows = $this->rowsFor($side);

            if ($rows === []) {
                $this->info($side['label'].': nothing to report.');

                continue;
            }

            $total = array_sum(array_column($rows, 'settlement_amount'));
            $grandTotal += $total;

            $this->warn($side['label'].': '.count($rows).' settlement row(s), total '.number_format($total, 2));

            $this->table(
                ['settlement', $side['label'], 'company', 'amount', 'currency', 'invoice_id', 'why', 'created'],
                array_map(fn ($r) => [
                    $r['id'],
                    '#'.$r['money_id'],
                    $r['company_id'],
                    number_format((float) $r['settlement_amount'], 2),
                    $r['currency'],
                    $r['invoice_id'] ?? 'NULL',
                    $r['reason'],
                    substr((string) $r['created_at'], 0, 10),
                ], array_slice($rows, 0, 40))
            );

            if (count($rows) > 40) {
                $this->line('  … '.(count($rows) - 40).' more (use --csv to get the full list)');
            }

            foreach ($rows as $r) {
                $all[] = ['side' => $side['label']] + $r;
            }

            $this->newLine();
        }

        if ($all === []) {
            $this->info('Nothing to report — every settlement points at an invoice that exists.');

            return self::SUCCESS;
        }

        $this->warn('Total across both sides: '.count($all).' row(s), '.number_format($grandTotal, 2));
        $this->line('These amounts already count against the partner balances, so nothing here should be');
        $this->line('deleted or re-pointed without an accountant deciding what each one belongs to.');

        if ($path = $this->option('csv')) {
            $this->writeCsv($path, $all);
            $this->newLine();
            $this->info('Full list written to '.$path);
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function rowsFor(array $side): array
    {
        $query = DB::table($side['table'].' as s')
            ->join($side['money'].' as m', 'm.id', '=', 's.'.$side['fk'])
            ->leftJoin($side['invoices'].' as i', 'i.id', '=', 's.invoice_id')
            ->whereRaw('coalesce(s.settlement_amount, 0) <> 0')
            ->where(function ($q) {
                $q->whereNull('s.invoice_id')->orWhereNull('i.id');
            })
            ->select([
                's.id',
                's.'.$side['fk'].' as money_id',
                's.company_id',
                's.settlement_amount',
                's.invoice_id',
                's.created_at',
                'm.currency',
                'm.money_type',
            ])
            ->orderBy('s.id');

        if ($company = $this->option('company')) {
            $query->where('s.company_id', $company);
        }

        return $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'money_id' => $r->money_id,
            'company_id' => $r->company_id,
            'settlement_amount' => (float) $r->settlement_amount,
            'invoice_id' => $r->invoice_id,
            'currency' => $r->currency,
            'money_type' => $r->money_type,
            'created_at' => $r->created_at,
            'reason' => $r->invoice_id === null ? 'never linked' : 'invoice deleted',
        ])->all();
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');

        fputcsv($handle, ['side', 'settlement_id', 'money_id', 'money_type', 'company_id',
            'settlement_amount', 'currency', 'invoice_id', 'reason', 'created_at']);

        foreach ($rows as $r) {
            fputcsv($handle, [
                $r['side'], $r['id'], $r['money_id'], $r['money_type'], $r['company_id'],
                $r['settlement_amount'], $r['currency'], $r['invoice_id'] ?? '', $r['reason'], $r['created_at'],
            ]);
        }

        fclose($handle);
    }
}
