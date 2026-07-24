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
        Schema::create('branch', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->string('odoo_outbound_cheque_payment_method_id')->nullable();
            $table->string('odoo_inbound_cheque_payment_method_id')->nullable();
            $table->string('odoo_outbound_transfer_payment_method_id')->nullable();
            $table->string('odoo_inbound_transfer_payment_method_id')->nullable();
            $table->string('odoo_code')->nullable();
            $table->string('name')->nullable();
            $table->string('currency')->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->integer('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch');
    }
};
