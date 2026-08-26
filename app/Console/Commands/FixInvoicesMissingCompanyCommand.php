<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FixInvoicesMissingCompanyCommand
 * ------------------------------------------------------------------
 * Backfills company_id on invoices saved without one.
 *
 * SalesGatheringTestController::storeModel()/updateModel() never set
 * company_id on a Customer/Supplier Invoice created through the
 * single-invoice form at /create-item/{model} — only the LoanSchedule
 * branches beside them did. The row landed with company_id = 0, and
 * every screen that scopes by company then denied it existed:
 *
 *   /uploading/{model}   where('company_id', …)  → invoice missing
 *   Invoice Report       where('company_id', …)  → "No Data Found"
 *   Statement Report     where('company_id', …)  → invoice missing
 *
 * while Customers/Suppliers Balances kept showing it, because that
 * page joins on partners.company_id and never filters the invoice's
 * own — which is why the balance looked right while the invoice was
 * unreachable everywhere else.
 *
 * The controller is fixed, so no NEW row can land this way. This
 * command repairs the ones already written.
 *
 * WHICH ROWS
 * Only invoices with no company (0 or NULL) whose partner
 * (customer_id / supplier_id) still exists and itself belongs to a
 * real company. Partners are company-scoped, so the partner is the
 * only trustworthy evidence of where the invoice belongs.
 *
 * Anything else — no partner, or a partner with no company of its own
 * — is REPORTED and never touched: guessing a company for a financial
 * record is worse than leaving it visibly broken.
 *
 * The Excel upload path (SalesGatheringTestJob) always set company_id
 * and never produced these rows, so a backfill cannot disturb it.
 *
 * USAGE
 *   php artisan invoices:fix-missing-company              # report only
 *   php artisan invoices:fix-missing-company --company=92
 *   php artisan invoices:fix-missing-company --fix        # apply
 */
class FixInvoicesMissingCompanyCommand extends Command
{
    protected $signature = 'invoices:fix-missing-company
        {--fix : Apply the backfill instead of only reporting it}
        {--company= : Restrict to invoices whose partner belongs to this company}
        {--samples=10 : How many sample rows to print per table}';

    protected $description = 'Backfill company_id on Customer/Supplier invoices saved without one by the single-invoice form';

    /** table => the column holding its partner id */
    private const TABLES = [
        'customer_invoices' => 'customer_id',
        'supplier_invoices' => 'supplier_id',
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $companyId = $this->option('company');
        $samples = (int) $this->option('samples');

        $this->line('');
        $this->info($fix
            ? 'MODE: FIX (company_id will be written)'
            : 'MODE: REPORT ONLY (nothing will be changed — re-run with --fix to apply)');
        $this->line('');

        $summary = [];
        $totalFixable = 0;
        $totalNeedsReview = 0;

        foreach (self::TABLES as $table => $partnerColumn) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $fixable = $this->fixableQuery($table, $partnerColumn, $companyId);
            $needsReview = $this->needsReviewQuery($table, $partnerColumn);

            $fixableCount = (clone $fixable)->count();
            $reviewCount = $companyId ? 0 : (clone $needsReview)->count();

            if ($fixableCount === 0 && $reviewCount === 0) {
                continue;
            }

            $totalFixable += $fixableCount;
            $totalNeedsReview += $reviewCount;
            $summary[] = [$table, $fixableCount, $reviewCount];

            if ($samples > 0 && $fixableCount > 0) {
                $this->warn("{$table}: {$fixableCount} invoice(s) to backfill");
                foreach ((clone $fixable)->limit($samples)
                    ->get([
                        $table.'.id', $table.'.invoice_number', $table.'.currency',
                        $table.'.net_invoice_amount', 'partners.name as partner_name',
                        'partners.company_id as target_company_id',
                    ]) as $row) {
                    $this->line('    '.json_encode($row, JSON_UNESCAPED_UNICODE));
                }
            }

            if ($reviewCount > 0) {
                $this->error("{$table}: {$reviewCount} invoice(s) have no usable partner — left untouched, check them by hand");
                foreach ((clone $needsReview)->limit($samples)
                    ->get(['id', 'invoice_number', $partnerColumn, 'currency', 'net_invoice_amount']) as $row) {
                    $this->line('    '.json_encode($row, JSON_UNESCAPED_UNICODE));
                }
            }

            if ($fix && $fixableCount > 0) {
                $updated = DB::transaction(fn () => (clone $fixable)
                    ->update([$table.'.company_id' => DB::raw('partners.company_id')]));
                $this->info("    backfilled company_id on {$updated} invoice(s)");
            }
        }

        $this->line('');

        if ($summary === []) {
            $this->info('No invoices are missing a company. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(['table', 'to backfill', 'needs manual review'], $summary);
        $this->line("TOTAL to backfill: {$totalFixable} | needing review: {$totalNeedsReview}");

        if (! $fix && $totalFixable > 0) {
            $this->line('');
            $this->comment('Re-run with --fix to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Invoices with no company whose partner exists and has one.
     * Joined (not a subquery) so the UPDATE can copy the company across
     * in a single statement.
     */
    private function fixableQuery(string $table, string $partnerColumn, $companyId)
    {
        $query = DB::table($table)
            ->join('partners', 'partners.id', '=', $table.'.'.$partnerColumn)
            ->where(fn ($q) => $q->where($table.'.company_id', 0)->orWhereNull($table.'.company_id'))
            ->whereNotNull('partners.company_id')
            ->where('partners.company_id', '!=', 0);

        if ($companyId) {
            $query->where('partners.company_id', $companyId);
        }

        return $query;
    }

    /** Invoices with no company that this command must NOT guess for. */
    private function needsReviewQuery(string $table, string $partnerColumn)
    {
        return DB::table($table)
            ->where(fn ($q) => $q->where('company_id', 0)->orWhereNull('company_id'))
            ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))->from('partners')
                ->whereColumn('partners.id', $table.'.'.$partnerColumn)
                ->whereNotNull('partners.company_id')
                ->where('partners.company_id', '!=', 0));
    }
}
