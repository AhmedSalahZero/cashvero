<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('status')->default('in-safe');
            $table->integer('money_received_id')->index('cheques_money_received_id_foreign');
            $table->integer('drawee_bank_id')->nullable()->index('cheques_drawee_bank_id_foreign');
            $table->integer('drawl_bank_id')->nullable()->index('cheques_drawl_bank_id_foreign');
            $table->string('account_type');
            $table->string('account_number')->nullable();
            $table->date('due_date')->nullable();
            $table->date('deposit_date')->nullable();
            $table->bigInteger('days_count')->default(0);
            $table->date('expected_collection_date')->nullable();
            $table->date('actual_collection_date')->nullable();
            $table->integer('clearance_days')->nullable()->default(0);
            $table->decimal('account_balance', 14)->default(0);
            $table->decimal('collection_fees', 14)->default(0);
            $table->timestamps();
            $table->integer('company_id')->nullable();

            $table->index(['money_received_id', 'status'], 'cheques_mr_status_idx');
            $table->index(['status', 'actual_collection_date'], 'cheques_status_actual_coll_idx');
            $table->index(['status', 'expected_collection_date'], 'cheques_status_expected_coll_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
