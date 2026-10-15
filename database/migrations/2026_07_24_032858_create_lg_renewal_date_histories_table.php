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
        Schema::create('lg_renewal_date_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('renewal_fees_account_bank_statement_odoo_id')->nullable();
            $table->unsignedBigInteger('renewal_fees_journal_entry_id')->nullable();
            $table->unsignedBigInteger('letter_of_guarantee_issuance_id')->index('lg_renewal_foreign');
            $table->string('renewal_date')->comment('تاريخ التجديد');
            $table->decimal('fees_amount', 14)->comment('هي عبارة عن المبلغ اللي هيدفعه للبنك علشان يجدد');
            $table->integer('company_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lg_renewal_date_histories');
    }
};
