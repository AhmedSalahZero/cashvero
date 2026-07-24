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
        Schema::create('financial_institution_accounts', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->string('odoo_outbound_cheque_payment_method_id')->nullable();
            $table->string('odoo_inbound_cheque_payment_method_id')->nullable();
            $table->string('odoo_outbound_transfer_payment_method_id')->nullable();
            $table->string('odoo_inbound_transfer_payment_method_id')->nullable();
            $table->string('odoo_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('financial_institution_id')->nullable()->index('financial_institution_accounts_financial_institution_id_foreign');
            $table->date('balance_date')->nullable();
            $table->string('account_number')->nullable();
            $table->string('currency')->nullable();
            $table->double('balance_amount')->nullable()->default(0);
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->string('iban')->nullable();
            $table->decimal('exchange_rate', 5)->nullable()->default(1);
            $table->integer('company_id')->nullable();
            $table->longText('synced_end_of_month_years')->nullable()->comment('لو عمل حركة مثلا في الفين خمسة وعشرين بنروح ننزل في السنه كاملة صفوف علشان ال
			end of month interest 
			ففي الكولوم دا هنسجل ان الفين خمسه وعشرين موجودة علشان ما نروحش ننزلهم تاني
			');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_institution_accounts');
    }
};
