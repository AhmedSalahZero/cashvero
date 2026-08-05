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
echo 'Target-only (untouched): '.count($analysis['target_only'])."\n";
if (count($analysis['target_only'])) {
    echo '  '.implode(', ', $analysis['target_only'])."\n";
}
echo 'Collisions: '.count($analysis['collisions'])."\n";

if (! $analysis['source_company']) {
    echo "FAIL: company missing on source\n";
    exit(1);
}

if (count($analysis['collisions'])) {
    echo "WARN: PK collisions present (informational — import remaps PKs)\n";
    foreach (array_slice($analysis['collisions'], 0, 20) as $c) {
        echo "  {$c['table']} id={$c['id']} other={$c['other_company_id']}\n";
    }
}

$preflight = $importer->preflight($companyId, $analysis['intersection']);
echo 'Preflight: '.($preflight['ok'] ? 'PASS' : 'FAIL')."\n";
if (! $preflight['ok']) {
    foreach ($preflight['errors'] as $error) {
        echo "  ERROR: {$error}\n";
    }
}
if (count($preflight['order_violations'])) {
    echo 'Order violations: '.count($preflight['order_violations'])."\n";
}
if (count($preflight['unmapped_local_ids'])) {
    echo "Unmapped local IDs:\n";
    foreach ($preflight['unmapped_local_ids'] as $u) {
        echo "  {$u['table']}.{$u['column']} rows={$u['rows']}\n";
    }
}
if (count($preflight['cycle_breaks'])) {
    echo 'Cycle breaks: '.implode(', ', $preflight['cycle_breaks'])."\n";
}

$fk = $importer->checkForeignKeyIntegrity($companyId, $analysis['intersection']);
echo 'FK integrity checks: '.$fk['checks']."\n";
$strictFails = 0;
if (count($fk['orphans'])) {
    echo "FK orphans (target vs source):\n";
    foreach ($fk['orphans'] as $o) {
        $worse = (((int) $o['target_orphans']) + ((int) ($o['target_cross_company'] ?? 0)))
            > (((int) $o['source_orphans']) + ((int) ($o['source_cross_company'] ?? 0)));
        if ($worse) {
            $strictFails++;
        }
        echo sprintf(
            "  %s.%s -> %s  total=%d  target_orphans=%d  source_orphans=%d  target_cross=%d  source_cross=%d%s\n",
            $o['table'],
            $o['column'],
            $o['parent'],
            $o['total'],
            $o['target_orphans'],
            $o['source_orphans'],
            $o['target_cross_company'] ?? 0,
            $o['source_cross_company'] ?? 0,
            $worse ? '  [FAIL]' : ''
        );
    }
} else {
    echo "FK orphans: none\n";
}

$verification = $importer->verify($companyId, $analysis['intersection']);
echo 'Checks: '.$verification['checks']."\n";

if (! $verification['ok'] || ! $preflight['ok']) {
    echo "FAIL: mismatches\n";
    foreach ($verification['mismatches'] as $m) {
        echo '  '.json_encode($m)."\n";
    }
    exit(1);
}

echo "PASS\n";
exit(0);
