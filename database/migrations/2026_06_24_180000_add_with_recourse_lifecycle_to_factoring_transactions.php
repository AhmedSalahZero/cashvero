<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('factoring_transactions', 'is_collected')) {
            Schema::table('factoring_transactions', function (Blueprint $table) {
                $table->boolean('is_collected')->default(false)->after('difference_account_number');
                $table->date('collected_at')->nullable()->after('is_collected');
                $table->date('collection_date')->nullable()->after('collected_at');
                $table->unsignedBigInteger('collection_financial_institution_id')->nullable()->after('collection_date');
                $table->unsignedBigInteger('collection_account_type_id')->nullable()->after('collection_financial_institution_id');
                $table->string('collection_account_number')->nullable()->after('collection_account_type_id');
                $table->decimal('collection_difference_amount', 20, 2)->nullable()->after('collection_account_number');

                $table->boolean('is_rejected')->default(false)->after('collection_difference_amount');
                $table->date('rejected_at')->nullable()->after('is_rejected');
                $table->date('rejection_date')->nullable()->after('rejected_at');
                $table->unsignedBigInteger('rejection_financial_institution_id')->nullable()->after('rejection_date');
                $table->unsignedBigInteger('rejection_account_type_id')->nullable()->after('rejection_financial_institution_id');
                $table->string('rejection_account_number')->nullable()->after('rejection_account_type_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('factoring_transactions', 'is_collected')) {
            Schema::table('factoring_transactions', function (Blueprint $table) {
                $table->dropColumn([
                    'is_collected',
                    'collected_at',
                    'collection_date',
                    'collection_financial_institution_id',
                    'collection_account_type_id',
                    'collection_account_number',
                    'collection_difference_amount',
                    'is_rejected',
                    'rejected_at',
                    'rejection_date',
                    'rejection_financial_institution_id',
                    'rejection_account_type_id',
                    'rejection_account_number',
                ]);
            });
        }
    }
};
