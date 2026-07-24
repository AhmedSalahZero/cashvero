<?php

/**
 * Regenerates database/seeders/data/cash_vero_general.php from the cash_vero connection.
 * Run only when the cash-vero source DB is available:
 *
 *   php scripts/export-cash-vero-general-seeder-data.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = DB::connection('cash_vero');
$userId = 1;

$data = [];

foreach (['account_types', 'banks', 'currencies', 'permissions', 'roles', 'role_has_permissions', 'sections'] as $table) {
    $data[$table] = $source->table($table)->get()->map(fn ($row) => (array) $row)->all();
}

$data['users'] = $source->table('users')->where('id', $userId)->get()->map(fn ($row) => (array) $row)->all();

$companyIds = $source->table('companies_users')
    ->where('user_id', $userId)
    ->pluck('company_id')
    ->unique()
    ->values()
    ->all();

$data['companies'] = $companyIds === []
    ? []
    : $source->table('companies')->whereIn('id', $companyIds)->get()->map(fn ($row) => (array) $row)->all();

$data['companies_users'] = $source->table('companies_users')
    ->where('user_id', $userId)
    ->get()
    ->map(fn ($row) => (array) $row)
    ->all();

$data['model_has_roles'] = $source->table('model_has_roles')
    ->where('model_type', 'App\\Models\\User')
    ->where('model_id', $userId)
    ->get()
    ->map(fn ($row) => (array) $row)
    ->all();

$data['model_has_permissions'] = $source->table('model_has_permissions')
    ->where('model_type', 'App\\Models\\User')
    ->where('model_id', $userId)
    ->get()
    ->map(fn ($row) => (array) $row)
    ->all();

$dir = __DIR__.'/../database/seeders/data';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$path = $dir.'/cash_vero_general.php';
file_put_contents(
    $path,
    "<?php\n\n// Static snapshot from cash-vero. Regenerate with: php scripts/export-cash-vero-general-seeder-data.php\nreturn ".var_export($data, true).";\n"
);

echo "Wrote {$path} (".filesize($path)." bytes)\n";
foreach ($data as $table => $rows) {
    echo "{$table}: ".count($rows)."\n";
}
