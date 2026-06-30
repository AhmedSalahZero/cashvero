<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('factoring_transactions', 'is_difference_received')) {
            Schema::table('factoring_transactions', function (Blueprint $table) {
                $table->boolean('is_difference_received')->default(false)->after('settled_at');
                $table->date('difference_received_date')->nullable()->after('is_difference_received');
                $table->decimal('difference_received_amount', 20, 2)->nullable()->after('difference_received_date');
                $table->unsignedBigInteger('difference_financial_institution_id')->nullable()->after('difference_received_amount');
                $table->unsignedBigInteger('difference_account_type_id')->nullable()->after('difference_financial_institution_id');
                $table->string('difference_account_number')->nullable()->after('difference_account_type_id');
            });
        }

        foreach ([
            'current_account_bank_statements',
            'clean_overdraft_bank_statements',
            'fully_secured_overdraft_bank_statements',
            'overdraft_against_commercial_paper_bank_statements',
            'overdraft_against_assignment_of_contract_bank_statements',
        ] as $tableName) {
            if (!Schema::hasColumn($tableName, 'factoring_bank_movement_type')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('factoring_bank_movement_type')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'current_account_bank_statements',
            'clean_overdraft_bank_statements',
            'fully_secured_overdraft_bank_statements',
            'overdraft_against_commercial_paper_bank_statements',
            'overdraft_against_assignment_of_contract_bank_statements',
        ] as $tableName) {
            if (Schema::hasColumn($tableName, 'factoring_bank_movement_type')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('factoring_bank_movement_type');
                });
            }
        }

        if (Schema::hasColumn('factoring_transactions', 'is_difference_received')) {
            Schema::table('factoring_transactions', function (Blueprint $table) {
                $table->dropColumn([
                    'is_difference_received',
                    'difference_received_date',
                    'difference_received_amount',
                    'difference_financial_institution_id',
                    'difference_account_type_id',
                    'difference_account_number',
                ]);
            });
        }
    }
};
