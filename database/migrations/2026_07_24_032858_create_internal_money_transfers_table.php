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
        Schema::create('internal_money_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('odoo_error_message')->nullable();
            $table->boolean('synced_with_odoo')->nullable()->default(false);
            $table->unsignedBigInteger('inbound_journal_entry_id')->nullable();
            $table->string('inbound_odoo_reference')->nullable();
            $table->unsignedBigInteger('outbound_journal_entry_id')->nullable();
            $table->string('outbound_odoo_reference')->nullable();
            $table->unsignedBigInteger('outbound_account_bank_statement_odoo_id')->nullable();
            $table->unsignedBigInteger('inbound_account_bank_statement_odoo_id')->nullable();
            $table->string('type')->nullable();
            $table->date('transfer_date')->nullable()->comment('هو التاريخ اللي اللي هيتم فيه العميله ');
            $table->unsignedInteger('transfer_days')->comment('عدد الايام المتوقع فيها اتمام هذه العمليه');
            $table->integer('from_bank_id')->nullable()->index('internal_money_transfers_from_bank_id_foreign');
            $table->integer('to_bank_id')->nullable()->index('internal_money_transfers_to_bank_id_foreign');
            $table->decimal('amount', 14)->default(0)->comment('مقدار مبلغ التحويل');
            $table->unsignedBigInteger('from_account_type_id')->nullable()->index('internal_money_transfers_from_account_type_id_foreign');
            $table->string('from_account_number')->nullable();
            $table->string('currency')->nullable();
            $table->unsignedBigInteger('to_account_type_id')->nullable()->index('internal_money_transfers_to_account_type_id_foreign');
            $table->string('to_account_number')->nullable();
            $table->string('cheque_number')->nullable();
            $table->unsignedBigInteger('from_branch_id')->nullable();
            $table->unsignedBigInteger('to_branch_id')->nullable();
            $table->unsignedBigInteger('company_id')->index('internal_money_transfers_company_id_foreign');
            $table->string('from_comment_ar')->nullable();
            $table->string('from_comment_en')->nullable();
            $table->string('to_comment_ar')->nullable();
            $table->string('to_comment_en')->nullable();
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
        Schema::dropIfExists('internal_money_transfers');
    }
};
