<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FixPartnerStatementOwnerCommand
 * ------------------------------------------------------------------
 * Clears the bogus money_received_id that MoneyPayment used to write on
 * partner statement rows.
 *
 * MoneyPayment::employeeStatement() (and the shareholder / subsidiary /
 * other-partner / tax equivalents) were declared with the foreign key
 * 'money_received_id' instead of 'money_payment_id'. So creating a
 * partner statement from a money payment wrote:
 *
 *     money_payment_id  = <payment id>   (from handlePartnerDebitStatement)
 *     money_received_id = <payment id>   (from the wrong relation key)
 *
 * Both columns end up holding the same number, and the row then looks
 * like it belongs to a money received that usually does not exist — or,
 * worse, to a *different* money received that happens to share the id.
 * That second case is a live data-loss risk: deleting money received
 * #223 would go looking for money_received_id = 223 and find the money
 * payment's statement row.
 *
 * The relation keys are fixed in the model (the broken overrides were
 * removed so the correct ones in HasPartnerStatement apply). This
 * command repairs the rows already written.
 *
 * WHICH ROWS
 * Only rows where money_payment_id and money_received_id are both set
 * AND equal AND that money payment still exists.
 *
 * money_payment_id is written in exactly one place —
 * HasPartnerStatement::handlePartnerDebitStatement() — and that method
 * is only ever called by MoneyPayment. The MoneyReceived side goes
 * through handlePartnerCreditStatement(), which writes money_received_id
 * only. So a row carrying money_payment_id at all was created from a
 * money payment, and when the two columns match it is the wrong-key bug.
 * Requiring the payment to still exist is the extra proof that the id
 * really is a payment id and not something historical.
 *
 * Rows where the two ids match but no such payment exists are reported
 * for manual review and never touched — they might belong to a money
 * received that happens to share the number.
 *
 * USAGE
 *   php artisan money:fix-partner-statement-owner            # report only
 *   php artisan money:fix-partner-statement-owner --company=92
 *   php artisan money:fix-partner-statement-owner --fix      # apply
 */
class FixPartnerStatementOwnerCommand extends Command
{
    protected $signature = 'money:fix-partner-statement-owner
        {--fix : Apply the correction instead of only reporting it}
        {--company= : Restrict to a single company id}
        {--samples=5 : How many sample rows to print per table}';

    protected $description = 'Clear the bogus money_received_id written on partner statement rows that actually belong to a money payment';

    private const TABLES = [
        'employee_statements',
        'shareholder_statements',
        'subsidiary_company_statements',
        'other_partner_statements',
        'tax_statements',
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $companyId = $this->option('company');
        $samples = (int) $this->option('samples');

        $this->line('');
        $this->info($fix ? 'MODE: FIX (money_received_id will be cleared)' : 'MODE: REPORT ONLY (nothing will be changed)');
        $this->line('');

        $rows = [];
        $totalFixable = 0;
        $totalAmbiguous = 0;

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $fixable = $this->query($table, $companyId)
                ->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('money_payments')
                    ->whereColumn('money_payments.id', $table.'.money_payment_id'));

            $ambiguous = $this->query($table, $companyId)
                ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))->from('money_payments')
                    ->whereColumn('money_payments.id', $table.'.money_payment_id'));

            $fixableCount = (clone $fixable)->count();
            $ambiguousCount = (clone $ambiguous)->count();

            if ($fixableCount === 0 && $ambiguousCount === 0) {
                continue;
            }

            $totalFixable += $fixableCount;
            $totalAmbiguous += $ambiguousCount;
            $rows[] = [$table, $fixableCount, $ambiguousCount];

            if ($samples > 0 && $fixableCount > 0) {
                $this->warn("{$table}: {$fixableCount} row(s) to correct");
                foreach ((clone $fixable)->limit($samples)->get(['id', 'company_id', 'date', 'debit', 'credit', 'money_payment_id', 'money_received_id']) as $row) {
                    $this->line('    '.json_encode($row, JSON_UNESCAPED_UNICODE));
                }
            }

            if ($ambiguousCount > 0) {
                $this->error("{$table}: {$ambiguousCount} row(s) have both ids equal but that money payment no longer exists — left untouched, check them by hand");
                foreach ((clone $ambiguous)->limit($samples)->get(['id', 'company_id', 'date', 'debit', 'credit', 'money_payment_id', 'money_received_id']) as $row) {
                    $this->line('    '.json_encode($row, JSON_UNESCAPED_UNICODE));
                }
            }

            if ($fix && $fixableCount > 0) {
                $updated = (clone $fixable)->update(['money_received_id' => 0]);
                $this->info("    cleared money_received_id on {$updated} row(s)");
            }
        }

        $this->line('');

        if ($rows === []) {
            $this->info('Nothing to correct.');

            return self::SUCCESS;
        }

        $this->table(['table', 'to correct', 'needs manual review'], $rows);
        $this->line("TOTAL to correct: {$totalFixable} | needing review: {$totalAmbiguous}");

        if (! $fix) {
            $this->line('');
            $this->warn('Nothing was changed. Re-run with --fix to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Rows carrying the same id in both owner columns — the signature of
     * the wrong-foreign-key bug.
     */
    private function query(string $table, ?string $companyId)
    {
        $query = DB::table($table)
            ->whereNotNull('money_payment_id')->where('money_payment_id', '!=', 0)
            ->whereNotNull('money_received_id')->where('money_received_id', '!=', 0)
            ->whereColumn('money_payment_id', 'money_received_id');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }
}
