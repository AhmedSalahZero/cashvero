<?php

namespace App\Console\Commands;

use App\Services\CompanyData\CompanyImportService;
use Illuminate\Console\Command;

/**
 * Copy every row of one company into a newly created company, then replace
 * partner and invoice names on the copy only.
 *
 *   php artisan run:cloneExistingCompanyToANewOne 148 Demo
 */
class CloneExistingCompanyToANewOneCommand extends Command
{
    protected $signature = 'run:cloneExistingCompanyToANewOne
        {company_id : Company to copy}
        {name : Name of the new company}';

    protected $description = 'Clone a company into a new company and anonymize partner and invoice names on the copy';

    public function handle(CompanyImportService $importer): int
    {
        $companyId = (int) $this->argument('company_id');
        $name = (string) $this->argument('name');

        $this->info("Cloning company {$companyId} into \"{$name}\".");

        $result = $importer->cloneToNewCompany($companyId, $name);
        $report = $result['report'];

        foreach ($report['errors'] ?? [] as $error) {
            $this->error($error);
        }

        $verification = $report['verification'] ?? null;
        if (is_array($verification) && ! empty($verification['mismatches'])) {
            $this->error('Verification mismatches:');
            foreach (array_slice($verification['mismatches'], 0, 30) as $mismatch) {
                $this->line('  '.json_encode($mismatch));
            }
        }

        if (! $result['ok']) {
            return self::FAILURE;
        }

        $copied = 0;
        foreach ($report['copied'] ?? [] as $count) {
            if (is_int($count)) {
                $copied += $count;
            }
        }
        $this->line('Copied rows: '.$copied);
        $this->info('New company id: '.$result['new_company_id']);

        return self::SUCCESS;
    }
}
