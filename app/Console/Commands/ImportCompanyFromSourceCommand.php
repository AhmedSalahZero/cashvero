<?php

namespace App\Console\Commands;

use App\Services\CompanyData\CompanyImportService;
use Illuminate\Console\Command;

/**
 * Cut over one company from the local mysql_source DB (veroanalysis /
 * system.veroanalysis.com) into this app's default DB (cash-vero).
 *
 * Row primary keys are remapped (auto-increment) on the target; foreign keys
 * are rewritten so relationships stay intact. company_id stays the same.
 *
 *   php artisan company:import-from-source {company_id} --dry-run
 *   php artisan company:import-from-source {company_id} --force
 */
class ImportCompanyFromSourceCommand extends Command
{
    protected $signature = 'company:import-from-source
        {company_id : Company id (same on source and target)}
        {--dry-run : Schema report, row counts, and collision check only — no writes}
        {--force : Required to wipe target company data and import}';

    protected $description = 'Import one company from local mysql_source (veroanalysis) into cash-vero with remapped PKs, wiping target company rows first';

    public function handle(CompanyImportService $importer): int
    {
        $companyId = (int) $this->argument('company_id');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Refusing to run without --dry-run or --force.');

            return self::FAILURE;
        }

        if ($dryRun && $force) {
            $this->warn('--dry-run takes precedence; no writes will happen.');
            $force = false;
        }

        $this->info(sprintf(
            'Company import  id=%d  source=%s  target=%s  mode=%s  pk=remap',
            $companyId,
            $importer->sourceConnection(),
            $importer->targetConnection(),
            $dryRun ? 'dry-run' : 'force'
        ));

        $result = $importer->import($companyId, $dryRun);
        $report = $result['report'];
        $analysis = $report['analysis'];

        $this->line("Source DB: {$analysis['source_db']}");
        $this->line("Target DB: {$analysis['target_db']}");
        $this->line('Intersection tables: '.count($analysis['intersection']));
        $this->line('Source-only (skipped): '.count($analysis['source_only']));
        $this->line('Target-only (untouched): '.count($analysis['target_only']));

        if (! $analysis['source_company']) {
            $this->error("Company {$companyId} not found on source.");

            return self::FAILURE;
        }

        $this->line('Source company present: yes');
        $this->line('Target company present: '.($analysis['target_company'] ? 'yes' : 'no'));

        if (count($analysis['source_only'])) {
            $this->warn('Source-only tables (not copied): '.implode(', ', array_slice($analysis['source_only'], 0, 20))
                .(count($analysis['source_only']) > 20 ? ' …' : ''));
        }

        $totalSourceRows = array_sum($analysis['source_counts']);
        $totalTargetRows = array_sum($analysis['target_counts']);
        $this->line("Rows on source (intersection): {$totalSourceRows}");
        $this->line("Rows on target before import (intersection): {$totalTargetRows}");

        if (count($analysis['collisions'])) {
            $this->warn('PK collisions with other companies (informational — PKs will be remapped):');
            foreach (array_slice($analysis['collisions'], 0, 20) as $collision) {
                $this->line("  {$collision['table']} id={$collision['id']} other_company_id={$collision['other_company_id']}");
            }
            if (count($analysis['collisions']) >= 20) {
                $this->line('  …');
            }
        } else {
            $this->info('Collision preflight: none (remap mode still assigns new PKs)');
        }

        if ($dryRun) {
            $this->info('Dry-run complete — no writes.');

            return self::SUCCESS;
        }

        if (count($report['errors'])) {
            foreach ($report['errors'] as $error) {
                $this->error($error);
            }
        }

        $copiedRows = 0;
        foreach ($report['copied'] as $table => $count) {
            if (is_int($count)) {
                $copiedRows += $count;
            }
        }
        $this->line("Copied company_id rows: {$copiedRows}");
        if (isset($report['triggers_suspended'])) {
            $this->line('Statement balance triggers suspended during import: '.$report['triggers_suspended']);
        }
        if (isset($report['users'])) {
            $this->line('Users: '.json_encode($report['users']));
        }
        if (isset($report['indirect'])) {
            $this->line('Indirect: '.json_encode($report['indirect']));
        }
        if (! empty($report['id_maps']) && is_array($report['id_maps'])) {
            $mapped = array_sum($report['id_maps']);
            $this->line("ID maps: {$mapped} keys across ".count($report['id_maps']).' tables');
            $top = $report['id_maps'];
            arsort($top);
            foreach (array_slice($top, 0, 10, true) as $table => $n) {
                $this->line("  {$table}: {$n}");
            }
        }

        $verification = $report['verification'] ?? null;
        if (is_array($verification)) {
            $this->line('Verification checks: '.($verification['checks'] ?? 0));
            if (! empty($verification['mismatches'])) {
                $this->error('Verification mismatches:');
                foreach (array_slice($verification['mismatches'], 0, 30) as $mismatch) {
                    $this->line('  '.json_encode($mismatch));
                }
            } else {
                $this->info('Verification: PASS');
            }
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
