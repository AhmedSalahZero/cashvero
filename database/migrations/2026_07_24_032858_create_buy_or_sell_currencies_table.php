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
        Schema::create('buy_or_sell_currencies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('outbound_odoo_reference')->nullable();
            $table->string('inbound_odoo_reference')->nullable();
            $table->string('synced_with_odoo')->nullable();
            $table->string('odoo_error_message')->nullable();
            $table->unsignedBigInteger('inbound_journal_entry_id')->nullable();
            $table->unsignedBigInteger('outbound_journal_entry_id')->nullable();
            $table->unsignedBigInteger('outbound_account_bank_statement_odoo_id')->nullable();
            $table->unsignedBigInteger('inbound_account_bank_statement_odoo_id')->nullable();
            $table->string('type')->nullable();
            $table->date('transaction_date')->nullable()->comment('هو التاريخ اللي اللي هيتم فيه العميله ');
            $table->string('currency_to_sell')->nullable();
            $table->string('currency_to_buy')->nullable();
            $table->decimal('currency_to_sell_amount', 14)->nullable();
            $table->decimal('exchange_rate', 20, 10)->nullable();
            $table->decimal('currency_to_buy_amount', 14)->nullable();
            $table->integer('from_bank_id')->nullable()->index('buy_or_sell_currencies_from_bank_id_foreign');
            $table->unsignedBigInteger('from_account_type_id')->nullable()->index('buy_or_sell_currencies_from_account_type_id_foreign');
            $table->string('from_account_number')->nullable();
            $table->unsignedBigInteger('to_bank_id')->comment('بنوكي');
            $table->unsignedBigInteger('to_account_type_id')->nullable()->index('buy_or_sell_currencies_to_account_type_id_foreign');
            $table->string('to_account_number')->nullable();
            $table->unsignedBigInteger('to_branch_id')->nullable();
            $table->unsignedBigInteger('from_branch_id')->nullable();
            $table->unsignedBigInteger('company_id')->index('buy_or_sell_currencies_company_id_foreign');
            $table->string('buy_comment_ar')->nullable();
            $table->string('buy_comment_en')->nullable();
            $table->string('sell_comment_ar')->nullable();
            $table->string('sell_comment_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->text('user_comment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buy_or_sell_currencies');
    }
};
