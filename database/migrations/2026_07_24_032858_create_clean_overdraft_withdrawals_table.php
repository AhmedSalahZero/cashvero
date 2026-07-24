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
        Schema::create('clean_overdraft_withdrawals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('clean_overdraft_bank_statement_id')->index('clean_overdrafts_identifier');
            $table->unsignedBigInteger('clean_overdraft_id');
            $table->unsignedBigInteger('company_id');
            $table->integer('max_settlement_days')->default(0);
            $table->date('due_date')->comment('تاريخ الاستحقاق وهو عباره عن جدول التاريخ 
			date
			من جدول ال 
			bank statement
			زائد ال
			max_settlement_days
			');
            $table->decimal('settlement_amount', 14)->default(0);
            $table->decimal('net_balance', 14)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clean_overdraft_withdrawals');
    }
};
