<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('factoring_transactions')) {
            Schema::create('factoring_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('recourse_type');
                $table->date('factoring_date');
                $table->unsignedBigInteger('factoring_company_id');
                $table->unsignedBigInteger('factoring_contract_id');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('customer_invoice_id')->unique();
                $table->string('invoice_currency');
                $table->decimal('invoice_amount', 20, 2);
                $table->decimal('factoring_percentage', 8, 4);
                $table->decimal('factoring_amount', 20, 2);
                $table->decimal('contract_interest_rate', 8, 4);
                $table->unsignedInteger('diff_in_days');
                $table->decimal('factoring_interest_amount', 20, 2);
                $table->decimal('other_charges', 20, 2)->default(0);
                $table->decimal('received_amount', 20, 2);
                $table->unsignedBigInteger('financial_institution_id');
                $table->unsignedBigInteger('account_type_id');
                $table->string('account_number');
                $table->unsignedBigInteger('settlement_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'recourse_type']);
                $table->index('factoring_company_id');
                $table->index('customer_id');
            });
        }

        if (!Schema::hasColumn('settlements', 'factoring_transaction_id')) {
            Schema::table('settlements', function (Blueprint $table) {
                $table->unsignedBigInteger('factoring_transaction_id')->nullable()->after('money_received_id');
                $table->index('factoring_transaction_id', 'settlements_factoring_tx_idx');
            });
        }

        foreach ([
            'current_account_bank_statements' => 'ca_bs_factoring_tx_idx',
            'clean_overdraft_bank_statements' => 'co_bs_factoring_tx_idx',
            'fully_secured_overdraft_bank_statements' => 'fso_bs_factoring_tx_idx',
            'overdraft_against_commercial_paper_bank_statements' => 'odcp_bs_factoring_tx_idx',
            'overdraft_against_assignment_of_contract_bank_statements' => 'odaoc_bs_factoring_tx_idx',
        ] as $tableName => $indexName) {
            if (!Schema::hasColumn($tableName, 'factoring_transaction_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->unsignedBigInteger('factoring_transaction_id')->nullable();
                    $table->index('factoring_transaction_id', $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'current_account_bank_statements' => 'ca_bs_factoring_tx_idx',
            'clean_overdraft_bank_statements' => 'co_bs_factoring_tx_idx',
            'fully_secured_overdraft_bank_statements' => 'fso_bs_factoring_tx_idx',
            'overdraft_against_commercial_paper_bank_statements' => 'odcp_bs_factoring_tx_idx',
            'overdraft_against_assignment_of_contract_bank_statements' => 'odaoc_bs_factoring_tx_idx',
        ] as $tableName => $indexName) {
            if (Schema::hasColumn($tableName, 'factoring_transaction_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                    $table->dropColumn('factoring_transaction_id');
                });
            }
        }

        if (Schema::hasColumn('settlements', 'factoring_transaction_id')) {
            Schema::table('settlements', function (Blueprint $table) {
                $table->dropIndex('settlements_factoring_tx_idx');
                $table->dropColumn('factoring_transaction_id');
            });
        }

        Schema::dropIfExists('factoring_transactions');
    }
};
