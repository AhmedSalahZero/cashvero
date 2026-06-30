<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('factoring_statements')) {
            Schema::create('factoring_statements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('factoring_company_id');
                $table->unsignedBigInteger('factoring_contract_id');
                $table->unsignedBigInteger('factoring_transaction_id')->nullable();
                $table->string('entry_type');
                $table->date('date');
                $table->decimal('debit', 20, 2)->default(0);
                $table->decimal('credit', 20, 2)->default(0);
                $table->string('currency')->nullable();
                $table->text('comment_en')->nullable();
                $table->text('comment_ar')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'factoring_contract_id'], 'fs_company_contract_idx');
                $table->index('factoring_transaction_id', 'fs_transaction_idx');
                $table->index('entry_type', 'fs_entry_type_idx');
            });
        }

        if (!Schema::hasColumn('factoring_transactions', 'is_settled')) {
            Schema::table('factoring_transactions', function (Blueprint $table) {
                $table->boolean('is_settled')->default(false)->after('settlement_id');
                $table->date('settled_at')->nullable()->after('is_settled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('factoring_transactions', 'is_settled')) {
            Schema::table('factoring_transactions', function (Blueprint $table) {
                $table->dropColumn(['is_settled', 'settled_at']);
            });
        }

        Schema::dropIfExists('factoring_statements');
    }
};
