<?php

use App\Support\Permissions\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger for `php artisan permissions:rollout` (App\Support\Permissions\PermissionRollout).
 *
 * Permissions are held per user, so a key added to PermissionRegistry reaches nobody on its own.
 * The rollout gives each NEW key, once, to the users who already hold the screen it was carved out
 * of (its `legacy` alias). A key with a row here has been handed out and is never touched again,
 * so an admin who later takes it away from someone is not overruled on the next deploy.
 *
 * Baseline: every key the registry declares today is recorded as already handed out — those went
 * through `permissions:migrate-to-user`, and admins have tuned them since — EXCEPT the keys below.
 * They shipped after production had migrated, so no existing user holds them yet, and the first
 * rollout hands them out. Granting by parent across every key instead would undo deliberate
 * restrictions (company.* for a company admin, odoo_integration.* for managers).
 */
return new class extends Migration
{
    private const PENDING = [
        'report_bank_charges_analysis.view',
        'report_bank_charges_analysis.export',
        'report_lg_expired.view',
        'report_lg_expired.export',
        'cheques_report_payable.view',
        'cheques_report_payable.export',
        'cheques_report_received.view',
        'cheques_report_received.export',
        'down_payment_report_supplier.view',
        'down_payment_report_supplier.export',
        'down_payment_report_customer.view',
        'down_payment_report_customer.export',
    ];

    public function up(): void
    {
        Schema::create('permission_rollouts', function (Blueprint $table) {
            $table->string('key', 191)->primary();
            $table->unsignedInteger('granted_users')->default(0);
            $table->timestamp('rolled_out_at')->nullable();
        });

        $now = now();
        $rows = collect(PermissionRegistry::keys())
            ->reject(fn (string $key) => in_array($key, self::PENDING, true))
            ->map(fn (string $key) => ['key' => $key, 'granted_users' => 0, 'rolled_out_at' => $now])
            ->values();

        foreach ($rows->chunk(200) as $chunk) {
            DB::table('permission_rollouts')->insert($chunk->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_rollouts');
    }
};
