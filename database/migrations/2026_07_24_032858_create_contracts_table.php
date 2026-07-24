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
        Schema::create('contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_account_id')->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable()->comment('عباره عن انه مربوط بيه');
            $table->unsignedBigInteger('overdraft_against_assignment_of_contract_id')->nullable();
            $table->string('status')->default('running');
            $table->string('model_type')->nullable()->comment('اما Customer or Supplier');
            $table->unsignedBigInteger('partner_id')->nullable()->index('contracts_partner_id_foreign');
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->integer('company_id');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('amount', 14);
            $table->string('currency')->nullable();
            $table->decimal('exchange_rate', 8, 3)->nullable()->default(1);

            $table->index(['company_id', 'status'], 'contracts_co_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
