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
        Schema::create('factoring_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('factoring_company_id');
            $table->unsignedBigInteger('factoring_contract_id');
            $table->unsignedBigInteger('factoring_transaction_id')->nullable()->index('fs_transaction_idx');
            $table->string('entry_type')->index('fs_entry_type_idx');
            $table->date('date');
            $table->decimal('debit', 20)->default(0);
            $table->decimal('credit', 20)->default(0);
            $table->string('currency')->nullable();
            $table->text('comment_en')->nullable();
            $table->text('comment_ar')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'factoring_contract_id'], 'fs_company_contract_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factoring_statements');
    }
};
