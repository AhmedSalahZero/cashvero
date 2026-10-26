<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SyncInvoicePartnerNamesCommand
 * ------------------------------------------------------------------
 * Copies partners.name onto the denormalized name stored on invoices.
 *
 * customer_invoices.customer_name and supplier_invoices.supplier_name
 * are written when the invoice is saved. Renaming the partner later
 * does not walk those rows, so lists and reports keep showing the old
 * name. This command makes the stored invoice name match the partner
 * the invoice already points at.
 *
 * WHICH ROWS
 * An invoice is updated only when its partner still exists, that
 * partner has a name, and the invoice name is different (including a
 * null invoice name). The partner id on the invoice is the link; the
 * command does not guess a partner from the old text.
 *
 * An invoice whose partner is missing, or whose partner has no name,
 * is reported and left as it is. Blanking a name that is still on the
 * invoice would hide who it belonged to.
 *
 * USAGE
 *   php artisan invoices:sync-partner-names                 # report only
 *   php artisan invoices:sync-partner-names --company=148
 *   php artisan invoices:sync-partner-names --fix           # apply
 */
class SyncInvoicePartnerNamesCommand extends Command
{
    protected $signature = 'invoices:sync-partner-names
        {--fix : Write partners.name onto the invoice}
        {--company= : Restrict to invoices of this company id}
        {--samples=10 : How many sample rows to print per table}';

    protected $description = 'Set customer and supplier invoice names from the current partners.name';

    /** table => [partner id column, denormalized name column] */
    private const TABLES = [
        'customer_invoices' => ['customer_id', 'customer_name'],
        'supplier_invoices' => ['supplier_id', 'supplier_name'],
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $companyId = $this->option('company');
        $samples = (int) $this->option('samples');

        $this->line('');
        $this->info($fix
            ? 'MODE: FIX (invoice names will be written from partners.name)'
            : 'MODE: REPORT ONLY (nothing will be changed — re-run with --fix to apply)');
        $this->line('');

        $summary = [];
        $totalFixable = 0;
        $totalNeedsReview = 0;

        foreach (self::TABLES as $table => [$partnerColumn, $nameColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $nameColumn)) {
                continue;
            }

            $fixable = $this->fixableQuery($table, $partnerColumn, $nameColumn, $companyId);
            $needsReview = $this->needsReviewQuery($table, $partnerColumn, $nameColumn, $companyId);

            $fixableCount = (clone $fixable)->count();
            $reviewCount = (clone $needsReview)->count();

            if ($fixableCount === 0 && $reviewCount === 0) {
                continue;
            }

            $totalFixable += $fixableCount;
            $totalNeedsReview += $reviewCount;
            $summary[] = [$table, $fixableCount, $reviewCount];

            if ($samples > 0 && $fixableCount > 0) {
                $this->warn("{$table}: {$fixableCount} invoice(s) whose name differs from the partner");
                foreach ((clone $fixable)->limit($samples)->get([
                    $table.'.id',
                    $table.'.'.$nameColumn.' as invoice_name',
                    'partners.name as partner_name',
                    $table.'.'.$partnerColumn,
                ]) as $row) {
                    $this->line('    '.json_encode($row, JSON_UNESCAPED_UNICODE));
                }
            }

            if ($reviewCount > 0) {
                $this->error("{$table}: {$reviewCount} invoice(s) have no usable partner name — left untouched");
                foreach ((clone $needsReview)->limit($samples)->get([
                    'id', $partnerColumn, $nameColumn,
                ]) as $row) {
                    $this->line('    '.json_encode($row, JSON_UNESCAPED_UNICODE));
                }
            }

            if ($fix && $fixableCount > 0) {
                $payload = [$table.'.'.$nameColumn => DB::raw('partners.name')];
                if (Schema::hasColumn($table, 'updated_at')) {
                    $payload[$table.'.updated_at'] = now();
                }
                $updated = DB::transaction(fn () => (clone $fixable)->update($payload));
                $this->info("    updated {$updated} invoice name(s)");
            }
        }

        $this->line('');

        if ($summary === []) {
            $this->info('Every invoice name already matches its partner. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(['table', 'to update', 'needs manual review'], $summary);
        $this->line("TOTAL to update: {$totalFixable} | needing review: {$totalNeedsReview}");

        if (! $fix && $totalFixable > 0) {
            $this->line('');
            $this->comment('Re-run with --fix to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Invoices whose partner exists, has a name, and that name is not
     * what the invoice currently stores. Joined so the UPDATE can copy
     * partners.name in one statement.
     */
    private function fixableQuery(string $table, string $partnerColumn, string $nameColumn, $companyId)
    {
        $query = DB::table($table)
            ->join('partners', 'partners.id', '=', $table.'.'.$partnerColumn)
            ->whereNotNull('partners.name')
            ->where(function ($query) use ($table, $nameColumn) {
                $query->whereColumn($table.'.'.$nameColumn, '!=', 'partners.name')
                    ->orWhereNull($table.'.'.$nameColumn);
            });

        if ($companyId) {
            $query->where($table.'.company_id', $companyId);
        }

        return $query;
    }

    /**
     * Invoices this command must not rename: the partner row is gone,
     * or it exists but has no name while the invoice still shows one.
     */
    private function needsReviewQuery(string $table, string $partnerColumn, string $nameColumn, $companyId)
    {
        $query = DB::table($table)
            ->where(function ($query) use ($table, $partnerColumn, $nameColumn) {
                $query->whereNotExists(function ($sub) use ($table, $partnerColumn) {
                    $sub->select(DB::raw(1))
                        ->from('partners')
                        ->whereColumn('partners.id', $table.'.'.$partnerColumn);
                })->orWhereExists(function ($sub) use ($table, $partnerColumn, $nameColumn) {
                    $sub->select(DB::raw(1))
                        ->from('partners')
                        ->whereColumn('partners.id', $table.'.'.$partnerColumn)
                        ->whereNull('partners.name')
                        ->whereNotNull($table.'.'.$nameColumn);
                });
            });

        if ($companyId) {
            $query->where($table.'.company_id', $companyId);
        }

        return $query;
    }
}
