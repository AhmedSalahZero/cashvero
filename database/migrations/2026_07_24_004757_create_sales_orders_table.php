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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->unsignedBigInteger('company_id')->index('sales_orders_company_id_foreign');
            $table->unsignedBigInteger('contract_id')->index('sales_orders_contract_id_foreign');
            $table->string('so_number')->nullable();
            $table->decimal('amount', 14)->nullable()->default(0);
            $table->date('start_date_1')->nullable();
            $table->date('end_date_1')->nullable();
            $table->decimal('execution_percentage_1', 5)->nullable()->default(0);
            $table->integer('execution_days_1')->nullable()->default(0);
            $table->integer('collection_days_1')->nullable()->default(0);
            $table->date('start_date_2')->nullable();
            $table->date('end_date_2')->nullable();
            $table->decimal('execution_percentage_2', 5)->nullable()->default(0);
            $table->integer('execution_days_2')->nullable()->default(0);
            $table->integer('collection_days_2')->nullable()->default(0);
            $table->date('start_date_3')->nullable();
            $table->date('end_date_3')->nullable();
            $table->decimal('execution_percentage_3', 5)->nullable()->default(0);
            $table->integer('execution_days_3')->nullable()->default(0);
            $table->integer('collection_days_3')->nullable()->default(0);
            $table->date('start_date_4')->nullable();
            $table->date('end_date_4')->nullable();
            $table->decimal('execution_percentage_4', 5)->nullable()->default(0);
            $table->integer('execution_days_4')->nullable()->default(0);
            $table->integer('collection_days_4')->nullable()->default(0);
            $table->date('start_date_5')->nullable();
            $table->date('end_date_5')->nullable();
            $table->decimal('execution_percentage_5', 5)->nullable()->default(0);
            $table->integer('execution_days_5')->nullable()->default(0);
            $table->integer('collection_days_5')->nullable()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
