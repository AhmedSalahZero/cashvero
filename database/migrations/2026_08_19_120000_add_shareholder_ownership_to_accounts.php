<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shareholder / owner personal accounts — the ownership flag.
 *
 * See docs/shareholder-accounts.md. Option B from the roadmap: an account
 * stays inside the same company record and is simply tagged as belonging to
 * a shareholder, rather than the owner getting their own tenant.
 *
 * Exactly 4 tables get the flag. Facilities a bank does not issue to an
 * individual (all ODA types, LG, LC, Leasing, Factoring) are deliberately
 * left alone, and `fully_secured_overdrafts` inherits ownership for free
 * through its existing cd_or_td_account_id pointer.
 */
return new class extends Migration
{
    /** @var string[] */
    private array $tables = [
        'financial_institution_accounts',
        'time_of_deposits',
        'certificates_of_deposits',
        'medium_term_loans',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'is_shareholder_account')) {
                    $table->boolean('is_shareholder_account')
                        ->default(false)
                        ->comment('1 = the account belongs to a shareholder personally, not to the company');
                }

                if (! Schema::hasColumn($tableName, 'shareholder_partner_id')) {
                    $table->unsignedBigInteger('shareholder_partner_id')
                        ->nullable()
                        ->comment('partners.id of the owning shareholder (is_shareholder = 1). Null for company accounts.');
                    $table->index('shareholder_partner_id', $tableName.'_shareholder_partner_id_index');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'shareholder_partner_id')) {
                    $table->dropIndex($tableName.'_shareholder_partner_id_index');
                    $table->dropColumn('shareholder_partner_id');
                }

                if (Schema::hasColumn($tableName, 'is_shareholder_account')) {
                    $table->dropColumn('is_shareholder_account');
                }
            });
        }
    }
};
