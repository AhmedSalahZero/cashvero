<?php

/**
 * Re-runnable parity check for a company import from mysql_source → default DB.
 *
 * Run: php scripts/verify_company_import.php {company_id}
 */

use App\Services\CompanyData\CompanyImportService;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$companyId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($companyId <= 0) {
    fwrite(STDERR, "Usage: php scripts/verify_company_import.php {company_id}\n");
    exit(1);
}

$importer = new CompanyImportService();
$analysis = $importer->analyze($companyId);

echo "Company {$companyId}\n";
echo "Source: {$analysis['source_db']}  Target: {$analysis['target_db']}\n";
echo 'Intersection tables: '.count($analysis['intersection'])."\n";
echo 'Collisions: '.count($analysis['collisions'])."\n";

if (! $analysis['source_company']) {
    echo "FAIL: company missing on source\n";
    exit(1);
}

if (count($analysis['collisions'])) {
    echo "FAIL: PK collisions present\n";
    foreach (array_slice($analysis['collisions'], 0, 20) as $c) {
        echo "  {$c['table']} id={$c['id']} other={$c['other_company_id']}\n";
    }
    exit(1);
}

$verification = $importer->verify($companyId, $analysis['intersection']);
echo 'Checks: '.$verification['checks']."\n";

if (! $verification['ok']) {
    echo "FAIL: mismatches\n";
    foreach ($verification['mismatches'] as $m) {
        echo '  '.json_encode($m)."\n";
    }
    exit(1);
}

echo "PASS\n";
exit(0);
